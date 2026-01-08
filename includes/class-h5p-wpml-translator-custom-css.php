<?php

/**
 * Custom CSS storage and file handling.
 *
 * @link       https://example.com
 * @since      1.1.0
 *
 * @package    H5p_Wpml_Translator
 * @subpackage H5p_Wpml_Translator/includes
 */

class H5p_Wpml_Translator_Custom_Css {

	const OPTION_NAME = 'h5p_wpml_translator_custom_css';
	const SETTINGS_GROUP = 'h5p_wpml_translator_custom_css';
	const SUBDIR = 'h5p-wpml-translator';
	const FILE_NAME = 'h5p-custom.css';

	/**
	 * Normalize user-provided CSS.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public static function sanitize_css( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = wp_unslash( $value );
		$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
		return trim( $value );
	}

	/**
	 * Get the stored custom CSS.
	 *
	 * @return string
	 */
	public static function get_css() {
		$css = get_option( self::OPTION_NAME, '' );
		return is_string( $css ) ? $css : '';
	}

	/**
	 * Get upload paths for the custom CSS file.
	 *
	 * @return array|null
	 */
	public static function get_paths() {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return null;
		}

		$dir = trailingslashit( $uploads['basedir'] ) . self::SUBDIR;
		$path = trailingslashit( $dir ) . self::FILE_NAME;
		$url = trailingslashit( $uploads['baseurl'] ) . self::SUBDIR . '/' . self::FILE_NAME;

		return array(
			'dir'  => $dir,
			'path' => $path,
			'url'  => $url,
		);
	}

	/**
	 * Write the custom CSS file (or remove it if empty).
	 *
	 * @param string $css
	 * @return string|null
	 */
	public static function write_css_file( $css ) {
		$paths = self::get_paths();
		if ( ! $paths ) {
			return null;
		}

		if ( '' === $css ) {
			if ( file_exists( $paths['path'] ) ) {
				@unlink( $paths['path'] );
			}
			return null;
		}

		if ( ! wp_mkdir_p( $paths['dir'] ) ) {
			return null;
		}

		$existing = '';
		if ( file_exists( $paths['path'] ) ) {
			$existing = file_get_contents( $paths['path'] );
			if ( false === $existing ) {
				$existing = '';
			}
		}

		if ( $existing !== $css ) {
			$written = file_put_contents( $paths['path'], $css );
			if ( false === $written ) {
				return null;
			}
		}

		return $paths['url'];
	}
}
