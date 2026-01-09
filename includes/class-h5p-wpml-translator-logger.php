<?php

/**
 * Handle logging of translation events for diagnostic purposes.
 */
class H5p_Wpml_Translator_Logger {

	const OPTION_ENABLED = 'h5p_wpml_translator_log_enabled';
	const LOG_FILENAME = 'h5p-translator-diagnostics.log';

	/**
	 * Get the path to the log file.
	 *
	 * @return string
	 */
	public static function get_log_path() {
		$uploads = wp_upload_dir();
		return trailingslashit( $uploads['basedir'] ) . self::LOG_FILENAME;
	}

	/**
	 * Check if logging is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return 'yes' === get_option( self::OPTION_ENABLED, 'no' );
	}

	/**
	 * Add an entry to the log.
	 *
	 * @param string|array|object $data
	 */
	public static function log( $data ) {
		if ( ! self::is_enabled() ) {
			return;
		}

		$path = self::get_log_path();
		$timestamp = date( 'Y-m-d H:i:s' );
		
		if ( is_array( $data ) || is_object( $data ) ) {
			$data = json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		}

		$entry = sprintf( "[%s] %s\n", $timestamp, $data );
		
		// Use file_put_contents with FILE_APPEND to keep it simple and efficient
		@file_put_contents( $path, $entry, FILE_APPEND );

		// Enforce a maximum file size (e.g., 5MB)
		if ( @filesize( $path ) > 5 * 1024 * 1024 ) {
			self::rotate_logs();
		}
	}

	/**
	 * Get the contents of the log file.
	 *
	 * @return string
	 */
	public static function get_logs() {
		$path = self::get_log_path();
		if ( ! file_exists( $path ) ) {
			return '';
		}
		return (string) @file_get_contents( $path );
	}

	/**
	 * Clear the log file.
	 */
	public static function clear_logs() {
		$path = self::get_log_path();
		if ( file_exists( $path ) ) {
			@unlink( $path );
		}
	}

	/**
	 * Rotate logs if they get too big.
	 */
	private static function rotate_logs() {
		$path = self::get_log_path();
		if ( ! file_exists( $path ) ) {
			return;
		}
		
		$rotated = $path . '.old';
		@rename( $path, $rotated );
	}
}
