<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    H5p_Wpml_Translator
 * @subpackage H5p_Wpml_Translator/public
 */

class H5p_Wpml_Translator_Public {

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
	 * Cache for loaded semantics by library.
	 *
	 * @var array
	 */
	private $semantics_cache = array();

	/**
	 * Whether WPML is available.
	 *
	 * @var bool|null
	 */
	private $wpml_active = null;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name    The name of the plugin.
	 * @param      string    $version        The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Register and translate H5P parameters via WPML String Translation.
	 *
	 * @param object $parameters
	 * @param string $library_name
	 * @param int    $major_version
	 * @param int    $minor_version
	 */
	public function translate_parameters( &$parameters, $library_name, $major_version, $minor_version ) {
		if ( ! $this->is_wpml_active() || ! class_exists( 'H5P_Plugin' ) ) {
			return;
		}

		if ( ! is_object( $parameters ) && ! is_array( $parameters ) ) {
			return;
		}

		$core = $this->get_h5p_core();
		if ( ! $core ) {
			return;
		}

		$semantics = $this->get_semantics( $core, $library_name, $major_version, $minor_version );
		if ( empty( $semantics ) || ! is_array( $semantics ) ) {
			return;
		}

		$content_id = $this->resolve_content_id();
		$context = $content_id ? 'H5P Content ' . $content_id : 'H5P Content';
		$path_prefix = $library_name . ' ' . $major_version . '.' . $minor_version;

		$this->translate_fields( $parameters, $semantics, $context, $path_prefix, $core );
	}

	/**
	 * Get the H5P core instance.
	 *
	 * @return H5PCore|null
	 */
	private function get_h5p_core() {
		if ( ! class_exists( 'H5P_Plugin' ) ) {
			return null;
		}

		$plugin = H5P_Plugin::get_instance();
		if ( ! $plugin || ! method_exists( $plugin, 'get_h5p_instance' ) ) {
			return null;
		}

		return $plugin->get_h5p_instance( 'core' );
	}

	/**
	 * H5P doesn't pass the content id into the hook, so read it from the call stack.
	 *
	 * @return int|null
	 */
	private function resolve_content_id() {
		$trace = debug_backtrace( DEBUG_BACKTRACE_PROVIDE_OBJECT, 12 );

		foreach ( $trace as $frame ) {
			if ( empty( $frame['function'] ) || 'get_content_settings' !== $frame['function'] ) {
				continue;
			}

			if ( empty( $frame['args'][0] ) || ! is_array( $frame['args'][0] ) ) {
				continue;
			}

			if ( isset( $frame['args'][0]['id'] ) ) {
				return (int) $frame['args'][0]['id'];
			}
		}

		return null;
	}

	/**
	 * Load and cache semantics for a library.
	 *
	 * @param H5PCore $core
	 * @param string $name
	 * @param int    $major
	 * @param int    $minor
	 * @return array|null
	 */
	private function get_semantics( $core, $name, $major, $minor ) {
		$key = $name . ':' . $major . '.' . $minor;
		if ( array_key_exists( $key, $this->semantics_cache ) ) {
			return $this->semantics_cache[ $key ];
		}

		$semantics = $core->loadLibrarySemantics( $name, $major, $minor );
		$this->semantics_cache[ $key ] = $semantics;

		return $semantics;
	}

	/**
	 * Walk semantics fields and translate matching parameter values.
	 *
	 * @param mixed  $params
	 * @param array  $fields
	 * @param string $context
	 * @param string $path_prefix
	 * @param H5PCore $core
	 */
	private function translate_fields( &$params, $fields, $context, $path_prefix, $core ) {
		if ( ! is_array( $fields ) ) {
			return;
		}

		foreach ( $fields as $field ) {
			if ( ! is_object( $field ) || empty( $field->name ) ) {
				continue;
			}

			$field_name = $field->name;
			if ( is_object( $params ) && property_exists( $params, $field_name ) ) {
				$value = &$params->{$field_name};
			} elseif ( is_array( $params ) && array_key_exists( $field_name, $params ) ) {
				$value = &$params[ $field_name ];
			} else {
				continue;
			}

			$path = $path_prefix ? $path_prefix . '.' . $field_name : $field_name;
			$this->translate_field( $value, $field, $context, $path, $core );
		}
	}

