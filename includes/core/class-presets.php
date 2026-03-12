<?php
/**
 * Preset System.
 *
 * @package DashClean\Core
 */

namespace DashClean\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Presets
 *
 * Provides predefined configurations for various use cases.
 */
class Presets {

	/**
	 * Get all available presets (System + Custom).
	 *
	 * @return array
	 */
	public static function get_presets() {
		$system_presets = array(
			'client'      => array(
				'title'       => __( 'Client Mode', 'dashclean' ),
				'description' => __( 'Maximum cleaning for non-technical clients. Hides almost everything distracting.', 'dashclean' ),
				'settings'    => array(
					'dashboard_widgets_enabled' => 1,
					'dashboard_widgets'         => array( 'remove_all' => 1 ),
					'menu_cleaner_enabled'      => 1,
					'menu_cleaner'              => array( 'items' => array( 'tools.php', 'options-general.php' ) ),
					'notice_cleaner_enabled'    => 1,
					'notice_cleaner'            => array( 'hide_all' => 1, 'keep_errors' => 1 ),
					'admin_bar_enabled'         => 1,
					'admin_bar'                 => array( 'nodes' => array( 'wp-logo', 'comments' ) ),
				),
			),
			'developer'   => array(
				'title'       => __( 'Developer Mode', 'dashclean' ),
				'description' => __( 'Optimized for coders. Removes visual bloat but keeps critical tools.', 'dashclean' ),
				'settings'    => array(
					'performance_enabled' => 1,
					'performance'         => array( 'disable_emojis' => 1, 'remove_jquery_migrate' => 1 ),
					'notice_cleaner_enabled' => 1,
					'notice_cleaner'         => array( 'hide_ads' => 1, 'keep_errors' => 1 ),
				),
			),
		);

		$custom_presets = Settings::get_instance()->get_setting( 'custom_presets' );
		if ( ! is_array( $custom_presets ) ) {
			$custom_presets = array();
		}

		return array_merge( $system_presets, $custom_presets );
	}

	/**
	 * Save current settings or provided data as a custom preset.
	 *
	 * @param string $name        Preset name.
	 * @param array  $custom_data Optional settings data to save.
	 * @return bool
	 */
	public static function save_current_as_preset( $name, $custom_data = array() ) {
		$settings_handler = Settings::get_instance();
		$current_settings = ! empty( $custom_data ) ? $custom_data : $settings_handler->get_settings();
		
		// Remove system data from saved preset.
		unset( $current_settings['custom_presets'] );
		unset( $current_settings['active_preset'] );
		
		$custom_presets = $settings_handler->get_setting( 'custom_presets' );
		if ( ! is_array( $custom_presets ) ) {
			$custom_presets = array();
		}

		$preset_id = 'custom_' . sanitize_title( $name ) . '_' . time();
		$custom_presets[ $preset_id ] = array(
			'title'       => sanitize_text_field( $name ),
			'description' => sprintf(
				/* translators: %s: Date when the preset was created. */
				__( 'Custom preset created on %s', 'dashclean' ),
				date_i18n( get_option( 'date_format' ) )
			),
			'settings'    => $current_settings,
			'is_custom'   => true,
		);

		return $settings_handler->update_settings( array( 'custom_presets' => $custom_presets ) );
	}

	/**
	 * Delete a custom preset.
	 *
	 * @param string $preset_id
	 * @return bool
	 */
	public static function delete_preset( $preset_id ) {
		if ( strpos( $preset_id, 'custom_' ) !== 0 ) {
			return false;
		}

		$settings_handler = Settings::get_instance();
		$custom_presets = $settings_handler->get_setting( 'custom_presets' );

		if ( isset( $custom_presets[ $preset_id ] ) ) {
			unset( $custom_presets[ $preset_id ] );
			return $settings_handler->update_settings( array( 'custom_presets' => $custom_presets ) );
		}

		return false;
	}

	public static function apply_preset( $preset_id ) {
		$presets = self::get_presets();
		if ( ! isset( $presets[ $preset_id ] ) ) {
			return false;
		}

		$settings = Settings::get_instance();
		$new_settings = $presets[ $preset_id ]['settings'];
		
		// Ensure we don't lose the custom presets list.
		$new_settings['custom_presets'] = $settings->get_setting( 'custom_presets' );
		
		// Track the active preset and enable the system.
		$new_settings['active_preset']   = $preset_id;
		$new_settings['presets_enabled'] = 1;

		return $settings->update_settings( $new_settings, true ); // Use overwrite mode.
	}

	/**
	 * Reset all settings to default.
	 *
	 * @return bool
	 */
	public static function reset_to_defaults() {
		$settings = Settings::get_instance();
		// We keep custom presets but clear everything else to defaults.
		$custom_presets = $settings->get_setting( 'custom_presets' );
		
		$default_settings = array(
			'custom_presets'  => $custom_presets,
			'presets_enabled' => 0,
			'active_preset'   => '',
		);

		return $settings->update_settings( $default_settings, true ); // Use overwrite mode.
	}
}
