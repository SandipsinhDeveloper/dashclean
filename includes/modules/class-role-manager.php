<?php
/**
 * Role Manager Module.
 *
 * @package DashClean\Modules
 */

namespace DashClean\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Role_Manager
 *
 * Orchestrates role-based experiences.
 */
class Role_Manager {

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
		// Role manager acts as an logic orchestrator.
		// It can be expanded to create custom roles or granular capabilities in future phases.
	}

	/**
	 * Get specific settings for a given role.
	 *
	 * @param string $role The role name.
	 * @return array
	 */
	public function get_role_settings( $role ) {
		$config = $this->settings->get_setting( 'role_manager' );
		if ( isset( $config['settings'][ $role ] ) ) {
			return (array) $config['settings'][ $role ];
		}
		return array();
	}
}
