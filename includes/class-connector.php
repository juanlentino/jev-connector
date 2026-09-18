<?php
/**
 * Core Connectors API registration and credential resolution.
 *
 * @package JevConnector
 */

declare( strict_types = 1 );

namespace JevConnector;

defined( 'ABSPATH' ) || exit;

/**
 * Registers TypeSafe as a connector so WordPress owns the key.
 *
 * Jev is deliberately not registered as an `ai_provider`. That type is
 * special-cased by core: keys are handed to the PHP AI Client and validated
 * against it on save, and a key that fails is cleared. The AI Client covers
 * generative capabilities only, so a System One key would be wiped by a
 * validator that has no idea what it is looking at. Akismet sets the
 * precedent for a non-generative service connector, and this follows it.
 */
final class Connector {

	public const ID           = 'typesafe';
	public const TYPE         = 'ai_decision';
	public const SETTING_NAME = 'connectors_typesafe_api_key';
	public const CONSTANT     = 'TYPESAFE_API_KEY';
	public const ENV_VAR      = 'TYPESAFE_API_KEY';

	/**
	 * Hook registration up.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_connectors_init', array( __CLASS__, 'on_connectors_init' ) );
	}

	/**
	 * Register the connector with core.
	 *
	 * @param \WP_Connector_Registry $registry Core connector registry.
	 * @return void
	 */
	public static function on_connectors_init( $registry ): void {
		if ( ! is_object( $registry ) || ! method_exists( $registry, 'register' ) ) {
			return;
		}

		if ( method_exists( $registry, 'is_registered' ) && $registry->is_registered( self::ID ) ) {
			return;
		}

		$registry->register( self::ID, self::args() );
	}

	/**
	 * The connector card definition.
	 *
	 * @return array<string,mixed>
	 */
	public static function args(): array {
		$args = array(
			'name'           => __( 'TypeSafe Jev', 'connector-for-typesafe-jev' ),
			'description'    => __( 'System One model for classification, scoring, and typed decisions. Returns probabilities and confidence instead of prose.', 'connector-for-typesafe-jev' ),
			/**
			 * Filters the connector type used to group the card.
			 *
			 * Changing this does not move the stored credential, because
			 * setting_name, constant_name and env_var_name are all declared
			 * explicitly below. Do not return 'ai_provider' unless you want
			 * core to validate the key against the generative AI Client.
			 *
			 * @param string $type Connector type.
			 */
			'type'           => (string) apply_filters( 'jevc_connector_type', self::TYPE ),
			'authentication' => array(
				'method'          => 'api_key',
				'credentials_url' => 'https://console.typesafe.ai/settings/keys',
				'setting_name'    => self::SETTING_NAME,
				'constant_name'   => self::CONSTANT,
				'env_var_name'    => self::ENV_VAR,
			),
			'plugin'         => array(
				'file' => plugin_basename( PLUGIN_FILE ),
			),
		);

		/**
		 * Filters the whole connector definition before registration.
		 *
		 * @param array<string,mixed> $args Connector arguments.
		 */
		return (array) apply_filters( 'jevc_connector_args', $args );
	}

	/**
	 * Resolve the API key.
	 *
	 * Mirrors core's own precedence for connector credentials, which is not
	 * exposed publicly: environment variable, then constant, then the stored
	 * option that the Connectors screen writes.
	 *
	 * @return string
	 */
	public static function get_api_key(): string {
		$env = getenv( self::ENV_VAR );

		if ( is_string( $env ) && '' !== trim( $env ) ) {
			return trim( $env );
		}

		if ( defined( self::CONSTANT ) ) {
			$constant = constant( self::CONSTANT );

			if ( is_string( $constant ) && '' !== trim( $constant ) ) {
				return trim( $constant );
			}
		}

		$stored = get_option( self::SETTING_NAME, '' );

		return is_string( $stored ) ? trim( $stored ) : '';
	}

	/**
	 * Where the key came from, for display on the settings screen.
	 *
	 * @return string One of env, constant, database, none.
	 */
	public static function key_source(): string {
		$env = getenv( self::ENV_VAR );

		if ( is_string( $env ) && '' !== trim( $env ) ) {
			return 'env';
		}

		if ( defined( self::CONSTANT ) && '' !== trim( (string) constant( self::CONSTANT ) ) ) {
			return 'constant';
		}

		$stored = get_option( self::SETTING_NAME, '' );

		return ( is_string( $stored ) && '' !== trim( $stored ) ) ? 'database' : 'none';
	}

	/**
	 * Whether a key is available from any source.
	 *
	 * @return bool
	 */
	public static function is_connected(): bool {
		return '' !== self::get_api_key();
	}

	/**
	 * Admin URL of the core Connectors screen.
	 *
	 * @return string
	 */
	public static function settings_url(): string {
		return admin_url( 'options-general.php?page=connectors' );
	}
}
