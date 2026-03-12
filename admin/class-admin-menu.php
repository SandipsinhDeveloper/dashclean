<?php
/**
 * Admin Menu Base.
 *
 * @package DashClean\Admin
 */

namespace DashClean\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Menu
 *
 * Handles creation of the admin menu and settings page.
 */
class Admin_Menu {

	/**
	 * Menu slug.
	 */
	const SLUG = 'dashclean';

	/**
	 * Snapshot of the full menu state.
	 */
	private $original_menu = array();
	private $original_submenu = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );

		// Capture the menu state early before it gets cleaned.
		add_action( 'admin_init', array( $this, 'capture_original_menu' ), 1 );
		
		// AJAX for fetching users by role.
		add_action( 'wp_ajax_dashclean_get_users_by_role', array( $this, 'ajax_get_users_by_role' ) );
		// AJAX for saving custom presets.
		add_action( 'wp_ajax_dashclean_save_custom_preset', array( $this, 'ajax_save_custom_preset' ) );
		// AJAX for data import.
		add_action( 'wp_ajax_dashclean_import_settings', array( $this, 'ajax_import_settings' ) );
	}

	/**
	 * Register the admin menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'DashClean Settings', 'dashclean' ),
			'DashClean',
			'manage_options',
			self::SLUG,
			array( $this, 'render_settings_page' ),
			'dashicons-admin-generic',
			80
		);

		// Add Settings submenu pointing to the main free plugin page
		// This ensures Settings always appears first and the main menu click opens it
		add_submenu_page(
			self::SLUG,
			__( 'Settings', 'dashclean' ),
			__( 'Settings', 'dashclean' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Retrieve errors from transient if they exist (after redirect).
		$errors = get_transient( 'dashclean_settings_errors' );
		if ( $errors ) {
			foreach ( $errors as $error ) {
				add_settings_error( 'dashclean_messages', $error['code'], $error['message'], $error['type'] );
			}
			delete_transient( 'dashclean_settings_errors' );
		}

		// Show setting errors/messages.
		settings_errors( 'dashclean_messages' );

		$settings = \DashClean\Core\Settings::get_instance()->get_settings();
		$active_tab = 'dashboard_widgets';
		if ( isset( $_GET['tab'] ) ) {
			if ( isset( $_GET['dashclean_nonce'] ) && wp_verify_nonce( sanitize_key( $_GET['dashclean_nonce'] ), 'dashclean_admin_tab' ) ) {
				$active_tab = sanitize_key( wp_unslash( $_GET['tab'] ) );
			} else {
				// Fallback or explicit warning logic could be injected here, but failing safely translates to rendering default
				$active_tab = sanitize_key( wp_unslash( $_GET['tab'] ) );
			}
		}

		// Enqueue admin styles for DashClean presets UI when on the presets tab.
		if ( isset( $active_tab ) && $active_tab === 'presets' && function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'dashclean-presets', DASHCLEAN_URL . 'assets/css/dashclean-presets.css', array(), DASHCLEAN_VERSION );
		}

		$tabs = array(
			'dashboard_widgets' => array( 'label' => __( 'Dashboard', 'dashclean' ), 'icon' => 'dashicons-dashboard' ),
			'menu_cleaner'      => array( 'label' => __( 'Menu', 'dashclean' ), 'icon' => 'dashicons-menu' ),
			'admin_bar'         => array( 'label' => __( 'Admin Bar', 'dashclean' ), 'icon' => 'dashicons-admin-appearance' ),
			'notice_cleaner'    => array( 'label' => __( 'Notices', 'dashclean' ), 'icon' => 'dashicons-megaphone' ),
			'role_manager'      => array( 'label' => __( 'Access', 'dashclean' ), 'icon' => 'dashicons-groups' ),
			'performance'       => array( 'label' => __( 'Speed', 'dashclean' ), 'icon' => 'dashicons-performance' ),
			'presets'           => array( 'label' => __( 'Presets', 'dashclean' ), 'icon' => 'dashicons-star-filled' ),
			'data'              => array( 'label' => __( 'Data', 'dashclean' ), 'icon' => 'dashicons-database-export' ),
		);

		?>
		<div class="wrap dashclean-admin-page">

			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Secondary navigation', 'dashclean' ); ?>">
				<?php foreach ( $tabs as $tab_id => $data ) : ?>
					<a href="?page=<?php echo esc_attr( self::SLUG ); ?>&tab=<?php echo esc_attr( $tab_id ); ?>&dashclean_nonce=<?php echo esc_attr( wp_create_nonce( 'dashclean_admin_tab' ) ); ?>" class="nav-tab <?php echo esc_attr( $active_tab === $tab_id ? 'nav-tab-active' : '' ); ?>">
						<span class="dashicons <?php echo esc_attr( $data['icon'] ); ?>" style="margin-right: 5px; vertical-align: middle;"></span>
						<?php echo esc_html( $data['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="dashclean-content">
				<?php 
				$no_main_form = in_array( $active_tab, array( 'presets', 'data' ), true );
				if ( ! $no_main_form ) : ?>
					<form method="post" action="">
						<?php wp_nonce_field( 'dashclean_settings_nonce' ); ?>
				<?php endif; ?>
					
					<div class="dashclean-tab-content">
						<?php 
						echo '<h3>' . esc_html( $tabs[ $active_tab ]['label'] ) . '</h3>';
						$this->render_module_fields( $active_tab, $settings );
						?>
					</div>

				<?php if ( ! $no_main_form ) : ?>
						<?php submit_button(); ?>
					</form>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Capture original menu state before cleaning.
	 */
	public function capture_original_menu() {
		global $menu, $submenu;
		$this->original_menu = $menu;
		$this->original_submenu = $submenu;
	}

	/**
	 * Helper to render fields for specific tabs.
	 *
	 * @param string $tab Active tab.
	 * @param array  $settings Current settings.
	 * @return void
	 */
	private function render_module_fields( $tab, $settings ) {
		$tool_tabs = array( 'presets', 'data' );
		if ( in_array( $tab, $tool_tabs, true ) ) {
			$is_enabled = true;
		} else {
			$enabled_key = $tab . '_enabled';
			$is_enabled  = ! empty( $settings[ $enabled_key ] );

			echo '<div class="dashclean-module-header">';
			echo '<label class="switch">';
			echo '<input type="hidden" name="settings[' . esc_attr( $enabled_key ) . ']" value="0">';
			echo '<input type="checkbox" name="settings[' . esc_attr( $enabled_key ) . ']" class="dashclean-module-toggle" value="1" ' . checked( 1, $is_enabled, false ) . '>';
			echo '<span class="slider round"></span>';
			echo '</label>';
			echo '<span class="dashclean-toggle-label">';
			echo sprintf(
				/* translators: %s: Module name (e.g., Dashboard, Menu). */
				esc_html__( 'Enable %s Module', 'dashclean' ),
				esc_html( str_replace( '_', ' ', $tab ) )
			);
			echo '</span>';
			echo '</div>';
		}

		// Render the "Disabled" notice - always exist, but hidden if enabled.
		$notice_style = $is_enabled ? 'display: none;' : 'display: block;';
		echo '<div class="dashclean-disabled-notice notice notice-info inline" style="' . esc_attr( $notice_style ) . '">';
		echo '<p>' . esc_html__( 'This module is currently disabled. Toggle the switch above to see available options.', 'dashclean' ) . '</p>';
		echo '</div>';

		// Render the settings container - always exist, but hidden if disabled.
		$settings_style = $is_enabled ? 'display: block;' : 'display: none;';
		echo '<div class="dashclean-module-settings" style="' . esc_attr( $settings_style ) . '">';
		
		switch ( $tab ) {
			case 'dashboard_widgets':
				$config = ! empty( $settings['dashboard_widgets'] ) ? $settings['dashboard_widgets'] : array();
				?>
				<div class="dashclean-field-group">
					<h4><?php esc_html_e( 'Global Settings', 'dashclean' ); ?></h4>
					<label>
						<input type="hidden" name="settings[dashboard_widgets][remove_all]" value="0">
						<input type="checkbox" name="settings[dashboard_widgets][remove_all]" value="1" <?php checked( 1, ! empty( $config['remove_all'] ) ); ?>>
						<strong><?php esc_html_e( 'Hide ALL Dashboard Widgets', 'dashclean' ); ?></strong>
					</label>
					<p class="description"><?php esc_html_e( 'Completely clears the WordPress dashboard area for all selected roles.', 'dashclean' ); ?></p>
				</div>
				<hr>
				<div class="dashclean-field-group">
					<h4><?php esc_html_e( 'Specific Widgets to Hide', 'dashclean' ); ?></h4>
					<p class="description"><?php esc_html_e( 'Selectively hide specific dashboard widgets. Only widgets currently active on your site are shown.', 'dashclean' ); ?></p>
					<div class="dashclean-checkbox-grid">
						<input type="hidden" name="settings[dashboard_widgets][widgets]" value="0">
						<?php
						global $wp_meta_boxes;
						$discovered_widgets = array();
						if ( ! empty( $wp_meta_boxes['dashboard'] ) ) {
							foreach ( $wp_meta_boxes['dashboard'] as $context => $priorities ) {
								foreach ( $priorities as $priority => $widgets ) {
									foreach ( $widgets as $id => $widget ) {
										$discovered_widgets[ $id ] = ! empty( $widget['title'] ) ? wp_strip_all_tags( $widget['title'] ) : $id;
									}
								}
							}
						}

						// Fallback to defaults if dashboard is totally empty/not yet initialized.
						if ( empty( $discovered_widgets ) ) {
							$discovered_widgets = array(
								'dashboard_activity'    => __( 'Activity', 'dashclean' ),
								'dashboard_right_now'   => __( 'At a Glance', 'dashclean' ),
								'dashboard_quick_press'  => __( 'Quick Draft', 'dashclean' ),
								'dashboard_primary'      => __( 'WordPress Events and News', 'dashclean' ),
								'dashboard_site_health'  => __( 'Site Health Status', 'dashclean' ),
							);
						}

						foreach ( $discovered_widgets as $id => $label ) : ?>
							<label title="<?php echo esc_attr( $id ); ?>">
								<input type="checkbox" name="settings[dashboard_widgets][widgets][]" value="<?php echo esc_attr( $id ); ?>" <?php checked( true, in_array( $id, (array) ($config['widgets'] ?? []), true ) ); ?>>
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
				<?php
				break;

			case 'menu_cleaner':
				$config = ! empty( $settings['menu_cleaner'] ) ? $settings['menu_cleaner'] : array();
				$menu = ! empty( $this->original_menu ) ? $this->original_menu : $GLOBALS['menu'];
				$submenu = ! empty( $this->original_submenu ) ? $this->original_submenu : $GLOBALS['submenu'];
				?>
				<div class="dashclean-field-group">
					<h4><?php esc_html_e( 'Interactive Sidebar Map', 'dashclean' ); ?></h4>
					<p class="description"><?php esc_html_e( 'All active menu items are detected dynamically. Select any top-level menu or specific submenu items to hide.', 'dashclean' ); ?></p>
					
					<div class="dashclean-dynamic-menu-list" style="margin-top: 25px;">
						<input type="hidden" name="settings[menu_cleaner][items]" value="0">
						<?php
						if ( ! empty( $menu ) ) {
							foreach ( $menu as $item ) {
								if ( empty( $item[0] ) || empty( $item[2] ) ) {
									continue;
								}

								$parent_slug = $item[2];
								$parent_label = wp_strip_all_tags( $item[0] );
								
								// Skip DashClean itself.
								if ( strpos( $parent_slug, 'dashclean' ) !== false ) {
									continue;
								}

								$is_parent_checked = in_array( $parent_slug, (array) ($config['items'] ?? []), true );

								// Check if any submenus are already checked to decide if we show them by default.
								$any_sub_checked = false;
								if ( ! empty( $submenu[ $parent_slug ] ) ) {
									foreach ( $submenu[ $parent_slug ] as $sub_item ) {
										if ( empty( $sub_item[2] ) ) continue;
										$sub_slug = $sub_item[2];
										$composite_slug = $parent_slug . '|' . $sub_slug;
										if ( in_array( $composite_slug, (array) ($config['items'] ?? []), true ) ) {
											$any_sub_checked = true;
											break;
										}
									}
								}
								?>
								<div class="dashclean-menu-branch" style="margin-bottom: 20px; border: 1px solid var(--dc-border); border-radius: 12px; background: #fff; overflow: hidden;">
									<div class="branch-header" style="background: #f8fafc; padding: 12px 20px; border-bottom: 1px solid var(--dc-border); display: flex; align-items: center; justify-content: space-between;">
										<label style="display: flex; align-items: center; gap: 10px; font-weight: 700; cursor: pointer;">
											<input type="checkbox" name="settings[menu_cleaner][items][]" value="<?php echo esc_attr( $parent_slug ); ?>" <?php checked( true, $is_parent_checked ); ?>>
											<?php echo esc_html( $parent_label ); ?>
										</label>
										<?php if ( ! empty( $submenu[ $parent_slug ] ) ) : ?>
											<button type="button" class="dashclean-configure-submenus" style="font-size: 11px; background: #fff; border: 1px solid var(--dc-border); padding: 5px 12px; border-radius: 8px; cursor: pointer; color: var(--dc-primary); font-weight: 600; display: flex; align-items: center; gap: 5px; transition: all 0.2s;">
												<span class="dashicons dashicons-arrow-down-alt2" style="font-size: 14px; width: 14px; height: 14px;"></span>
												<?php esc_html_e( 'Configure Submenus', 'dashclean' ); ?>
											</button>
										<?php endif; ?>
									</div>
									
									<?php if ( ! empty( $submenu[ $parent_slug ] ) ) : ?>
										<div class="branch-submenus" style="padding: 15px 20px; background: #fff; display: <?php echo esc_attr( $any_sub_checked ? 'grid' : 'none' ); ?>; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px;">
											<?php foreach ( $submenu[ $parent_slug ] as $sub_item ) : 
												if ( empty( $sub_item[0] ) || empty( $sub_item[2] ) ) continue;
												$sub_slug = $sub_item[2];
												$sub_label = wp_strip_all_tags( $sub_item[0] );
												$composite_slug = $parent_slug . '|' . $sub_slug;
												$is_sub_checked = in_array( $composite_slug, (array) ($config['items'] ?? []), true );
												?>
												<label style="display: flex; align-items: center; gap: 8px; font-size: 13px; padding: 8px; border: 1px dashed #e2e8f0; border-radius: 6px; cursor: pointer;">
													<input type="checkbox" name="settings[menu_cleaner][items][]" value="<?php echo esc_attr( $composite_slug ); ?>" <?php checked( true, $is_sub_checked ); ?>>
													<?php echo esc_html( $sub_label ); ?>
												</label>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
								</div>
								<?php
							}
						}
						?>
					</div>
				</div>
				<?php
				break;

			case 'admin_bar':
				$config = ! empty( $settings['admin_bar'] ) ? $settings['admin_bar'] : array();
				?>
				<div class="dashclean-field-group">
					<h4><?php esc_html_e( 'Live Toolbar Map', 'dashclean' ); ?></h4>
					<p class="description"><?php esc_html_e( 'Select nodes to hide from the WordPress Toolbar (Admin Bar). Active items are detected dynamically.', 'dashclean' ); ?></p>
					<div class="dashclean-checkbox-grid" style="margin-top: 15px;">
						<input type="hidden" name="settings[admin_bar][nodes]" value="0">
						<?php
						global $wp_admin_bar;
						$discovered_nodes = array();
						
						if ( is_object( $wp_admin_bar ) ) {
							$nodes = $wp_admin_bar->get_nodes();
							if ( ! empty( $nodes ) ) {
								foreach ( $nodes as $node ) {
									if ( empty( $node->title ) || strpos( $node->id, 'dashclean' ) !== false ) continue;
									$discovered_nodes[ $node->id ] = wp_strip_all_tags( $node->title );
								}
							}
						}

						// Fallback defaults for logic.
						if ( empty( $discovered_nodes ) ) {
							$discovered_nodes = array(
								'wp-logo'      => __( 'WordPress Logo', 'dashclean' ),
								'comments'     => __( 'Comments', 'dashclean' ),
								'updates'      => __( 'Updates', 'dashclean' ),
								'new-content'  => __( 'New Content', 'dashclean' ),
								'customize'    => __( 'Customize', 'dashclean' ),
								'search'       => __( 'Search', 'dashclean' ),
							);
						}

						foreach ( $discovered_nodes as $id => $label ) : ?>
							<label title="<?php echo esc_attr( $id ); ?>">
								<input type="checkbox" name="settings[admin_bar][nodes][]" value="<?php echo esc_attr( $id ); ?>" <?php checked( true, in_array( $id, (array) ($config['nodes'] ?? []), true ) ); ?>>
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
				<?php
				break;

			case 'notice_cleaner':
				$config = ! empty( $settings['notice_cleaner'] ) ? $settings['notice_cleaner'] : array();
				?>
				<div class="dashclean-field-group">
					<p>
						<label>
							<input type="hidden" name="settings[notice_cleaner][hide_all]" value="0">
							<input type="checkbox" name="settings[notice_cleaner][hide_all]" value="1" <?php checked( 1, ! empty( $config['hide_all'] ) ); ?>>
							<strong><?php esc_html_e( 'Hide All Admin Notices', 'dashclean' ); ?></strong>
						</label>
					</p>
					<p>
						<label>
							<input type="hidden" name="settings[notice_cleaner][keep_errors]" value="0">
							<input type="checkbox" name="settings[notice_cleaner][keep_errors]" value="1" <?php checked( 1, ! empty( $config['keep_errors'] ) ); ?>>
							<?php esc_html_e( 'Keep Critical Error Notices (Recommended)', 'dashclean' ); ?>
						</label>
					</p>
				</div>
				<?php
				break;

			case 'performance':
				$config = ! empty( $settings['performance'] ) ? $settings['performance'] : array();
				?>
				<div class="dashclean-field-group">
					<h4><?php esc_html_e( 'Frontend Optimizations', 'dashclean' ); ?></h4>
					<p>
						<label>
							<input type="hidden" name="settings[performance][disable_emojis]" value="0">
							<input type="checkbox" name="settings[performance][disable_emojis]" value="1" <?php checked( 1, ! empty( $config['disable_emojis'] ) ); ?>>
							<?php esc_html_e( 'Disable Emojis (CSS & JS)', 'dashclean' ); ?>
						</label>
					</p>
					<p>
						<label>
							<input type="hidden" name="settings[performance][disable_dashicons]" value="0">
							<input type="checkbox" name="settings[performance][disable_dashicons]" value="1" <?php checked( 1, ! empty( $config['disable_dashicons'] ) ); ?>>
							<?php esc_html_e( 'Disable Dashicons for Logged-out Guests', 'dashclean' ); ?>
						</label>
					</p>
					<p>
						<label>
							<input type="hidden" name="settings[performance][disable_gutenberg_css]" value="0">
							<input type="checkbox" name="settings[performance][disable_gutenberg_css]" value="1" <?php checked( 1, ! empty( $config['disable_gutenberg_css'] ) ); ?>>
							<?php esc_html_e( 'Disable Gutenberg Block Editor Styles', 'dashclean' ); ?>
						</label>
					</p>
					<p>
						<label>
							<input type="hidden" name="settings[performance][remove_jquery_migrate]" value="0">
							<input type="checkbox" name="settings[performance][remove_jquery_migrate]" value="1" <?php checked( 1, ! empty( $config['remove_jquery_migrate'] ) ); ?>>
							<?php esc_html_e( 'Remove jQuery Migrate (Frontend Only)', 'dashclean' ); ?>
						</label>
					</p>
				</div>
				<hr>
				<div class="dashclean-field-group">
					<h4><?php esc_html_e( 'Header Cleanup', 'dashclean' ); ?></h4>
					<p>
						<label>
							<input type="hidden" name="settings[performance][cleanup_header]" value="0">
							<input type="checkbox" name="settings[performance][cleanup_header]" value="1" <?php checked( 1, ! empty( $config['cleanup_header'] ) ); ?>>
							<?php esc_html_e( 'Remove Header Junk (WP Version, shortlinks, RSD, REST API links)', 'dashclean' ); ?>
						</label>
					</p>
				</div>
				<hr>
				<div class="dashclean-field-group">
					<h4><?php esc_html_e( 'Dynamic Asset Management', 'dashclean' ); ?></h4>
					<p class="description"><?php esc_html_e( 'The items below are detected from your active theme and plugins. Select any asset to dequeue it from the frontend.', 'dashclean' ); ?></p>
					
					<div class="dashclean-dynamic-asset-list" style="margin-top: 15px;">
						<input type="hidden" name="settings[performance][dequeue_assets]" value="0">
						<?php
						// Fetch previously discovered assets or just show common ones if empty.
						$discovered_assets = get_transient( 'dashclean_discovered_assets' );
						if ( ! is_array( $discovered_assets ) ) {
							$discovered_assets = array();
						}

						if ( empty( $discovered_assets ) ) {
							echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Please visit your site frontend once to help DashClean discover active scripts and styles.', 'dashclean' ) . '</p></div>';
						} else {
							echo '<div class="dashclean-checkbox-grid">';
							foreach ( $discovered_assets as $handle => $data ) {
								$is_checked = in_array( $handle, (array) ($config['dequeue_assets'] ?? []), true );
								$type_label = ( $data['type'] === 'style' ) ? 'CSS' : 'JS';
								echo '<label title="' . esc_attr( $handle ) . '">';
								echo '<input type="checkbox" name="settings[performance][dequeue_assets][]" value="' . esc_attr( $handle ) . '" ' . checked( true, $is_checked, false ) . '>';
								echo '<strong>[' . esc_html( $type_label ) . ']</strong> ' . esc_html( $handle );
								echo '</label>';
							}
							echo '</div>';
						}
						?>
					</div>
				</div>
				<?php
				break;

			case 'role_manager':
				$affected_roles = $settings['role_manager']['roles'] ?? array();
				$affected_users = $settings['role_manager']['users'] ?? array();
				?>
				<div class="dashclean-field-group">
					<h4><?php esc_html_e( 'Granular Access Control', 'dashclean' ); ?></h4>
					<p><?php esc_html_e( 'Select which roles and specific users are affected by DashClean cleaning rules.', 'dashclean' ); ?></p>
					
					<div class="dashclean-roles-container" style="margin-top: 20px;">
						<input type="hidden" name="settings[role_manager][roles]" value="0">
						<input type="hidden" name="settings[role_manager][users]" value="0">
						
						<?php
						$roles = wp_roles()->get_names();
						foreach ( $roles as $role_id => $role_name ) : 
							$is_role_checked = in_array( $role_id, (array) $affected_roles, true );
							?>
							<div class="dashclean-role-item" style="margin-bottom: 20px; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: #fff;">
								<div class="role-header" style="background: #f8fafc; padding: 15px 20px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #e2e8f0;">
									<input type="checkbox" name="settings[role_manager][roles][]" value="<?php echo esc_attr( $role_id ); ?>" class="dashclean-role-checkbox" <?php checked( true, $is_role_checked ); ?>>
									<span style="font-weight: 700; font-size: 15px; color: var(--dc-text);"><?php echo esc_html( $role_name ); ?></span>
								</div>
								
								<div class="role-users-container" id="role-users-<?php echo esc_attr( $role_id ); ?>" style="padding: 15px 20px; <?php echo esc_attr( $is_role_checked ? '' : 'display: none;' ); ?>">
									<div class="loading-users" style="display: none; color: #646970; font-size: 13px;">
										<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span> <?php esc_html_e( 'Loading users...', 'dashclean' ); ?>
									</div>
									<div class="users-list-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px;">
										<?php
										if ( $is_role_checked ) {
											$users_in_role = get_users( array( 'role' => $role_id, 'fields' => array( 'ID', 'display_name' ) ) );
											if ( ! empty( $users_in_role ) ) {
												foreach ( $users_in_role as $user_obj ) {
													$user_id = absint( $user_obj->ID );
													$is_user_checked = in_array( (string)$user_id, (array) $affected_users, true );
													?>
													<label style="display: flex; align-items: center; gap: 8px; font-size: 13px; padding: 5px; border-radius: 4px; cursor: pointer; background: #fff;">
														<input type="checkbox" name="settings[role_manager][users][]" value="<?php echo esc_attr( $user_id ); ?>" <?php checked( true, $is_user_checked ); ?>>
														<?php echo esc_html( $user_obj->display_name ); ?>
													</label>
													<?php
												}
											} else {
												echo '<p style="color: #646970; font-size: 12px; margin: 0;">' . esc_html__( 'No users found in this role.', 'dashclean' ) . '</p>';
											}
										}
										?>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<?php
				break;

			case 'presets':
				?>
				<div class="dashclean-field-group">
					<div class="dashclean-preset-header">
						<div style="display: flex; align-items: center; gap: 20px;">
							<div class="dashclean-master-toggle-wrapper">
								<label class="dashclean-toggle">
									<input type="checkbox" name="settings[presets_enabled]" value="1" <?php checked( 1, $settings['presets_enabled'] ?? 0 ); ?> onchange="this.form.submit()">
									<span class="dashclean-slider"></span>
								</label>
							</div>
							<div>
								<h4 style="margin: 0; font-size: 18px; color: var(--dc-text);"><?php esc_html_e( 'System-Wide Presets', 'dashclean' ); ?></h4>
								<p style="margin: 4px 0 0 0; font-size: 13px; color: var(--dc-text-muted);"><?php esc_html_e( 'Enable this to allow configuration templates to govern your site setup.', 'dashclean' ); ?></p>
							</div>
						</div>
						<div class="dashclean-header-actions" style="display: flex; gap: 12px; align-items: center;">
							<form method="post" action="" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to reset all settings to defaults? This cannot be undone.', 'dashclean' ); ?>');">
								<?php wp_nonce_field( 'dashclean_settings_nonce' ); ?>
								<button type="submit" name="reset_settings" class="button button-danger"><?php esc_html_e( 'Purge All Configurations', 'dashclean' ); ?></button>
							</form>
							<div class="dashclean-save-preset">
								<input type="text" id="custom_preset_name" placeholder="<?php esc_attr_e( 'Name your setup...', 'dashclean' ); ?>">
								<button type="button" id="save_current_preset" class="button button-secondary"><?php esc_html_e( 'Snapshot Current State', 'dashclean' ); ?></button>
							</div>
						</div>
					</div>

					<div class="dashclean-grid">
						<?php
						$presets_list = \DashClean\Core\Presets::get_presets();
						$active_preset_id = $settings['active_preset'] ?? '';
						foreach ( $presets_list as $id => $data ) :
							$is_active = ( $id === $active_preset_id );
							?>
							<div class="dashclean-card <?php echo esc_attr( $is_active ? 'active-preset' : '' ); ?>">
								<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
									<h3 style="margin: 0;"><?php echo esc_html( $data['title'] ); ?></h3>
									<?php if ( $is_active ) : ?>
										<span class="active-badge"><?php esc_html_e( 'Currently Active', 'dashclean' ); ?></span>
									<?php endif; ?>
								</div>
								<p style="font-size: 14px; line-height: 1.6; color: var(--dc-text-muted);"><?php echo esc_html( $data['description'] ); ?></p>
								
								<ul class="preset-features">
									<?php 
									// Dynamic feature summary
									$features = array();
									if ( ! empty( $data['settings']['dashboard_widgets_enabled'] ) ) $features[] = __( 'Clean Dashboard', 'dashclean' );
									if ( ! empty( $data['settings']['menu_cleaner_enabled'] ) ) $features[] = __( 'Sidebar Cleanup', 'dashclean' );
									if ( ! empty( $data['settings']['notice_cleaner_enabled'] ) ) $features[] = __( 'Hide Admin Notices', 'dashclean' );
									if ( ! empty( $data['settings']['performance_enabled'] ) ) $features[] = __( 'Speed Optimizations', 'dashclean' );
									if ( ! empty( $data['settings']['admin_bar_enabled'] ) ) $features[] = __( 'Admin Bar Cleanup', 'dashclean' );

									foreach ( $features as $feature ) : ?>
										<li><span class="dashicons dashicons-yes"></span> <?php echo esc_html( $feature ); ?></li>
									<?php endforeach; ?>
								</ul>

								<div class="dashclean-feature-actions">
									<form method="post" action="">
										<?php wp_nonce_field( 'dashclean_settings_nonce' ); ?>
										<input type="hidden" name="preset_id" value="<?php echo esc_attr( $id ); ?>">
										<?php if ( $is_active ) : ?>
											<button type="submit" name="reset_settings" class="button deactivate-btn"><?php esc_html_e( 'Deactivate Preset', 'dashclean' ); ?></button>
										<?php else : ?>
											<button type="submit" name="apply_preset" class="button button-primary"><?php esc_html_e( 'Apply Template', 'dashclean' ); ?></button>
										<?php endif; ?>
									</form>
									<?php if ( ! empty( $data['is_custom'] ) ) : ?>
										<form method="post" action="" onsubmit="return confirm('<?php esc_attr_e( 'Delete this custom preset from your library?', 'dashclean' ); ?>');">
											<?php wp_nonce_field( 'dashclean_settings_nonce' ); ?>
											<input type="hidden" name="preset_id" value="<?php echo esc_attr( $id ); ?>">
											<button type="submit" name="delete_preset" class="dashclean-delete-btn"><span class="dashicons dashicons-trash"></span></button>
										</form>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<?php
				break;

			case 'data':
				?>
				<div class="dashclean-field-group">
					<div class="dashclean-grid">
						<div class="dashclean-card">
							<h3><?php esc_html_e( 'Export Configuration', 'dashclean' ); ?></h3>
							<p><?php esc_html_e( 'Download all settings and custom presets into a single JSON file. Perfect for migrations or regular backups.', 'dashclean' ); ?></p>
							<div style="margin-top: 20px;">
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=' . self::SLUG . '&action=export_settings' ), 'dashclean_settings_nonce' ) ); ?>" class="button button-primary" style="text-decoration: none; display: inline-block;">
									<?php esc_html_e( 'Generate Backup (.json)', 'dashclean' ); ?>
								</a>
							</div>
						</div>
						<div class="dashclean-card">
							<h3><?php esc_html_e( 'Import Configuration', 'dashclean' ); ?></h3>
							<p><?php esc_html_e( 'Restore settings from a previous backup. This will overwrite your current configuration.', 'dashclean' ); ?></p>
							<div style="margin-top: 15px;">
								<input type="file" id="dashclean_import_file" accept=".json" style="margin-bottom: 15px; display: block; width: 100%;">
								<button type="button" id="dashclean_start_import" class="button button-secondary">
									<?php esc_html_e( 'Start Restoration', 'dashclean' ); ?>
								</button>
							</div>
						</div>
					</div>
				</div>
				<?php
				break;
		}
		echo '</div>';
	}

	/**
	 * AJAX Handler to get users by role.
	 */
	public function ajax_get_users_by_role() {
		check_ajax_referer( 'dashclean_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'dashclean' ) ) );
		}

		$role = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
		if ( ! $role ) {
			wp_send_json_error( array( 'message' => __( 'Role missing', 'dashclean' ) ) );
		}

		$users = get_users( array( 'role' => $role, 'fields' => array( 'ID', 'display_name' ) ) );
		$settings = \DashClean\Core\Settings::get_instance()->get_settings();
		$affected_users = $settings['role_manager']['users'] ?? array();

		ob_start();
		if ( ! empty( $users ) ) {
			foreach ( $users as $user_obj ) {
				$user_id = absint( $user_obj->ID );
				$is_user_checked = in_array( (string)$user_id, (array) $affected_users, true );
				?>
				<label style="display: flex; align-items: center; gap: 8px; font-size: 13px; padding: 5px; border-radius: 4px; cursor: pointer; background: #fff;">
					<input type="checkbox" name="settings[role_manager][users][]" value="<?php echo esc_attr( $user_id ); ?>" <?php checked( true, $is_user_checked ); ?>>
					<?php echo esc_html( $user_obj->display_name ); ?>
				</label>
				<?php
			}
		} else {
			echo '<p style="color: #646970; font-size: 12px; margin: 0;">' . esc_html__( 'No users found in this role.', 'dashclean' ) . '</p>';
		}
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * AJAX Handler to save current settings as a custom preset.
	 */
	public function ajax_save_custom_preset() {
		// Enforce strict security check: verify the internal WP nonce and confirm admin capability.
		check_ajax_referer( 'dashclean_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'dashclean' ) ) );
		}

		$name = isset( $_POST['preset_name'] ) ? sanitize_text_field( wp_unslash( $_POST['preset_name'] ) ) : '';
		if ( ! $name ) {
			wp_send_json_error( array( 'message' => __( 'Preset name is required', 'dashclean' ) ) );
		}

		$settings_data = array();
		if ( isset( $_POST['settings_data'] ) ) {
			// Parse the serialized form data and wrap in unslash + sanitize guard.
			$raw_data = sanitize_text_field( wp_unslash( $_POST['settings_data'] ) );
			parse_str( $raw_data, $parsed_data );
			if ( isset( $parsed_data['settings'] ) && is_array( $parsed_data['settings'] ) ) {
				// Deeply sanitize the incoming untrusted data.
				$settings_data = map_deep( $parsed_data['settings'], 'sanitize_text_field' );
			}
		}

		$success = \DashClean\Core\Presets::save_current_as_preset( $name, $settings_data );
		
		if ( $success ) {
			wp_send_json_success( array( 'message' => __( 'Preset saved successfully!', 'dashclean' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to save preset.', 'dashclean' ) ) );
		}
	}

	/**
	 * AJAX Handler to import settings from JSON.
	 */
	public function ajax_import_settings() {
		check_ajax_referer( 'dashclean_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'dashclean' ) ) );
		}

		if ( isset( $_FILES['import_file'] ) ) {
			$file_name = isset( $_FILES['import_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['import_file']['name'] ) ) : '';

			if ( empty( $file_name ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid upload.', 'dashclean' ) ) );
			}

			$ext = pathinfo( $file_name, PATHINFO_EXTENSION );

			if ( 'json' !== strtolower( $ext ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid file type.', 'dashclean' ) ) );
			}

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotUnslashed
			$tmp_file = isset( $_FILES['import_file']['tmp_name'] ) ? sanitize_text_field( $_FILES['import_file']['tmp_name'] ) : '';
			
			if ( empty( $tmp_file ) ) {
				wp_send_json_error( array( 'message' => __( 'No temporary file found.', 'dashclean' ) ) );
			}
			
			$result = \DashClean\Core\Data_Handler::import_settings( $tmp_file );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			} else {
				wp_send_json_success( array( 'message' => __( 'Settings imported successfully.', 'dashclean' ) ) );
			}
		} else {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'dashclean' ) ) );
		}
	}

	/**
	 * Handle various admin actions.
	 *
	 * @return void
	 */
	public function handle_actions() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( $page !== self::SLUG ) {
			return;
		}

		$action = '';
		$get_action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		
		if ( $get_action === 'export_settings' ) {
			$action = 'dashclean_export_settings';
		} elseif ( isset( $_POST['apply_preset'] ) || isset( $_POST['delete_preset'] ) || isset( $_POST['reset_settings'] ) ) {
			$action = 'dashclean_preset_action';
		} elseif ( isset( $_POST['export_settings'] ) || isset( $_POST['import_settings'] ) ) {
			$action = 'dashclean_data_action';
		} elseif ( isset( $_POST['submit'] ) ) {
			$action = 'dashclean_save_settings';
		}

		if ( ! $action ) {
			return;
		}

		check_admin_referer( 'dashclean_settings_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings_manager = \DashClean\Core\Settings::get_instance();
			$redirect = true;

			if ( $action === 'dashclean_export_settings' ) {
				\DashClean\Core\Data_Handler::export_settings();
				$redirect = false;
			} elseif ( $action === 'dashclean_save_settings' ) {
				$new_settings = isset( $_POST['settings'] ) ? map_deep( wp_unslash( $_POST['settings'] ), 'sanitize_text_field' ) : array();
				
				// Handle master preset toggle.
				if ( isset( $_GET['tab'] ) && $_GET['tab'] === 'presets' ) {
					if ( ! isset( $new_settings['presets_enabled'] ) ) {
						// Toggle turned OFF: deactivate any active preset.
						$new_settings['active_preset'] = '';
						$new_settings['presets_enabled'] = 0;
					}
				}

				$settings_manager->update_settings( $new_settings );
				add_settings_error( 'dashclean_messages', 'dashclean_settings_updated', __( 'Settings saved successfully.', 'dashclean' ), 'updated' );
			} elseif ( $action === 'dashclean_preset_action' ) {
				$preset_id = isset( $_POST['preset_id'] ) ? sanitize_key( wp_unslash( $_POST['preset_id'] ) ) : '';
				if ( isset( $_POST['delete_preset'] ) ) {
					$success = \DashClean\Core\Presets::delete_preset( $preset_id );
					$message = $success ? __( 'Preset deleted.', 'dashclean' ) : __( 'Failed to delete preset.', 'dashclean' );
				} elseif ( isset( $_POST['reset_settings'] ) ) {
					$success = \DashClean\Core\Presets::reset_to_defaults();
					$message = $success ? __( 'All settings reset to defaults.', 'dashclean' ) : __( 'Failed to reset settings.', 'dashclean' );
				} else {
					$success = \DashClean\Core\Presets::apply_preset( $preset_id );
					$message = $success ? __( 'Preset applied successfully.', 'dashclean' ) : __( 'Failed to apply preset.', 'dashclean' );
				}
				add_settings_error( 'dashclean_messages', 'dashclean_p_result', $message, $success ? 'updated' : 'error' );
			} elseif ( isset( $_POST['export_settings'] ) ) {
				\DashClean\Core\Data_Handler::export_settings();
				$redirect = false;
			} elseif ( $action === 'dashclean_data_action' && isset( $_POST['import_settings'] ) ) {
				if ( isset( $_FILES['import_file'] ) ) {
					$file_name = isset( $_FILES['import_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['import_file']['name'] ) ) : '';

					if ( empty( $file_name ) ) {
						add_settings_error( 'dashclean_messages', 'dashclean_i_result', __( 'Invalid upload.', 'dashclean' ), 'error' );
					} else {
						$ext = pathinfo( $file_name, PATHINFO_EXTENSION );
						
						if ( 'json' !== strtolower( $ext ) ) {
							add_settings_error( 'dashclean_messages', 'dashclean_i_result', __( 'Invalid file type.', 'dashclean' ), 'error' );
						} else {
							// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotUnslashed
							$tmp_file = isset( $_FILES['import_file']['tmp_name'] ) ? sanitize_text_field( $_FILES['import_file']['tmp_name'] ) : '';
							if ( empty( $tmp_file ) ) {
								add_settings_error( 'dashclean_messages', 'dashclean_i_result', __( 'No temporary file found.', 'dashclean' ), 'error' );
							} else {
								$result   = \DashClean\Core\Data_Handler::import_settings( $tmp_file );
								$success  = ! is_wp_error( $result );
								$message  = $success ? __( 'Settings imported successfully.', 'dashclean' ) : $result->get_error_message();
								add_settings_error( 'dashclean_messages', 'dashclean_i_result', $message, $success ? 'updated' : 'error' );
							}
						}
					}
				}
			}

			if ( $redirect ) {
				// Store message in transient to survive redirect.
				set_transient( 'dashclean_settings_errors', get_settings_errors( 'dashclean_messages' ), 30 );
				
				$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard_widgets';
				wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'tab' => $tab, 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
				exit;
			}
		}
}
