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
	 * Sanitizes a comma-separated list of IP addresses or CIDR ranges.
	 *
	 * Accepts plain IPs (e.g. 82.168.45.127) and CIDR notation
	 * (e.g. 2a02:a467:fddb:1::/64, 192.168.1.0/24). Invalid entries are
	 * silently dropped. IPv6-mapped IPv4 addresses are normalized to plain IPv4.
	 *
	 * @param string $ip_list Comma-separated IP/CIDR string from user input.
	 * @return array Indexed array of validated, unique IP/CIDR strings.
	 */
	protected function sanitize_ip_list( string $ip_list ): array {
		$validated = array();
		$entries   = array_filter( array_map( 'trim', explode( ',', $ip_list ) ) );

		foreach ( $entries as $entry ) {
			if ( str_contains( $entry, '/' ) ) {
				$cidr = $this->sanitize_cidr( $entry );
				if ( null !== $cidr ) {
					$validated[] = $cidr;
				}
			} else {
				$filtered = filter_var( $entry, FILTER_VALIDATE_IP );
				if ( false !== $filtered ) {
					$validated[] = $this->normalize_ip( $filtered );
				}
			}
		}

		return array_values( array_unique( $validated ) );
	}

	/**
	 * Validates and normalizes a CIDR range string.
	 *
	 * Returns null for invalid input. IPv4 prefix must be 0–32,
	 * IPv6 prefix must be 0–128.
	 *
	 * @param string $cidr Raw CIDR string (e.g. "2a02:a467::/32").
	 * @return string|null Normalized CIDR string, or null if invalid.
	 */
	protected function sanitize_cidr( string $cidr ): ?string {
		$parts = explode( '/', $cidr, 2 );
		if ( 2 !== count( $parts ) ) {
			return null;
		}

		$ip     = trim( $parts[0] );
		$prefix = trim( $parts[1] );

		if ( ! ctype_digit( $prefix ) ) {
			return null;
		}

		$prefix = (int) $prefix;

		if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return ( $prefix >= 0 && $prefix <= 32 ) ? $ip . '/' . $prefix : null;
		}

		if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return ( $prefix >= 0 && $prefix <= 128 ) ? strtolower( $ip ) . '/' . $prefix : null;
		}

		return null;
	}

	/**
	 * Returns true when $ip falls within the given IP or CIDR entry.
	 *
	 * @param string $ip    Visitor IP (already normalized).
	 * @param string $entry Whitelist entry — plain IP or CIDR range.
	 * @return bool
	 */
	protected function ip_matches_entry( string $ip, string $entry ): bool {
		if ( ! str_contains( $entry, '/' ) ) {
			return $ip === $entry;
		}

		[ $network, $prefix ] = explode( '/', $entry, 2 );
		$prefix               = (int) $prefix;
		$ip_bin               = @inet_pton( $ip );      // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$net_bin              = @inet_pton( $network ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( false === $ip_bin || false === $net_bin || strlen( $ip_bin ) !== strlen( $net_bin ) ) {
			return false;
		}

		$total_bits = strlen( $ip_bin ) * 8;
		if ( $prefix < 0 || $prefix > $total_bits ) {
			return false;
		}

		$full_bytes = intdiv( $prefix, 8 );
		$spare_bits = $prefix % 8;

		if ( substr( $ip_bin, 0, $full_bytes ) !== substr( $net_bin, 0, $full_bytes ) ) {
			return false;
		}

		if ( $spare_bits > 0 ) {
			$mask = 0xFF & ( 0xFF << ( 8 - $spare_bits ) );
			if ( ( ord( $ip_bin[ $full_bytes ] ) & $mask ) !== ( ord( $net_bin[ $full_bytes ] ) & $mask ) ) {
				return false;
			}
		}

		return true;
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
		// Short form: ::ffff:x.x.x.x.
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
