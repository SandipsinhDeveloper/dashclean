<?php
/**
 * Plugin Name:       DashClean
 * Plugin URI:        https://wordpress.org/plugins/dashclean
 * Description:       A professional WordPress Admin Optimization Plugin to clean up the dashboard and improve performance.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Sandipsinh Chauhan
 * Author URI:        https://profiles.wordpress.org/sandipdeveloper/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       dashclean
 * Domain Path:       /languages
 */

// Block direct access to this file. This is our first layer of security defense.
// If WordPress isn't loading this via standard execution flow, kill the process immediately.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define Plugin Constants.
 */
define( 'DASHCLEAN_VERSION', '1.0.1' );
define( 'DASHCLEAN_FILE', __FILE__ );
define( 'DASHCLEAN_PATH', plugin_dir_path( DASHCLEAN_FILE ) );
define( 'DASHCLEAN_URL', plugin_dir_url( DASHCLEAN_FILE ) );
define( 'DASHCLEAN_BASENAME', plugin_basename( DASHCLEAN_FILE ) );

/**
 * Plugin Activation Hook Handler.
 * Sets up the internal configuration the first time the plugin is turned on by the user.
 *
 * @return void
 */
function dashclean_activate() {
	// Let's ensure default settings are populated into the database.
	// We call Settings->get_settings() to trigger the default schema merge if it's empty.
	require_once DASHCLEAN_PATH . 'includes/core/class-settings.php';
	\DashClean\Core\Settings::get_instance()->get_settings();
	
	// Safely flush permalinks if the admin optimizations affect routing visually.
	flush_rewrite_rules();
}
// Attach our activation routine to WordPress core.
register_activation_hook( DASHCLEAN_FILE, 'dashclean_activate' );

/**
 * Deactivation Hook.
 *
 * @return void
 */
function dashclean_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( DASHCLEAN_FILE, 'dashclean_deactivate' );

/**
 * Initialize the plugin.
 *
 * @return void
 */
function dashclean_init() {
	// Load our primary components logic and routing map.
	// This separates core initialization from the raw plugin header file.
	require_once DASHCLEAN_PATH . 'includes/core/class-loader.php';

	// Boot up the main engine if the loader system is healthy.
	if ( class_exists( 'DashClean\Core\Loader' ) ) {
		$loader = new DashClean\Core\Loader();
		$loader->run();
	}
}
add_action( 'plugins_loaded', 'dashclean_init' );
