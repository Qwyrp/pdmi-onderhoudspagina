<?php
/**
 * Public-facing functionality: renders the maintenance screen for non-allowed visitors.
 *
 * @package PDMI\Onderhoud\Public_
 */

namespace PDMI\Under\Construction\Public_;

defined( 'ABSPATH' ) || exit;

use PDMI\Under\Construction\Traits\Security;

require_once PDMIUC_PLUGIN_DIR . 'includes/trait-security.php';

/**
 * Handles the frontend maintenance gate.
 */
class Public_Class {
	use Security;

	/** @var string WordPress options key. */
	private const OPTION_KEY = 'pdmiuc_settings';

	/** @var string Cookie name for password-based access. */
	private const ACCESS_COOKIE = 'pdmiuc_access';

	/** @var string Nonce action for the password form. */
	private const NONCE_ACTION = 'pdmiuc_password_form';

	/** @var string Nonce field name in the password form. */
	private const NONCE_FIELD = 'pdmiuc_password_nonce';

	/**
	 * Checks whether the maintenance screen should be shown and renders it if so.
	 *
	 * Bails early for logged-in administrators, allowed IPs, and valid cookie holders.
	 *
	 * @return void
	 */
	public function maybe_render_maintenance_screen(): void {
		$settings = get_option( self::OPTION_KEY, array() );

		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		// Admins always pass through.
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle password submission before checking access so a valid submission
		// sets the cookie and redirects in the same request.
		$this->handle_password_submission( $settings );

		if ( $this->has_cookie_access( $settings ) ) {
			return;
		}

		if ( $this->is_ip_allowed( $settings ) ) {
			return;
		}

		status_header( 503 );
		nocache_headers();

		wp_die(
			$this->build_maintenance_markup( $settings ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup is built with proper escaping functions below.
			__( 'PDMI Onderhoud', 'pdmi-onderhoudspagina' ),
			array( 'response' => 503 )
		);
	}

	// -------------------------------------------------------------------------
	// Access checks
	// -------------------------------------------------------------------------

	/**
	 * Returns true when the visitor's IP is in the allowed list.
	 *
	 * @param array $settings Plugin settings.
	 * @return bool
	 */
	private function is_ip_allowed( array $settings ): bool {
		$allowed = isset( $settings['allowed_ips'] ) && is_array( $settings['allowed_ips'] )
			? $settings['allowed_ips']
			: array();

		if ( empty( $allowed ) ) {
			return false;
		}

		return in_array( $this->get_visitor_ip(), $allowed, true );
	}

	/**
	 * Returns true when the visitor holds a valid access cookie.
	 *
	 * @param array $settings Plugin settings.
	 * @return bool
	 */
	private function has_cookie_access( array $settings ): bool {
		if ( empty( $settings['access_password_hash'] ) ) {
			return false;
		}

		return isset( $_COOKIE[ self::ACCESS_COOKIE ] ) && '1' === $_COOKIE[ self::ACCESS_COOKIE ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	// -------------------------------------------------------------------------
	// Password form handling
	// -------------------------------------------------------------------------

	/**
	 * Validates a submitted password and sets the access cookie on success.
	 *
	 * @param array $settings Plugin settings.
	 * @return void
	 */
	private function handle_password_submission( array $settings ): void {
		if ( empty( $settings['access_password_hash'] ) ) {
			return;
		}

		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( empty( $_POST['pdmiuc_password'] ) || empty( $_POST[ self::NONCE_FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		$password = (string) wp_unslash( $_POST['pdmiuc_password'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! wp_check_password( $password, $settings['access_password_hash'] ) ) {
			return;
		}

		setcookie(
			self::ACCESS_COOKIE,
			'1',
			time() + DAY_IN_SECONDS,
			defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
			defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
			is_ssl(),
			true
		);
		$_COOKIE[ self::ACCESS_COOKIE ] = '1';

		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: home_url( '/' );

		wp_safe_redirect( $request_uri );
		exit;
	}

	// -------------------------------------------------------------------------
	// Visitor IP
	// -------------------------------------------------------------------------

	/**
	 * Returns the visitor's IP address normalized to IPv4 when possible.
	 *
	 * Checks common proxy headers in order of reliability; falls back to
	 * REMOTE_ADDR. The Security trait's normalize_ip() converts IPv6-mapped
	 * IPv4 addresses to plain IPv4 so they match whitelist entries.
	 *
	 * @return string
	 */
	private function get_visitor_ip(): string {
		$keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $keys as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				continue;
			}

			$candidates = array_map(
				'trim',
				explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			);

			foreach ( $candidates as $candidate ) {
				$validated = filter_var( $candidate, FILTER_VALIDATE_IP );
				if ( false !== $validated ) {
					return $this->normalize_ip( $validated );
				}
			}
		}

		return '0.0.0.0';
	}

	// -------------------------------------------------------------------------
	// Markup builders
	// -------------------------------------------------------------------------

	/**
	 * Builds and returns the full HTML string for the maintenance screen.
	 *
	 * All dynamic values are escaped at the point of insertion.
	 *
	 * @param array $settings Plugin settings.
	 * @return string
	 */
	private function build_maintenance_markup( array $settings ): string {
		$display_type = $settings['display_type'] ?? 'text';
		$text_content = wp_kses_post( $settings['text_content'] ?? '' );
		$image_url    = esc_url( $settings['image_url'] ?? '' );
		$has_password = ! empty( $settings['access_password_hash'] );
		$is_image     = ( 'image' === $display_type && ! empty( $image_url ) );

		$style = $this->get_lockscreen_css( $has_password );

		$wrapper_class = $is_image ? 'pdmiuc-stage pdmiuc-stage--image' : 'pdmiuc-stage pdmiuc-stage--text';

		$open  = '<div class="' . esc_attr( $wrapper_class ) . '"><div class="pdmiuc-layer">';
		$close = '</div></div>';

		if ( $is_image ) {
			$body = sprintf(
				'<div class="pdmiuc-media"><img src="%1$s" alt="%2$s" /></div>',
				esc_url( $image_url ),
				esc_attr__( 'Onderhoudsafbeelding', 'pdmi-onderhoudspagina' )
			);
		} else {
			$default = '<div class="pdmiuc-message"><h2>'
				. esc_html__( 'We zijn zo terug', 'pdmi-onderhoudspagina' )
				. '</h2><p>'
				. esc_html__( 'Onze website krijgt op dit moment een update. Kom later gerust terug.', 'pdmi-onderhoudspagina' )
				. '</p></div>';

			$body = ! empty( $text_content )
				? '<div class="pdmiuc-message">' . $text_content . '</div>'
				: $default;
		}

		$password_markup = $has_password ? $this->get_password_form_markup() : '';
		$script          = $has_password ? $this->get_toggle_script() : '';

		return $style . $open . $body . $password_markup . $close . $script;
	}

	/**
	 * Returns the CSS block for the maintenance screen.
	 *
	 * @param bool $has_password Whether to include password-form styles.
	 * @return string
	 */
	private function get_lockscreen_css( bool $has_password ): string {
		$css  = 'html,body{margin:0!important;padding:0!important;min-height:100%;background:#fff!important;color:#000;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}';
		$css .= 'body{min-height:100vh;overflow:hidden;}';
		$css .= '.wp-die-message{margin:0!important;padding:0!important;border:none!important;background:#fff!important;box-shadow:none!important;max-width:none;width:100%;height:100%;}';
		$css .= '#error-page{margin:0;padding:0;background:#fff;}';
		$css .= '.pdmiuc-stage{position:fixed;inset:0;width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#fff;}';
		$css .= '.pdmiuc-layer{width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;}';
		$css .= '.pdmiuc-stage--text .pdmiuc-layer{padding:8vw 6vw;}';
		$css .= '.pdmiuc-stage--image .pdmiuc-layer{padding:4vw 6vw;}';
		$css .= '.pdmiuc-message{width:100%;max-width:960px;margin:0 auto;text-align:center;white-space:pre-line;}';
		$css .= '.pdmiuc-message h2{margin:0 0 1rem;font-size:clamp(2.4rem,5vw,4rem);color:#000;}';
		$css .= '.pdmiuc-message p{margin:0 auto;font-size:1.2rem;line-height:1.8;max-width:720px;color:#000;}';
		$css .= '.pdmiuc-media{width:100%;max-width:960px;display:flex;align-items:center;justify-content:center;background:#fff;margin:0 auto 1.5rem;}';
		$css .= '.pdmiuc-media img{max-width:100%;max-height:80vh;width:auto;height:auto;object-fit:contain;display:block;}';
		$css .= '@media(max-width:768px){.pdmiuc-stage--text .pdmiuc-layer{padding:12vw 6vw;}}';

		if ( $has_password ) {
			$css .= '.pdmiuc-password-wrapper{margin-top:2rem;text-align:center;}';
			$css .= '.pdmiuc-password-toggle{background:none;border:none;padding:0;color:#000;font-weight:600;cursor:pointer;text-decoration:underline;text-underline-offset:0.15em;font-size:0.875rem;}';
			$css .= '.pdmiuc-password-toggle:focus{outline:2px solid #000;outline-offset:2px;}';
			$css .= '.pdmiuc-password-form{margin-top:1rem;display:none;flex-wrap:wrap;justify-content:center;gap:0.75rem;}';
			$css .= '.pdmiuc-password-form input[type="password"]{padding:0.5rem 0.75rem;font-size:1rem;min-width:220px;border:1px solid #ccc;border-radius:4px;}';
			$css .= '.pdmiuc-password-form button{padding:0.5rem 1.25rem;font-size:1rem;border-radius:4px;border:none;background:#000;color:#fff;cursor:pointer;}';
			$css .= '.pdmiuc-password-form button:hover{background:#111;}';
		}

		return '<style>' . $css . '</style>';
	}

	/**
	 * Returns the password-form HTML.
	 *
	 * @return string
	 */
	private function get_password_form_markup(): string {
		$nonce_field = wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false );

		$markup  = '<div class="pdmiuc-password-wrapper">';
		$markup .= '<button type="button" class="pdmiuc-password-toggle">' . esc_html__( 'WACHTWOORD', 'pdmi-onderhoudspagina' ) . '</button>';
		$markup .= '<form method="post" class="pdmiuc-password-form">';
		$markup .= $nonce_field;
		$markup .= '<label class="screen-reader-text" for="pdmiuc_password">' . esc_html__( 'Voer wachtwoord in om de site te bekijken:', 'pdmi-onderhoudspagina' ) . '</label>';
		$markup .= '<input type="password" id="pdmiuc_password" name="pdmiuc_password" autocomplete="off" />';
		$markup .= '<button type="submit">' . esc_html__( 'Toegang', 'pdmi-onderhoudspagina' ) . '</button>';
		$markup .= '</form>';
		$markup .= '</div>';

		return $markup;
	}

	/**
	 * Returns a minimal inline script that toggles the password form visibility.
	 *
	 * Kept inline because the maintenance page is rendered outside the normal
	 * WordPress template — wp_enqueue_script() is not available at this point.
	 *
	 * @return string
	 */
	private function get_toggle_script(): string {
		return '<script>'
			. 'document.addEventListener("DOMContentLoaded",function(){'
			. 'document.querySelectorAll(".pdmiuc-password-toggle").forEach(function(toggle){'
			. 'toggle.addEventListener("click",function(e){'
			. 'e.preventDefault();'
			. 'var w=toggle.closest(".pdmiuc-password-wrapper");'
			. 'if(!w)return;'
			. 'var f=w.querySelector(".pdmiuc-password-form");'
			. 'if(!f)return;'
			. 'f.style.display="none"===getComputedStyle(f).display?"flex":"none";'
			. '});});});'
			. '</script>';
	}
}
