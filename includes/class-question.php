<?php
/**
 * Builders for the three TypeSafe question primitives.
 *
 * @package JevConnector
 */

declare( strict_types = 1 );

namespace JevConnector;

defined( 'ABSPATH' ) || exit;

/**
 * Static factories that return the array shape the API expects.
 *
 * Every factory returns a plain array so the result can be dropped straight
 * into the `questions` map of a request and JSON-encoded without conversion.
 */
final class Question {

	/**
	 * Build a noul question: the probability that a statement is true.
	 *
	 * @param string                          $instructions The yes/no question to evaluate.
	 * @param array<string,mixed>|string|null $criteria Optional extra context.
	 * @return array<string,mixed>
	 */
	public static function noul( string $instructions, $criteria = null ): array {
		return self::build( 'noul', $instructions, $criteria );
	}

	/**
	 * Build a choice question: pick one option from a named set.
	 *
	 * @param string              $instructions The question to evaluate.
	 * @param array<string,mixed> $criteria     Map of option name => description
	 *                                          (string, null, or array). At least
	 *                                          two options are required.
	 * @return array<string,mixed>
	 *
	 * @throws Exception When fewer than two options are supplied.
	 */
	public static function choice( string $instructions, array $criteria ): array {
		if ( count( $criteria ) < 2 ) {
			throw new Exception( 'A choice question needs at least two options.' );
		}

		return self::build( 'choice', $instructions, $criteria );
	}

	/**
	 * Build a score question: rate state against ordered levels.
	 *
	 * Levels are numbered from 0 by their position in the array.
	 *
	 * @param string            $instructions The question to evaluate.
	 * @param array<int,string> $criteria Ordered level descriptions, 2 to 10 entries.
	 * @return array<string,mixed>
	 *
	 * @throws Exception When the level count is outside the supported range.
	 */
	public static function score( string $instructions, array $criteria ): array {
		$levels = array_values( $criteria );

		if ( count( $levels ) < 2 || count( $levels ) > 10 ) {
			throw new Exception( 'A score question needs between 2 and 10 levels.' );
		}

		return self::build( 'score', $instructions, $levels );
	}

	/**
	 * Assemble a question array.
	 *
	 * @param string $type         One of noul, choice, score.
	 * @param string $instructions The question text.
	 * @param mixed  $criteria     Optional criteria payload.
	 * @return array<string,mixed>
	 */
	private static function build( string $type, string $instructions, $criteria ): array {
		$question = array(
			'type'         => $type,
			'instructions' => $instructions,
		);

		if ( null !== $criteria && array() !== $criteria ) {
			$question['criteria'] = $criteria;
		}

		return $question;
	}

	/**
	 * Validate a questions map before it is sent.
	 *
	 * @param array<string,mixed> $questions Map of question id => question array.
	 * @return void
	 *
	 * @throws Exception When the map is empty or a member is malformed.
	 */
	public static function validate_map( array $questions ): void {
		if ( array() === $questions ) {
			throw new Exception( 'At least one question is required.' );
		}

		foreach ( $questions as $id => $question ) {
			if ( ! is_string( $id ) || '' === $id ) {
				throw new Exception( 'Every question needs a non-empty string id.' );
			}

			if ( ! is_array( $question ) || ! isset( $question['type'], $question['instructions'] ) ) {
				throw new Exception(
					sprintf( 'Question "%s" must have a type and instructions.', esc_html( $id ) )
				);
			}

			if ( ! in_array( $question['type'], array( 'noul', 'choice', 'score' ), true ) ) {
				throw new Exception(
					sprintf( 'Question "%s" has an unknown type "%s".', esc_html( $id ), esc_html( (string) $question['type'] ) )
				);
			}
		}
	}
}
