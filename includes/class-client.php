<?php
/**
 * HTTP client for the TypeSafe System One endpoint.
 *
 * @package JevConnector
 */

declare( strict_types = 1 );

namespace JevConnector;

defined( 'ABSPATH' ) || exit;

/**
 * Talks to POST https://api.typesafe.ai/v1/systemone.
 */
final class Client {

	public const API_BASE       = 'https://api.typesafe.ai';
	public const ENDPOINT       = '/v1/systemone';
	public const DEFAULT_MODEL  = 'jev-latest';
	public const MAX_ATTEMPTS   = 3;
	public const RETRY_STATUSES = array( 429, 500, 502, 503, 504, 529 );

	/**
	 * API key used for this instance.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Constructor.
	 *
	 * @param string|null $api_key Explicit key, or null to read the stored one.
	 */
	public function __construct( ?string $api_key = null ) {
		$this->api_key = null !== $api_key ? $api_key : Settings::get_api_key();
	}

	/**
	 * Whether a key is configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return '' !== $this->api_key && Settings::is_enabled();
	}

	/**
	 * Ask one or more typed questions about a piece of state.
	 *
	 * @param mixed               $state     String, array, or object to evaluate.
	 * @param array<string,mixed> $questions Map of question id => question array.
	 * @param array<string,mixed> $args      Optional. 'model', 'timeout', 'max_attempts'.
	 * @return Response|\WP_Error
	 */
	public function ask( $state, array $questions, array $args = array() ) {
		if ( ! $this->is_configured() ) {
			return new \WP_Error(
				'jevc_not_configured',
				__( 'Set a TypeSafe API key and allow outbound requests on the settings screen first.', 'connector-for-typesafe-jev' )
			);
		}

		try {
			Question::validate_map( $questions );
		} catch ( Exception $e ) {
			return new \WP_Error( 'jevc_invalid_question', $e->getMessage() );
		}

		$defaults = array(
			/**
			 * Filters the model used for System One requests.
			 *
			 * @param string $model Model identifier.
			 */
			'model'        => (string) apply_filters( 'jevc_default_model', self::DEFAULT_MODEL ),
			/**
			 * Filters the HTTP timeout, in seconds.
			 *
			 * @param int $timeout Timeout in seconds.
			 */
			'timeout'      => (int) apply_filters( 'jevc_http_timeout', 20 ),
			'max_attempts' => self::MAX_ATTEMPTS,
		);

		$args = array_merge( $defaults, $args );

		$payload = array(
			'state'     => $state,
			'model'     => (string) $args['model'],
			'questions' => $questions,
		);

		/**
		 * Filters the request payload just before it is encoded.
		 *
		 * @param array<string,mixed> $payload Request body.
		 * @param array<string,mixed> $args    Request arguments.
		 */
		$payload = (array) apply_filters( 'jevc_request_payload', $payload, $args );

		$body = wp_json_encode( $payload );

		if ( false === $body ) {
			return new \WP_Error(
				'jevc_encode_failed',
				__( 'The request could not be encoded as JSON.', 'connector-for-typesafe-jev' )
			);
		}

		return $this->send( $body, $args );
	}

	/**
	 * Send the request, retrying transient failures.
	 *
	 * @param string              $body JSON request body.
	 * @param array<string,mixed> $args Request arguments.
	 * @return Response|\WP_Error
	 */
	private function send( string $body, array $args ) {
		$url = self::API_BASE . self::ENDPOINT;

		$request_args = array(
			'headers'     => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
				'User-Agent'    => 'Connector-for-TypeSafe-Jev/' . VERSION . '; ' . home_url( '/' ),
			),
			'body'        => $body,
			'timeout'     => (int) $args['timeout'],
			'redirection' => 0,
		);

		/**
		 * Filters the arguments passed to wp_remote_post().
		 *
		 * @param array<string,mixed> $request_args HTTP arguments.
		 * @param array<string,mixed> $args         Request arguments.
		 */
		$request_args = (array) apply_filters( 'jevc_request_args', $request_args, $args );

		$attempts     = max( 1, (int) $args['max_attempts'] );
		$last_error   = null;
		$attempt      = 0;

