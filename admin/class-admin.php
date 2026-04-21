<?php
/**
 * Admin-facing functionality: settings page, field rendering, and asset loading.
 *
 * @package PDMI\Onderhoud\Admin
 */

namespace PDMI\Under\Construction\Admin;

defined( 'ABSPATH' ) || exit;

use PDMI\Under\Construction\Traits\Security;

require_once PDMIUC_PLUGIN_DIR . 'includes/trait-security.php';

/**
 * Handles all wp-admin integration for the plugin.
 */
class Admin {
	use Security;

	/** @var string WordPress options group name. */
	private const SETTINGS_GROUP = 'pdmiuc_settings_group';

	/** @var string WordPress options key. */
	private const OPTION_KEY = 'pdmiuc_settings';

	/** @var string Settings page slug. */
	private const PAGE_SLUG = 'pdmi-onderhoud';

	/** @var string Admin JS handle. */
	private const SCRIPT_HANDLE = 'pdmiuc-admin';

	/**
	 * Registers the settings submenu page under Settings.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( 'PDMI Onderhoud', 'pdmi-onderhoudspagina' ),
			__( 'PDMI Onderhoud', 'pdmi-onderhoudspagina' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Registers settings, sections, and fields via the Settings API.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_KEY,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			'pdmiuc_main_section',
			__( 'Onderhoudspagina', 'pdmi-onderhoudspagina' ),
			array( $this, 'render_settings_section' ),
			self::PAGE_SLUG
		);

		$fields = array(
			array( 'pdmiuc_enabled',         __( 'Onderhoudspagina inschakelen', 'pdmi-onderhoudspagina' ), 'render_enabled_field' ),
			array( 'pdmiuc_allowed_ips',      __( 'Toegestane IP-adressen',       'pdmi-onderhoudspagina' ), 'render_allowed_ips_field' ),
			array( 'pdmiuc_display_type',     __( 'Weergave',                     'pdmi-onderhoudspagina' ), 'render_display_type_field' ),
			array( 'pdmiuc_text_content',     __( 'Tekstinvoer',                  'pdmi-onderhoudspagina' ), 'render_text_content_field' ),
			array( 'pdmiuc_image_url',        __( 'Afbeeldings-URL',              'pdmi-onderhoudspagina' ), 'render_image_url_field' ),
			array( 'pdmiuc_access_password',  __( 'Toegangswachtwoord',           'pdmi-onderhoudspagina' ), 'render_access_password_field' ),
		);

		foreach ( $fields as list( $id, $title, $callback ) ) {
			add_settings_field( $id, $title, array( $this, $callback ), self::PAGE_SLUG, 'pdmiuc_main_section' );
		}
	}

	/**
	 * Enqueues admin scripts and passes localised data to JavaScript.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			PDMIUC_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			PDMIUC_VERSION,
			true
		);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'pdmiucAdmin',
			array(
				'ipv4ApiUrl'    => 'https://api4.ipify.org?format=text',
				'currentIpv6'   => $this->get_raw_server_ip(),
				'currentIpv4'   => $this->get_current_ip(),
				'i18n'          => array(
					'fetching'       => __( 'ophalen...', 'pdmi-onderhoudspagina' ),
					'unavailable'    => __( 'niet beschikbaar', 'pdmi-onderhoudspagina' ),
					'addToWhitelist' => __( 'Toevoegen aan whitelist', 'pdmi-onderhoudspagina' ),
					'added'          => __( 'Toegevoegd', 'pdmi-onderhoudspagina' ),
				),
			)
		);
	}

	/**
	 * Sanitizes and validates the settings array on save.
	 *
	 * @param mixed $input Raw POST values.
	 * @return array Sanitized settings ready for storage.
	 */
	public function sanitize_settings( $input ): array {
		$input    = is_array( $input ) ? wp_unslash( $input ) : array();
		$existing = get_option( self::OPTION_KEY, array() );
		$output   = array();

		$output['enabled']      = ! empty( $input['enabled'] );
		$output['allowed_ips']  = $this->sanitize_ip_list( (string) ( $input['allowed_ips'] ?? '' ) );
		$output['display_type'] = in_array( $input['display_type'] ?? 'text', array( 'text', 'image' ), true )
			? $input['display_type']
			: 'text';
		$output['text_content'] = isset( $input['text_content'] ) ? wp_kses_post( $input['text_content'] ) : '';
		$output['image_url']    = isset( $input['image_url'] ) ? esc_url_raw( trim( $input['image_url'] ) ) : '';

		// Only re-hash when a new password is submitted; keep existing hash otherwise.
		$existing_hash = $existing['access_password_hash'] ?? '';
		if ( ! empty( $input['access_password'] ) ) {
			$output['access_password_hash'] = wp_hash_password( (string) $input['access_password'] );
		} elseif ( ! empty( $existing_hash ) ) {
			$output['access_password_hash'] = $existing_hash;
		} else {
			$output['access_password_hash'] = '';
		}

		return $output;
	}

