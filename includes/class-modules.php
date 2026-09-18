<?php
/**
 * Module registry.
 *
 * @package JevConnector
 */

declare( strict_types = 1 );

namespace JevConnector;

defined( 'ABSPATH' ) || exit;

use JevConnector\Modules\Auto_Tagger;
use JevConnector\Modules\Comment_Guardrail;
use JevConnector\Modules\Module;

/**
 * Collects the available modules and boots the enabled ones.
 */
final class Modules {

	/**
	 * Instantiated modules, keyed by id.
	 *
	 * @var array<string,Module>|null
	 */
	private static ?array $instances = null;

	/**
	 * Every available module class.
	 *
	 * @return array<int,class-string<Module>>
	 */
	public static function classes(): array {
		/**
		 * Filters the list of module classes.
		 *
		 * @param array<int,class-string<Module>> $classes Module class names.
		 */
		return (array) apply_filters(
			'jevc_modules',
			array(
				Comment_Guardrail::class,
				Auto_Tagger::class,
			)
		);
	}

	/**
	 * All modules, instantiated once, keyed by id.
	 *
	 * @return array<string,Module>
	 */
	public static function all(): array {
		if ( null === self::$instances ) {
			self::$instances = array();

			foreach ( self::classes() as $class ) {
				if ( is_subclass_of( $class, Module::class ) ) {
					self::$instances[ $class::id() ] = new $class();
				}
			}
		}

		return self::$instances;
	}

	/**
	 * Attach hooks for every enabled module.
	 *
	 * @return void
	 */
	public static function boot(): void {
		foreach ( self::all() as $id => $module ) {
			if ( self::is_enabled( $id ) ) {
				$module->register();
			}
		}
	}

	/**
	 * Whether a module is switched on.
	 *
	 * Modules are off by default. A site owner opts in, one at a time.
	 *
	 * @param string $id Module id.
	 * @return bool
	 */
	public static function is_enabled( string $id ): bool {
		$stored = self::stored();

		return ! empty( $stored[ $id ]['enabled'] );
	}

	/**
	 * A module's settings, merged over its defaults.
	 *
	 * @param string $id Module id.
	 * @return array<string,mixed>
	 */
	public static function settings( string $id ): array {
		$modules = self::all();

		if ( ! isset( $modules[ $id ] ) ) {
			return array();
		}

		$class    = get_class( $modules[ $id ] );
		$defaults = $class::defaults();
		$stored   = self::stored();
		$own      = isset( $stored[ $id ] ) && is_array( $stored[ $id ] ) ? $stored[ $id ] : array();

		return array_merge( $defaults, $own );
	}

	/**
	 * The raw modules branch of the plugin option.
	 *
	 * @return array<string,mixed>
	 */
	private static function stored(): array {
		$settings = Settings::all();

		return isset( $settings['modules'] ) && is_array( $settings['modules'] )
			? $settings['modules']
			: array();
	}

	/**
	 * Clean the modules branch of submitted settings.
	 *
	 * @param mixed $input Raw submitted modules array.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$clean = array();

		foreach ( self::all() as $id => $module ) {
			$class  = get_class( $module );
			$own    = isset( $input[ $id ] ) && is_array( $input[ $id ] ) ? $input[ $id ] : array();
			$fields = $class::sanitize( $own );

			$clean[ $id ] = array_merge(
				$class::defaults(),
				$fields,
				array( 'enabled' => ! empty( $own['enabled'] ) )
			);
		}

		return $clean;
	}

	/**
	 * Reset the instance cache. Test seam.
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$instances = null;
	}
}
