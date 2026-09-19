<?php
/**
 * Connectors API registration and credential resolution tests.
 *
 * @package JevConnector
 */

declare( strict_types = 1 );

use JevConnector\Connector;
use PHPUnit\Framework\TestCase;

/**
 * Covers the connector card shape and the key precedence chain.
 */
final class ConnectorTest extends TestCase {

	/**
	 * Reset the shared stub state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		JevTestState::reset();
	}

	/**
	 * The card declares api_key auth with every name set explicitly.
	 *
	 * @return void
	 */
	public function test_connector_args_shape(): void {
		$args = Connector::args();

		$this->assertSame( 'api_key', $args['authentication']['method'] );
		$this->assertSame( 'connectors_typesafe_api_key', $args['authentication']['setting_name'] );
		$this->assertSame( 'TYPESAFE_API_KEY', $args['authentication']['constant_name'] );
		$this->assertSame( 'TYPESAFE_API_KEY', $args['authentication']['env_var_name'] );
		$this->assertArrayHasKey( 'credentials_url', $args['authentication'] );
		$this->assertArrayHasKey( 'file', $args['plugin'] );
		$this->assertStringEndsWith( '/assets/images/typesafe.png', $args['logo_url'] );
		$this->assertFileExists( dirname( __DIR__ ) . '/assets/images/typesafe.png' );
	}

	/**
	 * Registering as an AI provider would hand the key to the generative AI
	 * Client, which validates it and clears what it cannot verify.
	 *
	 * @return void
	 */
	public function test_connector_is_not_an_ai_provider(): void {
		$this->assertSame( 'ai_decision', Connector::args()['type'] );
	}

	/**
	 * Core registers the Connectors screen as its own admin file
	 * (wp-admin/menu.php: options-connectors.php), not as a page under
	 * options-general.php. The wrong form answers "you are not allowed".
	 *
	 * @return void
	 */
	public function test_settings_url_is_core_connectors_screen(): void {
		$this->assertStringEndsWith( '/wp-admin/options-connectors.php', Connector::settings_url() );
	}

	/**
	 * The stored option is used when no environment or constant is present.
	 *
	 * @return void
	 */
	public function test_key_from_database(): void {
		$this->assertSame( 'test-key', Connector::get_api_key() );
		$this->assertSame( 'database', Connector::key_source() );
		$this->assertTrue( Connector::is_connected() );
	}

	/**
	 * An environment variable outranks the stored option.
	 *
	 * @return void
	 */
	public function test_environment_wins(): void {
		putenv( 'TYPESAFE_API_KEY=env-key' );

		$this->assertSame( 'env-key', Connector::get_api_key() );
		$this->assertSame( 'env', Connector::key_source() );

		putenv( 'TYPESAFE_API_KEY' );
	}

	/**
	 * With nothing set anywhere, the connector reports itself unconnected.
	 *
	 * @return void
	 */
	public function test_no_key_anywhere(): void {
		JevTestState::$options['connectors_typesafe_api_key'] = '';

		$this->assertSame( '', Connector::get_api_key() );
		$this->assertSame( 'none', Connector::key_source() );
		$this->assertFalse( Connector::is_connected() );
	}

	/**
	 * Registration is skipped when the id is already taken.
	 *
	 * @return void
	 */
	public function test_registration_is_idempotent(): void {
		$registry = new class() {
			/**
			 * Ids passed to register().
			 *
			 * @var array<int,string>
			 */
			public array $registered = array();

			/**
			 * Record a registration.
			 *
			 * @param string              $id   Connector id.
			 * @param array<string,mixed> $args Connector args.
			 * @return void
			 */
			public function register( string $id, array $args ): void {
				$this->registered[] = $id;
			}

			/**
			 * Whether an id is taken.
			 *
			 * @param string $id Connector id.
			 * @return bool
			 */
			public function is_registered( string $id ): bool {
				return in_array( $id, $this->registered, true );
			}
		};

		Connector::on_connectors_init( $registry );
		Connector::on_connectors_init( $registry );

		$this->assertSame( array( 'typesafe' ), $registry->registered );
	}
}
