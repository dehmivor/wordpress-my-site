<?php
/**
 * PA Admin Helper
 */

namespace PremiumAddons\Admin\Includes;

use PremiumAddons\Includes\Abilities\Bootstrap;
use PremiumAddons\Includes\Abilities\Connection_Log;
use PremiumAddons\Includes\Abilities\OAuth;
use PremiumAddons\Includes\Helper_Functions;
use PremiumAddons\Includes\Assets_Manager;
use Elementor\Modules\Usage\Module;
use Elementor\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Helper
 */
class Admin_Helper {

	/**
	 * Posts processed per usage-scan request.
	 *
	 * @since 4.11.95
	 */
	const SCAN_BATCH_SIZE = 20;

	/**
	 * Admin settings tabs
	 *
	 * @var tabs
	 */
	private static $tabs = null;

	/**
	 * Class instance
	 *
	 * @var instance
	 */
	private static $instance = null;

	/**
	 * Premium Addons Settings Page Slug
	 *
	 * @var page_slug
	 */
	public static $page_slug = 'premium-addons';

	/**
	 * Elements List
	 *
	 * @var elements_list
	 */
	public static $elements_list = null;

	/**
	 * Elements Keys
	 *
	 * @var elements_list
	 */
	public static $elements_keys = null;

	/**
	 * Enabled Elements
	 *
	 * @var enabled_elements
	 */
	public static $enabled_elements = null;

	/**
	 * Integrations Settings
	 *
	 * @var integrations_settings
	 */
	public static $integrations_settings = null;

	/**
	 * AI abilities settings.
	 *
	 * @var array|null
	 */
	public static $ai_abilities_settings = null;

	/**
	 * Elements Names
	 *
	 * @var elements_names
	 */
	public static $elements_names = null;

	/**
	 * Integrations List
	 *
	 * @var integrations_list
	 */
	public static $integrations_list = null;