	/**
	 * Translate a single field based on its semantics.
	 *
	 * @param mixed  $value
	 * @param object $field
	 * @param string $context
	 * @param string $path
	 * @param H5PCore $core
	 */
	private function translate_field( &$value, $field, $context, $path, $core ) {
		if ( empty( $field->type ) ) {
			return;
		}

		switch ( $field->type ) {
			case 'text':
			case 'textarea':
			case 'html':
				if ( is_string( $value ) && $value !== '' ) {
					$allow_html = ( 'html' === $field->type ) || isset( $field->tags );
					$value = $this->register_and_translate( $value, $context, $path, $allow_html );
				}
				return;

			case 'group':
				if ( isset( $field->fields ) && ( is_array( $value ) || is_object( $value ) ) ) {
					$this->translate_fields( $value, $field->fields, $context, $path, $core );
				}
				return;

			case 'list':
				if ( ! is_array( $value ) || empty( $field->field ) ) {
					return;
				}

				foreach ( $value as $index => &$item ) {
					$suffix = $index;
					if ( is_object( $item ) && isset( $item->subContentId ) ) {
						$suffix = 'subContentId:' . $item->subContentId;
					}

					$item_path = $path . '[' . $suffix . ']';
					$this->translate_field( $item, $field->field, $context, $item_path, $core );
				}
				unset( $item );
				return;

			case 'library':
				$library_string = null;
				$params_present = false;

				if ( is_object( $value ) ) {
					$library_string = isset( $value->library ) ? $value->library : null;
					$params_present = isset( $value->params );
				} elseif ( is_array( $value ) ) {
					$library_string = isset( $value['library'] ) ? $value['library'] : null;
					$params_present = isset( $value['params'] );
				}

				if ( ! $library_string || ! $params_present ) {
					return;
				}

				$library = $this->parse_library_string( $library_string );
				if ( ! $library ) {
					return;
				}

				$semantics = $this->get_semantics( $core, $library['name'], $library['major'], $library['minor'] );
				if ( empty( $semantics ) || ! is_array( $semantics ) ) {
					return;
				}

				$nested_path = $path . '.library[' . $library['key'] . ']';
				if ( is_object( $value ) && isset( $value->params ) ) {
					$this->translate_fields( $value->params, $semantics, $context, $nested_path, $core );
				} elseif ( is_array( $value ) && isset( $value['params'] ) ) {
					$this->translate_fields( $value['params'], $semantics, $context, $nested_path, $core );
				}
				return;

			default:
				if ( isset( $field->fields ) ) {
					$this->translate_fields( $value, $field->fields, $context, $path, $core );
				}
				return;
		}
	}

	/**
	 * Register and translate a string via WPML.
	 *
	 * @param string $value
	 * @param string $context
	 * @param string $name
	 * @param bool   $allow_html
	 * @return string
	 */
	private function register_and_translate( $value, $context, $name, $allow_html ) {
		$this->register_string( $context, $name, $value, $allow_html );
		return $this->translate_string( $value, $context, $name );
	}

	/**
	 * Register a string for translation.
	 *
	 * @param string $context
	 * @param string $name
	 * @param string $value
	 * @param bool   $allow_html
	 */
	private function register_string( $context, $name, $value, $allow_html ) {
		if ( has_action( 'wpml_register_single_string' ) ) {
			do_action( 'wpml_register_single_string', $context, $name, $value, $allow_html );
		} elseif ( function_exists( 'icl_register_string' ) ) {
			icl_register_string( $context, $name, $value, $allow_html );
		}
	}

	/**
	 * Translate a string via WPML.
	 *
	 * @param string $value
	 * @param string $context
	 * @param string $name
	 * @return string
	 */
	private function translate_string( $value, $context, $name ) {
		if ( has_filter( 'wpml_translate_single_string' ) ) {
			$translated = apply_filters( 'wpml_translate_single_string', $value, $context, $name );
			return is_string( $translated ) ? $translated : $value;
		}

		if ( function_exists( 'icl_t' ) ) {
			return icl_t( $context, $name, $value );
		}

		return $value;
	}

	/**
	 * Parse a library string to name and versions.
	 *
	 * @param string $library_string
	 * @return array|null
	 */
	private function parse_library_string( $library_string ) {
		if ( ! is_string( $library_string ) ) {
			return null;
		}

		if ( preg_match( '/^([^\\s]+)\\s+(\\d+)\\.(\\d+)/', $library_string, $matches ) !== 1 ) {
			return null;
		}

		return array(
			'name'  => $matches[1],
			'major' => (int) $matches[2],
			'minor' => (int) $matches[3],
			'key'   => $matches[1] . ' ' . $matches[2] . '.' . $matches[3],
		);
	}

	/**
	 * Determine if WPML String Translation is available.
	 *
	 * @return bool
	 */
	private function is_wpml_active() {
		if ( null !== $this->wpml_active ) {
			return $this->wpml_active;
		}

		$this->wpml_active = defined( 'ICL_SITEPRESS_VERSION' )
			|| has_action( 'wpml_register_single_string' )
			|| has_filter( 'wpml_translate_single_string' )
			|| function_exists( 'icl_register_string' )
			|| function_exists( 'icl_t' );

		return $this->wpml_active;
	}
}
