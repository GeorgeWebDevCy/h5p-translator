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
	 * Register and translate H5P parameters via WPML.
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

		$this->translate_fields( $parameters, $semantics, $context, $path_prefix, $core, $content_id );
	}

	/**
	 * Inject custom CSS into H5P's iframe pipeline.
	 *
	 * @param array  $styles
	 * @param array  $libraries
	 * @param string $embed_type
	 */
	public function add_custom_css_styles( &$styles, $libraries, $embed_type ) {
		$css = H5p_Wpml_Translator_Custom_Css::get_css();
		if ( '' === trim( $css ) ) {
			return;
		}

		$paths = H5p_Wpml_Translator_Custom_Css::get_paths();
		if ( ! $paths ) {
			return;
		}

		if ( ! file_exists( $paths['path'] ) ) {
			$normalized = H5p_Wpml_Translator_Custom_Css::sanitize_css( $css );
			H5p_Wpml_Translator_Custom_Css::write_css_file( $normalized );
		}

		if ( ! file_exists( $paths['path'] ) ) {
			return;
		}

		$mtime = filemtime( $paths['path'] );
		$version_value = false !== $mtime ? (string) $mtime : $this->version;
		$version = '?ver=' . $version_value;

		$styles[] = (object) array(
			'path'    => $paths['url'],
			'version' => $version,
		);
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
	 * @param int|null $content_id
	 */
	private function translate_fields( &$params, $fields, $context, $path_prefix, $core, $content_id ) {
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
			$this->translate_field( $value, $field, $context, $path, $core, $content_id );
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
	 * @param int|null $content_id
	 */
	private function translate_field( &$value, $field, $context, $path, $core, $content_id ) {
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

			case 'image':
				$this->translate_media_field( $value, $content_id, $core );
				return;

			case 'file':
				if ( $this->media_value_is_image( $value ) ) {
					$this->translate_media_field( $value, $content_id, $core );
				}
				return;

			case 'group':
				if ( isset( $field->fields ) && ( is_array( $value ) || is_object( $value ) ) ) {
					$this->translate_fields( $value, $field->fields, $context, $path, $core, $content_id );
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
					$this->translate_field( $item, $field->field, $context, $item_path, $core, $content_id );
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
					$this->translate_fields( $value->params, $semantics, $context, $nested_path, $core, $content_id );
				} elseif ( is_array( $value ) && isset( $value['params'] ) ) {
					$this->translate_fields( $value['params'], $semantics, $context, $nested_path, $core, $content_id );
				}
				return;

			default:
				if ( isset( $field->fields ) ) {
					$this->translate_fields( $value, $field->fields, $context, $path, $core, $content_id );
				}
				return;
		}
	}

	/**
	 * Translate media fields (images) via WPML Media Translation.
	 *
	 * @param mixed   $value
	 * @param int|null $content_id
	 * @param H5PCore $core
	 */
	private function translate_media_field( &$value, $content_id, $core ) {
		if ( ! is_object( $value ) && ! is_array( $value ) ) {
			return;
		}

		$path = $this->get_media_value( $value, 'path' );
		if ( ! is_string( $path ) || '' === $path ) {
			return;
		}

		if ( false !== strpos( $path, '#tmp' ) ) {
			return;
		}

		$url = $this->resolve_media_url( $path, $content_id, $core );
		if ( ! $url ) {
			return;
		}

		$normalized_url = $this->strip_url_query( $url );
		if ( '' === $normalized_url ) {
			return;
		}

		$attachment_id = attachment_url_to_postid( $normalized_url );
		if ( ! $attachment_id ) {
			$attachment_id = $this->ensure_attachment_for_url( $normalized_url );
		}

		if ( ! $attachment_id ) {
			return;
		}

		$translated_id = $this->get_translated_attachment_id( $attachment_id );
		if ( ! $translated_id || $translated_id === $attachment_id ) {
			return;
		}

		$translated_url = wp_get_attachment_url( $translated_id );
		if ( ! $translated_url ) {
			return;
		}

		$this->set_media_value( $value, 'path', $translated_url );

		if ( $this->media_value_has_key( $value, 'mime' ) ) {
			$mime_type = get_post_mime_type( $translated_id );
			if ( is_string( $mime_type ) && '' !== $mime_type ) {
				$this->set_media_value( $value, 'mime', $mime_type );
			}
		}

		$this->update_media_dimensions( $value, $translated_id );
	}

	/**
	 * Resolve an H5P media path to a URL.
	 *
	 * @param string  $path
	 * @param int|null $content_id
	 * @param H5PCore $core
	 * @return string|null
	 */
	private function resolve_media_url( $path, $content_id, $core ) {
		if ( preg_match( '#^https?://#i', $path ) ) {
			return $path;
		}

		if ( 0 === strpos( $path, '//' ) ) {
			$scheme = is_ssl() ? 'https:' : 'http:';
			return $scheme . $path;
		}

		if ( 0 === strpos( $path, '/' ) ) {
			return home_url( $path );
		}

		$base_url = $this->get_h5p_base_url( $core );
		if ( ! $base_url ) {
			return null;
		}

		$trimmed = ltrim( $path, '/' );
		if ( 0 === strpos( $trimmed, 'content/' ) || 0 === strpos( $trimmed, 'editor/' ) ) {
			return trailingslashit( $base_url ) . $trimmed;
		}

		if ( ! $content_id ) {
			return null;
		}

		return trailingslashit( $base_url ) . 'content/' . $content_id . '/' . $trimmed;
	}

	/**
	 * Strip query strings and fragments from a URL.
	 *
	 * @param string $url
	 * @return string
	 */
	private function strip_url_query( $url ) {
		if ( ! is_string( $url ) ) {
			return '';
		}

		return preg_replace( '/[?#].*$/', '', $url );
	}

	/**
	 * Get the base URL for H5P content files.
	 *
	 * @param H5PCore $core
	 * @return string|null
	 */
	private function get_h5p_base_url( $core ) {
		if ( is_object( $core ) && ! empty( $core->url ) ) {
			return rtrim( $core->url, '/' );
		}

		return null;
	}

	/**
	 * Ensure an attachment exists for the given URL.
	 *
	 * @param string $url
	 * @return int
	 */
	private function ensure_attachment_for_url( $url ) {
		if ( ! function_exists( 'attachment_url_to_postid' ) ) {
			return 0;
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			return 0;
		}

		$uploads = wp_upload_dir();
		if ( empty( $uploads['baseurl'] ) || empty( $uploads['basedir'] ) ) {
			return 0;
		}

		if ( 0 !== strpos( $url, $uploads['baseurl'] ) ) {
			return 0;
		}

		$relative_path = ltrim( substr( $url, strlen( $uploads['baseurl'] ) ), '/' );
		if ( '' === $relative_path ) {
			return 0;
		}

		$file_path = trailingslashit( $uploads['basedir'] ) . $relative_path;
		if ( ! file_exists( $file_path ) ) {
			return 0;
		}

		$existing = attachment_url_to_postid( $url );
		if ( $existing ) {
			return (int) $existing;
		}

		$filetype = wp_check_filetype( basename( $file_path ), null );
		$attachment = array(
			'guid'           => $url,
			'post_mime_type' => $filetype['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file_path ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $file_path );
		if ( is_wp_error( $attachment_id ) ) {
			return 0;
		}

		if ( ! empty( $filetype['type'] ) && 0 === strpos( $filetype['type'], 'image/' ) ) {
			if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}

			$metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
			if ( ! is_wp_error( $metadata ) && ! empty( $metadata ) ) {
				wp_update_attachment_metadata( $attachment_id, $metadata );
			}
		}

		$this->maybe_set_attachment_language( $attachment_id );

		return (int) $attachment_id;
	}

	/**
	 * Set attachment language to the default site language when possible.
	 *
	 * @param int $attachment_id
	 */
	private function maybe_set_attachment_language( $attachment_id ) {
		if ( ! has_action( 'wpml_set_element_language_details' ) || ! has_filter( 'wpml_default_language' ) ) {
			return;
		}

		$default_language = apply_filters( 'wpml_default_language', null );
		if ( ! is_string( $default_language ) || '' === $default_language ) {
			return;
		}

		$trid = null;
		if ( has_filter( 'wpml_element_trid' ) ) {
			$trid = apply_filters( 'wpml_element_trid', null, $attachment_id, 'post_attachment' );
		}

		do_action(
			'wpml_set_element_language_details',
			array(
				'element_id'           => $attachment_id,
				'element_type'         => 'post_attachment',
				'trid'                 => $trid,
				'language_code'        => $default_language,
				'source_language_code' => null,
			)
		);
	}

	/**
	 * Get a translated attachment ID for the current language.
	 *
	 * @param int $attachment_id
	 * @return int
	 */
	private function get_translated_attachment_id( $attachment_id ) {
		if ( function_exists( 'icl_object_id' ) ) {
			$translated = icl_object_id( $attachment_id, 'attachment', true );
			return $translated ? (int) $translated : 0;
		}

		if ( has_filter( 'wpml_object_id' ) ) {
			$translated = apply_filters( 'wpml_object_id', $attachment_id, 'attachment', true );
			return $translated ? (int) $translated : 0;
		}

		return (int) $attachment_id;
	}

	/**
	 * Update width/height for translated media when available.
	 *
	 * @param mixed $value
	 * @param int   $attachment_id
	 */
	private function update_media_dimensions( &$value, $attachment_id ) {
		if ( ! $this->media_value_has_key( $value, 'width' ) && ! $this->media_value_has_key( $value, 'height' ) ) {
			return;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $metadata ) ) {
			return;
		}

		if ( ! empty( $metadata['width'] ) && $this->media_value_has_key( $value, 'width' ) ) {
			$this->set_media_value( $value, 'width', (int) $metadata['width'] );
		}

		if ( ! empty( $metadata['height'] ) && $this->media_value_has_key( $value, 'height' ) ) {
			$this->set_media_value( $value, 'height', (int) $metadata['height'] );
		}
	}

	/**
	 * Determine if a media value represents an image.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	private function media_value_is_image( $value ) {
		$mime = $this->get_media_value( $value, 'mime' );
		if ( is_string( $mime ) && 0 === strpos( $mime, 'image/' ) ) {
			return true;
		}

		$path = $this->get_media_value( $value, 'path' );
		if ( ! is_string( $path ) ) {
			return false;
		}

		return (bool) preg_match( '/\.(png|jpe?g|gif|webp|bmp|svg)$/i', $path );
	}

	/**
	 * Get a value from a media field.
	 *
	 * @param mixed  $value
	 * @param string $key
	 * @return mixed|null
	 */
	private function get_media_value( $value, $key ) {
		if ( is_object( $value ) && property_exists( $value, $key ) ) {
			return $value->{$key};
		}

		if ( is_array( $value ) && array_key_exists( $key, $value ) ) {
			return $value[ $key ];
		}

		return null;
	}

	/**
	 * Set a value on a media field.
	 *
	 * @param mixed  $value
	 * @param string $key
	 * @param mixed  $new_value
	 */
	private function set_media_value( &$value, $key, $new_value ) {
		if ( is_object( $value ) ) {
			$value->{$key} = $new_value;
		} elseif ( is_array( $value ) ) {
			$value[ $key ] = $new_value;
		}
	}

	/**
	 * Check whether a media field has a key.
	 *
	 * @param mixed  $value
	 * @param string $key
	 * @return bool
	 */
	private function media_value_has_key( $value, $key ) {
		if ( is_object( $value ) ) {
			return property_exists( $value, $key );
		}

		if ( is_array( $value ) ) {
			return array_key_exists( $key, $value );
		}

		return false;
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
			|| function_exists( 'icl_t' )
			|| has_filter( 'wpml_object_id' )
			|| function_exists( 'icl_object_id' );

		return $this->wpml_active;
	}
}
