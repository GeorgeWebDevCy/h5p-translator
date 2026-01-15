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

	private const STRING_NAME_MAX_LENGTH = 160;
	private const STRING_NAME_HASH_LENGTH = 12;

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
	 * Track translated paths to avoid duplicate fallback registrations.
	 *
	 * @var array
	 */
	private $translated_paths = array();

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

		$this->translated_paths = array();

		$content_id = $this->resolve_content_id();
		$context = $content_id ? 'H5P Content ' . $content_id : 'H5P Content';
		$raw_path_prefix = $library_name . ' ' . $major_version . '.' . $minor_version;
		$stable_path_prefix = $this->get_stable_root_path( $parameters, $raw_path_prefix );

		$language = $this->get_current_language();
		H5p_Wpml_Translator_Logger::log( sprintf( 
			"--- Translation Start: %s (Content ID: %s, Lang: %s) ---", 
			$library_name, 
			$content_id ?: 'Unknown',
			$language ?: 'Default'
		) );

		$semantics = $this->get_semantics( $core, $library_name, $major_version, $minor_version );
		if ( empty( $semantics ) || ! is_array( $semantics ) ) {
			if ( $this->should_use_text_fallback( $library_name ) ) {
				H5p_Wpml_Translator_Logger::log( "Starting text fallback for library: " . $library_name );
				$this->translate_text_fallback( $parameters, $context, $raw_path_prefix, $stable_path_prefix );
			}
			$this->translate_media_fallback( $parameters, $content_id, $core );
			return;
		}

		$this->translate_fields( $parameters, $semantics, $context, $raw_path_prefix, $stable_path_prefix, $core, $content_id );
		if ( $this->should_use_text_fallback( $library_name ) ) {
			H5p_Wpml_Translator_Logger::log( "Starting text fallback for library: " . $library_name );
			$this->translate_text_fallback( $parameters, $context, $raw_path_prefix, $stable_path_prefix );
		}
		$this->translate_media_fallback( $parameters, $content_id, $core );
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
	 * Sync H5P media when editing content in admin.
	 */
	public function maybe_sync_media_on_h5p_edit() {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! current_user_can( 'edit_h5p_contents' ) && ! current_user_can( 'edit_others_h5p_contents' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'h5p_new' !== $page ) {
			return;
		}

		$content_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( $content_id < 1 ) {
			return;
		}

		$this->sync_media_for_content_id( $content_id );
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
	 * Sync media translation for a specific H5P content id.
	 *
	 * @param int $content_id
	 */
	private function sync_media_for_content_id( $content_id ) {
		if ( ! $this->is_wpml_active() || ! class_exists( 'H5P_Plugin' ) ) {
			return;
		}

		$plugin = H5P_Plugin::get_instance();
		if ( ! $plugin || ! method_exists( $plugin, 'get_content' ) ) {
			return;
		}

		$content = $plugin->get_content( $content_id );
		if ( ! is_array( $content ) || empty( $content['params'] ) ) {
			return;
		}

		$parameters = json_decode( $content['params'] );
		if ( ! is_object( $parameters ) && ! is_array( $parameters ) ) {
			return;
		}

		$core = $this->get_h5p_core();
		if ( ! $core ) {
			return;
		}

		$this->translated_paths = array();

		if ( empty( $content['libraryName'] ) || ! isset( $content['libraryMajorVersion'], $content['libraryMinorVersion'] ) ) {
			return;
		}

		$library_name = (string) $content['libraryName'];
		$major_version = (int) $content['libraryMajorVersion'];
		$minor_version = (int) $content['libraryMinorVersion'];

		$semantics = $this->get_semantics( $core, $library_name, $major_version, $minor_version );
		$context = 'H5P Content ' . $content_id;
		$raw_path_prefix = $library_name . ' ' . $major_version . '.' . $minor_version;
		$stable_path_prefix = $this->get_stable_root_path( $parameters, $raw_path_prefix );

		if ( ! empty( $semantics ) && is_array( $semantics ) ) {
			$this->translate_fields( $parameters, $semantics, $context, $raw_path_prefix, $stable_path_prefix, $core, $content_id );
		}

		if ( $this->should_use_text_fallback( $library_name ) ) {
			$this->translate_text_fallback( $parameters, $context, $raw_path_prefix, $stable_path_prefix );
		}

		$this->translate_media_fallback( $parameters, $content_id, $core );
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
	 * @param string $raw_path_prefix
	 * @param string $stable_path_prefix
	 * @param H5PCore $core
	 * @param int|null $content_id
	 */
	private function translate_fields( &$params, $fields, $context, $raw_path_prefix, $stable_path_prefix, $core, $content_id ) {
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

			$raw_path = $raw_path_prefix ? $raw_path_prefix . '.' . $field_name : $field_name;
			$stable_path = $stable_path_prefix ? $stable_path_prefix . '.' . $field_name : $field_name;
			$this->translate_field( $value, $field, $context, $raw_path, $stable_path, $core, $content_id );
		}
	}

	/**
	 * Translate a single field based on its semantics.
	 *
	 * @param mixed  $value
	 * @param object $field
	 * @param string $context
	 * @param string $raw_path
	 * @param string $stable_path
	 * @param H5PCore $core
	 * @param int|null $content_id
	 */
	private function translate_field( &$value, $field, $context, $raw_path, $stable_path, $core, $content_id ) {
		if ( empty( $field->type ) ) {
			return;
		}

		switch ( $field->type ) {
			case 'text':
			case 'textarea':
			case 'html':
				if ( is_string( $value ) && $value !== '' ) {
					$allow_html = ( 'html' === $field->type ) || isset( $field->tags );
					if ( ! $allow_html && false !== strpos( $value, '<' ) && false !== strpos( $value, '>' ) ) {
						$allow_html = true;
					}
					$value = $this->register_and_translate( $value, $context, $raw_path, $stable_path, $allow_html );
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
					$group_stable_path = $this->get_subcontent_root( $value );
					if ( ! $group_stable_path ) {
						$group_stable_path = $stable_path;
					}
					$this->translate_fields( $value, $field->fields, $context, $raw_path, $group_stable_path, $core, $content_id );
				}
				return;

			case 'list':
				if ( ! is_array( $value ) || empty( $field->field ) ) {
					return;
				}

				foreach ( $value as $index => &$item ) {
					$suffix = $index;
					$sub_id = $this->get_subcontent_id( $item );
					if ( $sub_id ) {
						$suffix = 'subContentId:' . $sub_id;
					}

					$item_raw_path = $raw_path . '[' . $suffix . ']';
					$item_stable_path = $this->get_subcontent_root( $item );
					if ( ! $item_stable_path ) {
						$item_stable_path = $stable_path ? $stable_path . '[' . $index . ']' : '[' . $index . ']';
					}
					$this->translate_field( $item, $field->field, $context, $item_raw_path, $item_stable_path, $core, $content_id );
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

				$nested_raw_path = $raw_path . '.library[' . $library['key'] . ']';
				$params_value = null;
				if ( is_object( $value ) && isset( $value->params ) ) {
					$params_value = &$value->params;
				} elseif ( is_array( $value ) && isset( $value['params'] ) ) {
					$params_value = &$value['params'];
				}

				if ( null === $params_value ) {
					return;
				}

				$nested_stable_path = $this->get_subcontent_root( $value );
				if ( ! $nested_stable_path ) {
					$nested_stable_path = $this->get_subcontent_root( $params_value );
				}
				if ( ! $nested_stable_path ) {
					$nested_stable_path = $stable_path ? $stable_path . '.library[' . $library['key'] . ']' : 'library[' . $library['key'] . ']';
				}

				$semantics = $this->get_semantics( $core, $library['name'], $library['major'], $library['minor'] );
				if ( empty( $semantics ) || ! is_array( $semantics ) ) {
					if ( $this->should_use_text_fallback( $library['name'] ) ) {
						$this->translate_text_fallback( $params_value, $context, $nested_raw_path, $nested_stable_path );
					}
					return;
				}

				$this->translate_fields( $params_value, $semantics, $context, $nested_raw_path, $nested_stable_path, $core, $content_id );
				if ( $this->should_use_text_fallback( $library['name'] ) ) {
					$this->translate_text_fallback( $params_value, $context, $nested_raw_path, $nested_stable_path );
				}
				return;

			default:
				if ( isset( $field->fields ) ) {
					$nested_stable_path = $this->get_subcontent_root( $value );
					if ( ! $nested_stable_path ) {
						$nested_stable_path = $stable_path;
					}
					$this->translate_fields( $value, $field->fields, $context, $raw_path, $nested_stable_path, $core, $content_id );
				}
				return;
		}
	}

	/**
	 * Fallback scan for image fields that are missing semantics.
	 *
	 * @param mixed   $params
	 * @param int|null $content_id
	 * @param H5PCore $core
	 * @param int     $depth
	 */
	private function translate_media_fallback( &$params, $content_id, $core, $depth = 0 ) {
		if ( $depth > 20 ) {
			return;
		}

		if ( is_object( $params ) ) {
			if ( $this->media_value_is_image( $params ) ) {
				$this->translate_media_field( $params, $content_id, $core );
			}

			foreach ( get_object_vars( $params ) as &$value ) {
				if ( is_array( $value ) || is_object( $value ) ) {
					$this->translate_media_fallback( $value, $content_id, $core, $depth + 1 );
				}
			}
			unset( $value );
			return;
		}

		if ( is_array( $params ) ) {
			if ( $this->media_value_is_image( $params ) ) {
				$this->translate_media_field( $params, $content_id, $core );
			}

			foreach ( $params as &$value ) {
				if ( is_array( $value ) || is_object( $value ) ) {
					$this->translate_media_fallback( $value, $content_id, $core, $depth + 1 );
				}
			}
			unset( $value );
		}
	}

	/**
	 * Determine if text fallback should run for a library.
	 *
	 * @param string $library_name
	 * @return bool
	 */
	private function should_use_text_fallback( $library_name ) {
		$default = array( '*' );
		$libraries = apply_filters( 'h5p_wpml_translator_text_fallback_libraries', $default );

		if ( true === $libraries ) {
			return true;
		}

		if ( false === $libraries ) {
			return false;
		}

		if ( ! is_array( $libraries ) ) {
			$libraries = $default;
		}

		if ( in_array( '*', $libraries, true ) ) {
			return true;
		}

		return in_array( $library_name, $libraries, true );
	}

	/**
	 * Fallback scan for strings when semantics are missing.
	 *
	 * @param mixed  $value
	 * @param string $context
	 * @param string $raw_path
	 * @param string $stable_path
	 * @param int    $depth
	 */
	private function translate_text_fallback( &$value, $context, $raw_path, $stable_path, $depth = 0 ) {
		if ( $depth > 20 ) {
			return;
		}

		if ( is_string( $value ) ) {
			if ( '' === trim( $value ) ) {
				return;
			}

			$translation_name = $this->get_translation_name( $stable_path, $raw_path );
			if ( '' !== $translation_name && $this->is_path_translated( $translation_name ) ) {
				return;
			}

			if ( $this->should_skip_fallback_string( $value, $raw_path ) ) {
				return;
			}

			$allow_html = ( false !== strpos( $value, '<' ) && false !== strpos( $value, '>' ) );
			$value = $this->register_and_translate( $value, $context, $raw_path, $stable_path, $allow_html );
			return;
		}

		if ( is_object( $value ) ) {
			$library = null;
			if ( isset( $value->library ) && is_string( $value->library ) ) {
				$library = $this->parse_library_string( $value->library );
			}

			$stable_base = $this->get_subcontent_root( $value );
			if ( ! $stable_base ) {
				$stable_base = $stable_path;
			}
			$use_stable_root = is_string( $stable_base ) && 0 === strpos( $stable_base, 'subContentId:' );

			foreach ( get_object_vars( $value ) as $key => &$child ) {
				if ( 'params' === $key && $library ) {
					$raw_child_path = $raw_path ? $raw_path . '.library[' . $library['key'] . ']' : 'library[' . $library['key'] . ']';
					if ( $use_stable_root ) {
						$stable_child_path = $stable_base;
					} else {
						$stable_child_path = $stable_path ? $stable_path . '.library[' . $library['key'] . ']' : 'library[' . $library['key'] . ']';
					}
				} else {
					$raw_child_path = $raw_path ? $raw_path . '.' . $key : $key;
					$stable_child_path = $stable_base ? $stable_base . '.' . $key : $key;
				}
				$this->translate_text_fallback( $child, $context, $raw_child_path, $stable_child_path, $depth + 1 );
			}
			unset( $child );
			return;
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $key => &$child ) {
				if ( is_int( $key ) ) {
					$segment = '[' . $key . ']';
					$sub_id = $this->get_subcontent_id( $child );
					if ( $sub_id ) {
						$segment = '[subContentId:' . $sub_id . ']';
					}
				} else {
					$segment = '.' . $key;
				}
				$raw_child_path = $raw_path ? $raw_path . $segment : ltrim( $segment, '.' );

				$stable_child_path = $this->get_subcontent_root( $child );
				if ( ! $stable_child_path ) {
					$stable_segment = is_int( $key ) ? '[' . $key . ']' : '.' . $key;
					$stable_child_path = $stable_path ? $stable_path . $stable_segment : ltrim( $stable_segment, '.' );
				}

				$this->translate_text_fallback( $child, $context, $raw_child_path, $stable_child_path, $depth + 1 );
			}
			unset( $child );
		}
	}

	/**
	 * Determine if a fallback string should be skipped.
	 *
	 * @param string $value
	 * @param string $path
	 * @return bool
	 */
	private function should_skip_fallback_string( $value, $path ) {
		$leaf = $this->get_path_leaf( $path );
		$skip_keys = array(
			'path',
			'mime',
			'library',
			'contentId',
			'subContentId',
			'file',
			'files',
			'source',
			'src',
			'url',
			'href',
			'action',
			'id',
			'stageScoreId',
			'stageProgressId',
			'contentId',
			'x',
			'y',
			'width',
			'height',
			'duration',
			'time',
			'color',
			'colorPath',
			'colorStage',
			'colorStageLocked',
			'colorStageCleared',
			'colorPathCleared',
			'telemetry',
			'telemetry_x',
			'telemetry_y',
			'telemetry_width',
			'telemetry_height',
		);

		if ( $leaf && in_array( $leaf, $skip_keys, true ) ) {
			return true;
		}

		if ( preg_match( '#^https?://#i', $value ) || 0 === strpos( $value, '//' ) ) {
			return true;
		}

		if ( preg_match( '/\\.(png|jpe?g|gif|webp|svg|bmp|mp3|mp4|webm|ogg|wav|vtt|srt|pdf|docx?|pptx?|xlsx?)(\\?.*)?$/i', $value ) ) {
			return true;
		}

		if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value ) ) {
			return true;
		}

		if ( preg_match( '/^rgba?\\(/i', $value ) || preg_match( '/^hsla?\\(/i', $value ) ) {
			return true;
		}

		if ( is_numeric( $value ) ) {
			return true;
		}

		$trimmed = trim( $value );
		if ( '' === $trimmed ) {
			return true;
		}

		// Skip empty HTML tags.
		if ( false !== strpos( $trimmed, '<' ) ) {
			$no_tags = trim( strip_tags( $trimmed ) );
			if ( '' === $no_tags || '&nbsp;' === $no_tags ) {
				return true;
			}
		}

		if ( ( ( 0 === strpos( $trimmed, '{' ) && strrpos( $trimmed, '}' ) === ( strlen( $trimmed ) - 1 ) )
			|| ( 0 === strpos( $trimmed, '[' ) && strrpos( $trimmed, ']' ) === ( strlen( $trimmed ) - 1 ) ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the last key segment for a fallback path.
	 *
	 * @param string $path
	 * @return string|null
	 */
	private function get_path_leaf( $path ) {
		if ( ! is_string( $path ) || '' === $path ) {
			return null;
		}

		$clean = preg_replace( '/\\[[^\\]]+\\]$/', '', $path );
		$pos = strrpos( $clean, '.' );
		if ( false === $pos ) {
			return $clean;
		}

		return substr( $clean, $pos + 1 );
	}

	/**
	 * Get subcontent ID from a value when available.
	 *
	 * @param mixed $value
	 * @return string|null
	 */
	private function get_subcontent_id( $value ) {
		if ( is_object( $value ) && isset( $value->subContentId ) ) {
			$sub_id = trim( (string) $value->subContentId );
			return '' !== $sub_id ? $sub_id : null;
		}

		if ( is_array( $value ) && array_key_exists( 'subContentId', $value ) ) {
			$sub_id = trim( (string) $value['subContentId'] );
			return '' !== $sub_id ? $sub_id : null;
		}

		return null;
	}

	/**
	 * Build a stable root path for a subcontent node.
	 *
	 * @param mixed $value
	 * @return string|null
	 */
	private function get_subcontent_root( $value ) {
		$sub_id = $this->get_subcontent_id( $value );
		if ( ! $sub_id ) {
			return null;
		}

		return 'subContentId:' . $sub_id;
	}

	/**
	 * Get the stable path prefix, falling back to the provided path.
	 *
	 * @param mixed  $value
	 * @param string $fallback
	 * @return string
	 */
	private function get_stable_root_path( $value, $fallback ) {
		$root = $this->get_subcontent_root( $value );
		return $root ? $root : $fallback;
	}

	/**
	 * Get a stable translation name with hashing for long paths.
	 *
	 * @param string $stable_path
	 * @return string
	 */
	private function get_string_name( $stable_path ) {
		if ( ! is_string( $stable_path ) || '' === $stable_path ) {
			return '';
		}

		if ( strlen( $stable_path ) <= self::STRING_NAME_MAX_LENGTH ) {
			return $stable_path;
		}

		$hash = substr( sha1( $stable_path ), 0, self::STRING_NAME_HASH_LENGTH );
		$prefix_length = self::STRING_NAME_MAX_LENGTH - self::STRING_NAME_HASH_LENGTH - 1;
		if ( $prefix_length < 1 ) {
			return '#' . $hash;
		}

		return substr( $stable_path, 0, $prefix_length ) . '#' . $hash;
	}

	/**
	 * Get the translation name using the stable path or raw path fallback.
	 *
	 * @param string $stable_path
	 * @param string $raw_path
	 * @return string
	 */
	private function get_translation_name( $stable_path, $raw_path ) {
		$name = $this->get_string_name( $stable_path );
		if ( '' === $name && is_string( $raw_path ) && '' !== $raw_path ) {
			$name = $this->get_string_name( $raw_path );
		}

		return $name;
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
		$status = ( $translated_id && $translated_id !== $attachment_id ) ? 'FOUND' : 'NOT FOUND';
		
		$translated_url = null;
		if ( 'FOUND' === $status ) {
			$translated_url = wp_get_attachment_url( $translated_id );
		}

		H5p_Wpml_Translator_Logger::log( array(
			'type'           => 'media',
			'status'         => $status,
			'source_path'    => $path,
			'source_url'     => $normalized_url,
			'translated_url' => $translated_url ?: $url,
		) );

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
			$origin = $this->get_site_origin();
			if ( $origin ) {
				return $origin . $path;
			}
			return null;
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
		$uploads = wp_upload_dir();
		$uploads_base = ! empty( $uploads['baseurl'] ) ? rtrim( $uploads['baseurl'], '/' ) : null;
		$default_base = $uploads_base ? $uploads_base . '/h5p' : null;

		if ( is_object( $core ) && ! empty( $core->url ) ) {
			$base = rtrim( (string) $core->url, '/' );

			if ( preg_match( '#^https?://#i', $base ) ) {
				return $base;
			}

			if ( 0 === strpos( $base, '//' ) ) {
				if ( $default_base ) {
					return $default_base;
				}

				$scheme = is_ssl() ? 'https:' : 'http:';
				return $scheme . $base;
			}

			if ( 0 === strpos( $base, '/' ) ) {
				if ( $default_base ) {
					return $default_base;
				}

				$origin = $this->get_site_origin();
				return $origin ? $origin . $base : null;
			}
		}

		return $default_base;
	}

	/**
	 * Get site origin from uploads base URL to avoid language prefixes.
	 *
	 * @return string|null
	 */
	private function get_site_origin() {
		$uploads = wp_upload_dir();
		if ( empty( $uploads['baseurl'] ) ) {
			return null;
		}

		$parts = wp_parse_url( $uploads['baseurl'] );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return null;
		}

		$origin = $parts['scheme'] . '://' . $parts['host'];
		if ( ! empty( $parts['port'] ) ) {
			$origin .= ':' . $parts['port'];
		}

		return $origin;
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

		// We don't check for upload_files capability here because this is an automated 
		// registration of existing H5P files into the Media Library for translation purposes.

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
		$language = $this->get_current_language();

		if ( function_exists( 'icl_object_id' ) ) {
			$translated = $language
				? icl_object_id( $attachment_id, 'attachment', true, $language )
				: icl_object_id( $attachment_id, 'attachment', true );
			return $translated ? (int) $translated : 0;
		}

		if ( has_filter( 'wpml_object_id' ) ) {
			$translated = $language
				? apply_filters( 'wpml_object_id', $attachment_id, 'attachment', true, $language )
				: apply_filters( 'wpml_object_id', $attachment_id, 'attachment', true );
			return $translated ? (int) $translated : 0;
		}

		return (int) $attachment_id;
	}

	/**
	 * Get the current WPML language code.
	 *
	 * @return string|null
	 */
	private function get_current_language() {
		$language = null;

		$languages = null;
		if ( has_filter( 'wpml_active_languages' ) ) {
			$languages = apply_filters(
				'wpml_active_languages',
				null,
				array(
					'skip_missing' => 0,
					'orderby'      => 'code',
				)
			);
		}

		if ( is_array( $languages ) ) {
			if ( isset( $_SERVER['REQUEST_URI'] ) ) {
				$language = $this->detect_language_from_url( wp_unslash( $_SERVER['REQUEST_URI'] ), $languages );
			}

			if ( ( ! is_string( $language ) || '' === $language ) && isset( $_SERVER['HTTP_REFERER'] ) ) {
				$language = $this->detect_language_from_url( wp_unslash( $_SERVER['HTTP_REFERER'] ), $languages );
			}
		}

		if ( ( ! is_string( $language ) || '' === $language ) && has_filter( 'wpml_current_language' ) ) {
			$language = apply_filters( 'wpml_current_language', null );
		}

		if ( ( ! is_string( $language ) || '' === $language ) && defined( 'ICL_LANGUAGE_CODE' ) ) {
			$language = ICL_LANGUAGE_CODE;
		}

		if ( ! is_string( $language ) || '' === $language ) {
			if ( isset( $_GET['lang'] ) ) {
				$language = sanitize_key( wp_unslash( $_GET['lang'] ) );
			} elseif ( isset( $_COOKIE['wp-wpml_current_language'] ) ) {
				$language = sanitize_key( wp_unslash( $_COOKIE['wp-wpml_current_language'] ) );
			}
		}

		if ( ! is_string( $language ) || '' === $language ) {
			return null;
		}

		return $language;
	}

	/**
	 * Get the default WPML language code.
	 *
	 * @return string|null
	 */
	private function get_default_language() {
		if ( has_filter( 'wpml_default_language' ) ) {
			$default_language = apply_filters( 'wpml_default_language', null );
			if ( is_string( $default_language ) && '' !== $default_language ) {
				return $default_language;
			}
		}

		return null;
	}

	/**
	 * Determine if strings should be registered for the current request.
	 *
	 * @return bool
	 */
	private function should_register_strings() {
		if ( ! $this->is_wpml_active() ) {
			return true;
		}

		$default_language = $this->get_default_language();
		if ( ! $default_language ) {
			return true;
		}

		$current_language = $this->get_current_language();
		if ( ! $current_language ) {
			return true;
		}

		return $current_language === $default_language;
	}

	/**
	 * Detect the language from a URL path based on active languages.
	 *
	 * @param string $url
	 * @param array  $languages
	 * @return string|null
	 */
	private function detect_language_from_url( $url, $languages ) {
		if ( ! is_string( $url ) || '' === $url || ! is_array( $languages ) ) {
			return null;
		}

		$parts = wp_parse_url( $url );
		$path  = isset( $parts['path'] ) ? trim( $parts['path'], '/' ) : '';

		if ( '' !== $path ) {
			$segment = strtok( $path, '/' );
			if ( $segment && isset( $languages[ $segment ] ) ) {
				return $segment;
			}
		}

		if ( isset( $parts['query'] ) ) {
			parse_str( $parts['query'], $query_vars );
			if ( ! empty( $query_vars['lang'] ) && is_string( $query_vars['lang'] ) && isset( $languages[ $query_vars['lang'] ] ) ) {
				return $query_vars['lang'];
			}
		}

		return null;
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
	 * @param string $raw_path
	 * @param string $stable_path
	 * @param bool   $allow_html
	 * @return string
	 */
	private function register_and_translate( $value, $context, $raw_path, $stable_path, $allow_html ) {
		$string_name = $this->get_translation_name( $stable_path, $raw_path );
		if ( '' === $string_name ) {
			return $value;
		}

		$this->mark_translated_path( $string_name );

		// Normalize string before registration/translation.
		// Use html_entity_decode to handle &nbsp; and other entities from H5P.
		$normalized = trim( html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		
		if ( $this->should_register_strings() ) {
			$this->register_string( $context, $string_name, $normalized, $allow_html );
		}
		
		$translated = $this->translate_string( $normalized, $context, $string_name );
		$language   = $this->get_current_language();

		H5p_Wpml_Translator_Logger::log( array(
			'type'       => 'string',
			'status'     => ( $translated !== $normalized ) ? 'FOUND' : 'NOT FOUND (Source returned)',
			'lang'       => $language ?: 'Default',
			'context'    => $context,
			'path'       => $string_name,
			'raw_path'   => $raw_path,
			'stable_path' => $stable_path,
			'source'     => $value,
			'normalized' => $normalized,
			'translated' => $translated,
		) );

		return $translated;
	}

	/**
	 * Track a translated path for the current request.
	 *
	 * @param string $path
	 */
	private function mark_translated_path( $path ) {
		if ( ! is_string( $path ) || '' === $path ) {
			return;
		}

		$this->translated_paths[ $path ] = true;
	}

	/**
	 * Check if a path was already translated.
	 *
	 * @param string $path
	 * @return bool
	 */
	private function is_path_translated( $path ) {
		return is_string( $path ) && isset( $this->translated_paths[ $path ] );
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
			$language = $this->get_current_language();
			$translated = $language
				? apply_filters( 'wpml_translate_single_string', $value, $context, $name, $language )
				: apply_filters( 'wpml_translate_single_string', $value, $context, $name );
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
