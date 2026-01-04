<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    H5p_Wpml_Translator
 * @subpackage H5p_Wpml_Translator/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    H5p_Wpml_Translator
 * @subpackage H5p_Wpml_Translator/admin
 * @author     Georg <georg@example.com>
 */
class H5p_Wpml_Translator_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Missing dependency names for admin notice.
	 *
	 * @var array
	 */
	private $missing_dependencies = array();

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Check required plugins and show an admin notice if missing.
	 *
	 * @since    1.0.0
	 */
	public function check_dependencies() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-h5p-wpml-translator-activator.php';

		$missing = H5p_Wpml_Translator_Activator::get_missing_plugins();
		if ( empty( $missing ) ) {
			return;
		}

		$this->missing_dependencies = $missing;

		if ( defined( 'H5P_WPML_TRANSLATOR_PLUGIN_FILE' ) ) {
			$plugin = plugin_basename( H5P_WPML_TRANSLATOR_PLUGIN_FILE );
			$network_wide = is_multisite() && is_network_admin();
			deactivate_plugins( $plugin, true, $network_wide );
		}

		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
	}

	/**
	 * Render dependency notice after a failed activation attempt.
	 *
	 * @since 1.0.0
	 */
	public function render_dependency_notice() {
		if ( empty( $this->missing_dependencies ) ) {
			return;
		}

		$message  = '<p><strong>H5P WPML Translator</strong> requires the following plugins to be installed and active:</p>';
		$message .= '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $this->missing_dependencies ) ) . '</li></ul>';
		$message .= '<p>Please activate them and try again.</p>';

		echo '<div class="notice notice-error">' . $message . '</div>';
	}

}
