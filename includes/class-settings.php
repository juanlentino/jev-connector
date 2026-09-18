<?php
/**
 * Settings screen and option storage.
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

	public const OPTION     = 'jevc_settings';
	public const GROUP      = 'jevc_settings_group';
	public const PAGE_SLUG  = 'connector-for-typesafe-jev';
	public const PRIVACY_URL = 'https://docs.typesafe.ai/legal';

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
			'api_key'         => '',
			'enabled'         => false,
			'default_model'   => Client::DEFAULT_MODEL,
			'rest_capability' => 'edit_posts',
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
	 * The API key in use.
	 *
	 * A JEVC_API_KEY constant always wins, so the key can be kept out of the
	 * database on sites that prefer wp-config.php.
	 *
	 * @return string
	 */
	public static function get_api_key(): string {
		if ( defined( 'JEVC_API_KEY' ) && is_string( constant( 'JEVC_API_KEY' ) ) ) {
			return trim( (string) constant( 'JEVC_API_KEY' ) );
		}

		$settings = self::all();

		return trim( (string) $settings['api_key'] );
	}

	/**
	 * Whether the site owner has switched outbound requests on.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		if ( defined( 'JEVC_API_KEY' ) ) {
			return true;
		}

		$settings = self::all();

		return (bool) $settings['enabled'];
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
		$url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );

		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( $url ),
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
			__( 'Connection', 'connector-for-typesafe-jev' ),
			array( $this, 'render_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'jevc_enabled',
			__( 'Send requests to TypeSafe', 'connector-for-typesafe-jev' ),
			array( $this, 'render_enabled_field' ),
			self::PAGE_SLUG,
			'jevc_main'
		);

		add_settings_field(
			'jevc_api_key',
			__( 'API key', 'connector-for-typesafe-jev' ),
			array( $this, 'render_api_key_field' ),
			self::PAGE_SLUG,
			'jevc_main'
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
	}

	/**
	 * Clean submitted values.
	 *
	 * @param mixed $input Raw submitted value.
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$current  = self::all();
		$defaults = self::defaults();

		$api_key = isset( $input['api_key'] ) ? trim( sanitize_text_field( (string) $input['api_key'] ) ) : '';

		// An unchanged masked field must not wipe the stored key.
		if ( '' === $api_key || 1 === preg_match( '/^\*+$/', $api_key ) ) {
			$api_key = (string) $current['api_key'];
		}

		$model = isset( $input['default_model'] )
			? sanitize_text_field( (string) $input['default_model'] )
			: $defaults['default_model'];

		$capability = isset( $input['rest_capability'] )
			? sanitize_key( (string) $input['rest_capability'] )
			: $defaults['rest_capability'];

		return array(
			'api_key'         => $api_key,
			'enabled'         => ! empty( $input['enabled'] ),
			'default_model'   => '' !== $model ? $model : $defaults['default_model'],
			'rest_capability' => '' !== $capability ? $capability : $defaults['rest_capability'],
		);
	}

	/**
	 * Section description, including the external-service disclosure.
	 *
	 * @return void
	 */
	public function render_section(): void {
		?>
		<p>
			<?php
			printf(
				/* translators: 1: opening link tag to the TypeSafe policies page, 2: closing link tag. */
				esc_html__( 'This plugin sends the content you pass to it, along with your questions, to the TypeSafe System One API at api.typesafe.ai. Nothing leaves your site until you switch requests on below and save an API key. See the %1$sTypeSafe policies%2$s for how that data is handled.', 'connector-for-typesafe-jev' ),
				'<a href="' . esc_url( self::PRIVACY_URL ) . '" target="_blank" rel="noopener noreferrer">',
				'</a>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Consent checkbox.
	 *
	 * @return void
	 */
	public function render_enabled_field(): void {
		$settings = self::all();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[enabled]" value="1"
				<?php checked( (bool) $settings['enabled'] ); ?> />
			<?php esc_html_e( 'Allow this site to contact api.typesafe.ai', 'connector-for-typesafe-jev' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'While this is off, every call returns an error instead of making a request.', 'connector-for-typesafe-jev' ); ?>
		</p>
		<?php
	}

	/**
	 * API key field.
	 *
	 * @return void
	 */
	public function render_api_key_field(): void {
		if ( defined( 'JEVC_API_KEY' ) ) {
			?>
			<p><code>JEVC_API_KEY</code>
				<?php esc_html_e( 'is defined in wp-config.php, so it is used instead of this field.', 'connector-for-typesafe-jev' ); ?>
			</p>
			<?php
			return;
		}

		$settings = self::all();
		$stored   = (string) $settings['api_key'];
		?>
		<input type="password" class="regular-text" autocomplete="off"
			name="<?php echo esc_attr( self::OPTION ); ?>[api_key]"
			value="<?php echo esc_attr( '' !== $stored ? str_repeat( '*', 12 ) : '' ); ?>" />
		<p class="description">
			<?php
			printf(
				/* translators: 1: opening link tag to the TypeSafe console, 2: closing link tag. */
				esc_html__( 'Create a key in the %1$sTypeSafe console%2$s. Leave the masked value alone to keep the stored key.', 'connector-for-typesafe-jev' ),
				'<a href="https://console.typesafe.ai/settings/keys" target="_blank" rel="noopener noreferrer">',
				'</a>'
			);
			?>
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
		<p class="description">
			<?php esc_html_e( 'Default: jev-latest', 'connector-for-typesafe-jev' ); ?>
		</p>
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
