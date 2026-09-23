<?php
/**
 * The surface other plugins depend on.
 *
 * Signal & Noise Tools 16.5.3 routes every Jev request through this plugin
 * and holds no transport or key of its own. It reaches in by name, with
 * function_exists() and class_exists(), so these tests do the same rather
 * than importing the classes: they should fail the way a consumer would.
 * Changing anything asserted here is a breaking change for that plugin.
 *
 * @package JevConnector
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

/**
 * Pins the public contract consumers are written against.
 */
final class ConsumerContractTest extends TestCase {

	private const ASK       = 'JevConnector\\ask';
	private const CONNECTOR = 'JevConnector\\Connector';

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
	 * The entry point a consumer guards with function_exists().
	 *
	 * @return void
	 */
	public function test_ask_function_exists_in_the_namespace(): void {
		$this->assertTrue( function_exists( self::ASK ) );
	}

	/**
	 * Key resolution is reached by class name and static call.
	 *
	 * @return void
	 */
	public function test_connector_exposes_key_and_setting_name(): void {
		$this->assertTrue( class_exists( self::CONNECTOR ) );
		$this->assertTrue( method_exists( self::CONNECTOR, 'get_api_key' ) );
		$this->assertSame( 'test-key', call_user_func( array( self::CONNECTOR, 'get_api_key' ) ) );

		// A consumer migrating a legacy key writes to this option by name.
		$this->assertTrue( defined( self::CONNECTOR . '::SETTING_NAME' ) );
		$this->assertSame( 'connectors_typesafe_api_key', constant( self::CONNECTOR . '::SETTING_NAME' ) );
	}

	/**
	 * A successful call returns an object whose to_array() is the API body,
	 * untouched, with the model argument honored in the request.
	 *
	 * @return void
	 */
	public function test_ask_returns_response_with_untouched_body(): void {
		$body = array(
			'model'   => 'jev-latest',
			'answers' => array(
				'q' => array(
					'type' => 'noul',
					'noul' => 0.97,
				),
			),
			'usage'   => array(
				'input_tokens'  => 312,
				'output_tokens' => 48,
			),
		);

		JevTestState::$responses[] = JevTestState::http( 200, $body );

		$result = call_user_func(
			self::ASK,
			array( 'title' => 'x' ),
			array(
				'q' => array(
					'type'         => 'noul',
					'instructions' => 'Is it?',
				),
			),
			array( 'model' => 'jev-latest' )
		);

		$this->assertFalse( is_wp_error( $result ) );
		$this->assertTrue( method_exists( $result, 'to_array' ) );
		$this->assertSame( $body, $result->to_array() );

		$sent = json_decode( JevTestState::$requests[0]['args']['body'], true );
		$this->assertSame( 'jev-latest', $sent['model'] );
		$this->assertSame( array( 'title' => 'x' ), $sent['state'] );
		$this->assertArrayHasKey( 'q', $sent['questions'] );
	}

	/**
	 * An API failure is a WP_Error whose data carries the HTTP status.
	 *
	 * @return void
	 */
	public function test_api_failure_is_wp_error_with_status_in_data(): void {
		JevTestState::$responses[] = JevTestState::http( 401, array( 'error' => 'bad key' ) );

		$result = call_user_func(
			self::ASK,
			'x',
			array(
				'q' => array(
					'type'         => 'noul',
					'instructions' => 'Is it?',
				),
			),
			array( 'model' => 'jev-latest' )
		);

		$this->assertTrue( is_wp_error( $result ) );
		$data = $result->get_error_data();
		$this->assertIsArray( $data );
		$this->assertSame( 401, $data['status'] );
	}

	/**
	 * The example in README.md's "In five lines" section, executed.
	 *
	 * The README is the first thing anyone reads, so its example is part of
	 * the public surface: a reader copies it verbatim. Pinning it here means
	 * a change to ask(), to Question::noul() or to Response::noul() fails the
	 * suite instead of silently leaving a broken snippet at the top of the
	 * page. Keep the two in step; if this test changes, the README changes.
	 *
	 * @return void
	 */
	public function test_readme_five_line_example(): void {
		JevTestState::$responses[] = JevTestState::http(
			200,
			array(
				'model'   => 'jev-latest',
				'answers' => array(
					'spam' => array( 'noul' => 0.97 ),
				),
			)
		);

		$answer = call_user_func(
			self::ASK,
			array( 'comment' => 'Cheap watches, best prices, click here.' ),
			array( 'spam' => \JevConnector\Question::noul( 'Is this comment spam?' ) )
		);

		$this->assertFalse( is_wp_error( $answer ) );
		$this->assertSame( 0.97, $answer->noul( 'spam' ) );
		$this->assertTrue( $answer->noul( 'spam' ) >= 0.9 );

		// The state and the question reach the wire in the shape shown.
		$sent = json_decode( JevTestState::$requests[0]['args']['body'], true );
		$this->assertSame( array( 'comment' => 'Cheap watches, best prices, click here.' ), $sent['state'] );
		$this->assertSame( 'noul', $sent['questions']['spam']['type'] );
		$this->assertSame( 'Is this comment spam?', $sent['questions']['spam']['instructions'] );
	}

	/**
	 * Without a key nothing is sent and the error is jevc_not_configured.
	 *
	 * @return void
	 */
	public function test_no_key_sends_nothing(): void {
		JevTestState::$options['connectors_typesafe_api_key'] = '';

		$result = call_user_func(
			self::ASK,
			'x',
			array(
				'q' => array(
					'type'         => 'noul',
					'instructions' => 'Is it?',
				),
			),
			array( 'model' => 'jev-latest' )
		);

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'jevc_not_configured', $result->get_error_code() );
		$this->assertSame( array(), JevTestState::$requests );
	}
}
