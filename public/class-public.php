<?php
/**
 * Public-facing functionality.
 *
 * @package PDMI\Under\Construction\Public_
 */

namespace PDMI\Under\Construction\Public_;

defined( 'ABSPATH' ) || exit;

/**
 * Handles frontend logic.
 */
class Public_Class {

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_name Plugin slug.
	 * @param string $version     Plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Registers public assets (placeholder for future use).
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		// Reserved for future scripts/styles.
	}

	/**
	 * Renders maintenance experience if enabled.
	 *
	 * @return void
	 */
	public function maybe_render_maintenance_screen() {
		$settings = get_option( 'pdmiuc_settings', array() );

		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		// Handle password form submission and early access.
		$this->handle_password_submission( $settings );

		if ( $this->has_password_access( $settings ) ) {
			return;
		}

		if ( $this->is_ip_allowed( $settings ) ) {
			return;
		}

		status_header( 503 );
		nocache_headers();

		$content = $this->build_maintenance_markup( $settings );

		wp_die(
			$content,
			__( 'PDMI Onderhoudspagina', 'pdmi-under-construction' ),
			array(
				'response' => 503,
			)
		);
	}

	/**
	 * Determines if visitor IP is whitelisted.
	 *
	 * @param array $settings Plugin settings.
	 *
	 * @return bool
	 */
	private function is_ip_allowed( $settings ) {
		$allowed_ips = isset( $settings['allowed_ips'] ) && is_array( $settings['allowed_ips'] ) ? $settings['allowed_ips'] : array();

		if ( empty( $allowed_ips ) ) {
			return false;
		}

		$visitor_ip = $this->get_visitor_ip();

		return in_array( $visitor_ip, $allowed_ips, true );
	}

	/**
	 * Returns visitor IP address.
	 *
	 * @return string
	 */
	private function get_visitor_ip() {
		$ip_keys = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$ip_list = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$ip_candidates = array_map( 'trim', explode( ',', $ip_list ) );

				foreach ( $ip_candidates as $ip_candidate ) {
					$validated = filter_var( $ip_candidate, FILTER_VALIDATE_IP );
					if ( false !== $validated ) {
						return $validated;
					}
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Builds markup for maintenance page.
	 *
	 * @param array $settings Settings array.
	 *
	 * @return string
	 */
	private function build_maintenance_markup( $settings ) {
		$display_type = $settings['display_type'] ?? 'text';
		$text_content = wp_kses_post( $settings['text_content'] ?? '' );
		$image_url    = esc_url( $settings['image_url'] ?? '' );
		$has_password = ! empty( $settings['access_password_hash'] ?? '' );

		$style = '<style>html,body{margin:0!important;padding:0!important;min-height:100%;background:#fff!important;color:#000;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}'
			. 'body{min-height:100vh;overflow:hidden;}'
			. '.wp-die-message{margin:0!important;padding:0!important;border:none!important;background:#fff!important;box-shadow:none!important;max-width:none;width:100%;height:100%;}'
			. '#error-page{margin:0;padding:0;background:#fff;}'
			. '.pdmiuc-stage{position:fixed;inset:0;width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#fff;}'
			. '.pdmiuc-layer{width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;}'
			. '.pdmiuc-stage--text .pdmiuc-layer{padding:8vw 6vw;}'
			. '.pdmiuc-stage--image .pdmiuc-layer{padding:4vw 6vw;}'
			. '.pdmiuc-message{width:100%;max-width:960px;margin:0 auto;text-align:center;white-space:pre-line;}'
			. '.pdmiuc-message h2{margin:0 0 1rem;font-size:clamp(2.4rem,5vw,4rem);color:#000;}'
			. '.pdmiuc-message p{margin:0 auto;font-size:1.2rem;line-height:1.8;max-width:720px;color:#000;}'
			. '.pdmiuc-media{width:100%;max-width:960px;display:flex;align-items:center;justify-content:center;background:#fff;margin:0 auto 1.5rem;}'
			. '.pdmiuc-media img{max-width:100%;max-height:80vh;width:auto;height:auto;object-fit:contain;display:block;}'
			. '.pdmiuc-password-wrapper{margin-top:2rem;text-align:center;}'
			. '.pdmiuc-password-toggle{background:none;border:none;padding:0;color:#000;font-weight:600;cursor:pointer;text-decoration:underline;text-underline-offset:0.15em;font-size:0.875rem;}'
			. '.pdmiuc-password-toggle:focus{outline:2px solid #000;outline-offset:2px;}'
			. '.pdmiuc-password-form{margin-top:1rem;display:none;flex-wrap:wrap;justify-content:center;gap:0.75rem;}'
			. '.pdmiuc-password-form input[type="password"]{padding:0.5rem 0.75rem;font-size:1rem;min-width:220px;border:1px solid #ccc;border-radius:4px;}'
			. '.pdmiuc-password-form button{padding:0.5rem 1.25rem;font-size:1rem;border-radius:4px;border:none;background:#000;color:#fff;cursor:pointer;}'
			. '.pdmiuc-password-form button:hover{background:#111;}'
			. '@media (max-width:768px){.pdmiuc-stage--text .pdmiuc-layer{padding:12vw 6vw;}}'
			. '</style>';

		$wrapper_classes = array( 'pdmiuc-stage' );
		$is_image        = ( 'image' === $display_type && ! empty( $image_url ) );
		if ( $is_image ) {
			$wrapper_classes[] = 'pdmiuc-stage--image';
		} else {
			$wrapper_classes[] = 'pdmiuc-stage--text';
		}

		$body_open  = '<div class="' . esc_attr( implode( ' ', array_map( 'sanitize_html_class', $wrapper_classes ) ) ) . '">';
		$body_open .= '<div class="pdmiuc-layer">';
		$body_close = '</div></div>';

		if ( $is_image ) {
			$content = sprintf(
				'<div class="pdmiuc-media"><img src="%1$s" alt="%2$s" /></div>',
				esc_url( $image_url ),
				esc_attr__( 'Onderhoudsafbeelding', 'pdmi-under-construction' )
			);
		} else {
			$default_text = '<div class="pdmiuc-message"><h2>' . esc_html__( 'We zijn zo terug', 'pdmi-under-construction' ) . '</h2><p>' . esc_html__( 'Onze website krijgt op dit moment een update. Kom later gerust terug voor de nieuwste versie.', 'pdmi-under-construction' ) . '</p></div>';

			$content = ! empty( $text_content )
				? '<div class="pdmiuc-message">' . $text_content . '</div>'
				: $default_text;
		}
		if ( $has_password ) {
			$content .= $this->get_password_form_markup();
		}

		$script = '';
		if ( $has_password ) {
			$script = '<script>'
				. 'document.addEventListener("DOMContentLoaded",function(){'
				. 'var toggles=document.querySelectorAll(".pdmiuc-password-toggle");'
				. 'toggles.forEach(function(toggle){'
				. 'toggle.addEventListener("click",function(e){e.preventDefault();'
				. 'var wrapper=toggle.closest(".pdmiuc-password-wrapper");'
				. 'if(!wrapper){return;}'
				. 'var form=wrapper.querySelector(".pdmiuc-password-form");'
				. 'if(!form){return;}'
				. 'if("none"===getComputedStyle(form).display){form.style.display="flex";}else{form.style.display="none";}'
				. '});'
				. '});'
				. '});'
				. '</script>';
		}

		return $style . $body_open . $content . $body_close . $script;
	}

	/**
	 * Returns password form markup.
	 *
	 * @return string
	 */
	private function get_password_form_markup() {
		$link_text   = esc_html__( 'WACHTWOORD', 'pdmi-under-construction' );
		$label_text  = esc_html__( 'Voer wachtwoord in om de site te bekijken:', 'pdmi-under-construction' );
		$button_text = esc_html__( 'Toegang', 'pdmi-under-construction' );
		$nonce_field = wp_nonce_field( 'pdmiuc_password_form', 'pdmiuc_password_nonce', true, false );

		$markup  = '<div class="pdmiuc-password-wrapper">';
		$markup .= '<button type="button" class="pdmiuc-password-toggle">' . $link_text . '</button>';
		$markup .= '<form method="post" class="pdmiuc-password-form">';
		$markup .= $nonce_field;
		$markup .= '<label class="screen-reader-text" for="pdmiuc_password">' . $label_text . '</label>';
		$markup .= '<input type="password" id="pdmiuc_password" name="pdmiuc_password" autocomplete="off" />';
		$markup .= '<button type="submit">' . $button_text . '</button>';
		$markup .= '</form>';
		$markup .= '</div>';

		return $markup;
	}

	/**
	 * Handles password form submission.
	 *
	 * @param array $settings Settings array.
	 *
	 * @return void
	 */
	private function handle_password_submission( $settings ) {
		if ( empty( $settings['access_password_hash'] ?? '' ) ) {
			return;
		}

		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( empty( $_POST['pdmiuc_password'] ) || empty( $_POST['pdmiuc_password_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['pdmiuc_password_nonce'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! wp_verify_nonce( $nonce, 'pdmiuc_password_form' ) ) {
			return;
		}

		$password = (string) wp_unslash( $_POST['pdmiuc_password'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! wp_check_password( $password, $settings['access_password_hash'] ) ) {
			return;
		}

		$cookie_value = '1';
		$expire       = time() + DAY_IN_SECONDS;
		$secure       = is_ssl();
		$httponly     = true;
		$path         = defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
		$domain       = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';

		setcookie( 'pdmiuc_access', $cookie_value, $expire, $path, $domain, $secure, $httponly );
		$_COOKIE['pdmiuc_access'] = $cookie_value;

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : home_url( '/' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		wp_safe_redirect( $request_uri );
		exit;
	}

	/**
	 * Checks if visitor already has password-based access.
	 *
	 * @param array $settings Settings array.
	 *
	 * @return bool
	 */
	private function has_password_access( $settings ) {
		if ( empty( $settings['access_password_hash'] ?? '' ) ) {
			return false;
		}

		return isset( $_COOKIE['pdmiuc_access'] ) && '1' === $_COOKIE['pdmiuc_access']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}
}