	/**
	 * Constructor for the class
	 */
	public function __construct() {

		// Insert admin settings submenus.
		add_action( 'admin_menu', array( $this, 'add_menu_tabs' ), 100 );

		// Enqueue required admin scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		// Plugin Action Links.
		add_filter( 'plugin_action_links_' . PREMIUM_ADDONS_BASENAME, array( $this, 'insert_action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );

		// Register AJAX HOOKS.
		add_action( 'wp_ajax_pa_save_global_btn', array( $this, 'pa_save_global_btn' ) );
		add_action( 'wp_ajax_pa_save_elements_settings', array( $this, 'pa_save_elements_settings' ) );
		add_action( 'wp_ajax_pa_disable_elementor_mc_template', array( $this, 'pa_disable_elementor_mc_template' ) );
		add_action( 'wp_ajax_pa_save_additional_settings', array( $this, 'pa_save_additional_settings' ) );
		add_action( 'wp_ajax_pa_save_ai_abilities', array( $this, 'pa_save_ai_abilities' ) );
		add_action( 'wp_ajax_pa_mcp_news_seen', array( $this, 'pa_mcp_news_seen' ) );
		add_action( 'wp_ajax_pa_mcp_connection_check', array( $this, 'pa_mcp_connection_check' ) );
		add_action( 'wp_ajax_pa_mcp_revoke_connection', array( $this, 'pa_mcp_revoke_connection' ) );
		add_action( 'wp_ajax_pa_enable_oauth_connect', array( $this, 'pa_enable_oauth_connect' ) );
		add_action( 'wp_ajax_pa_extend_oauth_window', array( $this, 'pa_extend_oauth_window' ) );
		add_action( 'wp_ajax_pa_scan_widgets_usage', array( $this, 'pa_scan_widgets_usage' ) );
		add_action( 'wp_ajax_pa_disable_unused_widgets', array( $this, 'pa_disable_unused_widgets' ) );
		add_action( 'wp_ajax_pa_get_menu_item_settings', array( $this, 'pa_get_menu_item_settings' ) );
		add_action( 'wp_ajax_pa_save_menu_item_settings', array( $this, 'pa_save_menu_item_settings' ) );
		add_action( 'wp_ajax_pa_save_mega_item_content', array( $this, 'pa_save_mega_item_content' ) );

		// Used to empty dynamic assets dir on plugin update to make sure new assets are generated.
		add_action( 'upgrader_process_complete', array( $this, 'pa_handle_upgrade' ), 10, 2 );

		// Register Deactivation hooks.
		register_deactivation_hook( PREMIUM_ADDONS_FILE, array( $this, 'clear_dynamic_assets_dir' ) );

		// Register AJAX Hooks for clearing saved site cursor.
		add_action( 'wp_ajax_pa_clear_site_cursor_settings', array( $this, 'clear_site_cursor_settings' ) );

		// Register AJAX Hooks for Newsletter.
		add_action( 'wp_ajax_subscribe_newsletter', array( $this, 'subscribe_newsletter' ) );

		// Add action for PA dashboard tab header.
		add_action( 'pa_before_render_admin_tabs', array( $this, 'render_dashboard_header' ) );

		// Register Rollback hooks.
		add_action( 'admin_post_premium_addons_rollback', array( $this, 'run_pa_rollback' ) );

		if ( is_admin() ) {

			Admin_Notices::get_instance();

			// Beta tester.
			// Not currently needed.
			// Beta_Testers::get_instance();.

			// PA Duplicator.
			if ( self::check_duplicator() ) {
				Duplicator::get_instance();
			}

			if ( self::check_user_can( 'install_plugins' ) ) {
				Feedback::get_instance();
			}
		}
	}

	/**
	 * Checks user credentials for specific action
	 *
	 * @since 2.6.8
	 *
	 * @param string $action action.
	 * @param mixed  ...$args optional further parameters, typically an object ID (for meta capabilities like edit_post).
	 *
	 * @return boolean
	 */
	public static function check_user_can( $action, ...$args ) {
		return current_user_can( $action, ...$args );
	}

	/**
	 * Get Elements List
	 *
	 * Get a list of all the elements available in the plugin
	 *
	 * @since 3.20.9
	 * @access private
	 *
	 * @return array elements_list
	 */
	public static function get_elements_list() {

		if ( null === self::$elements_list ) {

			self::$elements_list = require_once PREMIUM_ADDONS_PATH . 'admin/includes/elements.php';

		}

		return self::$elements_list;
	}

	/**
	 * Get Elements Keys
	 *
	 * Get a list of all the keys available in the plugin
	 *
	 * @since 4.10.54
	 * @access private
	 *
	 * @return array elements_keys
	 */
	public static function get_elements_keys() {

		if ( null === self::$elements_keys ) {

			self::$elements_keys = require_once PREMIUM_ADDONS_PATH . 'admin/includes/keys.php';

		}

		return self::$elements_keys;
	}

	/**
	 * Get Integrations List
	 *
	 * Get a list of all the integrations available in the plugin
	 *
	 * @since 3.20.9
	 * @access private
	 *
	 * @return array integrations_list
	 */
	private static function get_integrations_list() {

		if ( null === self::$integrations_list ) {

			self::$integrations_list = array(
				'premium-map-api',
				'premium-youtube-api',
				'premium-map-disable-api',
				'premium-map-cluster',
				'premium-wp-optimize-exclude',
				'premium-map-locale',
				'is-beta-tester',
			);

		}

		return self::$integrations_list;
	}

	/**
	 * Admin Enqueue Scripts
	 *
	 * Enqueue the required assets on our admin pages
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function admin_enqueue_scripts( $hook ) {

		$enabled_elements = self::get_enabled_elements();
		$action           = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		wp_enqueue_style(
			'pa_admin_icon',
			PREMIUM_ADDONS_URL . 'admin/assets/fonts/style.css',
			array(),
			PREMIUM_ADDONS_VERSION,
			'all'
		);

		wp_enqueue_style(
			'pa-notice',
			PREMIUM_ADDONS_URL . 'admin/assets/css/notice.css',
			array(),
			PREMIUM_ADDONS_VERSION,
			'all'
		);

		// admin.css only styles the PA pages, the nav-menu mega menu modal and the
		// deactivation popup on plugins.php. Everywhere else it is dead weight.
		$is_pa_admin_screen = false !== strpos( $hook, 'premium-addons' )
			|| 'nav-menus.php' === $hook
			|| 'plugins.php' === $hook;

		if ( $is_pa_admin_screen ) {
			wp_enqueue_style(
				'pa-admin',
				PREMIUM_ADDONS_URL . 'admin/assets/css/admin.css',
				array(),
				PREMIUM_ADDONS_VERSION,
				'all'
			);
		}

		if ( false !== strpos( $hook, 'premium-addons' ) ) {

			wp_enqueue_style(
				'pa-sweetalert-style',
				PREMIUM_ADDONS_URL . 'admin/assets/js/sweetalert2/sweetalert2.min.css',
				array(),
				PREMIUM_ADDONS_VERSION,
				'all'
			);

			wp_enqueue_script(
				'pa-admin',
				PREMIUM_ADDONS_URL . 'admin/assets/js/admin.js',
				array( 'jquery' ),
				PREMIUM_ADDONS_VERSION,
				true
			);

			wp_enqueue_script(
				'pa-sweetalert-core',
				PREMIUM_ADDONS_URL . 'admin/assets/js/sweetalert2/core.js',
				array( 'jquery' ),
				PREMIUM_ADDONS_VERSION,
				true
			);

			wp_enqueue_script(
				'pa-sweetalert',
				PREMIUM_ADDONS_URL . 'admin/assets/js/sweetalert2/sweetalert2.min.js',
				array( 'jquery', 'pa-sweetalert-core' ),
				PREMIUM_ADDONS_VERSION,
				true
			);

			$theme_slug = Helper_Functions::get_installed_theme();

			$is_second_run = get_option( 'pa_complete_wizard' ) ? false : true;

			$localized_data = array(
				'settings'               => array(
					'ajaxurl'           => admin_url( 'admin-ajax.php' ),
					'nonce'             => wp_create_nonce( 'pa-settings-tab' ),
					'unused_nonce'      => wp_create_nonce( 'pa-disable-unused' ),
					'generate_nonce'    => wp_create_nonce( 'pa-generate-nonce' ),
					'site_cursor_nonce' => wp_create_nonce( 'pa-site-cursor-nonce' ),
					'isSecondRun'       => $is_second_run,
					'theme'             => $theme_slug,
					'i18n'              => array(
						'successMsg'            => __( 'Your submission was successful.', 'premium-addons-for-elementor' ),
						'failMsg'               => __( 'Your submission failed because of an error', 'premium-addons-for-elementor' ),
						'aiAbilitiesSaving'     => __( 'Saving AI ability settings…', 'premium-addons-for-elementor' ),
						'aiAbilitiesSaveFailed' => __( 'AI ability settings could not be saved.', 'premium-addons-for-elementor' ),
						'oauthEnabling'         => __( 'Enabling OAuth…', 'premium-addons-for-elementor' ),
						'oauthRequestFailed'    => __( 'The request failed. Please try again.', 'premium-addons-for-elementor' ),
						'checkRunning'          => __( 'Checking…', 'premium-addons-for-elementor' ),
						'revokeConfirm'         => __( 'Revoke this connection? The client stops working right away.', 'premium-addons-for-elementor' ),
						'revoking'              => __( 'Revoking…', 'premium-addons-for-elementor' ),
						'revokeFailed'          => __( 'The connection could not be revoked. Please try again.', 'premium-addons-for-elementor' ),
						'unusedButton'          => __( 'Scan & Disable Unused Widgets', 'premium-addons-for-elementor' ),
						'unusedScanning'        => __( 'Scanning your site…', 'premium-addons-for-elementor' ),
						'unusedFailed'          => __( 'Scan Failed', 'premium-addons-for-elementor' ),
						'unusedFailedText'      => __( 'The scan could not be completed. Please try again.', 'premium-addons-for-elementor' ),
						/* translators: %d: number of widgets that were disabled */
						'unusedDisabledTitle'   => __( '%d Widgets Disabled!', 'premium-addons-for-elementor' ),
						'unusedDisabledText'    => __( 'Your Elementor editor should load faster now.', 'premium-addons-for-elementor' ),
						'unusedNothingTitle'    => __( 'Nothing to Disable', 'premium-addons-for-elementor' ),
						'unusedAlreadyOffText'  => __( 'Every unused widget was already switched off.', 'premium-addons-for-elementor' ),
						'unusedAllInUseText'    => __( 'All of your Premium Addons widgets are in use.', 'premium-addons-for-elementor' ),
					),
				),
				'premiumRollBackConfirm' => array(
					'home_url' => home_url(),
					'i18n'     => array(
						'rollback_to_previous_version' => __( 'Rollback to Previous Version', 'premium-addons-for-elementor' ),
						/* translators: %s: PA stable version */
						'rollback_confirm'             => sprintf( __( 'Are you sure you want to reinstall version %s?', 'premium-addons-for-elementor' ), PREMIUM_ADDONS_STABLE_VERSION ),
						'yes'                          => __( 'Continue', 'premium-addons-for-elementor' ),
						'cancel'                       => __( 'Cancel', 'premium-addons-for-elementor' ),
					),
				),
			);

			// Only add savedFeatures if it's the second run.
			if ( $is_second_run ) {
				$localized_data['settings']['savedFeatures'] = get_option( 'pa_saved_features', array() );
			}

			// Add PAPRO Rollback Confirm message if PAPRO installed.
			if ( Helper_Functions::check_papro_version() ) {
				/* translators: %s: PA stable version */
				$localized_data['premiumRollBackConfirm']['i18n']['papro_rollback_confirm'] = sprintf( __( 'Are you sure you want to reinstall version %s?', 'premium-addons-for-elementor' ), PREMIUM_ADDONS_STABLE_VERSION );
			}

			wp_localize_script( 'pa-admin', 'premiumAddonsSettings', $localized_data );

		}

		if ( false !== strpos( $action, 'page=pa-setup-wizard' ) ) {
			wp_enqueue_style(
				'pa-wizard',
				PREMIUM_ADDONS_URL . 'admin/assets/css/setup-wizard.css',
				array(),
				PREMIUM_ADDONS_VERSION,
				'all'
			);

			wp_enqueue_script(
				'pa-wizard',
				PREMIUM_ADDONS_URL . 'admin/assets/js/setup-wizard.js',
				array( 'jquery' ),
				PREMIUM_ADDONS_VERSION,
				true
			);

			wp_localize_script(
				'pa-wizard',
				'paWizardSettings',
				array(
					'ajaxurl'       => admin_url( 'admin-ajax.php' ),
					'nonce'         => wp_create_nonce( 'pa-wizard-nonce' ),
					'exitWizardURL' => admin_url( 'plugins.php' ),
					'isSecondRun'   => $is_second_run,
					'dashboardURL'  => admin_url( 'admin.php' ) . '?page=premium-addons#tab=elements',
					'newPageURL'    => Plugin::$instance->documents->get_create_new_post_url(),
				),
			);

		}

		if ( 'nav-menus.php' === $hook && $enabled_elements['premium-nav-menu'] ) {

			wp_enqueue_style(
				'pa-font-awesome',
				ELEMENTOR_ASSETS_URL . 'lib/font-awesome/css/font-awesome.min.css',
				array(),
				'4.7.0',
				'all'
			);

			wp_enqueue_style( 'wp-color-picker' );

			wp_enqueue_style(
				'jquery-fonticonpicker',
				PREMIUM_ADDONS_URL . 'admin/assets/css/jquery-fonticonpicker.css',
				array(),
				PREMIUM_ADDONS_VERSION,
				'all'
			);

			wp_enqueue_script(
				'jquery-fonticonpicker',
				PREMIUM_ADDONS_URL . 'admin/assets/js/jquery-fonticonpicker.js',
				array( 'jquery' ),
				PREMIUM_ADDONS_VERSION,
				true
			);

			wp_enqueue_script(
				'pa-icon-list',
				PREMIUM_ADDONS_URL . 'admin/assets/js/premium-icons-list.js',
				array(),
				PREMIUM_ADDONS_VERSION,
				true
			);

			wp_enqueue_script(
				'mega-content-handler',
				PREMIUM_ADDONS_URL . 'admin/assets/js/mega-content-handler.js',
				array( 'jquery' ),
				PREMIUM_ADDONS_VERSION,
				true
			);

			wp_enqueue_script(
				'menu-editor',
				PREMIUM_ADDONS_URL . 'admin/assets/js/menu-editor.js',
				array( 'jquery', 'wp-color-picker' ),
				PREMIUM_ADDONS_VERSION,
				true
			);

			$pa_menu_localized = array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'pa-menu-nonce' ),
			);

			$menu_content_localized = array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'pa-live-editor' ),
			);

			wp_localize_script( 'mega-content-handler', 'paMegaContent', $menu_content_localized );
			wp_localize_script( 'menu-editor', 'paMenuSettings', $pa_menu_localized );

			// menu screen popups.
			include_once PREMIUM_ADDONS_PATH . 'admin/includes/templates/nav-menu-settings.php';
		}
	}

	/**
	 * Get PA menu item settings.
	 * Retrieve menu items settings from postmeta table.
	 *
	 * @access public
	 * @since 4.9.4
	 */
	public function pa_get_menu_item_settings() {

		check_ajax_referer( 'pa-menu-nonce', 'security' );

		if ( ! self::check_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'User is not authorized!' );
		}

		if ( ! isset( $_POST['item_id'] ) ) {
			wp_send_json_error( 'Settings are not set!' );
		}

		$item_id       = sanitize_text_field( wp_unslash( $_POST['item_id'] ) );
		$item_settings = json_decode( get_post_meta( $item_id, 'pa_megamenu_item_meta', true ) );

		wp_send_json_success( $item_settings );
	}

	/**
	 * Save PA menu item settings.
	 * Save/Update menu items settings in postmeta table.
	 *
	 * @access public
	 * @since 4.9.4
	 */
	public function pa_save_menu_item_settings() {

		check_ajax_referer( 'pa-menu-nonce', 'security' );

		if ( ! self::check_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'User is not authorized!' );
		}

		if ( ! isset( $_POST['settings'] ) ) {
			wp_send_json_error( 'Settings are not set!' );
		}

		$settings = array_map(
			function ( $setting ) {
				return htmlspecialchars( $setting, ENT_QUOTES );
			},
			wp_unslash( $_POST['settings'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		);

		update_post_meta( $settings['item_id'], 'pa_megamenu_item_meta', wp_json_encode( $settings, JSON_UNESCAPED_UNICODE ) );

		wp_send_json_success( $settings );
	}

	/**
	 * Save Pa Mega Item Content.
	 * Saves mega content's id in postmeta table.
	 *
	 * @access public
	 * @since 4.9.4
	 */
	public function pa_save_mega_item_content() {

		check_ajax_referer( 'pa-live-editor', 'security' );

		if ( ! self::check_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error( 'Insufficient user permission' );
		}

		if ( ! isset( $_POST['template_id'] ) ) {
			wp_send_json_error( 'template id is not set!' );
		}

		if ( ! isset( $_POST['menu_item_id'] ) ) {
			wp_send_json_error( 'item id is not set!' );
		}

		$item_id = sanitize_text_field( wp_unslash( $_POST['menu_item_id'] ) );
		$temp_id = sanitize_text_field( wp_unslash( $_POST['template_id'] ) );

		update_post_meta( $item_id, 'pa_mega_content_temp', $temp_id );

		wp_send_json_success( 'Item Mega Content Saved' );
	}

	/**
	 * Insert action links.
	 *
	 * Adds action links to the plugin list table
	 *
	 * Fired by `plugin_action_links` filter.
	 *
	 * @param array $links plugin action links.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function insert_action_links( $links ) {

		// Check if Premium Addons PRO version is active.
		$is_papro_active = Helper_Functions::check_papro_version();

		// Create the Settings link that points to the plugin's settings page.
		$settings_link = sprintf( '<a href="%1$s">%2$s</a>', admin_url( 'admin.php?page=' . self::$page_slug . '#tab=elements' ), __( 'Settings', 'premium-addons-for-elementor' ) );

		// Initialize the new links array with the Settings link.
		$new_links = array( $settings_link );

		// If PRO version is not active, add a promotional link to upgrade.
		if ( ! $is_papro_active ) {

			// Get the campaign link for the deal.
			$link = Helper_Functions::get_campaign_link( 'https://premiumaddons.com/pro/#get-pa-pro', 'plugins-page', 'wp-dash', 'get-pro' );

			// Create a styled promotional link encouraging users to save money by upgrading.
			$pro_link = sprintf( '<a href="%s" target="_blank" style="color: #FF6000; font-weight: bold;">%s</a>', $link, __( 'Save 30%', 'premium-addons-for-elementor' ) );

			// Add the promotional link to the array.
			$new_links[] = $pro_link;
		}

		// Merge the original links with our new custom links.
		$new_links = array_merge( $links, $new_links );

		// Return the modified links array to display on the plugins page.
		return $new_links;
	}

	/**
	 * Plugin row meta.
	 *
	 * Extends plugin row meta links
	 *
	 * Fired by `plugin_row_meta` filter.
	 *
	 * @since 3.8.4
	 * @access public
	 *
	 * @param array  $meta array of the plugin's metadata.
	 * @param string $file path to the plugin file.
	 *
	 *  @return array An array of plugin row meta links.
	 */
	public function plugin_row_meta( $meta, $file ) {

		// Check if row meta should be hidden based on white label settings.
		if ( Helper_Functions::is_hide_row_meta() ) {
			return $meta;
		}

		// Only add custom meta links for Premium Addons plugin.
		if ( PREMIUM_ADDONS_BASENAME === $file ) {

			// Generate the support link with campaign tracking parameters.
			$link = Helper_Functions::get_campaign_link( 'https://premiumaddons.com/support', 'plugins-page', 'wp-dash', 'get-support' );

			// Create an array of additional meta links to display.
			$row_meta = array(
				// Add "Docs & FAQs" link pointing to support documentation.
				'docs'   => '<a href="' . esc_attr( $link ) . '" aria-label="' . esc_attr( __( 'View Premium Addons for Elementor Documentation', 'premium-addons-for-elementor' ) ) . '" target="_blank">' . __( 'Docs & FAQs', 'premium-addons-for-elementor' ) . '</a>',
				// Add "Video Tutorials" link pointing to YouTube channel.
				'videos' => '<a href="https://www.youtube.com/leap13" aria-label="' . esc_attr( __( 'View Premium Addons Video Tutorials', 'premium-addons-for-elementor' ) ) . '" target="_blank">' . __( 'Video Tutorials', 'premium-addons-for-elementor' ) . '</a>',
				// Add "Rate the plugin" link pointing to WordPress.org reviews page.
				'rate'   => '<a href="https://wordpress.org/support/plugin/premium-addons-for-elementor/reviews/#new-post" aria-label="' . esc_attr( __( 'Rate plugin', 'premium-addons-for-elementor' ) ) . '" target="_blank">' . __( 'Rate the plugin ★★★★★', 'premium-addons-for-elementor' ) . '</a>',
			);

			// Merge the custom links with existing meta links.
			$meta = array_merge( $meta, $row_meta );
		}

		// Return the modified meta array.
		return $meta;
	}

	/**
	 * Set Admin Tabs
	 *
	 * @access private
	 * @since 3.20.8
	 */
	private function set_admin_tabs() {

		$slug = self::$page_slug;

		self::$tabs = array(
			'general'         => array(
				'id'       => 'general',
				'slug'     => $slug . '#tab=general',
				'title'    => __( 'General', 'premium-addons-for-elementor' ),
				'href'     => '#tab=general',
				'template' => PREMIUM_ADDONS_PATH . 'admin/includes/templates/general',
			),
			'elements'        => array(
				'id'       => 'elements',
				'slug'     => $slug . '#tab=elements',
				'title'    => __( 'Widgets & Add-ons', 'premium-addons-for-elementor' ),
				'href'     => '#tab=elements',
				'template' => PREMIUM_ADDONS_PATH . 'admin/includes/templates/modules-settings',
			),
			'addons'          => array(
				'id'       => 'addons',
				'slug'     => $slug . '#tab=addons',
				'title'    => __( 'Features', 'premium-addons-for-elementor' ),
				'href'     => '#tab=addons',
				'template' => PREMIUM_ADDONS_PATH . 'admin/includes/templates/addons',
			),
			'integrations'    => array(
				'id'       => 'integrations',
				'slug'     => $slug . '#tab=integrations',
				'title'    => __( 'Integrations', 'premium-addons-for-elementor' ),
				'href'     => '#tab=integrations',
				'template' => PREMIUM_ADDONS_PATH . 'admin/includes/templates/integrations',
			),
			'version-control' => array(
				'id'       => 'vcontrol',
				'slug'     => $slug . '#tab=vcontrol',
				'title'    => __( 'Version Control', 'premium-addons-for-elementor' ),
				'href'     => '#tab=vcontrol',
				'template' => PREMIUM_ADDONS_PATH . 'admin/includes/templates/version-control',
			),
			'white-label'     => array(
				'id'       => 'white-label',
				'slug'     => $slug . '#tab=white-label',
				'title'    => __( 'White Labeling', 'premium-addons-for-elementor' ),
				'href'     => '#tab=white-label',
				'template' => PREMIUM_ADDONS_PATH . 'admin/includes/templates/white-label',
			),
			'info'            => array(
				'id'       => 'system-info',
				'slug'     => $slug . '#tab=system-info',
				'title'    => __( 'System Info', 'premium-addons-for-elementor' ),
				'href'     => '#tab=system-info',
				'template' => PREMIUM_ADDONS_PATH . 'admin/includes/templates/info',
			),
		);

		// AI Abilities dashboard tab. Always registered — it owns the feature
		// switcher, so it has to be reachable while the feature is off. Inserted right
		// after the Features tab so it sits with the other feature settings instead of
		// after System Info / License.
		$position = array_search( 'addons', array_keys( self::$tabs ), true ) + 1;

		self::$tabs = array_merge(
			array_slice( self::$tabs, 0, $position, true ),
			array(
				'ai-abilities' => array(
					'id'       => 'ai-abilities',
					'slug'     => $slug . '#tab=ai-abilities',
					'title'    => __( 'MCP Config & AI Abilities', 'premium-addons-for-elementor' ),
					'href'     => '#tab=ai-abilities',
					'template' => PREMIUM_ADDONS_PATH . 'admin/includes/templates/ai-abilities',
				),
			),
			array_slice( self::$tabs, $position, null, true )
		);

		if ( ! Helper_Functions::check_papro_version() ) {

			self::$tabs['license'] = array(
				'id'       => 'license',
				'slug'     => $slug . '#tab=license',
				'title'    => __( 'License', 'premium-addons-for-elementor' ),
				'href'     => '#tab=license',
				'template' => PREMIUM_ADDONS_PATH . 'admin/includes/templates/license',
			);

		}

		self::$tabs = apply_filters( 'pa_admin_register_tabs', self::$tabs );
	}

	/**
	 * Add Menu Tabs
	 *
	 * Create Submenu Page
	 *
	 * @since 3.20.9
	 * @access public
	 *
	 * @return void
	 */
	public function add_menu_tabs() {

		$this->set_admin_tabs();

		$plugin_name = Helper_Functions::name();

		call_user_func(
			'add_menu_page',
			$plugin_name,
			$plugin_name,
			'manage_options',
			self::$page_slug,
			array( $this, 'render_setting_tabs' ),
			'',
			100
		);

		foreach ( self::$tabs as $key => $tab ) {

			$menu_title = $tab['title'];

			// Unread MCP news dot. Computed from the cached feed only — the menu
			// renders on every admin page, so it must never trigger a remote fetch.
			// Inline-styled because admin.css loads only on PA screens.
			if ( 'ai-abilities' === $key && MCP_News::ENABLED && MCP_News::has_unread() ) {
				$menu_title .= '<span class="pa-mcp-news-dot" style="display:inline-block;width:8px;height:8px;margin-inline-start:6px;vertical-align:middle;border-radius:50%;background:#d63638;"></span>';
			}

			call_user_func(
				'add_submenu_page',
				self::$page_slug,
				$tab['title'],
				$menu_title,
				'manage_options',
				$tab['slug'],
				'__return_null'
			);
		}

		if ( Helper_Functions::check_elementor_version() ) {
			call_user_func(
				'add_submenu_page',
				self::$page_slug,
				__( 'PA Setup Wizard', 'premium-addons-for-elementor' ),
				__( 'Run Setup Wizard', 'premium-addons-for-elementor' ),
				'manage_options',
				'pa-setup-wizard',
				array( $this, 'pa_init_setup_wizard' )
			);
		}

		$is_papro_active = Helper_Functions::check_papro_version();

		if ( ! $is_papro_active ) {
			call_user_func(
				'add_submenu_page',
				self::$page_slug,
				'<span style="color: #FF6000;" class="pa_pro_upgrade">Get PRO (Up to 30% OFF)</span>',
				'<span style="color: #FF6000;" class="pa_pro_upgrade">Get PRO (Up to 30% OFF)</span>',
				'manage_options',
				'https://premiumaddons.com/pro/#get-pa-pro',
				''
			);
		}

		// To remove the main page link from the tabs.
		remove_submenu_page( self::$page_slug, self::$page_slug );
	}

	/**
	 * Initializes the setup wizard Add the PRO popup template.
	 *
	 * @access public
	 * @since 3.20.8
	 */
	public function pa_init_setup_wizard() {

		include_once PREMIUM_ADDONS_PATH . 'admin/includes/setup-wizard/main-view.php';
		// Add the PRO popup template.
		include_once PREMIUM_ADDONS_PATH . 'admin/includes/templates/pro-popup.php';
	}

	/**
	 * Render Setting Tabs.
	 *
	 * Render the final HTML content for admin setting tabs.
	 *
	 * @access public
	 * @since 3.20.8
	 */
	public function render_setting_tabs() {

		// Reaching this page means manage_options. Treat the visit as consent to
		// accept new MCP client registrations for a short window; see
		// OAuth\Bootstrap::open_registration_window().
		if ( ! empty( self::get_enabled_elements()['premium-ai-abilities'] ) ) {
			// The request path trusts an autoloaded flag rather than querying for
			// the OAuth tables. Confirm them here, where two extra queries do not
			// matter, so a site whose tables were dropped stops reporting OAuth as
			// installed from the next page load on.
			OAuth\Store::verify_tables();

			OAuth\Bootstrap::open_registration_window();
		}

		// add the PRO popup template.
		include_once PREMIUM_ADDONS_PATH . 'admin/includes/templates/pro-popup.php';

		?>
		<div class="pa-settings-wrap">
			<?php do_action( 'pa_before_render_admin_tabs' ); ?>
			<div class="pa-settings-tabs">
				<ul class="pa-settings-tabs-list">
					<?php
					foreach ( self::$tabs as $key => $tab ) {
						$link          = '<li class="pa-settings-tab">';
							$link     .= '<a id="pa-tab-link-' . esc_attr( $tab['id'] ) . '"';
							$link     .= ' href="' . esc_url( $tab['href'] ) . '">';
								$link .= '<i class="pa-dash-' . esc_attr( $tab['id'] ) . '"></i>';
								$link .= '<span>' . esc_html( $tab['title'] ) . '</span>';
							$link     .= '</a>';
						$link         .= '</li>';

						echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					?>
				</ul>
			</div> <!-- Settings Tabs -->

			<div class="pa-settings-sections">
				<?php
				foreach ( self::$tabs as $key => $tab ) {
					echo '<div id="pa-section-' . esc_attr( $tab['id'] ) . '" class="pa-section pa-section-' . esc_attr( $key ) . '">';
						include_once $tab['template'] . '.php';
					echo '</div>';
				}
				?>
			</div> <!-- Settings Sections -->
			<?php do_action( 'pa_after_render_admin_tabs' ); ?>
		</div> <!-- Settings Wrap -->
		<?php
	}

	/**
	 * Render Dashboard Header
	 *
	 * @since 4.0.0
	 * @access public
	 */
	public function render_dashboard_header() {

		$show_logo = Helper_Functions::is_hide_logo();

		?>

		<div class="papro-admin-notice">
			<?php if ( ! $show_logo ) : ?>
				<div class="papro-admin-notice-left">


						<div class="papro-admin-notice-logo">
							<img class="pa-notice-logo" src="<?php echo esc_attr( PREMIUM_ADDONS_URL . 'admin/images/papro-notice-logo.png' ); ?>">
						</div>

						<a href="https://premiumaddons.com/" target="_blank"></a>

				</div>
			<?php endif; ?>

			<?php
				$banner_content = $this->get_banner_strings();

			if ( is_array( $banner_content ) ) :
				?>
					<div class="papro-admin-notice-right">
						<div class="papro-admin-notice-info">
							<h4>
								<?php echo wp_kses_post( $banner_content['title'] ); ?>
							</h4>
							<p>
								<?php echo wp_kses_post( $banner_content['desc'] ); ?>
							</p>
						</div>
						<div class="papro-admin-notice-cta">
							<a class="papro-notice-btn" href="<?php echo esc_url( $banner_content['cta'] ); ?>" target="_blank">
								<?php echo wp_kses_post( $banner_content['btn'] ); ?>
							</a>
						</div>
					</div>
				<?php endif; ?>
		</div>

		<?php
	}

	/**
	 * Gets the license tier. PAPRO without a valid license counts as free.
	 *
	 * @since 4.11.106
	 * @access public
	 *
	 * @return string 'free' | 'pro' | 'lifetime'.
	 */
	public static function get_license_tier() {

		if ( ! Helper_Functions::check_papro_version() ) {
			return 'free';
		}

		$info = get_transient( 'pa_license_info' );

		if ( ! is_array( $info ) || empty( $info['status'] ) || 'valid' !== $info['status'] ) {
			return 'free';
		}

		// Plan id 4 is the only lifetime plan.
		return ( isset( $info['id'] ) && '4' === (string) $info['id'] ) ? 'lifetime' : 'pro';
	}

	/**
	 * Retrieves banner strings.
	 *
	 * @access public
	 * @return array|null
	 */
	public function get_banner_strings() {

		$tier = self::get_license_tier();

		if ( 'free' === $tier ) {
			return array(
				'title' => __( 'Summer SALE 2026', 'premium-addons-for-elementor' ),
				'desc'  => __( 'Supercharge your Elementor with PRO Widgets & Addons that you won\'t find anywhere else.', 'premium-addons-for-elementor' ) . '<span class="papro-sale-notice">' . __( 'save up to 30%!', 'premium-addons-for-elementor' ) . '</span>',
				'btn'   => __( 'Get Pro', 'premium-addons-for-elementor' ),
				'cta'   => 'https://premiumaddons.com/get/papro/#get-pa-pro',
			);
		}

		if ( 'pro' === $tier ) {

			$upgrade_link = Helper_Functions::get_campaign_link( 'http://premiumaddons.com/docs/upgrade-premium-addons-license/', 'dashboard-banner', 'wp-dash', 'upgrade-pro' );

			return array(
				'title' => __( 'Upgrade to Lifetime!', 'premium-addons-for-elementor' ),
				'desc'  => __( 'Pay only the difference and enjoy an <span class="papro-sale-notice"> EXTRA 30% OFF</span> when you upgrade your Premium Addons Pro license to Lifetime — no renewals, no hassle, just lifetime access forever.', 'premium-addons-for-elementor' ),
				'btn'   => __( 'Upgrade Now', 'premium-addons-for-elementor' ),
				'cta'   => $upgrade_link,
			);
		}

		return null;
	}

	/**
	 * Save Settings.
	 *
	 * Save elements settings using AJAX.
	 *
	 * @access public
	 * @since 3.20.8
	 */
	public function pa_save_elements_settings() {

		check_ajax_referer( 'pa-settings-tab', 'security' );

		if ( ! self::check_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You are not allowed to do this action', 'premium-addons-for-elementor' ) );
		}

		if ( ! isset( $_POST['fields'] ) ) {
			return;
		}

		parse_str( sanitize_text_field( wp_unslash( $_POST['fields'] ) ), $settings );

		$defaults = self::get_default_keys();

		// Full-replace semantics: a key present in the posted form is enabled,
		// every other known key is disabled.
		$enabled_map = array();
		foreach ( $defaults as $key => $value ) {
			$enabled_map[ $key ] = isset( $settings[ $key ] );
		}

		self::persist_elements_settings( $enabled_map );

		// Save the global addons only if it's the second run.
		$is_second_run = get_option( 'pa_complete_wizard' ) ? false : true;
		if ( $is_second_run ) {
			self::update_global_addons_option( $settings );
		} else {
			update_option( 'pa_complete_wizard', false );
		}

		wp_send_json_success();
	}

	private static function update_global_addons_option( $settings ) {

		$global_addons = array(
			'premium-mscroll',
			'premium-templates',
			'pa-display-conditions',
			'premium-equal-height',
			'premium-global-cursor',
			'premium-global-badge',
			'premium-shape-divider',
			'premium-global-tooltips',
			'premium-floating-effects',
			'premium-cross-domain',
			'premium-duplicator',
			'premium-wrapper-link',
			'premium-assets-generator',
		);

		$features = array();
		foreach ( $global_addons as $feature ) {
			if ( ! empty( $settings[ $feature ] ) ) {
				$features[] = $feature;
			}
		}

		update_option( 'pa_saved_features', $features );
	}

	/**
	 * Persist the elements settings option.
	 *
	 * Shared low-level writer for the enabled-elements store (pa_save_settings):
	 * stores the option in its canonical shape (only enabled keys, each => true),
	 * clears the cache, and purges the generated dynamic assets. Both the AJAX
	 * save handler and the update_elements_settings() service funnel through here
	 * so the stored shape and its side-effects never drift.
	 *
	 * @since 4.11.74
	 * @access private
	 *
	 * @param array $enabled_map full key => bool map of every known elements key.
	 */
	private static function persist_elements_settings( $enabled_map ) {

		$elements = array_fill_keys( array_keys( array_filter( $enabled_map ) ), true );

		update_option( 'pa_save_settings', $elements );

		// Clear cache and static property.
		wp_cache_delete( 'pa_elements', 'premium_addons' );
		self::$enabled_elements = null;

		// Remove all files in the dynamic assets folder.
		Assets_Manager::delete_assets_files();
	}

	/**
	 * Update elements settings.
	 *
	 * Read-merge-write service for the enabled-elements store (widgets, addons,
	 * global features). Reads the current normalized state, overlays the passed
	 * partial change set (keys it does not own are ignored and reported back),
	 * persists the result, and recomputes the saved global-features option.
	 * Untouched keys keep their current values.
	 *
	 * @since 4.11.74
	 * @access public
	 *
	 * @param array $changes key => bool partial change set.
	 *
	 * @return array {
	 *     @type array $updated List of { key, value, previous_value } for owned keys.
	 *     @type array $unknown Keys this store does not own.
	 * }
	 */
	public static function update_elements_settings( $changes ) {

		$current = self::get_enabled_elements();

		$owned   = array_intersect_key( $changes, $current );
		$unknown = array_keys( array_diff_key( $changes, $current ) );

		if ( empty( $owned ) ) {
			return array(
				'updated' => array(),
				'unknown' => $unknown,
			);
		}

		$updated = array();
		$merged  = $current;

		foreach ( $owned as $key => $value ) {

			$value = is_bool( $value ) ? $value : filter_var( $value, FILTER_VALIDATE_BOOLEAN );

			$updated[]      = array(
				'key'            => $key,
				'value'          => $value,
				'previous_value' => $current[ $key ],
			);
			$merged[ $key ] = $value;
		}

		self::persist_elements_settings( $merged );

		self::update_global_addons_option( $merged );

		return array(
			'updated' => $updated,
			'unknown' => $unknown,
		);
	}

	/**
	 * Update integrations settings.
	 *
	 * Read-merge-write service for the maps/integrations store
	 * (pa_maps_save_settings). Overlays the passed partial change set onto the
	 * current settings — whitelisting and type-sanitizing each key — then writes
	 * the result and clears the cache. Keys outside the whitelist are ignored and
	 * reported back. Untouched keys keep their current values.
	 *
	 * @since 4.11.74
	 * @access public
	 *
	 * @param array $changes key => value partial change set.
	 *
	 * @return array {
	 *     @type array $updated List of { key, value, previous_value } for owned keys.
	 *     @type array $unknown Keys this store does not own.
	 * }
	 */
	public static function update_integrations_settings( $changes ) {

		$int_keys  = array( 'premium-map-disable-api', 'premium-map-cluster', 'premium-wp-optimize-exclude', 'is-beta-tester' );
		$whitelist = array_merge( $int_keys, array( 'premium-map-api', 'premium-youtube-api', 'premium-map-locale' ) );

		$current = self::get_integrations_settings();

		$owned   = array_intersect_key( $changes, array_flip( $whitelist ) );
		$unknown = array_keys( array_diff_key( $changes, array_flip( $whitelist ) ) );

		if ( empty( $owned ) ) {
			return array(
				'updated' => array(),
				'unknown' => $unknown,
			);
		}

		$updated = array();
		$merged  = $current;

		foreach ( $owned as $key => $value ) {

			$value = in_array( $key, $int_keys, true ) ? (int) (bool) $value : sanitize_text_field( $value );

			$updated[]      = array(
				'key'            => $key,
				'value'          => $value,
				'previous_value' => $current[ $key ],
			);
			$merged[ $key ] = $value;
		}

		update_option( 'pa_maps_save_settings', $merged );

		// Clear cache and static property.
		wp_cache_delete( 'pa_integrations', 'premium_addons' );
		self::$integrations_settings = null;

		return array(
			'updated' => $updated,
			'unknown' => $unknown,
		);
	}

	/**
	 * Replace the disabled AI abilities set.
	 *
	 * @param array     $disabled    Full ability names to disable.
	 * @param bool|null $third_party Third-party widgets switch; null preserves the stored value.
	 * @return array
	 */
	public static function save_ai_abilities_settings( array $disabled, $third_party = null ) {
		$abilities_list  = Bootstrap::get_instance()->get_abilities_catalog();
		$abilities_names = wp_list_pluck( $abilities_list, 'full_name' );

		$clean_disabled = array();
		foreach ( wp_unslash( $disabled ) as $full_name ) {

			$full_name = sanitize_text_field( $full_name );

			if ( in_array( $full_name, $abilities_names, true ) ) {
				$clean_disabled[] = $full_name;
			}
		}

		$clean_disabled = array_values( array_unique( $clean_disabled ) );

		// Preserve the current value on an ability-only save (or a Pro-inactive
		// save where the locked switch is never posted).
		if ( null === $third_party ) {
			$current     = self::get_ai_abilities_settings();
			$third_party = ! empty( $current['third_party_widgets'] );
		}

		update_option(
			'pa_ai_abilities',
			array(
				'disabled_abilities'  => $clean_disabled,
				'third_party_widgets' => (bool) $third_party,
			)
		);

		wp_cache_delete( 'pa_ai_abilities', 'premium_addons' );
		self::$ai_abilities_settings = null;

		return $clean_disabled;
	}

	/**
	 * Save AI ability switcher settings.
	 *
	 * @return void
	 */
	public function pa_save_ai_abilities() {

		check_ajax_referer( 'pa-settings-tab', 'security' );

		if ( ! self::check_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You are not allowed to do this action.', 'premium-addons-for-elementor' ),
				)
			);
		}

		if ( ! isset( $_POST['disabled'] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No AI ability settings were provided.', 'premium-addons-for-elementor' ),
				)
			);
		}

		$disabled_abilities = wp_unslash( $_POST['disabled'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Decoded values are sanitized and catalog-whitelisted before storage.
		$disabled           = is_array( $disabled_abilities ) ? $disabled_abilities : json_decode( $disabled_abilities, true );

		if ( ! is_array( $disabled ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'The AI ability settings could not be saved.', 'premium-addons-for-elementor' ),
				)
			);
		}

		$third_party = isset( $_POST['third_party'] ) ? ( '1' === sanitize_text_field( wp_unslash( $_POST['third_party'] ) ) ) : null;

		$disabled = self::save_ai_abilities_settings( $disabled, $third_party );

		wp_send_json_success(
			array(
				'message'            => __( 'AI ability settings saved.', 'premium-addons-for-elementor' ),
				'disabled_abilities' => $disabled,
			)
		);
	}

	/**
	 * Mark the MCP news feed as seen. Fired when the AI Abilities tab is opened,
	 * so the submenu dot clears on the next admin page load.
	 *
	 * @since 4.11.102
	 * @return void
	 */
	public function pa_mcp_news_seen() {

		check_ajax_referer( 'pa-settings-tab', 'security' );

		if ( ! self::check_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You are not allowed to do this action.', 'premium-addons-for-elementor' ),
				),
				403
			);
		}

		MCP_News::mark_seen();

		wp_send_json_success();
	}

	/**
	 * Run the MCP connection check and return its rows. Nothing is cached:
	 * every click runs the checks again.
	 *
	 * @since 4.11.107
	 * @return void
	 */
	public function pa_mcp_connection_check() {

		check_ajax_referer( 'pa-settings-tab', 'security' );

		if ( ! self::check_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You are not allowed to do this action.', 'premium-addons-for-elementor' ),
				),
				403
			);
		}

		wp_send_json_success( array( 'rows' => MCP_Settings::run_connection_check() ) );
	}

	/**
	 * Revoke one of the current user's Premium Addons MCP connections. The row
	 * must be in that user's own list, which is the ownership check.
	 *
	 * @since 4.11.108
	 *
	 * @return void
	 */
	public function pa_mcp_revoke_connection() {

		check_ajax_referer( 'pa-settings-tab', 'security' );

		if ( ! self::check_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You are not allowed to do this action.', 'premium-addons-for-elementor' ),
				),
				403
			);
		}

		$kind = isset( $_POST['kind'] ) ? sanitize_key( $_POST['kind'] ) : '';
		$id   = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';

		if ( ! in_array( $kind, array( 'password', 'oauth' ), true ) || '' === $id ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid connection.', 'premium-addons-for-elementor' ),
				),
				400
			);
		}

		$user_id    = get_current_user_id();
		$connection = wp_list_filter(
			Connection_Log::get_connections( $user_id ),
			array(
				'kind' => $kind,
				'id'   => $id,
			)
		);

		if ( empty( $connection ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'That connection no longer exists. Reload the page.', 'premium-addons-for-elementor' ),
				),
				404
			);
		}

		$revoked = 'password' === $kind
			? true === \WP_Application_Passwords::delete_application_password( $user_id, $id )
			: OAuth\Store::revoke_token( (int) $id );

		if ( ! $revoked ) {
			wp_send_json_error(
				array(
					'message' => __( 'The connection could not be revoked. Try again.', 'premium-addons-for-elementor' ),
				),
				500
			);
		}

		// The wp_delete_application_password hook runs this too, but only while
		// the abilities feature is on, and OAuth has no core hook at all.
		Connection_Log::forget( $user_id );

		wp_send_json_success(
			array(
				'remaining' => count( Connection_Log::get_connections( $user_id ) ),
				'connected' => Connection_Log::is_connected(),
			)
		);
	}

	/**
	 * Enable the OAuth connect method: install the tables, set the flag, and
	 * verify anonymous REST is actually reachable.
	 *
	 * @since 4.11.90
	 * @return void
	 */
	public function pa_enable_oauth_connect() {

		check_ajax_referer( 'pa-settings-tab', 'security' );

		if ( ! self::check_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You are not allowed to do this action.', 'premium-addons-for-elementor' ),
				)
			);
		}

		$reason = OAuth\Bootstrap::unavailable_reason();

		if ( '' !== $reason ) {
			wp_send_json_error( array( 'message' => $reason ) );
		}

		// Check if a plugin blocks unauthenticated REST requests. If so, surface the error.
		$auth_probe = OAuth\Bootstrap::rest_lock_error();

		if ( null !== $auth_probe ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: error message from the REST authentication filter. */
						__( 'A password-protection or firewall plugin on this site blocks unauthenticated REST API requests, which OAuth needs: %s', 'premium-addons-for-elementor' ),
						$auth_probe->get_error_message()
					),
				)
			);
		}

		if ( ! OAuth\Store::maybe_install() ) {
			wp_send_json_error(
				array(
					'message' => __( 'OAuth tables could not be created. Confirm that your database user can create tables, then try again.', 'premium-addons-for-elementor' ),
				)
			);
		}
		update_option( OAuth\Bootstrap::OPTION_ENABLED, true );

		// Open the client-registration window now. The dashboard render that
		// normally opens it ran before the opt-in flag existed, and
		// Bootstrap::is_registered() was already memoized as false on init in
		// this request, so open_registration_window() would return early —
		// the option is written directly instead. Without this the first
		// connect attempt after enabling fails at registration until the
		// dashboard is reloaded.
		update_option( OAuth\Bootstrap::DCR_WINDOW, time() + OAuth\Bootstrap::DCR_WINDOW_TTL, true );

		if ( ! wp_next_scheduled( OAuth\Bootstrap::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', OAuth\Bootstrap::CRON_HOOK );
		}

		// A cached pre-opt-in 404 of the discovery documents would break the
		// handshake with nothing surfaced anywhere.
		OAuth\Bootstrap::flush_page_caches();

		wp_send_json_success(
			array(
				'message' => __( 'OAuth connection enabled. Connect your AI client with the configuration below.', 'premium-addons-for-elementor' ),
			)
		);
	}

	/**
	 * Re-open the client-registration window. Fired when an administrator
	 * copies a connection detail from the OAuth branch, so the 30 minutes count
	 * from the moment the endpoint was grabbed rather than from the page load
	 * that may have happened long before. No-op while OAuth is off.
	 *
	 * @since 4.11.101
	 * @return void
	 */
	public function pa_extend_oauth_window() {

		check_ajax_referer( 'pa-settings-tab', 'security' );

		if ( ! self::check_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You are not allowed to do this action.', 'premium-addons-for-elementor' ),
				)
			);
		}

		OAuth\Bootstrap::open_registration_window();

		wp_send_json_success();
	}

	/**
	 * Save Integrations Control Settings
	 *
	 * Stores integration and version control settings
	 *
	 * @since 3.20.8
	 * @access public
	 */
	public function pa_save_additional_settings() {

		check_ajax_referer( 'pa-settings-tab', 'security' );

		if ( ! self::check_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You are not allowed to do this action', 'premium-addons-for-elementor' ) );
		}

		if ( ! isset( $_POST['fields'] ) ) {
			return;
		}

		parse_str( sanitize_text_field( wp_unslash( $_POST['fields'] ) ), $settings );

		// Pass the full whitelist as a change set; the service whitelists,
		// type-sanitizes and persists. Absent checkbox keys resolve to 0.
		$changes = array(
			'premium-map-api'             => $settings['premium-map-api'] ?? '',
			'premium-youtube-api'         => $settings['premium-youtube-api'] ?? '',
			'premium-map-disable-api'     => $settings['premium-map-disable-api'] ?? '',
			'premium-map-cluster'         => $settings['premium-map-cluster'] ?? '',
			'premium-wp-optimize-exclude' => $settings['premium-wp-optimize-exclude'] ?? '',
			'premium-map-locale'          => $settings['premium-map-locale'] ?? '',
			'is-beta-tester'              => $settings['is-beta-tester'] ?? '',
		);

		self::update_integrations_settings( $changes );

		wp_send_json_success( $settings );
	}

	/**
	 * Save Global Button Value
	 *
	 * Saves value for elements global switcher
	 *
	 * @since 4.0.0
	 * @access public
	 */
	public function pa_save_global_btn() {

		check_ajax_referer( 'pa-settings-tab', 'security' );

		if ( ! self::check_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You are not allowed to do this action', 'premium-addons-for-elementor' ) );
		}

		if ( ! isset( $_POST['isGlobalOn'] ) ) {
			wp_send_json_error();
		}

		$global_btn_value = sanitize_text_field( wp_unslash( $_POST['isGlobalOn'] ) );

		update_option( 'pa_global_btn_value', $global_btn_value );

		wp_send_json_success();
	}

	/**
	 * Get default Elements
	 *
	 * @since 3.20.9
	 * @access private
	 *
	 * @return $default_keys array keys defaults
	 */
	private static function get_default_keys() {

		$elements = self::get_elements_keys();

		$keys = array();

		// Now, we need to fill our array with elements keys.

		foreach ( $elements as $elem ) {

			$keys[] = $elem['key'];

			if ( isset( $elem['draw_svg'] ) ) {
				$keys[] = 'svg_' . $elem['key'];
			}
		}

		$default_keys = array_fill_keys( $keys, true );

		$default_keys['pa_mc_temp'] = false;

		// AI Abilities is opt-in: it must stay OFF until the user enables it.
		$default_keys['premium-ai-abilities'] = false;

		return $default_keys;
	}

	/**
	 * Get Pro Elements.
	 * Return PAPRO Widgets.
	 *
	 * @since 4.5.3
	 * @access public
	 *
	 * @return array
	 */
	public static function get_pro_elements() {

		static $pro_elements = null;

		if ( null !== $pro_elements ) {
			return $pro_elements;
		}

		$elements = self::get_elements_list();

		$pro_elements = array();

		$all_elements = $elements['cat-1'];

		if ( count( $all_elements['elements'] ) ) {
			foreach ( $all_elements['elements'] as $elem ) {
				if ( isset( $elem['is_pro'] ) && ! isset( $elem['is_global'] ) ) {
					$elem['categories'] = '["premium-elements"]';
					$pro_elements[]     = $elem;
				}
			}
		}

		return $pro_elements;
	}

	/**
	 * Get PA Free Elements.
	 * Return PA Widgets.
	 *
	 * @since 4.6.1
	 * @access public
	 *
	 * @return array
	 */
	public static function get_free_widgets_names() {

		static $pa_elements = null;

		if ( null !== $pa_elements ) {
			return $pa_elements;
		}

		$elements = self::get_elements_list()['cat-1']['elements'];

		$pa_elements = array();

		if ( count( $elements ) ) {
			foreach ( $elements as $elem ) {
				if ( ! isset( $elem['is_pro'] ) && ! isset( $elem['is_global'] ) && isset( $elem['name'] ) ) {
					$pa_elements[] = $elem['name'];
				}
			}
		}

		return $pa_elements;
	}

	/**
	 * Get Info By Key.
	 *
	 * Returns elements by its key.
	 *
	 * @since 4.10.49
	 * @access public
	 *
	 * @param string $key element key.
	 *
	 * @return array
	 */
	public static function get_info_by_key( $key ) {

		$elements = self::get_elements_list()['cat-1']['elements'];

		$element = false;

		foreach ( $elements as $elem ) {

			if ( $key === $elem['name'] ) {
				$element = $elem;
				break;
			}
		}

		return $element;
	}

	/**
	 * Get Default Integrations
	 *
	 * @since 3.20.9
	 * @access private
	 *
	 * @return $default_keys array default keys
	 */
	private static function get_default_integrations() {

		$settings = self::get_integrations_list();

		$default_keys = array_fill_keys( $settings, true );

		// Beta Tester should NOT be enabled by default.
		$default_keys['is-beta-tester'] = false;

		return $default_keys;
	}

	/**
	 * Get enabled widgets
	 *
	 * @since 3.20.9
	 * @access public
	 *
	 * @return array $enabled_keys enabled elements as a key => bool map (true = enabled, false = disabled).
	 */
	public static function get_enabled_elements() {

		$cache_key = 'pa_elements';
		$cached    = wp_cache_get( $cache_key, 'premium_addons' );

		if ( false !== $cached ) {
			self::$enabled_elements = $cached;
			return self::$enabled_elements;
		}

		// Check static property as fallback for multiple calls in same request.
		if ( null !== self::$enabled_elements ) {
			return self::$enabled_elements;
		}

		$defaults = self::get_default_keys();

		$enabled_keys = get_option( 'pa_save_settings', $defaults );

		// Keys that default to OFF and must only turn on when the saved option says so.
		$opt_in_keys = array( 'pa_mc_temp', 'premium-ai-abilities' );

		foreach ( $defaults as $key => $value ) {

			if ( ! in_array( $key, $opt_in_keys, true ) && ! isset( $enabled_keys[ $key ] ) ) {
				$defaults[ $key ] = false;
			} elseif ( in_array( $key, $opt_in_keys, true ) ) {
				$defaults[ $key ] = ( isset( $enabled_keys[ $key ] ) && $enabled_keys[ $key ] ) ? true : false;
			}
		}

		self::$enabled_elements = $defaults;
		wp_cache_set( $cache_key, $defaults, 'premium_addons', HOUR_IN_SECONDS );

		return self::$enabled_elements;
	}

	/**
	 * Check Elementor By Key
	 *
	 * @since 4.10.52
	 * @access public
	 *
	 * @return string $key element key.
	 */
	public static function check_element_by_key( $key ) {

		if ( ! $key ) {
			return;
		}

		$settings = self::get_enabled_elements();

		if ( ! isset( $settings[ $key ] ) ) {
			return false;
		}

		return $settings[ $key ];
	}

	/**
	 * Check SVG Draw.
	 *
	 * @since 4.9.26
	 * @access public
	 *
	 * @param string $key element key.
	 *
	 * @return boolean $is_enabled is option enabled.
	 */
	public static function check_svg_draw( $key ) {

		$is_enabled = self::check_element_by_key( 'svg_' . $key );

		return $is_enabled;
	}

	/**
	 * Check If Premium Templates is enabled
	 *
	 * @since 3.6.0
	 * @access public
	 *
	 * @return boolean
	 */
	public static function check_premium_templates() {

		$settings = self::get_enabled_elements();

		if ( ! isset( $settings['premium-templates'] ) ) {
			return true;
		}

		$is_enabled = $settings['premium-templates'];

		return $is_enabled;
	}


	/**
	 * Check If Premium Duplicator is enabled
	 *
	 * @since 3.20.9
	 * @access public
	 *
	 * @return boolean
	 */
	public static function check_duplicator() {

		$settings = self::get_enabled_elements();

		if ( ! isset( $settings['premium-duplicator'] ) ) {
			return true;
		}

		$is_enabled = $settings['premium-duplicator'];

		return $is_enabled;
	}

	/**
	 * Check If Premium Duplicator is enabled
	 *
	 * @since 4.9.4
	 * @access public
	 *
	 * @return boolean
	 */
	public static function check_dynamic_assets() {

		$settings = self::get_enabled_elements();

		if ( ! isset( $settings['premium-assets-generator'] ) ) {
			return false;
		}

		$is_enabled = $settings['premium-assets-generator'];

		return $is_enabled;
	}

	/**
	 * Get Integrations Settings.
	 *
	 * Get plugin integrations settings.
	 *
	 * @since 3.20.9
	 * @access public
	 *
	 * @return array $settings integrations settings.
	 */
	public static function get_integrations_settings() {

		$cache_key = 'pa_integrations';
		$cached    = wp_cache_get( $cache_key, 'premium_addons' );

		if ( false !== $cached ) {
			self::$integrations_settings = $cached;
			return self::$integrations_settings;
		}

		// Check static property as fallback for multiple calls in same request.
		if ( null !== self::$integrations_settings ) {
			return self::$integrations_settings;
		}

		$defaults = self::get_default_integrations();

		$enabled_keys = get_option( 'pa_maps_save_settings', $defaults );

		foreach ( $defaults as $key => $value ) {

			if ( isset( $enabled_keys[ $key ] ) ) {

				$defaults[ $key ] = $enabled_keys[ $key ];
			}
		}

		self::$integrations_settings = $defaults;
		wp_cache_set( $cache_key, $defaults, 'premium_addons', HOUR_IN_SECONDS );

		return self::$integrations_settings;
	}

	/**
	 * Get normalized AI abilities settings.
	 *
	 * @return array
	 */
	public static function get_ai_abilities_settings() {

		$cached_settings = wp_cache_get( 'pa_ai_abilities', 'premium_addons' );

		if ( false !== $cached_settings ) {
			self::$ai_abilities_settings = $cached_settings;
			return self::$ai_abilities_settings;
		}

		if ( null !== self::$ai_abilities_settings ) {
			return self::$ai_abilities_settings;
		}

		$stored_settings = get_option( 'pa_ai_abilities', array( 'disabled_abilities' => array() ) );
		$stored_settings = is_array( $stored_settings ) ? $stored_settings : array();

		$disabled = isset( $stored_settings['disabled_abilities'] ) && is_array( $stored_settings['disabled_abilities'] )
			? array_values( array_filter( $stored_settings['disabled_abilities'], 'is_string' ) )
			: array();

		// Default-on: a missing key reads as enabled so existing Pro users are unaffected.
		$third_party_widgets = ! array_key_exists( 'third_party_widgets', $stored_settings )
			|| ! empty( $stored_settings['third_party_widgets'] );

		self::$ai_abilities_settings = array(
			'disabled_abilities'  => $disabled,
			'third_party_widgets' => $third_party_widgets,
		);

		wp_cache_set( 'pa_ai_abilities', self::$ai_abilities_settings, 'premium_addons' );

		return self::$ai_abilities_settings;
	}

	/**
	 * Check whether an ability is enabled.
	 *
	 * @param string $full_name Full ability name.
	 * @return bool
	 */
	public static function is_ability_enabled( $full_name ) {
		$settings = self::get_ai_abilities_settings();

		return ! in_array( $full_name, $settings['disabled_abilities'], true );
	}

	/**
	 * Run PA Rollback
	 *
	 * Trigger PA Rollback actions
	 *
	 * @since 4.2.5
	 * @access public
	 */
	public function run_pa_rollback() {

		check_admin_referer( 'premium_addons_rollback' );

		$plugin_slug = basename( PREMIUM_ADDONS_FILE, '.php' );

		$pa_rollback = new PA_Rollback(
			array(
				'version'     => PREMIUM_ADDONS_STABLE_VERSION,
				'plugin_name' => PREMIUM_ADDONS_BASENAME,
				'plugin_slug' => $plugin_slug,
				'package_url' => sprintf( 'https://downloads.wordpress.org/plugin/%s.%s.zip', $plugin_slug, PREMIUM_ADDONS_STABLE_VERSION ),
			)
		);

		$pa_rollback->run();

		wp_die(
			'',
			esc_html( __( 'Rollback to Previous Version', 'premium-addons-for-elementor' ) ),
			array(
				'response' => 200,
			)
		);
	}

	/**
	 * Disables Elementor Custom Mini Cart Template.
	 *
	 * @access public
	 * @since 4.11.6
	 * @see ElementorPro\Modules\Woocommerce\Module [elementor-pro\modules\woocommerce\module.php].
	 */
	public function pa_disable_elementor_mc_template() {

		check_ajax_referer( 'pa-settings-tab', 'security' );

		if ( ! self::check_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You are not allowed to do this action', 'premium-addons-for-elementor' ) );
		}

		update_option( 'elementor_use_mini_cart_template', 'no' );

		wp_send_json_success( 'Elementor Mini Cart Template Disabled.' );
	}



	/**
	 * Clear Cached Assets.
	 *
	 * Deletes assets options from DB And
	 * deletes assets files from uploads/premium-addons-for-elementor
	 * directory.
	 *
	 * @access public
	 * @since 4.9.3
	 */
	public function clear_site_cursor_settings() {

		check_ajax_referer( 'pa-site-cursor-nonce', 'security' );

		if ( ! self::check_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You are not allowed to do this action', 'premium-addons-for-elementor' ) );
		}

		delete_option( 'pa_site_custom_cursor' );

		wp_send_json_success( 'Site Cursor Settings Cleared' );
	}



	/**
	 * Get PA widget names.
	 *
	 * @access public
	 * @since 4.5.8
	 *
	 * @return array
	 */
	public static function get_pa_elements_names() {

		if ( null === self::$elements_names ) {

			$names = array_map(
				function ( $item ) {
					return isset( $item['name'] ) ? $item['name'] : 'global';
				},
				self::get_elements_list()['cat-1']['elements']
			);

			self::$elements_names = array_filter(
				$names,
				function ( $name ) {
					return 'global' !== $name;
				}
			);

		}

		return self::$elements_names;
	}

	/**
	 * Get used widgets.
	 *
	 * An empty result means "never scanned" as often as it means "nothing is used";
	 * has_usage_data() separates the two.
	 *
	 * @access public
	 * @since 4.5.8
	 *
	 * @return array
	 */
	public static function get_used_widgets() {

		$used_widgets = array();

		if ( class_exists( 'Elementor\Modules\Usage\Module' ) ) {

			$module = Module::instance();

			$elements = $module->get_formatted_usage( 'raw' );

			$pa_elements = self::get_pa_elements_names();

			if ( is_array( $elements ) || is_object( $elements ) ) {

				foreach ( $elements as $post_type => $data ) {

					foreach ( $data['elements'] as $element => $count ) {

						if ( in_array( $element, $pa_elements, true ) ) {

							if ( isset( $used_widgets[ $element ] ) ) {
								$used_widgets[ $element ] += $count;
							} else {
								$used_widgets[ $element ] = $count;
							}
						}
					}
				}
			}
		}

		return $used_widgets;
	}

	/**
	 * Whether a usage scan has ever run — which get_used_widgets() cannot report.
	 *
	 * @access public
	 * @since 4.11.95
	 *
	 * @return bool
	 */
	public static function has_usage_data() {

		if ( ! class_exists( 'Elementor\Modules\Usage\Module' ) ) {
			return false;
		}

		return ! empty( get_option( Module::OPTION_NAME, array() ) );
	}

	/**
	 * Recalculate one batch of the site's Elementor usage data.
	 *
	 * Deliberately not Elementor's recalc_usage(): that ends in wp_cache_flush(), which
	 * empties a shared Redis/Memcached store for the whole site. It flushes to bound
	 * memory across an unbounded loop; batching bounds it per request instead.
	 *
	 * @access public
	 * @since 4.11.95
	 *
	 * @param int $offset Posts already processed.
	 * @param int $limit  Posts to process in this batch.
	 *
	 * @return array|false Progress counters, or false when Elementor is unavailable.
	 */
	public static function scan_widgets_usage( $offset = 0, $limit = self::SCAN_BATCH_SIZE ) {

		if ( ! class_exists( 'Elementor\Modules\Usage\Module' ) ) {
			return false;
		}

		$module = Module::instance();

		// Fallback for a future Elementor without after_document_save(): unbatched, and
		// it flushes the cache — kept only so the feature does not break outright.
		if ( ! method_exists( $module, 'after_document_save' ) ) {

			$processed = $module->recalc_usage();

			return array(
				'processed' => $processed,
				'total'     => $processed,
				'done'      => true,
			);
		}

		if ( 0 === $offset ) {
			delete_option( Module::OPTION_NAME );
		}

		$query = new \WP_Query(
			array(
				'fields'         => 'ids',
				'meta_key'       => '_elementor_data', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- matches the post set Elementor's own recalc walks.
				'post_type'      => get_post_types( array( 'public' => true ) ),
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => $limit,
				'offset'         => $offset,
			)
		);

		foreach ( $query->posts as $post_id ) {

			$document = Plugin::$instance->documents->get( $post_id );

			if ( $document ) {
				$module->after_document_save( $document );
			}
		}

		$processed = $offset + count( $query->posts );

		return array(
			'processed' => $processed,
			'total'     => $query->found_posts,
			'done'      => $processed >= $query->found_posts,
		);
	}

	/**
	 * Get the settings keys of every unused Premium Addons widget.
	 *
	 * Usage is reported by widget name and settings are keyed by settings key; the two
	 * differ across the premium-addon-* family, so the diff happens in name space.
	 *
	 * @access public
	 * @since 4.11.95
	 *
	 * @return array
	 */
	public static function get_unused_element_keys() {

		$unused_names = array_diff(
			self::get_pa_elements_names(),
			array_keys( self::get_used_widgets() )
		);

		$keys = array();

		foreach ( self::get_elements_list()['cat-1']['elements'] as $element ) {

			if ( isset( $element['name'], $element['key'] ) && in_array( $element['name'], $unused_names, true ) ) {
				$keys[] = $element['key'];
			}
		}

		return $keys;
	}

	/**
	 * Scan one batch of the site's Elementor usage data.
	 *
	 * @access public
	 * @since 4.11.95
	 */
	public function pa_scan_widgets_usage() {

		check_ajax_referer( 'pa-disable-unused', 'security' );

		if ( ! self::check_user_can( 'install_plugins' ) ) {
			wp_send_json_error( __( 'You are not allowed to do this action', 'premium-addons-for-elementor' ) );
		}

		$offset = isset( $_POST['offset'] ) ? absint( wp_unslash( $_POST['offset'] ) ) : 0;

		$progress = self::scan_widgets_usage( $offset );

		if ( ! $progress ) {
			wp_send_json_error( __( 'Elementor usage data is not available on this site.', 'premium-addons-for-elementor' ) );
		}

		wp_send_json_success( $progress );
	}

	/**
	 * Disable every Premium Addons widget the last scan found unused.
	 *
	 * @access public
	 * @since 4.11.95
	 */
	public function pa_disable_unused_widgets() {

		check_ajax_referer( 'pa-disable-unused', 'security' );

		if ( ! self::check_user_can( 'install_plugins' ) ) {
			wp_send_json_error( __( 'You are not allowed to do this action', 'premium-addons-for-elementor' ) );
		}

		$unused   = self::get_unused_element_keys();
		$disabled = array();

		if ( ! empty( $unused ) ) {

			$result = self::update_elements_settings( array_fill_keys( $unused, false ) );

			// Report only the widgets this call switched off, not the ones already off.
			foreach ( $result['updated'] as $change ) {
				if ( $change['previous_value'] ) {
					$disabled[] = $change['key'];
				}
			}
		}

		wp_send_json_success(
			array(
				'unused'   => $unused,
				'disabled' => $disabled,
			)
		);
	}

	/**
	 * Subscribe Newsletter
	 *
	 * Adds an email to Premium Addons subscribers list
	 *
	 * @since 4.7.0
	 *
	 * @access public
	 */
	public function subscribe_newsletter() {

		check_ajax_referer( 'pa-settings-tab', 'security' );

		if ( ! self::check_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		$result = self::subscribe_newsletter_request( $email );

		wp_send_json_success( $result['response'] );
	}

	/**
	 * Subscribe Newsletter Request.
	 *
	 * Pure newsletter-subscribe service: sends the email to the Premium Addons
	 * MailChimp endpoint and returns the decoded response. Shared by the AJAX
	 * handler and the premium-addons/subscribe-newsletter ability so both hit
	 * the exact same remote call and never drift.
	 *
	 * @since 4.11.74
	 * @access public
	 *
	 * @param string $email subscriber email address.
	 *
	 * @return array {
	 *     @type bool  $success  Whether the request reached the endpoint with an HTTP 200.
	 *     @type array $response Decoded remote response body ( [] when unavailable ).
	 * }
	 */
	public static function subscribe_newsletter_request( $email ) {

		$api_url = 'https://premiumaddons.com/wp-json/mailchimp/v2/add';

		$request = add_query_arg(
			array(
				'email' => $email,
			),
			$api_url
		);

		$response = wp_remote_get(
			$request,
			array(
				'timeout'   => 15,
				'sslverify' => true,
			)
		);

		$body = wp_remote_retrieve_body( $response );
		$body = json_decode( $body, true );

		return array(
			'success'  => ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ),
			'response' => is_array( $body ) ? $body : array(),
		);
	}

	/**
	 * Get PA News
	 *
	 * Gets a list of the latest three blog posts
	 *
	 * @since 4.7.0
	 *
	 * @access public
	 */
	public function get_pa_news() {

		$posts = get_transient( 'pa_news' );

		if ( empty( $posts ) ) {

			$api_url = 'https://premiumaddons.com/wp-json/wp/v2/posts';

			$request = add_query_arg(
				array(
					'per_page'   => 3,
					'categories' => 32,
				),
				$api_url
			);

			$response = wp_remote_get(
				$request,
				array(
					'timeout'   => 5,
					'sslverify' => true,
				)
			);

			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				set_transient( 'pa_news', true, WEEK_IN_SECONDS );
				return;
			}

			$body  = wp_remote_retrieve_body( $response );
			$posts = json_decode( $body, true );

			set_transient( 'pa_news', $posts, WEEK_IN_SECONDS );

		}

		return $posts;
	}

	/**
	 * Clear Dynamic Assets Directory
	 *
	 * Deletes all files in the dynamic assets directory
	 *
	 * @since 4.9.3
	 * @access public
	 */
	public function clear_dynamic_assets_dir() {

		$path = PREMIUM_ASSETS_PATH;

		if ( ! is_dir( $path ) || ! file_exists( $path ) ) {
			return;
		}

		foreach ( scandir( $path ) as $file ) {
			if ( '.' === $file || '..' === $file ) {
				continue;
			}

			unlink( Helper_Functions::get_safe_path( $path . DIRECTORY_SEPARATOR . $file ) );
		}
	}

	/**
	 * Handle Plugin Upgrade
	 *
	 * Clears dynamic assets directory when Premium Addons or Premium Addons Pro is updated
	 *
	 * @since 4.11.63
	 * @access public
	 *
	 * @param object $upgrader_object Upgrader Object.
	 * @param array  $options         Upgrade Options.
	 */
	public function pa_handle_upgrade( $upgrader_object, $options ) {

		// We only care about plugin updates.
		if (
			empty( $options['action'] ) ||
			empty( $options['type'] ) ||
			'update' !== $options['action'] ||
			'plugin' !== $options['type']
		) {
			return;
		}

		// Plugins we want to react to.
		$target_plugins = array(
			PREMIUM_ADDONS_BASENAME,
			'premium-addons-pro/premium-addons-pro-for-elementor.php',
		);

		// Normalize updated plugins into an array
		$updated_plugins = array();

		if ( ! empty( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
			$updated_plugins = $options['plugins'];
		} elseif ( ! empty( $options['plugin'] ) ) {
			$updated_plugins = array( $options['plugin'] );
		}

		// No plugin info → nothing to do
		if ( empty( $updated_plugins ) ) {
			return;
		}

		// Check intersection
		if ( array_intersect( $target_plugins, $updated_plugins ) ) {
			// Remove dynamic assets files on plugin update.
			$this->clear_dynamic_assets_dir();
		}
	}

	/**
	 * Creates and returns an instance of the class
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return object
	 */
	public static function get_instance() {

		if ( ! isset( self::$instance ) ) {

			self::$instance = new self();

		}

		return self::$instance;
	}
}
