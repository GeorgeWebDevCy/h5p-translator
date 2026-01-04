<?php

/**
 * Fired during plugin activation
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    H5p_Wpml_Translator
 * @subpackage H5p_Wpml_Translator/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    H5p_Wpml_Translator
 * @subpackage H5p_Wpml_Translator/includes
 * @author     Georg <georg@example.com>
 */
class H5p_Wpml_Translator_Activator {

	/**
	 * Validate required plugins before activation.
	 *
	 * @since    1.0.0
	 * @param string $plugin_basename Plugin basename for deactivation.
	 */
	public static function activate( $plugin_basename ) {
		$missing = self::get_missing_plugins();
		if ( empty( $missing ) ) {
			return;
		}

		deactivate_plugins( $plugin_basename );

		$message  = '<p><strong>H5P WPML Translator</strong> requires the following plugins to be installed and active:</p>';
		$message .= '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $missing ) ) . '</li></ul>';
		$message .= '<p>Please activate them and try again.</p>';
		$message .= '<p><a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">&larr; Back to Plugins</a></p>';

		wp_die( $message, 'Plugin dependency check', array( 'response' => 200 ) );
	}

	/**
	 * Check if a plugin is active, including network-activated on multisite.
	 *
	 * @param string $plugin_file Plugin path relative to plugins directory.
	 * @return bool
	 */
	public static function get_missing_plugins() {
		self::ensure_plugin_api();

		$missing = array();

		if ( ! self::is_plugin_active_by_file( 'h5p/h5p.php' ) ) {
			$missing[] = 'H5P';
		}

		if ( ! self::is_plugin_active_by_file( 'sitepress-multilingual-cms/sitepress.php' ) ) {
			$missing[] = 'WPML';
		}

		if ( ! self::is_plugin_active_by_file( 'wpml-string-translation/plugin.php' ) ) {
			$missing[] = 'WPML String Translation';
		}

		return $missing;
	}

	/**
	 * Ensure plugin API functions are available.
	 */
	private static function ensure_plugin_api() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}

	private static function is_plugin_active_by_file( $plugin_file ) {
		if ( is_plugin_active( $plugin_file ) ) {
			return true;
		}

		if ( is_multisite() && is_plugin_active_for_network( $plugin_file ) ) {
			return true;
		}

		return false;
	}

}
