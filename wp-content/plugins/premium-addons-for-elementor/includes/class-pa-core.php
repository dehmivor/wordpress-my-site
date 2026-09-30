<?php
/**
 * PA Core.
 */

namespace PremiumAddons\Includes;

use PremiumAddons\Admin\Includes\Admin_Notices;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'PA_Core' ) ) {

	/**
	 * Intialize and Sets up the plugin
	 */
	class PA_Core {

		/**
		 * Member Variable
		 *
		 * @var PA_Core|null
		 */
		private static $instance = null;

		/**
		 * Sets up needed actions/filters for the plug-in to initialize.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @return void
		 */
		public function __construct() {

			// Load plugin textdomain.
			add_action( 'init', array( $this, 'i18n' ) );

			// Run plugin and require the necessary files.
			add_action( 'plugins_loaded', array( $this, 'pa_init' ) );

			add_action( 'init', array( $this, 'init' ), -999 );

			// Register Activation hooks.
			register_activation_hook( PREMIUM_ADDONS_FILE, array( $this, 'handle_activation' ) );
			register_deactivation_hook( PREMIUM_ADDONS_FILE, array( $this, 'handle_deactivation' ) );
			register_uninstall_hook( PREMIUM_ADDONS_FILE, array( __CLASS__, 'uninstall' ) );
		}

		/**
		 * Installs translation text domain and checks if Elementor is installed
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @return void
		 */
		public function pa_init() {

			// Load plugin necessary files.
			\PremiumAddons\Admin\Includes\Admin_Helper::get_instance();

			Addons_Integration::get_instance();

			$this->load_abilities();

			include_once PREMIUM_ADDONS_PATH . 'includes/promotion-pointer.php';
		}

		/**
		 * Load AI Abilities
		 *
		 * Stands up the bundled MCP server. Gated by the premium-ai-abilities
		 * switcher; the Abilities API capability check (in core since 6.9) lives
		 * in Abilities\Bootstrap. Route_Detector repeats it because it binds
		 * before the switcher gate.
		 *
		 * @since 4.11.74
		 * @access public
		 *
		 * @return void
		 */
		public function load_abilities() {

			// Bound before the switcher gate: the scheduled OAuth cleanup must keep
			// a callback even while the feature is switched off, or the daily event
			// fires into nothing and expired tokens are never cleared.
			add_action( 'pa_oauth_gc', array( Abilities\OAuth\Store::class, 'gc' ) );

			// Also before the gate: the dashboard must know which other MCP servers
			// already carry Premium Addons abilities the moment the switch is turned on.
			if ( function_exists( 'wp_register_ability' ) ) {
				Abilities\Route_Detector::init();
			}

			$enabled_elements = \PremiumAddons\Admin\Includes\Admin_Helper::get_enabled_elements();

			if ( empty( $enabled_elements['premium-ai-abilities'] ) ) {
				return;
			}

			Abilities\Bootstrap::get_instance();
		}

		/**
		 * Seeds the first-install state: review-notice quiet period, setup wizard
		 * and the install timestamp.
		 *
		 * @since 3.1.7
		 * @access public
		 *
		 * @return void
		 */
		public function handle_activation() {

			if ( get_option( 'pa_install_time' ) ) {
				return;
			}

			Admin_Notices::snooze_review_notice( DAY_IN_SECONDS * 7 );

			$current_time = gmdate( 'j F, Y', time() );

			update_option( 'pa_complete_wizard', true );
			update_option( 'pa_install_time', $current_time );

			$api_url = 'https://feedbackpa.leap13.com/wp-json/install/v2/add';

			wp_safe_remote_request(
				$api_url,
				array(
					'headers'     => array(
						'Content-Type' => 'application/json',
					),
					'body'        => wp_json_encode(
						array(
							'time' => $current_time,
						)
					),
					'blocking'    => false,
					'timeout'     => 3,
					'method'      => 'POST',
					'httpversion' => '1.1',
				)
			);

			set_transient( 'pa_activation_redirect', true, 30 );
		}

		/**
		 * Plugin Deactivation Hook.
		 *
		 * Drops the OAuth garbage-collection event. A recurring event outlives
		 * deactivation and keeps rescheduling itself against a hook nothing
		 * listens on, so leaving it behind orphans an entry in the cron array
		 * for good if the plugin is later deleted without uninstalling.
		 *
		 * OAuth data itself survives — deactivation is reversible, so the tables,
		 * the opt-in flag and issued tokens stay. Bootstrap::register() re-arms
		 * this event on the next request once the plugin is active again.
		 *
		 * @since 4.11.90
		 * @access public
		 *
		 * @return void
		 */
		public function handle_deactivation() {

			wp_clear_scheduled_hook( Abilities\OAuth\Bootstrap::CRON_HOOK );
		}

		/**
		 * Plugin Uninstall Hook.
		 *
		 * @since 3.1.7
		 * @access public
		 *
		 * @return void
		 */
		public static function uninstall() {

			delete_option( 'pa_complete_wizard' );
			delete_option( 'pa_install_time' );
			delete_option( 'pa_review_notice' );
			delete_option( 'pa-connect-ai-not' );
			delete_option( 'pa-angie-not' );

			// Per-user feature-notice dismissals, network-wide.
			delete_metadata( 'user', 0, 'pa_dismissed_notices', '', true );

			if ( is_multisite() ) {
				// 'number' => 0 overrides WP_Site_Query's default of 100; without it
				// a larger network keeps its tables, options and live tokens.
				$site_ids = get_sites(
					array(
						'fields' => 'ids',
						'number' => 0,
					)
				);

				foreach ( $site_ids as $site_id ) {
					switch_to_blog( $site_id ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog -- only the database context is needed to drop per-site tables and options.
					self::uninstall_oauth();
					restore_current_blog();
				}
			} else {
				self::uninstall_oauth();
			}

			$api_url = 'https://feedbackpa.leap13.com/wp-json/uninstall/v2/add';

			$current_time = gmdate( 'j F, Y', time() );

			wp_safe_remote_request(
				$api_url,
				array(
					'headers'     => array(
						'Content-Type' => 'application/json',
					),
					'body'        => wp_json_encode(
						array(
							'time' => $current_time,
						)
					),
					'blocking'    => false,
					'timeout'     => 3,
					'method'      => 'POST',
					'httpversion' => '1.1',
				)
			);
		}

		/**
		 * Remove the current site's OAuth data. The tables are per-site, so on
		 * multisite this runs once per site.
		 *
		 * @since 4.11.90
		 * @access private
		 *
		 * @return void
		 */
		private static function uninstall_oauth() {

			global $wpdb;

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- uninstall cleanup of plugin tables.
			$wpdb->query( 'DROP TABLE IF EXISTS ' . Abilities\OAuth\Store::tokens_table() );
			$wpdb->query( 'DROP TABLE IF EXISTS ' . Abilities\OAuth\Store::clients_table() );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared

			delete_option( Abilities\OAuth\Bootstrap::OPTION_ENABLED );
			delete_option( Abilities\OAuth\Bootstrap::DCR_WINDOW );
			delete_option( Abilities\OAuth\Store::DB_VERSION_OPTION );

			wp_clear_scheduled_hook( Abilities\OAuth\Bootstrap::CRON_HOOK );
		}

		/**
		 * Load plugin translated strings using text domain
		 *
		 * @since 2.6.8
		 * @access public
		 *
		 * @return void
		 */
		public function i18n() {

			load_plugin_textdomain( 'premium-addons-for-elementor' );
		}

		/**
		 * Init
		 *
		 * @since 3.4.0
		 * @access public
		 *
		 * @return void
		 */
		public function init() {

			if ( ! $this->is_templates_request() ) {
				return;
			}

			if ( is_user_logged_in() && \PremiumAddons\Admin\Includes\Admin_Helper::check_premium_templates() ) {
				require_once PREMIUM_ADDONS_PATH . 'includes/templates/templates.php';
			}
		}

		/**
		 * Is Templates Request
		 *
		 * Make sure templates are only loaded on editor.
		 *
		 * @since 4.11.92
		 * @access private
		 *
		 * @return boolean
		 */
		private function is_templates_request() {

			if ( ! is_admin() ) {
				return false;
			}

			$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			// Editor page: wp-admin/post.php?post=ID&action=elementor.
			if ( 'elementor' === $action ) {
				return true;
			}

			if ( ! wp_doing_ajax() ) {
				return false;
			}

			return in_array(
				$action,
				array(
					'premium_get_templates',
					'premium_inner_template',
					'get_pa_element_data',
					'elementor_ajax',
				),
				true
			);
		}


		/**
		 * Creates and returns an instance of the class
		 *
		 * @since 2.6.8
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
}

if ( ! function_exists( 'pa_core' ) ) {

	/**
	 * Returns an instance of the plugin class.
	 *
	 * @since  1.0.0
	 * @return object
	 */
	function pa_core() {
		return PA_Core::get_instance();
	}
}

pa_core();
