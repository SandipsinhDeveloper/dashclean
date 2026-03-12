<?php
/**
 * Core Loader System.
 *
 * @package DashClean\Core
 */

namespace DashClean\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Loader
 *
 * Orchestrates the loading of all plugin components.
 */
class Loader {

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	public function run() {
		$this->load_dependencies();
		$this->init_components();
	}

	/**
	 * Load project dependencies.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		require_once DASHCLEAN_PATH . 'includes/core/class-settings.php';
		require_once DASHCLEAN_PATH . 'includes/core/class-presets.php';
		require_once DASHCLEAN_PATH . 'includes/core/class-data-handler.php';

		// Modules required on both frontend and backend.
		require_once DASHCLEAN_PATH . 'includes/modules/class-performance.php';
		require_once DASHCLEAN_PATH . 'includes/modules/class-adminbar-cleaner.php';
		
		if ( is_admin() ) {
			require_once DASHCLEAN_PATH . 'admin/class-admin-menu.php';
			require_once DASHCLEAN_PATH . 'admin/class-assets.php';

			// Load Admin-only Modules.
			require_once DASHCLEAN_PATH . 'includes/modules/class-dashboard-widgets.php';
			require_once DASHCLEAN_PATH . 'includes/modules/class-menu-cleaner.php';
			require_once DASHCLEAN_PATH . 'includes/modules/class-notice-cleaner.php';
			require_once DASHCLEAN_PATH . 'includes/modules/class-role-manager.php';
		}
	}

	/**
	 * Initialize core components and modules.
	 *
	 * @return void
	 */
	private function init_components() {
		// Initialize settings handler (Singleton).
		Settings::get_instance();

		// Performance module (Both front and back).
		new \DashClean\Modules\Performance();

		// Admin Bar cleaner (Both front and back).
		if ( Settings::get_instance()->get_setting( 'admin_bar_enabled' ) ) {
			new \DashClean\Modules\AdminBar_Cleaner();
		}

		// Admin-only components.
		if ( is_admin() ) {
			// Initialize Menu early for hooks.
			new \DashClean\Admin\Admin_Menu();
			new \DashClean\Admin\Assets();

			$this->init_modules();
		}
	}

	/**
	 * Initialize optional admin modules based on settings.
	 *
	 * @return void
	 */
	private function init_modules() {
		$settings = Settings::get_instance();

		if ( $settings->get_setting( 'dashboard_widgets_enabled' ) ) {
			new \DashClean\Modules\Dashboard_Widgets();
		}

		if ( $settings->get_setting( 'menu_cleaner_enabled' ) ) {
			new \DashClean\Modules\Menu_Cleaner();
		}

		if ( $settings->get_setting( 'notice_cleaner_enabled' ) ) {
			new \DashClean\Modules\Notice_Cleaner();
		}

		if ( $settings->get_setting( 'role_manager_enabled' ) ) {
			new \DashClean\Modules\Role_Manager();
		}
	}
}