	// -------------------------------------------------------------------------
	// Section & field renderers
	// -------------------------------------------------------------------------

	/**
	 * Outputs the section description.
	 *
	 * @return void
	 */
	public function render_settings_section(): void {
		echo '<p>' . esc_html__( 'Beheer hier de whitelist en de manier waarop de onderhoudspagina getoond wordt.', 'pdmi-onderhoudspagina' ) . '</p>';
	}

	/**
	 * Renders the "enabled" checkbox.
	 *
	 * @return void
	 */
	public function render_enabled_field(): void {
		$options = $this->get_settings();
		?>
		<label for="pdmiuc_enabled">
			<input
				type="checkbox"
				id="pdmiuc_enabled"
				name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enabled]"
				value="1"
				<?php checked( ! empty( $options['enabled'] ) ); ?>
			/>
			<?php esc_html_e( 'Toon de onderhoudspagina aan bezoekers.', 'pdmi-onderhoudspagina' ); ?>
		</label>
		<?php
	}

	/**
	 * Renders the allowed-IPs textarea and current-IP display.
	 *
	 * IPv4 detection for native-IPv6 connections is handled by admin.js via
	 * the localised pdmiucAdmin object — no inline scripts here.
	 *
	 * @return void
	 */
	public function render_allowed_ips_field(): void {
		$options    = $this->get_settings();
		$ip_string  = implode( ', ', (array) ( $options['allowed_ips'] ?? array() ) );
		$current_ip = $this->get_current_ip();
		$raw_ip     = $this->get_raw_server_ip();
		?>
		<textarea
			id="pdmiuc_allowed_ips"
			name="<?php echo esc_attr( self::OPTION_KEY ); ?>[allowed_ips]"
			rows="4"
			cols="50"
			class="large-text code"
		><?php echo esc_textarea( $ip_string ); ?></textarea>

		<p class="description">
			<?php esc_html_e( "Komma-gescheiden lijst met IP's die de site altijd mogen bekijken.", 'pdmi-onderhoudspagina' ); ?>
		</p>

		<p class="description">
			<?php
			printf(
				'%1$s <code>%2$s</code>',
				esc_html__( 'Huidig IP-adres (server ziet):', 'pdmi-onderhoudspagina' ),
				esc_html( $current_ip )
			);
			if ( $raw_ip !== $current_ip && filter_var( $raw_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
				printf(
					' <span style="color:#888;font-size:0.9em;">(IPv6: <code>%s</code>)</span>',
					esc_html( $raw_ip )
				);
			}
			?>
		</p>

		<p class="description" id="pdmiuc-ipv4-row">
			<?php esc_html_e( 'Jouw IPv4-adres:', 'pdmi-onderhoudspagina' ); ?>
			<code id="pdmiuc-ipv4-display"></code>
			<button
				type="button"
				id="pdmiuc-add-ipv4"
				class="button button-small"
				style="display:none;margin-left:6px;"
			>
				<?php esc_html_e( 'Toevoegen aan whitelist', 'pdmi-onderhoudspagina' ); ?>
			</button>
		</p>

<?php
	}

	/**
	 * Renders the display-type select.
	 *
	 * @return void
	 */
	public function render_display_type_field(): void {
		$options = $this->get_settings();
		$value   = $options['display_type'] ?? 'text';
		?>
		<select id="pdmiuc_display_type" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[display_type]">
			<option value="text" <?php selected( $value, 'text' ); ?>>
				<?php esc_html_e( 'Tekst', 'pdmi-onderhoudspagina' ); ?>
			</option>
			<option value="image" <?php selected( $value, 'image' ); ?>>
				<?php esc_html_e( 'Afbeelding', 'pdmi-onderhoudspagina' ); ?>
			</option>
		</select>
		<?php
	}

	/**
	 * Renders the WYSIWYG text-content editor.
	 *
	 * @return void
	 */
	public function render_text_content_field(): void {
		$options = $this->get_settings();

		wp_editor(
			$options['text_content'] ?? '',
			'pdmiuc_text_content',
			array(
				'textarea_name' => self::OPTION_KEY . '[text_content]',
				'media_buttons' => false,
				'teeny'         => true,
				'textarea_rows' => 8,
			)
		);
	}

	/**
	 * Renders the image-URL field with media-library button.
	 *
	 * @return void
	 */
	public function render_image_url_field(): void {
		$options = $this->get_settings();
		$value   = $options['image_url'] ?? '';
		?>
		<div class="pdmiuc-media-field">
			<input
				type="url"
				id="pdmiuc_image_url"
				name="<?php echo esc_attr( self::OPTION_KEY ); ?>[image_url]"
				class="regular-text code"
				value="<?php echo esc_attr( $value ); ?>"
			/>
			<button
				type="button"
				class="button pdmiuc-media-button"
				data-target="pdmiuc_image_url"
				data-title="<?php echo esc_attr__( 'Selecteer afbeelding', 'pdmi-onderhoudspagina' ); ?>"
				data-button-text="<?php echo esc_attr__( 'Gebruik afbeelding', 'pdmi-onderhoudspagina' ); ?>"
			>
				<?php esc_html_e( 'Selecteer afbeelding', 'pdmi-onderhoudspagina' ); ?>
			</button>
		</div>
		<p class="description">
			<?php esc_html_e( 'Kies een afbeelding via de mediabibliotheek.', 'pdmi-onderhoudspagina' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders the access-password field.
	 *
	 * @return void
	 */
	public function render_access_password_field(): void {
		$options      = $this->get_settings();
		$has_password = ! empty( $options['access_password_hash'] );
		?>
		<input
			type="password"
			id="pdmiuc_access_password"
			name="<?php echo esc_attr( self::OPTION_KEY ); ?>[access_password]"
			class="regular-text"
			autocomplete="new-password"
			value=""
		/>
		<p class="description">
			<?php esc_html_e( 'Stel een wachtwoord in waarmee bezoekers de onderhoudspagina kunnen omzeilen.', 'pdmi-onderhoudspagina' ); ?>
		</p>
		<p class="description">
			<?php
			if ( $has_password ) {
				esc_html_e( 'Er is momenteel een wachtwoord ingesteld. Laat leeg om het huidige wachtwoord te behouden, of vul een nieuw wachtwoord in om het te wijzigen.', 'pdmi-onderhoudspagina' );
			} else {
				esc_html_e( 'Er is nog geen wachtwoord ingesteld. Vul een wachtwoord in om deze functie te activeren.', 'pdmi-onderhoudspagina' );
			}
			?>
		</p>
		<?php
	}

	/**
	 * Renders the full settings page wrapper.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Je hebt geen toestemming om deze pagina te bekijken.', 'pdmi-onderhoudspagina' ),
				esc_html__( 'Onvoldoende rechten', 'pdmi-onderhoudspagina' ),
				array( 'response' => 403 )
			);
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'PDMI Onderhoud', 'pdmi-onderhoudspagina' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::SETTINGS_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button( __( 'Wijzigingen opslaan', 'pdmi-onderhoudspagina' ) );
				?>
			</form>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns plugin settings merged with defaults.
	 *
	 * @return array
	 */
	private function get_settings(): array {
		return wp_parse_args(
			get_option( self::OPTION_KEY, array() ),
			array(
				'enabled'               => false,
				'allowed_ips'           => array(),
				'display_type'          => 'text',
				'text_content'          => '',
				'image_url'             => '',
				'access_password_hash'  => '',
			)
		);
	}

	/**
	 * Returns the visitor's IP normalized to IPv4 when possible.
	 *
	 * Uses only REMOTE_ADDR to prevent header-spoofing in the admin context.
	 *
	 * @return string
	 */
	private function get_current_ip(): string {
		return $this->normalize_ip( $this->get_raw_server_ip() );
	}

	/**
	 * Returns the raw IP address as seen by the server (may be IPv6).
	 *
	 * Falls back to proxy headers only when REMOTE_ADDR yields no valid IP.
	 *
	 * @return string
	 */
	private function get_raw_server_ip(): string {
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$validated = filter_var(
				sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
				FILTER_VALIDATE_IP
			);
			if ( false !== $validated ) {
				return $validated;
			}
		}

		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP' ) as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				continue;
			}
			$candidates = array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			foreach ( $candidates as $candidate ) {
				$validated = filter_var( $candidate, FILTER_VALIDATE_IP );
				if ( false !== $validated ) {
					return $validated;
				}
			}
		}

		return '0.0.0.0';
	}
}
