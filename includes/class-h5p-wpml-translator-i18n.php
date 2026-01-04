<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    H5p_Wpml_Translator
 * @subpackage H5p_Wpml_Translator/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    H5p_Wpml_Translator
 * @subpackage H5p_Wpml_Translator/includes
 * @author     Georg <georg@example.com>
 */
class H5p_Wpml_Translator_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'h5p-wpml-translator',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
