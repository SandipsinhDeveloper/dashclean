<?php
/**
 * Data Handler for Import/Export.
 *
 * @package DashClean\Core
 */

namespace DashClean\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Data_Handler
 *
 * Handles JSON import and export of plugin settings.
 */
class Data_Handler {

	/**
	 * Export settings to a JSON file.
	 *
	 * @return void
	 */
	public static function export_settings() {
		// Security check: Only administrators should be able to dump site configurations.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Settings::get_instance()->get_settings();
		$data     = array(
			'source'    => 'DashClean',
			'version'   => DASHCLEAN_VERSION,
			'timestamp' => time(),
			'settings'  => $settings,
		);

		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="dashclean-export-' . gmdate( 'Y-m-d' ) . '.json"' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );

		echo wp_json_encode( $data, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Import settings from a JSON file.
	 * 
	 * @param string $tmp_path Temporary file path.
	 * @return \WP_Error|bool
	 */
	public static function import_settings( $tmp_path ) {
		// Strict capability check before processing uploaded data.
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'insufficient_permissions', __( 'Insufficient permissions.', 'dashclean' ) );
		}

		if ( empty( $tmp_path ) || ! is_uploaded_file( $tmp_path ) || ! file_exists( $tmp_path ) ) {
			return new \WP_Error( 'not_found', __( 'Temporary file not found or invalid upload.', 'dashclean' ) );
		}

		if ( ! is_readable( $tmp_path ) ) {
			return new \WP_Error( 'not_readable', __( 'Temporary file is not readable.', 'dashclean' ) );
		}

		// Use WP_Filesystem to read the file.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			return new \WP_Error( 'fs_init_error', __( 'Could not initialize filesystem API.', 'dashclean' ) );
		}

		$json = $wp_filesystem->get_contents( $tmp_path );

		if ( false === $json || empty( $json ) ) {
			return new \WP_Error( 'read_error', __( 'Could not read the uploaded file contents.', 'dashclean' ) );
		}
		
		// Remove potential BOM and trim.
		$json = preg_replace( '/^\xEF\xBB\xBF/', '', $json );
		$json = trim( $json );

		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error( 'invalid_json', __( 'JSON Decode Error: ', 'dashclean' ) . json_last_error_msg() );
		}

		if ( ! is_array( $data ) || ! isset( $data['source'] ) || $data['source'] !== 'DashClean' ) {
			return new \WP_Error( 'invalid_content', __( 'Invalid or incompatible settings file.', 'dashclean' ) );
		}

		if ( empty( $data['settings'] ) ) {
			return new \WP_Error( 'no_settings', __( 'No settings found in the file.', 'dashclean' ) );
		}

		return Settings::get_instance()->update_settings( $data['settings'], true );
	}
}
