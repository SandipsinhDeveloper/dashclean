<?php
/**
 * Admin Bar Cleaner Module.
 *
 * @package DashClean\Modules
 */

namespace DashClean\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AdminBar_Cleaner
 *
 * Removes nodes from the WordPress Admin Bar.
 */
class AdminBar_Cleaner {

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
		add_action( 'admin_bar_menu', array( $this, 'cleanup_admin_bar' ), 999 );
	}

	/**
	 * Remove admin bar nodes.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar The admin bar object.
	 * @return void
	 */
	public function cleanup_admin_bar( $wp_admin_bar ) {
		$config = $this->settings->get_setting( 'admin_bar' );

		if ( ! $this->is_user_affected( $config ) ) {
			return;
		}

		$nodes_to_remove = ! empty( $config['nodes'] ) ? (array) $config['nodes'] : array();

		foreach ( $nodes_to_remove as $node_id ) {
			$wp_admin_bar->remove_node( $node_id );
		}
	}

	/**
	 * Check if the user should be affected.
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
