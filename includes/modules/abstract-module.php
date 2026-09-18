<?php
/**
 * Base class for optional feature modules.
 *
 * @package JevConnector
 */

declare( strict_types = 1 );

namespace JevConnector\Modules;

defined( 'ABSPATH' ) || exit;

use JevConnector\Modules;

/**
 * A module is a self-contained feature that is off until switched on.
 *
 * Core stays a client library. Modules are the opinionated part, and nothing
 * in the client may depend on one existing.
 */
abstract class Module {

	/**
	 * Stable identifier, used as the settings key.
	 *
	 * @return string
	 */
	abstract public static function id(): string;

	/**
	 * Human-readable name for the checkbox.
	 *
	 * @return string
	 */
	abstract public static function label(): string;

	/**
	 * One line explaining what switching it on does.
	 *
	 * @return string
	 */
	abstract public static function description(): string;

	/**
	 * Attach hooks. Only called when the module is enabled.
	 *
	 * @return void
	 */
	abstract public function register(): void;

	/**
	 * Default settings for this module, excluding the enabled flag.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array();
	}

	/**
	 * Clean this module's submitted settings.
	 *
	 * @param array<string,mixed> $input Raw submitted values for this module.
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $input ): array {
		unset( $input );

		return array();
	}

	/**
	 * Render this module's own fields, below its checkbox.
	 *
	 * @return void
	 */
	public function render_fields(): void {
	}

	/**
	 * This module's stored settings, merged over its defaults.
	 *
	 * @return array<string,mixed>
	 */
	public function settings(): array {
		return Modules::settings( static::id() );
	}

	/**
	 * Read one setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $fallback Value when unset.
	 * @return mixed
	 */
	protected function setting( string $key, $fallback = null ) {
		$settings = $this->settings();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $fallback;
	}

	/**
	 * Field name attribute for one of this module's settings.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	protected function field_name( string $key ): string {
		return sprintf(
			'%s[modules][%s][%s]',
			\JevConnector\Settings::OPTION,
			static::id(),
			$key
		);
	}
}
