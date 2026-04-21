<?php
/**
 * Plugin bootstrap: wires up all components and hook registrations.
 *
 * @package PDMI\Onderhoud
 */

namespace PDMI\Under\Construction;

defined( 'ABSPATH' ) || exit;

use PDMI\Under\Construction\Admin\Admin;
use PDMI\Under\Construction\Public_\Public_Class;

require_once PDMIUC_PLUGIN_DIR . 'includes/class-loader.php';
require_once PDMIUC_PLUGIN_DIR . 'includes/trait-security.php';
require_once PDMIUC_PLUGIN_DIR . 'admin/class-admin.php';
require_once PDMIUC_PLUGIN_DIR . 'public/class-public.php';

/**
 * Plugin bootstrapper — singleton.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Hook loader.
	 *
	 * @var Loader
	 */
	private Loader $loader;

	/**
	 * Admin component.
	 *
	 * @var Admin
	 */
	private Admin $admin;

	/**
	 * Public component.
	 *
	 * @var Public_Class
	 */
	private Public_Class $public;

	/**
	 * Private constructor — use get_instance().
	 */
	private function __construct() {
		$this->loader = new Loader();

		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Returns the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Runs all registered hooks.
	 *
	 * @return void
	 */
	public function run(): void {
		$this->loader->run();
	}

	/**
	 * Stores the plugin version on activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		update_option( 'pdmiuc_version', PDMIUC_VERSION );
	}

	/**
	 * Removes the version option on deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		delete_option( 'pdmiuc_version' );
	}

	/**
	 * Queues the text-domain loader.
	 *
	 * @return void
	 */
	private function set_locale(): void {
		$this->loader->add_action( 'plugins_loaded', $this, 'load_textdomain' );
	}

	/**
	 * Loads the plugin text domain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'pdmi-onderhoudspagina',
			false,
			dirname( plugin_basename( PDMIUC_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Registers admin-side hooks.
	 *
	 * @return void
	 */
	private function define_admin_hooks(): void {
		$this->admin = new Admin();

		$this->loader->add_action( 'admin_menu', $this->admin, 'add_settings_page' );
		$this->loader->add_action( 'admin_init', $this->admin, 'register_settings' );
		$this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_assets' );
	}

	/**
	 * Registers public-facing hooks.
	 *
	 * @return void
	 */
	private function define_public_hooks(): void {
		$this->public = new Public_Class();

		$this->loader->add_action( 'template_redirect', $this->public, 'maybe_render_maintenance_screen' );
	}
}
