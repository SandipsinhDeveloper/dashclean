<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package DashClean
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Cleanup plugin data.
 */
delete_option( 'dashclean_settings' );
delete_transient( 'dashclean_settings_errors' );
delete_transient( 'dashclean_discovered_assets' );

// If multisite, additional cleanup could be added here.
