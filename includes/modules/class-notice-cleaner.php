<?php
/**
 * Admin Notice Cleaner Module.
 *
 * @package DashClean\Modules
 */

namespace DashClean\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Notice_Cleaner
 *
 * Hides unwanted admin notices.
 */
class Notice_Cleaner {

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
		
		add_action( 'admin_enqueue_scripts', array( $this, 'cleanup_notices' ), 10 );
	}

	/**
	 * Hide notices using CSS for non-destructive removal.
	 *
	 * @return void
	 */
	public function cleanup_notices() {
		$config = $this->settings->get_setting( 'notice_cleaner' );

		if ( ! $this->is_user_affected( $config ) ) {
			return;
		}

		$styles = '';

		if ( ! empty( $config['hide_all'] ) ) {
			$styles .= '.notice, .updated, .update-nag { display: none !important; } ';
			
			// Optional: Keep errors visible.
			if ( ! empty( $config['keep_errors'] ) ) {
				$styles .= '.notice.notice-error, .error { display: block !important; } ';
			}
		} elseif ( ! empty( $config['hide_ads'] ) ) {
			// Targeted ad/promo hiding. Typical markers for non-priority notices.
			$styles .= '.is-dismissible:not(.notice-error), .updated:not(.notice-error) { display: none !important; } ';
		}

		if ( ! empty( $styles ) ) {
			wp_register_style( 'dashclean-notice-cleaner', false, array(), DASHCLEAN_VERSION );
			wp_enqueue_style( 'dashclean-notice-cleaner' );
			wp_add_inline_style( 'dashclean-notice-cleaner', wp_strip_all_tags( $styles ) );
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
