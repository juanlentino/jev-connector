<?php
/**
 * Module registry and per-module decision logic tests.
 *
 * @package JevConnector
 */

declare( strict_types = 1 );

use JevConnector\Modules;
use JevConnector\Modules\Auto_Tagger;
use JevConnector\Modules\Comment_Guardrail;
use JevConnector\Response;
use PHPUnit\Framework\TestCase;

/**
 * Covers enable/disable handling and both modules' pure decision functions.
 */
final class ModulesTest extends TestCase {

	/**
	 * Reset the shared stub state before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		JevTestState::reset();
		Modules::flush();
	}

	/**
	 * Build a guardrail response fixture.
	 *
	 * @param string $route      Chosen route.
	 * @param float  $confidence Route confidence.
	 * @param float  $spam       Spam probability.
	 * @return Response
	 */
	private function verdict( string $route, float $confidence, float $spam ): Response {
		return new Response(
			array(
				'model'   => 'jev-latest',
				'answers' => array(
					'spam'  => array(
						'type' => 'noul',
						'noul' => $spam,
					),
					'route' => array(
						'type'       => 'choice',
						'choice'     => $route,
						'confidence' => $confidence,
					),
				),
			)
		);
	}

	/**
	 * Both modules ship, and both are off until switched on.
	 *
	 * @return void
	 */
	public function test_modules_are_off_by_default(): void {
		$all = Modules::all();

		$this->assertArrayHasKey( 'comment_guardrail', $all );
		$this->assertArrayHasKey( 'auto_tagger', $all );
		$this->assertFalse( Modules::is_enabled( 'comment_guardrail' ) );
		$this->assertFalse( Modules::is_enabled( 'auto_tagger' ) );
	}

	/**
	 * Sanitizing a submitted checkbox turns exactly one module on.
	 *
	 * @return void
	 */
	public function test_sanitize_enables_one_module(): void {
		$clean = Modules::sanitize( array( 'comment_guardrail' => array( 'enabled' => '1' ) ) );

		$this->assertTrue( $clean['comment_guardrail']['enabled'] );
		$this->assertFalse( $clean['auto_tagger']['enabled'] );
		$this->assertSame( 0.95, $clean['comment_guardrail']['spam_threshold'] );
	}

	/**
	 * Thresholds outside 0 to 1 are pulled back into range.
	 *
	 * @return void
	 */
	public function test_thresholds_are_clamped(): void {
		$clean = Modules::sanitize(
			array(
				'comment_guardrail' => array(
					'enabled'           => '1',
					'spam_threshold'    => '4.2',
					'approve_threshold' => '-1',
				),
			)
		);

		$this->assertSame( 1.0, $clean['comment_guardrail']['spam_threshold'] );
		$this->assertSame( 0.0, $clean['comment_guardrail']['approve_threshold'] );
	}

	/**
	 * Both questions agreeing at high confidence is the only route to spam.
	 *
	 * @return void
	 */
	public function test_confident_agreement_marks_spam(): void {
		$this->assertSame(
			'spam',
			Comment_Guardrail::decide( $this->verdict( 'trash', 0.99, 0.98 ), array() )
		);
	}

	/**
	 * A confident route that the spam question disagrees with only holds.
	 *
	 * @return void
	 */
	public function test_disagreement_holds_instead_of_spamming(): void {
		$this->assertSame(
			0,
			Comment_Guardrail::decide( $this->verdict( 'trash', 0.99, 0.40 ), array() )
		);
	}

	/**
	 * A clean comment with both signals agreeing is approved.
	 *
	 * @return void
	 */
	public function test_confident_clean_comment_is_approved(): void {
		$this->assertSame(
			1,
			Comment_Guardrail::decide( $this->verdict( 'approve', 0.96, 0.02 ), array() )
		);
	}

	/**
	 * Low confidence always lands on hold, never on a verdict.
	 *
	 * @return void
	 */
	public function test_uncertainty_holds(): void {
		$this->assertSame( 0, Comment_Guardrail::decide( $this->verdict( 'approve', 0.55, 0.30 ), array() ) );
		$this->assertSame( 0, Comment_Guardrail::decide( $this->verdict( 'trash', 0.60, 0.80 ), array() ) );
		$this->assertSame( 0, Comment_Guardrail::decide( $this->verdict( 'hold', 0.99, 0.10 ), array() ) );
	}

	/**
	 * A truncated answer is treated as uncertainty, not as permission.
	 *
	 * @return void
	 */
	public function test_missing_answers_hold(): void {
		$empty = new Response( array( 'answers' => array() ) );

		$this->assertSame( 0, Comment_Guardrail::decide( $empty, array() ) );
	}

	/**
	 * Custom thresholds are honored.
	 *
	 * @return void
	 */
	public function test_custom_thresholds(): void {
		$settings = array(
			'spam_threshold'    => 0.60,
			'approve_threshold' => 0.60,
		);

		$this->assertSame(
			'spam',
			Comment_Guardrail::decide( $this->verdict( 'trash', 0.65, 0.70 ), $settings )
		);
	}

	/**
	 * The tagger asks one noul per term and caps the fan-out.
	 *
	 * @return void
	 */
	public function test_tagger_builds_one_question_per_term(): void {
		$terms = array();

		for ( $i = 1; $i <= 50; $i++ ) {
			$terms[] = (object) array(
				'term_id' => $i,
				'name'    => 'Term ' . $i,
			);
		}

		$questions = Auto_Tagger::questions_for( $terms );

		$this->assertCount( Auto_Tagger::MAX_CANDIDATES, $questions );
		$this->assertArrayHasKey( 'term_1', $questions );
		$this->assertSame( 'noul', $questions['term_1']['type'] );
	}

	/**
	 * Suggestions respect the threshold and come back strongest first.
	 *
	 * @return void
	 */
	public function test_tagger_filters_and_orders_suggestions(): void {
		$terms = array(
			(object) array(
				'term_id' => 1,
				'name'    => 'Provenance',
			),
			(object) array(
				'term_id' => 2,
				'name'    => 'Gardening',
			),
			(object) array(
				'term_id' => 3,
				'name'    => 'Royalties',
			),
		);

		$response = new Response(
			array(
				'answers' => array(
					'term_1' => array(
						'type' => 'noul',
						'noul' => 0.81,
					),
					'term_2' => array(
						'type' => 'noul',
						'noul' => 0.04,
					),
					'term_3' => array(
						'type' => 'noul',
						'noul' => 0.93,
					),
				),
			)
		);

		$suggestions = Auto_Tagger::suggestions_from( $response, $terms, 0.70 );

		$this->assertCount( 2, $suggestions );
		$this->assertSame( 'Royalties', $suggestions[0]['name'] );
		$this->assertSame( 0.93, $suggestions[0]['probability'] );
		$this->assertSame( 'Provenance', $suggestions[1]['name'] );
	}

	/**
	 * A term the model did not answer for is never suggested.
	 *
	 * @return void
	 */
	public function test_tagger_ignores_unanswered_terms(): void {
		$terms = array(
			(object) array(
				'term_id' => 9,
				'name'    => 'Missing',
			),
		);

		$this->assertSame(
			array(),
			Auto_Tagger::suggestions_from( new Response( array( 'answers' => array() ) ), $terms, 0.5 )
		);
	}
}
