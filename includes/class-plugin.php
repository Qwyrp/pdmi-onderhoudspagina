<?php
/**
 * Main plugin bootstrap class.
 *
 * @package PDMI\Under\Construction
 */

namespace PDMI\Under\Construction;

defined( 'ABSPATH' ) || exit;

use PDMI\Under\Construction\Admin\Admin;
use PDMI\Under\Construction\Public_\Public_Class;
use PDMI\Under\Construction\Traits\Security;

require_once __DIR__ . '/class-loader.php';
require_once __DIR__ . '/../admin/class-admin.php';
require_once __DIR__ . '/../public/class-public.php';
require_once __DIR__ . '/trait-security.php';

/**
 * Plugin bootstrapper.
 */
final class Plugin {
	use Security;

	const VERSION = '1.0';

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Loader instance.
	 *
	 * @var Loader
	 */
	protected $loader;

	/**
	 * Admin handler.
	 *
	 * @var Admin
	 */
	protected $admin;

	/**
	 * Public handler.
	 *
	 * @var Public_Class
	 */
	protected $public;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->loader = new Loader();

		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Returns singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Runs loader.
	 *
	 * @return void
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * Activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		update_option( 'pdmiuc_version', self::VERSION );
	}

	/**
	 * Deactivation callback.
	 *
	 * @return void
	 */
	public static function deactivate() {
		delete_option( 'pdmiuc_version' );
	}

	/**
	 * Loads translations.
	 *
	 * @return void
	 */
	private function set_locale() {
		$this->loader->add_action( 'plugins_loaded', $this, 'load_textdomain' );
	}

	/**
	 * Registers text domain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'pdmi-under-construction', false, dirname( plugin_basename( PDMIUC_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	private function define_admin_hooks() {
		$this->admin = new Admin( 'pdmi-under-construction', self::VERSION );

		$this->loader->add_action( 'admin_menu', $this->admin, 'add_settings_page' );
		$this->loader->add_action( 'admin_init', $this->admin, 'register_settings' );
		$this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_assets' );
	}

	/**
	 * Registers public hooks.
	 *
	 * @return void
	 */
	private function define_public_hooks() {
		$this->public = new Public_Class( 'pdmi-under-construction', self::VERSION );

		$this->loader->add_action( 'template_redirect', $this->public, 'maybe_render_maintenance_screen' );
		$this->loader->add_action( 'wp_enqueue_scripts', $this->public, 'enqueue_assets' );
	}
}

