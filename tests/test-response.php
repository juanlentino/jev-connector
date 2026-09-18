<?php
/**
 * Response reader tests.
 *
 * @package JevConnector
 */

declare( strict_types = 1 );

use JevConnector\Response;
use PHPUnit\Framework\TestCase;

/**
 * Covers the typed accessors and the confidence gate.
 */
final class ResponseTest extends TestCase {

	/**
	 * Build a response fixture covering all three answer types.
	 *
	 * @return Response
	 */
	private function fixture(): Response {
		return new Response(
			array(
				'model'   => 'jev-latest',
				'answers' => array(
					'urgency'    => array(
						'type' => 'noul',
						'noul' => 0.999,
					),
					'department' => array(
						'type'          => 'choice',
						'choice'        => 'returns',
						'confidence'    => 0.62,
						'probabilities' => array(
							'returns'  => 0.7,
							'shipping' => 0.3,
						),
					),
					'severity'   => array(
						'type'          => 'score',
						'score'         => 1.3,
						'confidence'    => 0.54,
						'legend'        => array(
							'0' => 'Cosmetic',
							'1' => 'Degraded',
							'2' => 'Blocking',
						),
						'probabilities' => array(
							'0' => 0.0,
							'1' => 0.7,
							'2' => 0.3,
						),
					),
				),
				'usage'   => array(
					'input_tokens'  => 312,
					'output_tokens' => 48,
				),
			)
		);
	}

	/**
	 * Each accessor reads its own answer type.
	 *
	 * @return void
	 */
	public function test_typed_accessors(): void {
		$response = $this->fixture();

		$this->assertSame( 'jev-latest', $response->model() );
		$this->assertSame( 0.999, $response->noul( 'urgency' ) );
		$this->assertSame( 'returns', $response->choice( 'department' ) );
		$this->assertSame( 1.3, $response->score( 'severity' ) );
		$this->assertSame( 0.54, $response->confidence( 'severity' ) );
		$this->assertSame( 'Blocking', $response->legend( 'severity' )['2'] );
		$this->assertSame( 312, $response->usage()['input_tokens'] );
	}

	/**
	 * Missing answers come back as null rather than throwing.
	 *
	 * @return void
	 */
	public function test_missing_answer_is_null(): void {
		$response = $this->fixture();

		$this->assertNull( $response->noul( 'nope' ) );
		$this->assertNull( $response->choice( 'nope' ) );
		$this->assertSame( array(), $response->probabilities( 'nope' ) );
	}

	/**
	 * Nouls do not report confidence, so distance from 0.5 stands in for it.
	 *
	 * @return void
	 */
	public function test_confidence_gate(): void {
		$response = $this->fixture();

		$this->assertTrue( $response->is_confident( 'urgency', 0.9 ) );
		$this->assertFalse( $response->is_confident( 'department', 0.8 ) );
		$this->assertTrue( $response->is_confident( 'department', 0.6 ) );
		$this->assertFalse( $response->is_confident( 'nope' ) );
	}
}
