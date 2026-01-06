<?php
/**
 * Security helpers trait.
 *
 * @package PDMI\Under\Construction\Traits
 */

namespace PDMI\Under\Construction\Traits;

defined( 'ABSPATH' ) || exit;

/**
 * Adds shared security helpers across plugin classes.
 */
trait Security {

	/**
	 * Sanitizes nonce field from settings submissions.
	 *
	 * @param array $input Raw input array.
	 *
	 * @return string
	 */
	protected function sanitize_nonce_field( $input ) {
		return isset( $input['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $input['_wpnonce'] ) ) : '';
	}

	/**
	 * Sanitizes comma-separated IP list.
	 *
	 * @param string $ip_list Comma-separated IP string.
	 *
	 * @return array
	 */
	protected function sanitize_ip_list( $ip_list ) {
		$validated = array();
		$ip_list   = (string) $ip_list;
		$ips       = array_filter(
			array_map(
				'trim',
				explode( ',', $ip_list )
			)
		);

		foreach ( $ips as $ip ) {
			$filtered_ip = filter_var( $ip, FILTER_VALIDATE_IP );
			if ( false !== $filtered_ip ) {
				$validated[] = $filtered_ip;
			}
		}

		return array_values( array_unique( $validated ) );
	}
}

