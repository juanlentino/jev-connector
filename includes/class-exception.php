<?php
/**
 * Exception type used by the TypeSafe client.
 *
 * @package JevConnector
 */

declare( strict_types = 1 );

namespace JevConnector;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown when a request cannot be built or an answer cannot be read.
 *
 * Transport and API failures are returned as WP_Error, not thrown, so that
 * calling code can follow normal WordPress conventions.
 */
class Exception extends \RuntimeException {
}
