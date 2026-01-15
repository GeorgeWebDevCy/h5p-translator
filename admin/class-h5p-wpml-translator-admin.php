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
	 * Register settings for custom H5P CSS.
	 */
	public function register_custom_css_settings() {
		register_setting(
			H5p_Wpml_Translator_Custom_Css::SETTINGS_GROUP,
			H5p_Wpml_Translator_Custom_Css::OPTION_NAME,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_custom_css' ),
				'default'           => '',
			)
		);
	}

	/**
	 * Register settings for the live logger.
	 */
	public function register_logger_settings() {
		register_setting(
			'h5p_wpml_translator_logger_group',
			H5p_Wpml_Translator_Logger::OPTION_ENABLED,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'no',
			)
		);
	}

	/**
	 * Register the custom CSS settings page.
	 */
	public function register_custom_css_page() {
		add_options_page(
			'H5P Custom CSS',
			'H5P Custom CSS',
			'manage_options',
			'h5p-wpml-translator-css',
			array( $this, 'render_custom_css_page' )
		);
	}

	/**
	 * Register the live logger page.
	 */
	public function register_logger_page() {
		add_options_page(
			'H5P Translation Logs',
			'H5P Live Logs',
			'manage_options',
			'h5p-wpml-translator-logs',
			array( $this, 'render_logger_page' )
		);
	}

	/**
	 * Render the custom CSS settings page.
	 */
	public function render_custom_css_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$option_name = H5p_Wpml_Translator_Custom_Css::OPTION_NAME;
		$css_value = get_option( $option_name, '' );
		?>
		<div class="wrap">
			<h1>H5P Custom CSS</h1>
			<form method="post" action="options.php">
				<?php settings_fields( H5p_Wpml_Translator_Custom_Css::SETTINGS_GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="h5p-wpml-translator-custom-css">Custom CSS</label>
						</th>
						<td>
							<textarea
								id="h5p-wpml-translator-custom-css"
								name="<?php echo esc_attr( $option_name ); ?>"
								rows="12"
								class="large-text code"
								spellcheck="false"
							><?php echo esc_textarea( $css_value ); ?></textarea>
							<p class="description">
								Your CSS is loaded inside H5P iframes via <code>h5p_alter_library_styles</code>.
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the live logger page.
	 */
	public function render_logger_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require_once plugin_dir_path( __FILE__ ) . 'partials/h5p-wpml-translator-admin-logs-display.php';
	}

	/**
	 * AJAX handler to fetch logs.
	 */
	public function ajax_fetch_logs() {
		check_ajax_referer( 'h5p_wpml_logger_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$logs = H5p_Wpml_Translator_Logger::get_logs();
		wp_send_json_success( array( 'logs' => $logs ) );
	}

	/**
	 * AJAX handler to clear logs.
	 */
	public function ajax_clear_logs() {
		check_ajax_referer( 'h5p_wpml_logger_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		H5p_Wpml_Translator_Logger::clear_logs();
		wp_send_json_success();
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'settings_page_h5p-wpml-translator-logs' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/h5p-wpml-translator-admin.js',
			array( 'jquery' ),
			$this->version,
			false
		);

		wp_localize_script(
			$this->plugin_name,
			'h5pTranslatorAdmin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'h5p_wpml_logger_nonce' ),
			)
		);
	}

	/**
	 * Sanitize and persist custom CSS.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public function sanitize_custom_css( $value ) {
		$css = H5p_Wpml_Translator_Custom_Css::sanitize_css( $value );
		H5p_Wpml_Translator_Custom_Css::write_css_file( $css );

		return $css;
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
