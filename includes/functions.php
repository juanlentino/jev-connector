<?php
/**
 * Public helper functions.
 *
 * @package JevConnector
 */

declare( strict_types = 1 );

namespace JevConnector;

defined( 'ABSPATH' ) || exit;

/**
 * Ask Jev one or more typed questions.
 *
 * Example:
 *
 *     $answer = JevConnector\ask(
 *         array( 'comment' => $comment_text ),
 *         array(
 *             'spam' => JevConnector\Question::noul( 'Is this comment spam?' ),
 *         )
 *     );
 *
 *     if ( ! is_wp_error( $answer ) && $answer->noul( 'spam' ) > 0.9 ) {
 *         // Act.
 *     }
 *
 * @param mixed               $state     String, array, or object to evaluate.
 * @param array<string,mixed> $questions Map of question id => question array.
 * @param array<string,mixed> $args      Optional request arguments.
 * @return Response|\WP_Error
 */
function ask( $state, array $questions, array $args = array() ) {
	return ( new Client() )->ask( $state, $questions, $args );
}

/**
 * Whether the connector is ready to make requests.
 *
 * @return bool
 */
function is_ready(): bool {
	return ( new Client() )->is_configured();
}
