<?php
/**
 * Settings screen for the options core does not own.
 *
 * The API key lives on the core Connectors screen, not here. This page only
 * covers what is specific to how this site calls Jev.
 *
 * @package JevConnector
 */

declare( strict_types = 1 );

namespace JevConnector;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the options page under Settings and reads stored values.
 */
final class Settings {

	public const OPTION    = 'jevc_settings';
	public const GROUP     = 'jevc_settings_group';
	public const PAGE_SLUG = 'connector-for-typesafe-jev';

	/**
	 * Singleton instance.
	 *
	 * @var Settings|null
	 */
	private static ?Settings $instance = null;

	/**
	 * Get the shared instance.
	 *
	 * @return Settings
	 */
	public static function instance(): Settings {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hook the settings screen up.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( PLUGIN_FILE ),
			array( $this, 'action_links' )
		);
	}

	/**
	 * Default option values.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'default_model'   => Client::DEFAULT_MODEL,
			'rest_capability' => 'edit_posts',
			'modules'         => array(),
		);
	}

	/**
	 * All settings, merged over the defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );

		return array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Capability required to use the REST proxy.
	 *
	 * @return string
	 */
	public static function rest_capability(): string {
		$settings = self::all();

		return (string) $settings['rest_capability'];
	}

	/**
	 * Add the options page.
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_options_page(
			__( 'TypeSafe Jev', 'connector-for-typesafe-jev' ),
			__( 'TypeSafe Jev', 'connector-for-typesafe-jev' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Add a settings shortcut to the plugins list.
	 *
	 * @param array<int,string> $links Existing action links.
	 * @return array<int,string>
	 */
	public function action_links( array $links ): array {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ),
				esc_html__( 'Settings', 'connector-for-typesafe-jev' )
			)
		);

		return $links;
	}

	/**
	 * Register the setting, section, and fields.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section(
			'jevc_main',
			__( 'Request defaults', 'connector-for-typesafe-jev' ),
			array( $this, 'render_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'jevc_default_model',
			__( 'Model', 'connector-for-typesafe-jev' ),
			array( $this, 'render_model_field' ),
			self::PAGE_SLUG,
			'jevc_main'
		);

		add_settings_field(
			'jevc_rest_capability',
			__( 'REST capability', 'connector-for-typesafe-jev' ),
			array( $this, 'render_capability_field' ),
			self::PAGE_SLUG,
			'jevc_main'
		);

		add_settings_section(
			'jevc_modules',
			__( 'Modules', 'connector-for-typesafe-jev' ),
			array( $this, 'render_modules_section' ),
			self::PAGE_SLUG
		);

		foreach ( Modules::all() as $id => $module ) {
			add_settings_field(
				'jevc_module_' . $id,
				esc_html( $module::label() ),
				function () use ( $module ): void {
					$this->render_module_field( $module );
				},
				self::PAGE_SLUG,
				'jevc_modules'
			);
		}
	}

	/**
	 * Modules section description.
	 *
	 * @return void
	 */
	public function render_modules_section(): void {
		?>
		<p>
			<?php esc_html_e( 'Everything here is off until you switch it on, and each one costs an API call when it runs.', 'connector-for-typesafe-jev' ); ?>
		</p>
		<?php
	}

	/**
	 * Render one module's checkbox and, when enabled, its own fields.
	 *
	 * @param \JevConnector\Modules\Module $module The module.
	 * @return void
	 */
	public function render_module_field( $module ): void {
		$id      = $module::id();
		$enabled = Modules::is_enabled( $id );
		?>
		<label>
			<input type="checkbox" value="1"
				name="<?php echo esc_attr( sprintf( '%s[modules][%s][enabled]', self::OPTION, $id ) ); ?>"
				<?php checked( $enabled ); ?> />
			<?php echo esc_html( $module::description() ); ?>
		</label>
		<?php if ( $enabled ) : ?>
			<div style="margin-top:8px;padding-left:24px;">
				<?php $module->render_fields(); ?>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Clean submitted values.
	 *
	 * @param mixed $input Raw submitted value.
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();

		$model = isset( $input['default_model'] )
			? sanitize_text_field( (string) $input['default_model'] )
			: $defaults['default_model'];

		$capability = isset( $input['rest_capability'] )
			? sanitize_key( (string) $input['rest_capability'] )
			: $defaults['rest_capability'];

		return array(
			'default_model'   => '' !== $model ? $model : $defaults['default_model'],
			'rest_capability' => '' !== $capability ? $capability : $defaults['rest_capability'],
			'modules'         => Modules::sanitize( $input['modules'] ?? array() ),
		);
	}

	/**
	 * Section description.
	 *
	 * @return void
	 */
	public function render_section(): void {
		?>
		<p>
			<?php esc_html_e( 'These apply to every call unless the calling code overrides them.', 'connector-for-typesafe-jev' ); ?>
		</p>
		<?php
	}

	/**
	 * Model field.
	 *
	 * @return void
	 */
	public function render_model_field(): void {
		$settings = self::all();
		?>
		<input type="text" class="regular-text"
			name="<?php echo esc_attr( self::OPTION ); ?>[default_model]"
			value="<?php echo esc_attr( (string) $settings['default_model'] ); ?>" />
		<p class="description"><?php esc_html_e( 'Default: jev-latest', 'connector-for-typesafe-jev' ); ?></p>
		<?php
	}

	/**
	 * REST capability field.
	 *
	 * @return void
	 */
	public function render_capability_field(): void {
		$settings = self::all();
		?>
		<input type="text" class="regular-text"
			name="<?php echo esc_attr( self::OPTION ); ?>[rest_capability]"
			value="<?php echo esc_attr( (string) $settings['rest_capability'] ); ?>" />
		<p class="description">
			<?php esc_html_e( 'Capability a logged-in user needs to call the REST proxy. Default: edit_posts', 'connector-for-typesafe-jev' ); ?>
		</p>
		<?php
	}

	/**
	 * Connection status, pointing at the screen that owns the key.
	 *
	 * @return void
	 */
	private function render_status(): void {
		$source = Connector::key_source();

		$labels = array(
			'env'      => __( 'Connected using the TYPESAFE_API_KEY environment variable.', 'connector-for-typesafe-jev' ),
			'constant' => __( 'Connected using the TYPESAFE_API_KEY constant.', 'connector-for-typesafe-jev' ),
			'database' => __( 'Connected. The key is stored by WordPress.', 'connector-for-typesafe-jev' ),
			'none'     => __( 'Not connected. No requests will be made until a key is added.', 'connector-for-typesafe-jev' ),
		);

		$notice = 'none' === $source ? 'notice-warning' : 'notice-success';
		?>
		<div class="notice <?php echo esc_attr( $notice ); ?> inline">
			<p>
				<?php echo esc_html( $labels[ $source ] ); ?>
				<?php if ( 'database' === $source || 'none' === $source ) : ?>
					<a href="<?php echo esc_url( Connector::settings_url() ); ?>">
						<?php esc_html_e( 'Manage the key under Settings then Connectors.', 'connector-for-typesafe-jev' ); ?>
					</a>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the options page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Connector for TypeSafe Jev', 'connector-for-typesafe-jev' ); ?></h1>
			<?php $this->render_status(); ?>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
