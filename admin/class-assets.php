<?php
/**
 * Asset Loader for DashClean Admin.
 *
 * @package DashClean\Admin
 */

namespace DashClean\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assets
 *
 * Handles enqueuing of CSS and JS assets for the admin area.
 */
class Assets {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue assets only on the plugin's settings page.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		// Only load assets on our plugin settings page.
		if ( 'toplevel_page_dashclean' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'dashclean-admin',
			DASHCLEAN_URL . 'assets/css/admin-style.css',
			array(),
			DASHCLEAN_VERSION
		);

		wp_enqueue_script(
			'dashclean-admin',
			DASHCLEAN_URL . 'assets/js/admin-script.js',
			array( 'jquery' ),
			DASHCLEAN_VERSION,
			true
		);

		// Localize script for secure AJAX passing if needed.
		wp_localize_script(
			'dashclean-admin',
			'dashcleanData',
			array(
				'nonce' => wp_create_nonce( 'dashclean_admin_nonce' ),
				'i18n'  => array(
					'loading_users_error'    => __( 'Error loading users.', 'dashclean' ),
					'network_error'          => __( 'Network error.', 'dashclean' ),
					'preset_name_required'   => __( 'Please enter a name for your preset.', 'dashclean' ),
					'saving'                 => __( 'Saving...', 'dashclean' ),
					'snapshot_state'         => __( 'Snapshot Current State', 'dashclean' ),
					'save_preset_error'      => __( 'Network error while saving preset.', 'dashclean' ),
				),
			)
		);
	}
}
