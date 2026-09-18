<?php
/**
 * Question builder tests.
 *
 * @package JevConnector
 */

declare( strict_types = 1 );

use JevConnector\Exception;
use JevConnector\Question;
use PHPUnit\Framework\TestCase;

/**
 * Covers the three primitives and map validation.
 */
final class QuestionTest extends TestCase {

	/**
	 * A noul carries only type and instructions unless criteria are given.
	 *
	 * @return void
	 */
	public function test_noul_shape(): void {
		$this->assertSame(
			array(
				'type'         => 'noul',
				'instructions' => 'Is this urgent?',
			),
			Question::noul( 'Is this urgent?' )
		);
	}

	/**
	 * Choice keeps the option map as criteria.
	 *
	 * @return void
	 */
	public function test_choice_shape(): void {
		$question = Question::choice(
			'Which team?',
			array(
				'returns'  => 'Exchanges and refunds',
				'shipping' => 'Delivery status',
			)
		);

		$this->assertSame( 'choice', $question['type'] );
		$this->assertArrayHasKey( 'returns', $question['criteria'] );
	}

	/**
	 * Choice needs at least two options.
	 *
	 * @return void
	 */
	public function test_choice_rejects_single_option(): void {
		$this->expectException( Exception::class );
		Question::choice( 'Which team?', array( 'returns' => 'Only one' ) );
	}

	/**
	 * Score keeps levels ordered and zero-indexed.
	 *
	 * @return void
	 */
	public function test_score_reindexes_levels(): void {
		$question = Question::score(
			'How severe?',
			array(
				5 => 'Cosmetic',
				9 => 'Blocking',
			)
		);

		$this->assertSame( array( 'Cosmetic', 'Blocking' ), $question['criteria'] );
	}

	/**
	 * Score caps out at ten levels.
	 *
	 * @return void
	 */
	public function test_score_rejects_eleven_levels(): void {
		$this->expectException( Exception::class );
		Question::score( 'How severe?', array_fill( 0, 11, 'level' ) );
	}

	/**
	 * An unknown type is refused before any request is made.
	 *
	 * @return void
	 */
	public function test_validate_map_rejects_unknown_type(): void {
		$this->expectException( Exception::class );
		Question::validate_map(
			array(
				'q' => array(
					'type'         => 'vibes',
					'instructions' => 'Anything?',
				),
			)
		);
	}

	/**
	 * An empty map is refused.
	 *
	 * @return void
	 */
	public function test_validate_map_rejects_empty(): void {
		$this->expectException( Exception::class );
		Question::validate_map( array() );
	}
}
