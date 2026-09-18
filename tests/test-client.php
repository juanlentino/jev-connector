<?php
/**
 * Client tests against a queued fake transport.
 *
 * @package JevConnector
 */

declare( strict_types = 1 );

use JevConnector\Client;
use JevConnector\Question;
use JevConnector\Response;
use PHPUnit\Framework\TestCase;

/**
 * Covers payload shape, auth header, error mapping, and retries.
 */
final class ClientTest extends TestCase {

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
	 * A minimal successful body.
	 *
	 * @return array<string,mixed>
	 */
	private function ok_body(): array {
		return array(
			'model'   => 'jev-latest',
			'answers' => array(
				'urgency' => array(
					'type' => 'noul',
					'noul' => 0.97,
				),
			),
			'usage'   => array(
				'input_tokens'  => 10,
				'output_tokens' => 2,
			),
		);
	}

	/**
	 * The request matches the documented endpoint, headers, and body.
	 *
	 * @return void
	 */
	public function test_request_matches_api_contract(): void {
		JevTestState::$responses[] = JevTestState::http( 200, $this->ok_body() );

		$client = new Client();
		$result = $client->ask(
			array( 'message' => 'My card was charged twice.' ),
			array( 'urgency' => Question::noul( 'Does this express urgency?' ) )
		);

		$this->assertInstanceOf( Response::class, $result );

		$request = JevTestState::$requests[0];

		$this->assertSame( 'https://api.typesafe.ai/v1/systemone', $request['url'] );
		$this->assertSame( 'Bearer test-key', $request['args']['headers']['Authorization'] );
		$this->assertSame( 'application/json', $request['args']['headers']['Content-Type'] );

		$payload = json_decode( $request['args']['body'], true );

		$this->assertSame( 'jev-latest', $payload['model'] );
		$this->assertSame( array( 'message' => 'My card was charged twice.' ), $payload['state'] );
		$this->assertSame( 'noul', $payload['questions']['urgency']['type'] );
		$this->assertSame( 0.97, $result->noul( 'urgency' ) );
	}

	/**
	 * A plain string state is sent as-is.
	 *
	 * @return void
	 */
	public function test_string_state(): void {
		JevTestState::$responses[] = JevTestState::http( 200, $this->ok_body() );

		( new Client() )->ask( 'Plain text', array( 'urgency' => Question::noul( 'Urgent?' ) ) );

		$payload = json_decode( JevTestState::$requests[0]['args']['body'], true );

		$this->assertSame( 'Plain text', $payload['state'] );
	}

