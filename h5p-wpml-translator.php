<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.georgenicolaou.me
 * @since             1.0.0
 * @package           H5p_Wpml_Translator
 *
 * @wordpress-plugin
 * Plugin Name:       H5P WPML Translator
 * Plugin URI:        https://www.georgenicolaou.me/h5p-wpml-translator
 * Description:       Make H5P content translatable with WPML String Translation.
 * Version:           1.2.3
 * Author:            George Nicolaou
 * Author URI:        https://www.georgenicolaou.me
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       h5p-wpml-translator
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'H5P_WPML_TRANSLATOR_VERSION', '1.2.3' );
define( 'H5P_WPML_TRANSLATOR_PLUGIN_FILE', __FILE__ );

/**
 * Composer autoloader.
 */
$h5p_wpml_translator_autoload = plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
if ( file_exists( $h5p_wpml_translator_autoload ) ) {
	require_once $h5p_wpml_translator_autoload;
}

/**
 * Set up plugin update checker.
 */
if ( class_exists( '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
	$h5p_wpml_translator_update_checker =
		\YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/GeorgeWebDevCy/h5p-translator',
			__FILE__,
			'h5p-wpml-translator'
		);
	$h5p_wpml_translator_update_checker->setBranch( 'main' );
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-h5p-wpml-translator-activator.php
 */
function activate_h5p_wpml_translator() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-h5p-wpml-translator-activator.php';
	H5p_Wpml_Translator_Activator::activate( plugin_basename( __FILE__ ) );
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-h5p-wpml-translator-deactivator.php
 */
function deactivate_h5p_wpml_translator() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-h5p-wpml-translator-deactivator.php';
	H5p_Wpml_Translator_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_h5p_wpml_translator' );
register_deactivation_hook( __FILE__, 'deactivate_h5p_wpml_translator' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-h5p-wpml-translator.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_h5p_wpml_translator() {

	$plugin = new H5p_Wpml_Translator();
	$plugin->run();

}
run_h5p_wpml_translator();
