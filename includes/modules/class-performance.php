<?php
/**
 * Performance Optimization Module.
 *
 * @package DashClean\Modules
 */

namespace DashClean\Modules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Performance
 *
 * Disables optional admin overhead and frontend bloat.
 */
class Performance {

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
		$this->init_hooks();
	}

	/**
	 * Initialize performance hooks based on settings.
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Fetch our performance config tree from the global settings array.
		$config = $this->settings->get_setting( 'performance', array() );

		// If the master switch is off, we bail early to save execution time.
		if ( ! $this->settings->get_setting( 'performance_enabled' ) ) {
			return;
		}

		// Disable Emojis.
		if ( ! empty( $config['disable_emojis'] ) ) {
			add_action( 'init', array( $this, 'disable_emojis' ) );
		}

		// Disable Dashicons for non-logged users.
		if ( ! empty( $config['disable_dashicons'] ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'disable_dashicons' ), 100 );
		}

		// Disable Block Editor styles.
		if ( ! empty( $config['disable_gutenberg_css'] ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'disable_gutenberg_css' ), 100 );
		}

		// Remove jQuery Migrate.
		if ( ! empty( $config['remove_jquery_migrate'] ) ) {
			add_action( 'wp_default_scripts', array( $this, 'remove_jquery_migrate' ) );
		}

		// Clean up WP Header.
		if ( ! empty( $config['cleanup_header'] ) ) {
			add_action( 'init', array( $this, 'cleanup_header' ) );
		}

		// Dynamic Asset Discovery (Frontend Only).
		if ( ! is_admin() ) {
			add_action( 'wp_footer', array( $this, 'discover_assets' ), 999 );
			
			// Dynamic Asset Dequeuing.
			if ( ! empty( $config['dequeue_assets'] ) ) {
				add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_dynamic_assets' ), 999 );
			}
		}
	}

	/**
	 * Completely disable emojis.
	 */
	public function disable_emojis() {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'tiny_mce_plugins', array( $this, 'disable_emojis_tinymce' ) );
		add_filter( 'wp_resource_hints', array( $this, 'disable_emojis_dns_prefetch' ), 10, 2 );
	}

	public function disable_emojis_tinymce( $plugins ) {
		return is_array( $plugins ) ? array_diff( $plugins, array( 'wpemoji' ) ) : array();
	}

	public function disable_emojis_dns_prefetch( $urls, $relation_type ) {
		if ( 'dns-prefetch' === $relation_type ) {
			$emoji_svg_url = apply_filters( 'dashclean_emoji_svg_url', 'https://s.w.org/images/core/emoji/14.0.0/svg/' );
			$urls = array_diff( $urls, array( $emoji_svg_url ) );
		}
		return $urls;
	}

	/**
	 * Disable dashicons on frontend for guests.
	 */
	public function disable_dashicons() {
		if ( ! is_user_logged_in() ) {
			wp_dequeue_style( 'dashicons' );
		}
	}

	/**
	 * Disable Gutenberg block library styles.
	 */
	public function disable_gutenberg_css() {
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'wc-block-style' );
	}

	/**
	 * Remove jQuery Migrate from the frontend.
	 */
	public function remove_jquery_migrate( $scripts ) {
		if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
			$script = $scripts->registered['jquery'];
			if ( $script->deps ) {
				$script->deps = array_diff( $script->deps, array( 'jquery-migrate' ) );
			}
		}
	}

	/**
	 * Clean up WP Header metadata.
	 */
	public function cleanup_header() {
		// Removing these generator tags and discovery links shrinks the DOM size slightly
		// and prevents automated scrapers from easily detecting the WP version and exposed endpoints.
		remove_action( 'wp_head', 'wp_generator' ); // WP Version
		remove_action( 'wp_head', 'rsd_link' ); // Really Simple Discovery
		remove_action( 'wp_head', 'wlwmanifest_link' ); // Windows Live Writer
		remove_action( 'wp_head', 'wp_shortlink_wp_head' ); // Shortlinks
		remove_action( 'wp_head', 'rest_output_link_wp_head' ); // REST API link
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' ); // oEmbed Discovery
	}

	/**
	 * Discover active assets and store in transient for the admin UI.
	 */
	public function discover_assets() {
		if ( is_admin() ) return;

		global $wp_scripts, $wp_styles;
		$assets = array();

		// De-register/De-queue logic might happen after this, but we want to see what's "there".
		if ( ! empty( $wp_scripts->queue ) ) {
			foreach ( $wp_scripts->queue as $handle ) {
				$assets[ $handle ] = array( 'type' => 'script' );
			}
		}

		if ( ! empty( $wp_styles->queue ) ) {
			foreach ( $wp_styles->queue as $handle ) {
				$assets[ $handle ] = array( 'type' => 'style' );
			}
		}

		if ( ! empty( $assets ) ) {
			set_transient( 'dashclean_discovered_assets', $assets, DAY_IN_SECONDS );
		}
	}

	/**
	 * Dequeue user-selected assets.
	 */
	public function dequeue_dynamic_assets() {
		$config = $this->settings->get_setting( 'performance', array() );
		$to_dequeue = ! empty( $config['dequeue_assets'] ) ? (array) $config['dequeue_assets'] : array();

		foreach ( $to_dequeue as $handle ) {
			// Check if it's a script or style.
			wp_dequeue_script( $handle );
			wp_dequeue_style( $handle );
		}
	}
}