	/**
	 * Until the connector holds a key, nothing is sent.
	 *
	 * @return void
	 */
	public function test_no_request_without_a_key(): void {
		JevTestState::$options['connectors_typesafe_api_key'] = '';

		$result = ( new Client() )->ask( 'x', array( 'q' => Question::noul( 'Urgent?' ) ) );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'jevc_not_configured', $result->get_error_code() );
		$this->assertCount( 0, JevTestState::$requests );
	}

	/**
	 * An identical second call is served from cache without a request.
	 *
	 * @return void
	 */
	public function test_identical_call_is_cached(): void {
		JevTestState::$responses[] = JevTestState::http( 200, $this->ok_body() );

		$question = array( 'urgency' => Question::noul( 'Urgent?' ) );

		$first  = ( new Client() )->ask( 'same state', $question );
		$second = ( new Client() )->ask( 'same state', $question );

		$this->assertInstanceOf( Response::class, $first );
		$this->assertInstanceOf( Response::class, $second );
		$this->assertSame( 0.97, $second->noul( 'urgency' ) );
		$this->assertCount( 1, JevTestState::$requests );
	}

	/**
	 * A different state misses the cache.
	 *
	 * @return void
	 */
	public function test_different_state_misses_cache(): void {
		JevTestState::$responses[] = JevTestState::http( 200, $this->ok_body() );
		JevTestState::$responses[] = JevTestState::http( 200, $this->ok_body() );

		$question = array( 'urgency' => Question::noul( 'Urgent?' ) );

		( new Client() )->ask( 'state one', $question );
		( new Client() )->ask( 'state two', $question );

		$this->assertCount( 2, JevTestState::$requests );
	}

	/**
	 * A failed call is never cached.
	 *
	 * @return void
	 */
	public function test_failures_are_not_cached(): void {
		JevTestState::$responses[] = JevTestState::http( 401 );
		JevTestState::$responses[] = JevTestState::http( 200, $this->ok_body() );

		$question = array( 'urgency' => Question::noul( 'Urgent?' ) );

		$first  = ( new Client() )->ask( 'same state', $question );
		$second = ( new Client() )->ask( 'same state', $question );

		$this->assertTrue( is_wp_error( $first ) );
		$this->assertInstanceOf( Response::class, $second );
		$this->assertCount( 2, JevTestState::$requests );
	}

	/**
	 * Caching can be turned off per call.
	 *
	 * @return void
	 */
	public function test_cache_can_be_bypassed(): void {
		JevTestState::$responses[] = JevTestState::http( 200, $this->ok_body() );
		JevTestState::$responses[] = JevTestState::http( 200, $this->ok_body() );

		$question = array( 'urgency' => Question::noul( 'Urgent?' ) );

		( new Client() )->ask( 'same state', $question, array( 'cache' => false ) );
		( new Client() )->ask( 'same state', $question, array( 'cache' => false ) );

		$this->assertCount( 2, JevTestState::$requests );
	}

	/**
	 * A 401 is reported without retrying.
	 *
	 * @return void
	 */
	public function test_401_is_not_retried(): void {
		JevTestState::$responses[] = JevTestState::http( 401, array( 'error' => 'bad key' ) );

		$result = ( new Client() )->ask( 'x', array( 'q' => Question::noul( 'Urgent?' ) ) );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'jevc_http_401', $result->get_error_code() );
		$this->assertCount( 1, JevTestState::$requests );
		$this->assertSame( 1, JevTestState::$actions['jevc_request_failed'] );
	}

	/**
	 * A 429 is retried and can succeed on a later attempt.
	 *
	 * @return void
	 */
	public function test_429_is_retried_then_succeeds(): void {
		JevTestState::$responses[] = JevTestState::http( 429, array(), array( 'retry-after' => '0' ) );
		JevTestState::$responses[] = JevTestState::http( 200, $this->ok_body() );

		$result = ( new Client() )->ask( 'x', array( 'urgency' => Question::noul( 'Urgent?' ) ) );

		$this->assertInstanceOf( Response::class, $result );
		$this->assertCount( 2, JevTestState::$requests );
	}

	/**
	 * Retries stop at the configured ceiling.
	 *
	 * @return void
	 */
	public function test_retries_are_bounded(): void {
		for ( $i = 0; $i < 5; $i++ ) {
			JevTestState::$responses[] = JevTestState::http( 529 );
		}

		$result = ( new Client() )->ask(
			'x',
			array( 'q' => Question::noul( 'Urgent?' ) ),
			array( 'max_attempts' => 3 )
		);

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertCount( 3, JevTestState::$requests );
	}

	/**
	 * A body that is not JSON is reported rather than returned.
	 *
	 * @return void
	 */
	public function test_undecodable_body(): void {
		JevTestState::$responses[] = array(
			'response' => array( 'code' => 200 ),
			'body'     => 'not json',
			'headers'  => array(),
		);

		$result = ( new Client() )->ask( 'x', array( 'q' => Question::noul( 'Urgent?' ) ) );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'jevc_invalid_response', $result->get_error_code() );
	}

	/**
	 * A malformed question is caught before the transport is touched.
	 *
	 * @return void
	 */
	public function test_invalid_question_short_circuits(): void {
		$result = ( new Client() )->ask( 'x', array( 'q' => array( 'type' => 'noul' ) ) );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'jevc_invalid_question', $result->get_error_code() );
		$this->assertCount( 0, JevTestState::$requests );
	}
}
