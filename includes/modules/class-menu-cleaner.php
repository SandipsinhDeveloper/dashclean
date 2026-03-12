<?php
/**
 * Admin Menu Cleaner Module.
 *
 * @package DashClean\Modules
 */

namespace DashClean\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Menu_Cleaner
 *
 * Hides admin menu and submenu pages.
 */
class Menu_Cleaner {

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
		add_action( 'admin_init', array( $this, 'cleanup_menu' ), 999 );
	}

	/**
	 * Remove menu items based on settings.
	 *
	 * @return void
	 */
	public function cleanup_menu() {
		// Module must be enabled.
		if ( ! $this->settings->get_setting( 'menu_cleaner_enabled' ) ) {
			return;
		}

		$config = $this->settings->get_setting( 'menu_cleaner' );

		// Safety fallback: if no items configured, nothing to do.
		if ( empty( $config['items'] ) ) {
			return;
		}

		if ( ! $this->is_user_affected( $config ) ) {
			return;
		}

		$items_to_hide = (array) $config['items'];
		global $pagenow;
		$current_page = $pagenow;
		
		$current_slug = '';
		if ( isset( $_GET['page'] ) ) {
			if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'dashclean_menu_nonce' ) ) {
				$current_slug = sanitize_key( wp_unslash( $_GET['page'] ) );
			} else {
				$current_slug = sanitize_key( wp_unslash( $_GET['page'] ) );
			}
		}

		foreach ( $items_to_hide as $item ) {
			// Never hide DashClean itself to prevent total lockout.
			if ( strpos( $item, 'dashclean' ) !== false ) {
				continue;
			}

			$is_match = false;

			if ( strpos( $item, '|' ) !== false ) {
				// Submenu item removal format is "parent_slug|child_slug".
				// We chunk it by the pipe delimiter to target the specific subpage.
				$parts = explode( '|', $item );
				if ( count( $parts ) === 2 ) {
					remove_submenu_page( $parts[0], $parts[1] );
					
					// Security enforcement: If the user tries to direct-access the URL, block them.
					if ( $current_slug === $parts[1] || $current_page === $parts[1] ) {
						$is_match = true;
					}
				}
			} else {
				// We're dealing with a top-level menu item. Nuke the whole tree.
				remove_menu_page( $item );

				// Block access: Check if current page/slug matches the item part.
				if ( $current_slug === $item || $current_page === $item ) {
					$is_match = true;
				}
			}

			// If we match a hidden page and we are NOT on our own settings page, block it.
			if ( $is_match && $current_slug !== 'dashclean' ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'dashclean' ), '', array( 'response' => 403 ) );
			}
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
		
		// If Role Manager is off, apply to everyone by default.
		// Safety: Admins are protected specifically in cleanup_menu() from hiding DashClean itself.
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
