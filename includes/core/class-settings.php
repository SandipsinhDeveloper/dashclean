<?php
/**
 * Settings Manager.
 *
 * @package DashClean\Core
 */

namespace DashClean\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 *
 * Handles storage, retrieval, and sanitization of plugin settings.
 */
class Settings {

	/**
	 * Settings option key.
	 *
	 * @var string
	 */
	private $option_name = 'dashclean_settings';

	/**
	 * Cached settings.
	 *
	 * @var array
	 */
	private $settings = null;

	/**
	 * Instance of this class.
	 *
	 * @var Settings
	 */
	private static $instance = null;

	/**
	 * Get class instance.
	 *
	 * @return Settings
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Private constructor to enforce Singleton pattern.
	}

	/**
	 * Get all settings with defaults.
	 *
	 * @return array
	 */
	public function get_settings() {
		// If we already loaded options during this request, return the cached array to save DB calls.
		if ( null !== $this->settings ) {
			return $this->settings;
		}

		$saved_settings = get_option( $this->option_name, array() );
		$defaults       = $this->get_defaults();

		// Use recursive merge to ensure nested keys exist.
		$this->settings = array_replace_recursive( $defaults, $saved_settings );

		return $this->settings;
	}

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	public function get_defaults() {
		return array(
			// Enablement flags.
			'dashboard_widgets_enabled' => 0,
			'menu_cleaner_enabled'      => 0,
			'admin_bar_enabled'         => 0,
			'notice_cleaner_enabled'    => 0,
			'performance_enabled'       => 0,
			'role_manager_enabled'      => 0,

			// Module-specific configurations.
			'dashboard_widgets' => array(
				'remove_all' => 0,
				'widgets'    => array(), // List of widget IDs.
				'roles'      => array(), // Affected roles.
			),
			'menu_cleaner'      => array(
				'items' => array(), // List of menu slugs (parent|child).
				'roles' => array(), // Affected roles.
			),
			'admin_bar'         => array(
				'nodes' => array(), // List of node IDs.
				'roles' => array(), // Affected roles.
			),
			'notice_cleaner'    => array(
				'hide_all'     => 1,
				'hide_ads'     => 1,
				'keep_errors' => 1,
				'roles'        => array(), // Affected roles.
			),
			'role_manager'      => array(
				'roles' => array(), // Affected roles.
				'users' => array(), // Specific affected user IDs.
			),
			'performance'       => array(),
			'custom_presets'    => array(), // User-saved custom presets.
			'presets_enabled'   => 0,       // Master toggle for presets system.
			'active_preset'     => '',      // Track if a preset is currently applied.
		);
	}

	/**
	 * Update settings.
	 *
	 * @param array $new_settings New settings values.
	 * @param bool  $overwrite    Whether to completely overwrite settings or merge.
	 * @return bool Whether the settings were updated.
	 */
	public function update_settings( $new_settings = array(), $overwrite = false ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Ensure we unslash data from $_POST before processing.
		$unslashed_settings = wp_unslash( $new_settings );
		$sanitized_settings = $this->sanitize_settings( $unslashed_settings );

		if ( $overwrite ) {
			// Complete replacement mode. We still merge with defaults so no top-level keys are ever missing.
			$this->settings = array_replace_recursive( $this->get_defaults(), $sanitized_settings );
		} else {
			// Standard mode: Merge the new sanitized data into existing settings dynamically.
			$current_settings = $this->get_settings();
			foreach ( $sanitized_settings as $key => $value ) {
				// Special case: custom_presets should always be a total replacement within its key.
				if ( 'custom_presets' === $key ) {
					$current_settings[ $key ] = $value;
					continue;
				}

				if ( is_array( $value ) && isset( $current_settings[ $key ] ) && is_array( $current_settings[ $key ] ) ) {
					$current_settings[ $key ] = array_replace( $current_settings[ $key ], $value );
				} else {
					$current_settings[ $key ] = $value;
				}
			}
			$this->settings = $current_settings;
		}

		// If this is a manual save from the main form (not applying a preset), 
		// clear the 'active_preset' because the configuration has changed.
		// We use a nonce check to ensure this is an intended manual save.
		$is_manual_save = isset( $_POST['dashclean_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dashclean_nonce'] ) ), 'dashclean_save_settings' );
		if ( $is_manual_save && ! isset( $_POST['apply_preset'] ) && ! $overwrite ) {
			$this->settings['active_preset'] = '';
		}

		$this->settings = $this->settings; // Ensure internal cache is set.
		update_option( $this->option_name, $this->settings );
		return true;
	}

	/**
	 * Sanitize settings array recursively.
	 *
	 * @param array $settings Settings to sanitize.
	 * @return array Sanitized settings.
	 */
	private function sanitize_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $settings as $key => $value ) {
			$clean_key = sanitize_key( $key );
			if ( is_array( $value ) ) {
				$sanitized[ $clean_key ] = $this->sanitize_settings( $value );
			} else {
				// Special handling for array keys that might be sent as '0' when empty.
				$array_keys = array( 'widgets', 'nodes', 'items', 'roles' );
				if ( in_array( $clean_key, $array_keys, true ) && ( '0' === $value || 0 === $value ) ) {
					$sanitized[ $clean_key ] = array();
				} else {
					$sanitized[ $clean_key ] = sanitize_text_field( $value );
				}
			}
		}

		return $sanitized;
	}

	/**
	 * Get a specific setting value.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value if not set.
	 * @return mixed
	 */
	public function get_setting( $key, $default = null ) {
		$settings = $this->get_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}
}
