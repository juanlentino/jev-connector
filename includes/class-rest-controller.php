<?php
/**
 * REST proxy so admin-side JavaScript can ask questions without shipping the key.
 *
 * @package JevConnector
 */

declare( strict_types = 1 );

namespace JevConnector;

defined( 'ABSPATH' ) || exit;

/**
 * Registers /wp-json/jev/v1/ask and /wp-json/jev/v1/status.
 */
final class REST_Controller {

	public const NAMESPACE_V1       = 'jev/v1';
	public const DEFAULT_CAPABILITY = 'edit_posts';

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE_V1,
			'/ask',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'ask' ),
					'permission_callback' => array( $this, 'can_ask' ),
					'args'                => array(
						'state'     => array(
							'required'    => true,
							'description' => __( 'The content to evaluate.', 'connector-for-typesafe-jev' ),
						),
						'questions' => array(
							'required'    => true,
							'type'        => 'object',
							'description' => __( 'Map of question id to question definition.', 'connector-for-typesafe-jev' ),
						),
						'model'     => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/status',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'status' ),
					'permission_callback' => array( $this, 'can_ask' ),
				),
			)
		);
	}

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public function can_ask(): bool {
		/**
		 * Filters the capability required to use the REST proxy.
		 *
		 * @param string $capability Capability name. Default 'edit_posts'.
		 */
		$capability = (string) apply_filters( 'jevc_rest_capability', self::DEFAULT_CAPABILITY );

		return current_user_can( $capability );
	}

	/**
	 * Handle POST /ask.
	 *
	 * @param \WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function ask( \WP_REST_Request $request ) {
		$questions = $request->get_param( 'questions' );

		if ( ! is_array( $questions ) ) {
			return new \WP_Error(
				'jevc_invalid_question',
				__( 'questions must be an object.', 'connector-for-typesafe-jev' ),
				array( 'status' => 400 )
			);
		}

		$args  = array();
		$model = $request->get_param( 'model' );

		if ( is_string( $model ) && '' !== $model ) {
			$args['model'] = $model;
		}

		$result = ask( $request->get_param( 'state' ), $questions, $args );

		if ( is_wp_error( $result ) ) {
			$data   = $result->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;

			if ( 'jevc_not_configured' === $result->get_error_code() ) {
				$status = 503;
			}

			return new \WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => $status ) );
		}

		return rest_ensure_response( $result->to_array() );
	}

	/**
	 * Handle GET /status.
	 *
	 * @return \WP_REST_Response
	 */
	public function status(): \WP_REST_Response {
		return rest_ensure_response(
			array(
				'ready'   => is_ready(),
				'model'   => (string) apply_filters( 'jevc_default_model', Client::DEFAULT_MODEL ),
				'version' => VERSION,
			)
		);
	}
}
