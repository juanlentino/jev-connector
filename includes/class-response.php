<?php
/**
 * Typed reader for a System One response body.
 *
 * @package JevConnector
 */

declare( strict_types = 1 );

namespace JevConnector;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps the decoded response and exposes one accessor per answer type.
 */
final class Response {

	/**
	 * Decoded response body.
	 *
	 * @var array<string,mixed>
	 */
	private array $data;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $data Decoded response body.
	 */
	public function __construct( array $data ) {
		$this->data = $data;
	}

	/**
	 * The model that answered.
	 *
	 * @return string
	 */
	public function model(): string {
		return isset( $this->data['model'] ) ? (string) $this->data['model'] : '';
	}

	/**
	 * Token usage for the call.
	 *
	 * @return array<string,int>
	 */
	public function usage(): array {
		$usage = isset( $this->data['usage'] ) && is_array( $this->data['usage'] ) ? $this->data['usage'] : array();

		return array(
			'input_tokens'  => isset( $usage['input_tokens'] ) ? (int) $usage['input_tokens'] : 0,
			'output_tokens' => isset( $usage['output_tokens'] ) ? (int) $usage['output_tokens'] : 0,
		);
	}

	/**
	 * All answers, keyed by question id.
	 *
	 * @return array<string,mixed>
	 */
	public function answers(): array {
		return isset( $this->data['answers'] ) && is_array( $this->data['answers'] )
			? $this->data['answers']
			: array();
	}

	/**
	 * A single raw answer.
	 *
	 * @param string $id Question id.
	 * @return array<string,mixed>|null
	 */
	public function answer( string $id ): ?array {
		$answers = $this->answers();

		return isset( $answers[ $id ] ) && is_array( $answers[ $id ] ) ? $answers[ $id ] : null;
	}

	/**
	 * Probability that a noul answer is yes.
	 *
	 * @param string $id Question id.
	 * @return float|null Null when the answer is missing or not a noul.
	 */
	public function noul( string $id ): ?float {
		$answer = $this->answer( $id );

		return ( $answer && isset( $answer['noul'] ) ) ? (float) $answer['noul'] : null;
	}

	/**
	 * Selected option for a choice answer.
	 *
	 * @param string $id Question id.
	 * @return string|null
	 */
	public function choice( string $id ): ?string {
		$answer = $this->answer( $id );

		return ( $answer && isset( $answer['choice'] ) ) ? (string) $answer['choice'] : null;
	}

	/**
	 * Probability-weighted score.
	 *
	 * @param string $id Question id.
	 * @return float|null
	 */
	public function score( string $id ): ?float {
		$answer = $this->answer( $id );

		return ( $answer && isset( $answer['score'] ) ) ? (float) $answer['score'] : null;
	}

	/**
	 * Confidence for a choice or score answer.
	 *
	 * Noul answers do not carry confidence; use the noul value itself, where
	 * a result near 0 or 1 is the confident case.
	 *
	 * @param string $id Question id.
	 * @return float|null
	 */
	public function confidence( string $id ): ?float {
		$answer = $this->answer( $id );

		return ( $answer && isset( $answer['confidence'] ) ) ? (float) $answer['confidence'] : null;
	}

	/**
	 * Full probability distribution for a choice or score answer.
	 *
	 * @param string $id Question id.
	 * @return array<string,float>
	 */
	public function probabilities( string $id ): array {
		$answer = $this->answer( $id );

		if ( ! $answer || ! isset( $answer['probabilities'] ) || ! is_array( $answer['probabilities'] ) ) {
			return array();
		}

		return array_map( 'floatval', $answer['probabilities'] );
	}

	/**
	 * Level descriptions returned with a score answer.
	 *
	 * @param string $id Question id.
	 * @return array<string,string>
	 */
	public function legend( string $id ): array {
		$answer = $this->answer( $id );

		if ( ! $answer || ! isset( $answer['legend'] ) || ! is_array( $answer['legend'] ) ) {
			return array();
		}

		return array_map( 'strval', $answer['legend'] );
	}

	/**
	 * Confidence gate.
	 *
	 * For choice and score answers this compares the reported confidence with
	 * the threshold. For noul answers it treats a value far from 0.5 as
	 * confident, so the same gate works across all three primitives.
	 *
	 * @param string $id        Question id.
	 * @param float  $threshold Minimum confidence, 0 to 1.
	 * @return bool
	 */
	public function is_confident( string $id, float $threshold = 0.8 ): bool {
		$confidence = $this->confidence( $id );

		if ( null !== $confidence ) {
			return $confidence >= $threshold;
		}

		$noul = $this->noul( $id );

		if ( null === $noul ) {
			return false;
		}

		return ( abs( $noul - 0.5 ) * 2 ) >= $threshold;
	}

	/**
	 * The decoded body, untouched.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return $this->data;
	}
}
