<?php
/**
 * Response cache keyed by the content of the request.
 *
 * @package JevConnector
 */

declare( strict_types = 1 );

namespace JevConnector;

defined( 'ABSPATH' ) || exit;

/**
 * Stores decoded responses in transients so identical evaluations are free.
 *
 * The key is a hash of the exact payload, so any change to the state, the
 * questions, or the model produces a miss. Nothing is cached for a request
 * that failed.
 */
final class Cache {

	public const PREFIX      = 'jevc_';
	public const DEFAULT_TTL = HOUR_IN_SECONDS;

	/**
	 * Build a cache key for a payload.
	 *
	 * @param array<string,mixed> $payload Request body.
	 * @return string
	 */
	public static function key( array $payload ): string {
		return self::PREFIX . md5( (string) wp_json_encode( $payload ) );
	}

	/**
	 * How long to keep a response, in seconds. Zero disables caching.
	 *
	 * @param array<string,mixed> $payload Request body.
	 * @return int
	 */
	public static function ttl( array $payload ): int {
		/**
		 * Filters the cache lifetime in seconds. Return 0 to disable caching.
		 *
		 * @param int                 $ttl     Lifetime in seconds.
		 * @param array<string,mixed> $payload Request body.
		 */
		return max( 0, (int) apply_filters( 'jevc_cache_ttl', self::DEFAULT_TTL, $payload ) );
	}

	/**
	 * Read a cached response.
	 *
	 * @param array<string,mixed> $payload Request body.
	 * @return Response|null
	 */
	public static function get( array $payload ): ?Response {
		if ( 0 === self::ttl( $payload ) ) {
			return null;
		}

		$cached = get_transient( self::key( $payload ) );

		return is_array( $cached ) ? new Response( $cached ) : null;
	}

	/**
	 * Store a response.
	 *
	 * @param array<string,mixed> $payload Request body.
	 * @param Response            $response Decoded response.
	 * @return void
	 */
	public static function set( array $payload, Response $response ): void {
		$ttl = self::ttl( $payload );

		if ( 0 === $ttl ) {
			return;
		}

		set_transient( self::key( $payload ), $response->to_array(), $ttl );
	}
}
