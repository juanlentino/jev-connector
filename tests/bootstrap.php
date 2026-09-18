<?php
/**
 * Minimal WordPress stubs so the client can be unit tested without a WP install.
 *
 * @package JevConnector
 */

declare( strict_types = 1 );

define( 'ABSPATH', __DIR__ . '/' );

/**
 * Test double state shared with the stubs below.
 */
final class JevTestState {

	/**
	 * Queued HTTP responses, consumed in order by wp_remote_post().
	 *
	 * @var array<int,mixed>
	 */
	public static array $responses = array();

	/**
	 * Requests captured by wp_remote_post().
	 *
	 * @var array<int,array{url:string,args:array<string,mixed>}>
	 */
	public static array $requests = array();

	/**
	 * Stored options.
	 *
	 * @var array<string,mixed>
	 */
	public static array $options = array();

	/**
	 * Actions that fired, keyed by hook name.
	 *
	 * @var array<string,int>
	 */
	public static array $actions = array();

	/**
	 * Stored transients.
	 *
	 * @var array<string,mixed>
	 */
	public static array $transients = array();

	/**
	 * Reset everything between tests.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$responses = array();
		self::$requests  = array();
		self::$actions   = array();
		self::$transients = array();
		self::$options    = array(
			'connectors_typesafe_api_key' => 'test-key',
			'jevc_settings'               => array(
				'default_model'   => 'jev-latest',
				'rest_capability' => 'edit_posts',
			),
		);
	}

	/**
	 * Build a fake successful HTTP response.
	 *
	 * @param int                 $status  HTTP status code.
	 * @param array<string,mixed> $body    Body to encode.
	 * @param array<string,mixed> $headers Response headers.
	 * @return array<string,mixed>
	 */
	public static function http( int $status, array $body = array(), array $headers = array() ): array {
		return array(
			'response' => array( 'code' => $status ),
			'body'     => (string) wp_json_encode( $body ),
			'headers'  => $headers,
		);
	}
}

JevTestState::reset();

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Small stand-in for the WordPress error object.
	 */
	class WP_Error {

		/**
		 * Error code.
		 *
		 * @var string
		 */
		private string $code;

		/**
		 * Error message.
		 *
		 * @var string
		 */
		private string $message;

		/**
		 * Error data.
		 *
		 * @var mixed
		 */
		private $data;

		/**
		 * Constructor.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @param mixed  $data    Error data.
		 */
		public function __construct( string $code = '', string $message = '', $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		/**
		 * Get the code.
		 *
		 * @return string
		 */
		public function get_error_code(): string {
			return $this->code;
		}

		/**
		 * Get the message.
		 *
		 * @return string
		 */
		public function get_error_message(): string {
			return $this->message;
		}

		/**
		 * Get the data.
		 *
		 * @return mixed
		 */
		public function get_error_data() {
			return $this->data;
		}
	}
}

/**
 * Whether a value is a WP_Error.
 *
 * @param mixed $thing Value to check.
 * @return bool
 */
function is_wp_error( $thing ): bool {
	return $thing instanceof WP_Error;
}

/**
 * Pass-through filter stub. Retry delays are forced to zero.
 *
 * @param string $hook  Hook name.
 * @param mixed  $value Value to filter.
 * @param mixed  ...$rest Extra arguments.
 * @return mixed
 */
function apply_filters( string $hook, $value, ...$rest ) {
	if ( 'jevc_retry_delay' === $hook ) {
		return 0;
	}

	return $value;
}

/**
 * Record a fired action.
 *
 * @param string $hook Hook name.
 * @param mixed  ...$args Arguments.
 * @return void
 */
function do_action( string $hook, ...$args ): void {
	JevTestState::$actions[ $hook ] = ( JevTestState::$actions[ $hook ] ?? 0 ) + 1;
}

/**
 * No-op.
 *
 * @param string $hook     Hook name.
 * @param mixed  $callback Callback.
 * @return bool
 */
function add_action( string $hook, $callback ): bool {
	return true;
}

/**
 * No-op.
 *
 * @param string $hook     Hook name.
 * @param mixed  $callback Callback.
 * @return bool
 */
function add_filter( string $hook, $callback ): bool {
	return true;
}

/**
 * JSON encode helper.
 *
 * @param mixed $data Data to encode.
 * @return string|false
 */
function wp_json_encode( $data ) {
	return json_encode( $data );
}

