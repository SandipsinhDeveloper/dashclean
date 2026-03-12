<?php
/**
 * Dashboard Widget Cleaner Module.
 *
 * @package DashClean\Modules
 */

namespace DashClean\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Dashboard_Widgets
 *
 * Removes dashboard widgets based on settings.
 */
class Dashboard_Widgets {

	/**
	 * Settings instance.
	 *
	 * @var \DashClean\Core\Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings = \DashClean\Core\Settings::get_instance();
		add_action( 'wp_dashboard_setup', array( $this, 'cleanup_dashboard' ), 999 );
	}

	/**
	 * Remove dashboard widgets.
	 *
	 * @return void
	 */
	public function cleanup_dashboard() {
		global $wp_meta_boxes;

		$config = $this->settings->get_setting( 'dashboard_widgets' );
		
		if ( ! $this->is_user_affected( $config ) ) {
			return;
		}

		// Remove all widgets if enabled.
		if ( ! empty( $config['remove_all'] ) ) {
			$wp_meta_boxes['dashboard'] = array();
			return;
		}

		$widgets_to_remove = ! empty( $config['widgets'] ) ? (array) $config['widgets'] : array();

		// We iterate over the user's blacklist and safely yank each meta box from the global array.
		foreach ( $widgets_to_remove as $widget_id ) {
			remove_meta_box( $widget_id, 'dashboard', 'normal' );
			remove_meta_box( $widget_id, 'dashboard', 'side' );
			remove_meta_box( $widget_id, 'dashboard', 'advanced' );
		}
	}

	/**
	 * Check if the user should be affected by these settings.
	 *
	 * @param array $config Module configuration.
	 * @return bool
	 */
	private function is_user_affected( $config ) {
		$role_manager_enabled = $this->settings->get_setting( 'role_manager_enabled' );
		
		// If Role Manager is disabled, apply to everyone.
		if ( ! $role_manager_enabled ) {
			return true;
		}

		$role_config = $this->settings->get_setting( 'role_manager' );
		$affected_roles = ! empty( $role_config['roles'] ) ? (array) $role_config['roles'] : array();
		$affected_users = ! empty( $role_config['users'] ) ? (array) $role_config['users'] : array();

		// If Role Manager is ON but nothing is selected, default to affecting everyone.
		if ( empty( $affected_roles ) && empty( $affected_users ) ) {
			return true;
		}

		$user = wp_get_current_user();

		// Check specific User IDs first.
		if ( in_array( (string) $user->ID, $affected_users, true ) ) {
			return true;
		}

		// Check Roles.
		$user_roles = (array) $user->roles;
		foreach ( $user_roles as $role ) {
			if ( in_array( $role, $affected_roles, true ) ) {
				return true;
			}
		}

		return false;
	}
}