		while ( $attempt < $attempts ) {
			++$attempt;

			$response = wp_remote_post( $url, $request_args );

			if ( is_wp_error( $response ) ) {
				$last_error = $response;

				if ( $attempt < $attempts ) {
					$this->wait( $attempt, 0 );
					continue;
				}

				break;
			}

			$status = (int) wp_remote_retrieve_response_code( $response );
			$raw    = (string) wp_remote_retrieve_body( $response );

			if ( 200 === $status ) {
				$decoded = json_decode( $raw, true );

				if ( ! is_array( $decoded ) ) {
					$last_error = new \WP_Error(
						'jevc_invalid_response',
						__( 'TypeSafe returned a response that could not be decoded.', 'connector-for-typesafe-jev' )
					);
					break;
				}

				$result = new Response( $decoded );

				/**
				 * Fires after a successful System One call.
				 *
				 * @param Response            $result The decoded response.
				 * @param array<string,mixed> $args   Request arguments.
				 */
				do_action( 'jevc_after_response', $result, $args );

				return $result;
			}

			$last_error = $this->error_for_status( $status, $raw );

			if ( in_array( $status, self::RETRY_STATUSES, true ) && $attempt < $attempts ) {
				$this->wait( $attempt, $this->retry_after( $response ) );
				continue;
			}

			break;
		}

		$error = $last_error instanceof \WP_Error
			? $last_error
			: new \WP_Error( 'jevc_request_failed', __( 'The TypeSafe request failed.', 'connector-for-typesafe-jev' ) );

		/**
		 * Fires when a System One call fails for good.
		 *
		 * @param \WP_Error           $error The failure.
		 * @param array<string,mixed> $args  Request arguments.
		 */
		do_action( 'jevc_request_failed', $error, $args );

		return $error;
	}

	/**
	 * Map a status code to a WP_Error with a readable message.
	 *
	 * @param int    $status HTTP status code.
	 * @param string $raw    Raw response body.
	 * @return \WP_Error
	 */
	private function error_for_status( int $status, string $raw ): \WP_Error {
		$messages = array(
			400 => __( 'TypeSafe rejected the request as malformed.', 'connector-for-typesafe-jev' ),
			401 => __( 'The TypeSafe API key is missing or invalid.', 'connector-for-typesafe-jev' ),
			403 => __( 'This TypeSafe API key is not permitted to make that call.', 'connector-for-typesafe-jev' ),
			404 => __( 'The TypeSafe endpoint was not found.', 'connector-for-typesafe-jev' ),
			422 => __( 'TypeSafe could not validate the question or state.', 'connector-for-typesafe-jev' ),
			429 => __( 'The TypeSafe rate limit was exceeded.', 'connector-for-typesafe-jev' ),
			529 => __( 'TypeSafe is overloaded. Try again shortly.', 'connector-for-typesafe-jev' ),
		);

		$message = isset( $messages[ $status ] )
			? $messages[ $status ]
			/* translators: %d: HTTP status code. */
			: sprintf( __( 'TypeSafe returned HTTP %d.', 'connector-for-typesafe-jev' ), $status );

		$detail = json_decode( $raw, true );

		return new \WP_Error(
			'jevc_http_' . $status,
			$message,
			array(
				'status' => $status,
				'detail' => is_array( $detail ) ? $detail : $raw,
			)
		);
	}

	/**
	 * Read a Retry-After header, in seconds.
	 *
	 * @param array<string,mixed> $response Raw wp_remote_post response.
	 * @return int
	 */
	private function retry_after( $response ): int {
		$header = wp_remote_retrieve_header( $response, 'retry-after' );

		if ( is_array( $header ) ) {
			$header = reset( $header );
		}

		$seconds = is_numeric( $header ) ? (int) $header : 0;

		return max( 0, min( 30, $seconds ) );
	}

	/**
	 * Back off between attempts.
	 *
	 * @param int $attempt     Attempt number, starting at 1.
	 * @param int $retry_after Seconds requested by the server, or 0.
	 * @return void
	 */
	private function wait( int $attempt, int $retry_after ): void {
		$seconds = $retry_after > 0 ? $retry_after : min( 8, 2 ** ( $attempt - 1 ) );

		/**
		 * Filters the backoff delay, in seconds. Return 0 to skip sleeping.
		 *
		 * @param int $seconds Delay in seconds.
		 * @param int $attempt Attempt number.
		 */
		$seconds = (int) apply_filters( 'jevc_retry_delay', $seconds, $attempt );

		if ( $seconds > 0 ) {
			sleep( $seconds );
		}
	}
}