/**
 * Site URL stub.
 *
 * @param string $path Path to append.
 * @return string
 */
function home_url( string $path = '' ): string {
	return 'https://example.test' . $path;
}

/**
 * Translation stub.
 *
 * @param string $text   Text.
 * @param string $domain Text domain.
 * @return string
 */
function __( string $text, string $domain = '' ): string {
	return $text;
}

/**
 * Option reader.
 *
 * @param string $key     Option name.
 * @param mixed  $default Default value.
 * @return mixed
 */
function get_option( string $key, $default = false ) {
	return JevTestState::$options[ $key ] ?? $default;
}

/**
 * Queued HTTP transport.
 *
 * @param string              $url  Request URL.
 * @param array<string,mixed> $args Request arguments.
 * @return mixed
 */
function wp_remote_post( string $url, array $args = array() ) {
	JevTestState::$requests[] = array(
		'url'  => $url,
		'args' => $args,
	);

	if ( array() === JevTestState::$responses ) {
		return new WP_Error( 'http_request_failed', 'No response queued.' );
	}

	return array_shift( JevTestState::$responses );
}

/**
 * Read a status code from a fake response.
 *
 * @param mixed $response Response.
 * @return int
 */
function wp_remote_retrieve_response_code( $response ): int {
	return isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
}

/**
 * Read a body from a fake response.
 *
 * @param mixed $response Response.
 * @return string
 */
function wp_remote_retrieve_body( $response ): string {
	return isset( $response['body'] ) ? (string) $response['body'] : '';
}

/**
 * Read a header from a fake response.
 *
 * @param mixed  $response Response.
 * @param string $name     Header name.
 * @return string
 */
function wp_remote_retrieve_header( $response, string $name ): string {
	return isset( $response['headers'][ $name ] ) ? (string) $response['headers'][ $name ] : '';
}

/**
 * Transient reader.
 *
 * @param string $key Transient name.
 * @return mixed
 */
function get_transient( string $key ) {
	return JevTestState::$transients[ $key ] ?? false;
}

/**
 * Transient writer.
 *
 * @param string $key   Transient name.
 * @param mixed  $value Value to store.
 * @param int    $ttl   Lifetime in seconds.
 * @return bool
 */
function set_transient( string $key, $value, int $ttl = 0 ): bool {
	JevTestState::$transients[ $key ] = $value;

	return true;
}

/**
 * Admin URL stub.
 *
 * @param string $path Path to append.
 * @return string
 */
function admin_url( string $path = '' ): string {
	return 'https://example.test/wp-admin/' . $path;
}

/**
 * Plugin basename stub.
 *
 * @param string $file Plugin file.
 * @return string
 */
function plugin_basename( string $file ): string {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}

/**
 * Key sanitizer stub.
 *
 * @param string $key Raw key.
 * @return string
 */
function sanitize_key( string $key ): string {
	return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', $key ) );
}

/**
 * Text field sanitizer stub.
 *
 * @param string $value Raw value.
 * @return string
 */
function sanitize_text_field( string $value ): string {
	return trim( wp_strip_all_tags( $value ) );
}

/**
 * Escaping stub.
 *
 * @param string $text Raw text.
 * @return string
 */
function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * Tag stripper stub.
 *
 * @param string $value Raw value.
 * @return string
 */
function wp_strip_all_tags( string $value ): string {
	return strip_tags( $value );
}

define( 'HOUR_IN_SECONDS', 3600 );
define( 'JevConnector\\VERSION', '0.1.0' );
define( 'JevConnector\\PLUGIN_FILE', dirname( __DIR__ ) . '/connector-for-typesafe-jev.php' );

require_once dirname( __DIR__ ) . '/includes/class-exception.php';
require_once dirname( __DIR__ ) . '/includes/class-question.php';
require_once dirname( __DIR__ ) . '/includes/class-response.php';
require_once dirname( __DIR__ ) . '/includes/class-cache.php';
require_once dirname( __DIR__ ) . '/includes/class-connector.php';
require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-client.php';
require_once dirname( __DIR__ ) . '/includes/modules/abstract-module.php';
require_once dirname( __DIR__ ) . '/includes/modules/class-comment-guardrail.php';
require_once dirname( __DIR__ ) . '/includes/modules/class-auto-tagger.php';
require_once dirname( __DIR__ ) . '/includes/class-modules.php';
