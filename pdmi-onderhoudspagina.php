<?php
/**
 * Plugin Name:       PDMI-onderhoudspagina
 * Plugin URI:        https://pdminternetdiensten.nl
 * Description:       Onderhoudspagina dmv tekst of een plaatje.
 * Version:           1.1
 * Author:            PDMI
 * Author URI:        https://pdminternetdiensten.nl
 * Text Domain:       pdmi-onderhoudspagina
 * Domain Path:       /languages
 *
 * @package PDMI\Under\Construction
 */

defined( 'ABSPATH' ) || exit;

/* Nodig voor auto update */
require_once __DIR__ . '/lib/plugin-update-checker/plugin-update-checker.php'; // Toegevoegd: __DIR__
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/Qwyrp/pdmi-onderhoudspagina', 
	__FILE__, 
	'pdmi-onderhoudspagina' // Aangepast naar jouw eigen plugin slug
);

// Optioneel: Als je een specifieke branch wilt controleren (bijv. 'main')
$myUpdateChecker->setBranch('main');

if ( ! defined( 'PDMIUC_PLUGIN_FILE' ) ) {
	define( 'PDMIUC_PLUGIN_FILE', __FILE__ );
}

use PDMI\Under\Construction\Plugin;

require_once __DIR__ . '/includes/class-plugin.php';

/**
 * Returns the singleton instance of the plugin.
 *
 * @return Plugin
 */
function pdmiuc() {
	return Plugin::get_instance();
}

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Plugin::class, 'deactivate' ) );

pdmiuc()->run();

