<?php
/**
 * Admin functionality definition.
 *
 * @package PDMI\Under\Construction\Admin
 */

namespace PDMI\Under\Construction\Admin;

defined( 'ABSPATH' ) || exit;

use PDMI\Under\Construction\Traits\Security;

require_once __DIR__ . '/../includes/trait-security.php';

/**
 * Handles admin-facing hooks.
 */
class Admin {
	use Security;

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
	 * Adds settings page under WordPress Settings.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'PDMI Onderhoudspagina', 'pdmi-under-construction' ),
			__( 'PDMI Onderhoudspagina', 'pdmi-under-construction' ),
			'manage_options',
			'pdmi-under-construction',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Registers settings, section, and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'pdmiuc_settings_group',
			'pdmiuc_settings',
			array( $this, 'sanitize_settings' )
		);

		add_settings_section(
			'pdmiuc_main_section',
			__( 'Onderhoudspagina', 'pdmi-under-construction' ),
			array( $this, 'render_settings_section' ),
			'pdmi-under-construction'
		);

		$this->add_field(
			'pdmiuc_enabled',
			__( 'Onderhoudspagina inschakelen', 'pdmi-under-construction' ),
			array( $this, 'render_enabled_field' )
		);

		$this->add_field(
			'pdmiuc_allowed_ips',
			__( 'Toegestane IP-adressen', 'pdmi-under-construction' ),
			array( $this, 'render_allowed_ips_field' )
		);

		$this->add_field(
			'pdmiuc_display_type',
			__( 'Weergave', 'pdmi-under-construction' ),
			array( $this, 'render_display_type_field' )
		);

		$this->add_field(
			'pdmiuc_text_content',
			__( 'Tekstinvoer', 'pdmi-under-construction' ),
			array( $this, 'render_text_content_field' )
		);

		$this->add_field(
			'pdmiuc_image_url',
			__( 'Afbeeldings-URL', 'pdmi-under-construction' ),
			array( $this, 'render_image_url_field' )
		);

		$this->add_field(
			'pdmiuc_access_password',
			__( 'Toegangswachtwoord', 'pdmi-under-construction' ),
			array( $this, 'render_access_password_field' )
		);
	}

	/**
	 * Enqueues admin scripts for the settings UI.
	 *
	 * @param string $hook Current admin page hook.
	 *
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'settings_page_pdmi-under-construction' !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'pdmiuc-admin',
			plugins_url( 'admin/js/admin.js', PDMIUC_PLUGIN_FILE ),
			array( 'jquery' ),
			$this->version,
			true
		);
	}

	/**
	 * Helper to register a field.
	 *
	 * @param string   $id       Field ID.
	 * @param string   $title    Field title.
	 * @param callable $callback Callback.
	 *
	 * @return void
	 */
	private function add_field( $id, $title, $callback ) {
		add_settings_field(
			$id,
			$title,
			$callback,
			'pdmi-under-construction',
			'pdmiuc_main_section'
		);
	}

	/**
	 * Sanitizes settings array.
	 *
	 * @param array $input Raw values.
	 *
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$input    = is_array( $input ) ? wp_unslash( $input ) : array();
		$output   = array();
		$allowed  = isset( $input['allowed_ips'] ) ? $input['allowed_ips'] : '';
		$existing = get_option( 'pdmiuc_settings', array() );

		$output['enabled']      = ! empty( $input['enabled'] );
		$output['allowed_ips']  = $this->sanitize_ip_list( $allowed );
		$output['display_type'] = in_array( $input['display_type'] ?? 'text', array( 'text', 'image' ), true ) ? $input['display_type'] : 'text';
		$output['text_content'] = isset( $input['text_content'] ) ? wp_kses_post( $input['text_content'] ) : '';
		$output['image_url']    = isset( $input['image_url'] ) ? esc_url_raw( trim( $input['image_url'] ) ) : '';

		// Handle access password hashing.
		$existing_hash = isset( $existing['access_password_hash'] ) ? $existing['access_password_hash'] : '';

		if ( ! empty( $input['access_password'] ) ) {
			$password                          = (string) $input['access_password'];
			$output['access_password_hash']    = wp_hash_password( $password );
		} elseif ( ! empty( $existing_hash ) ) {
			// Keep existing hash if no new password provided.
			$output['access_password_hash'] = $existing_hash;
		} else {
			$output['access_password_hash'] = '';
		}

		// Maintains compatibility if other code checks nonce sanitation output.
		$output['security_key'] = $this->sanitize_nonce_field( $input );

		return $output;
	}

	/**
	 * Section description output.
	 *
	 * @return void
	 */
	public function render_settings_section() {
		echo '<p>' . esc_html__( 'Beheer hier de whitelist en de manier waarop de onderhoudspagina getoond wordt.', 'pdmi-under-construction' ) . '</p>';
	}

	/**
	 * Enabled checkbox.
	 *
	 * @return void
	 */
	public function render_enabled_field() {
		$options = $this->get_settings();
		?>
		<label for="pdmiuc_enabled">
			<input type="checkbox" id="pdmiuc_enabled" name="pdmiuc_settings[enabled]" value="1" <?php checked( ! empty( $options['enabled'] ) ); ?> />
			<?php esc_html_e( 'Toon de onderhoudspagina aan bezoekers.', 'pdmi-under-construction' ); ?>
		</label>
		<?php
	}

	/**
	 * Allowed IPs textarea.
	 *
	 * @return void
	 */
	public function render_allowed_ips_field() {
		$options   = $this->get_settings();
		$ip_string = isset( $options['allowed_ips'] ) && is_array( $options['allowed_ips'] ) ? implode( ', ', $options['allowed_ips'] ) : '';
		$current_ip = $this->get_current_ip();
		?>
		<textarea
			id="pdmiuc_allowed_ips"
			name="pdmiuc_settings[allowed_ips]"
			rows="4"
			cols="50"
			class="large-text code"
			><?php echo esc_textarea( $ip_string ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Komma-gescheiden lijst met IP\'s die de site altijd mogen bekijken.', 'pdmi-under-construction' ); ?>
		</p>
		<p class="description">
			<?php
			printf(
				'%1$s <code>%2$s</code>',
				esc_html__( 'Huidig IP-adres:', 'pdmi-under-construction' ),
				esc_html( $current_ip )
			);
			?>
		</p>
		<p class="description" style="color: #00a32a; font-weight: 500; margin-top: 12px;">
			<span class="dashicons dashicons-warning" style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>
			<strong><?php esc_html_e( 'Beveiligingsnotitie:', 'pdmi-under-construction' ); ?></strong>
			<?php esc_html_e( 'IP-detectie kan in sommige gevallen omzeild worden. Test altijd grondig en overweeg extra authenticatie voor kritieke sites.', 'pdmi-under-construction' ); ?>
		</p>
		<?php
	}

	/**
	 * Display type select.
	 *
	 * @return void
	 */
	public function render_display_type_field() {
		$options = $this->get_settings();
		$value   = $options['display_type'] ?? 'text';
		?>
		<select id="pdmiuc_display_type" name="pdmiuc_settings[display_type]">
			<option value="text" <?php selected( $value, 'text' ); ?>>
				<?php esc_html_e( 'Tekst', 'pdmi-under-construction' ); ?>
			</option>
			<option value="image" <?php selected( $value, 'image' ); ?>>
				<?php esc_html_e( 'Afbeelding', 'pdmi-under-construction' ); ?>
			</option>
		</select>
		<?php
	}

	/**
	 * Renders WYSIWYG editor for text content.
	 *
	 * @return void
	 */
	public function render_text_content_field() {
		$options = $this->get_settings();
		$content = $options['text_content'] ?? '';

		wp_editor(
			$content,
			'pdmiuc_text_content',
			array(
				'textarea_name' => 'pdmiuc_settings[text_content]',
				'media_buttons' => false,
				'teeny'         => true,
				'textarea_rows' => 8,
			)
		);
	}

	/**
	 * Image URL field renderer.
	 *
	 * @return void
	 */
	public function render_image_url_field() {
		$options = $this->get_settings();
		$value   = $options['image_url'] ?? '';
		?>
		<div class="pdmiuc-media-field">
			<input type="url" id="pdmiuc_image_url" name="pdmiuc_settings[image_url]" class="regular-text code" value="<?php echo esc_attr( $value ); ?>" />
			<button
				type="button"
				class="button pdmiuc-media-button"
				data-target="pdmiuc_image_url"
				data-title="<?php echo esc_attr__( 'Selecteer afbeelding', 'pdmi-under-construction' ); ?>"
				data-button-text="<?php echo esc_attr__( 'Gebruik afbeelding', 'pdmi-under-construction' ); ?>"
			>
				<?php esc_html_e( 'Selecteer afbeelding', 'pdmi-under-construction' ); ?>
			</button>
		</div>
		<p class="description">
			<?php esc_html_e( 'Kies een afbeelding via de mediabibliotheek.', 'pdmi-under-construction' ); ?>
		</p>
		<?php
	}

	/**
	 * Access password field renderer.
	 *
	 * @return void
	 */
	public function render_access_password_field() {
		$options      = $this->get_settings();
		$has_password = ! empty( $options['access_password_hash'] );
		?>
		<input
			type="password"
			id="pdmiuc_access_password"
			name="pdmiuc_settings[access_password]"
			class="regular-text"
			autocomplete="new-password"
			value=""
		/>
		<p class="description">
			<?php esc_html_e( 'Stel een wachtwoord in waarmee bezoekers de onderhoudspagina kunnen omzeilen.', 'pdmi-under-construction' ); ?>
		</p>
		<p class="description">
			<?php
			if ( $has_password ) {
				esc_html_e( 'Er is momenteel een wachtwoord ingesteld. Laat dit veld leeg om het huidige wachtwoord te behouden, of vul een nieuw wachtwoord in om het te wijzigen.', 'pdmi-under-construction' );
			} else {
				esc_html_e( 'Er is nog geen wachtwoord ingesteld. Vul een wachtwoord in om deze functie te activeren.', 'pdmi-under-construction' );
			}
			?>
		</p>
		<?php
	}

	/**
	 * Settings page markup.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		// SECURITY FIX: Check capability before rendering page
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Je hebt geen toestemming om deze pagina te bekijken.', 'pdmi-under-construction' ),
				esc_html__( 'Onvoldoende rechten', 'pdmi-under-construction' ),
				array( 'response' => 403 )
			);
		}
		
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'PDMI Onderhoudspagina', 'pdmi-under-construction' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'pdmiuc_settings_group' );
				do_settings_sections( 'pdmi-under-construction' );
				submit_button( __( 'Wijzigingen opslaan', 'pdmi-under-construction' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Retrieves sanitized settings.
	 *
	 * @return array
	 */
	private function get_settings() {
		$defaults = array(
			'enabled'      => false,
			'allowed_ips'  => array(),
			'display_type' => 'text',
			'text_content' => '',
			'image_url'    => '',
			'access_password_hash' => '',
		);

		return wp_parse_args( get_option( 'pdmiuc_settings', array() ), $defaults );
	}

	/**
	 * Retrieves current visitor IP (admin view).
	 * 
	 * Note: Only uses REMOTE_ADDR for security. Proxy headers can be spoofed.
	 *
	 * @return string
	 */
	private function get_current_ip() {
		// Primary: Use REMOTE_ADDR (most secure)
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$remote_addr = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$validated = filter_var( $remote_addr, FILTER_VALIDATE_IP );
			
			if ( false !== $validated ) {
				return $validated;
			}
		}

		// Fallback: Check proxy headers (only if REMOTE_ADDR failed)
		// Note: These can be spoofed, use with caution
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP',  // CloudFlare
			'HTTP_X_FORWARDED_FOR',
			'HTTP_CLIENT_IP',
		);

		foreach ( $ip_keys as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				continue;
			}

			$ip_list = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$ip_candidates = array_map( 'trim', explode( ',', $ip_list ) );

			foreach ( $ip_candidates as $ip_candidate ) {
				$validated = filter_var( $ip_candidate, FILTER_VALIDATE_IP );
				if ( false !== $validated ) {
					return $validated;
				}
			}
		}

		return '0.0.0.0';
	}
}

