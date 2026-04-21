<?php
/**
 * Security helpers shared across plugin classes.
 *
 * @package PDMI\Onderhoud\Traits
 */

namespace PDMI\Under\Construction\Traits;

defined( 'ABSPATH' ) || exit;

/**
 * Provides IP sanitization and normalization helpers.
 */
trait Security {

	/**
	 * Sanitizes a comma-separated list of IP addresses.
	 *
	 * Invalid entries are silently dropped. IPv6-mapped IPv4 addresses
	 * (::ffff:x.x.x.x) are normalized to plain IPv4 before storing.
	 *
	 * @param string $ip_list Comma-separated IP string from user input.
	 * @return array Indexed array of validated, unique IP strings.
	 */
	protected function sanitize_ip_list( string $ip_list ): array {
		$validated = array();
		$ips       = array_filter( array_map( 'trim', explode( ',', $ip_list ) ) );

		foreach ( $ips as $ip ) {
			$filtered = filter_var( $ip, FILTER_VALIDATE_IP );
			if ( false !== $filtered ) {
				$validated[] = $this->normalize_ip( $filtered );
			}
		}

		return array_values( array_unique( $validated ) );
	}

	/**
	 * Converts an IPv6-mapped IPv4 address to plain IPv4.
	 *
	 * Handles both the short form (::ffff:x.x.x.x) and the fully-expanded
	 * form produced by inet_ntop(). Pure IPv6 addresses are returned unchanged.
	 *
	 * @param string $ip Validated IP address string.
	 * @return string IPv4 string when mappable, original otherwise.
	 */
	protected function normalize_ip( string $ip ): string {
		// Short form: ::ffff:x.x.x.x
		if ( 0 === strncasecmp( $ip, '::ffff:', 7 ) ) {
			$candidate = substr( $ip, 7 );
			if ( filter_var( $candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
				return $candidate;
			}
		}

		// Fully-expanded form: convert via inet_pton / inet_ntop.
		$packed = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false !== $packed && 16 === strlen( $packed ) ) {
			$expanded = @inet_ntop( $packed ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( $expanded && 0 === strncasecmp( $expanded, '::ffff:', 7 ) ) {
				$candidate = substr( $expanded, 7 );
				if ( filter_var( $candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
					return $candidate;
				}
			}
		}

		return $ip;
	}
}
