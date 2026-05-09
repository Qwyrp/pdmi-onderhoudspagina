<?php
/**
 * Plugin Name:       PDMI Onderhoud
 * Plugin URI:        https://pdmi.nl
 * Description:       Toon een onderhoudspagina met tekst of afbeelding. Vrijstelling op basis van IP-adres of wachtwoord.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            PDM internetdiensten
 * Author URI:        https://pdmi.nl
 * Text Domain:       pdmi-onderhoudspagina
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package PDMI\Onderhoud
 */

defined( 'ABSPATH' ) || exit;

define( 'PDMIUC_PLUGIN_FILE', __FILE__ );
define( 'PDMIUC_VERSION', '2.0.0' );
define( 'PDMIUC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PDMIUC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

use PDMI\Under\Construction\Plugin;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

require_once PDMIUC_PLUGIN_DIR . 'vendor/autoload.php';
require_once PDMIUC_PLUGIN_DIR . 'includes/class-plugin.php';

$pdmiuc_updater = PucFactory::buildUpdateChecker(
	'https://github.com/Qwyrp/pdmi-onderhoudspagina/',
	__FILE__,
	'pdmi-onderhoudspagina'
);
$pdmiuc_updater->setBranch( 'main' );

/**
 * Returns the singleton plugin instance.
 *
 * @return Plugin
 */
function pdmiuc(): Plugin {
	return Plugin::get_instance();
}

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Plugin::class, 'deactivate' ) );

pdmiuc()->run();
