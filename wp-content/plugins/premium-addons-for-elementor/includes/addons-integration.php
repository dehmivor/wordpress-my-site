<?php
/**
 * Addons Integration.
 */

namespace PremiumAddons\Includes;

use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Includes\Helpers\AJAX_Helper;
use PremiumAddons\Modules\Woocommerce\Module as Woocommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Class Addons_Integration.
 */
class Addons_Integration {

	use AJAX_Helper;

	/**
	 * Class instance
	 *
	 * @var instance
	 */
	private static $instance = null;

	/**
	 * CSS Content
	 *
	 * @var css_content
	 */
	private static $css_content = null;

	/**
	 * Modules
	 *
	 * @var modules
	 */
	private static $modules = null;

	/**
	 * Integrations Keys
	 *
	 * @var integrations
	 */
	private static $integrations = null;

	/**
	 * Initialize integration hooks
	 *
	 * @return void
	 */
	public function __construct() {

		if ( ! Helper_Functions::check_elementor_version() ) {
			return;
		}

		if ( null === self::$modules ) {
			self::$modules      = Admin_Helper::get_enabled_elements();
			self::$integrations = Admin_Helper::get_integrations_settings();
		}

		$this->register_hooks();

		$this->register_ajax_hooks();

		$this->load_extensions();

		// Promote PAPRO Elements. Runs late because Elementor overwrites `v4Promotions` at priority 10.
		add_filter( 'elementor/editor/localize_settings', array( $this, 'add_papro_elements' ), 20 );

		// Handle everything related to widgets assets.
		Assets_Manager::get_instance( self::$modules, self::$integrations );
	}

	/**
	 * Register Hooks
	 *
	 * @since 4.11.53
	 * @access public
	 */
	public function register_hooks() {

		// Load Editor CSS Files.
		add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue_preview_styles' ) );
		add_action( 'elementor/editor/before_enqueue_styles', array( $this, 'enqueue_editor_styles' ) );

		// Load Editor JS Files.
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'before_enqueue_scripts' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'after_enqueue_scripts' ) );

		// Register Controls and Widgets Area.
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_widgets_category' ), 9 );
		add_action( 'elementor/controls/register', array( $this, 'register_controls' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	/**
	 * After Enqueue Scripts
	 *
	 * Loads editor scripts for our controls.
	 *
	 * @access public
	 * @return void
	 */
	public function after_enqueue_scripts() {

		wp_enqueue_script(
			'pa-controls-handlers',
			PREMIUM_ADDONS_URL . 'assets/editor/js/controls-handlers.js',
			array( 'elementor-editor', 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_localize_script(
			'pa-controls-handlers',
			'PremiumSettings',
			array(
				'ajaxurl'      => esc_url( admin_url( 'admin-ajax.php' ) ),
				'nonce'        => wp_create_nonce( 'pa-blog-widget-nonce' ),
				'upgrade_link' => Helper_Functions::get_campaign_link( 'https://premiumaddons.com/pro/', '', 'wp-editor', 'get-pro' ),
			)
		);

		$data = array(
			'ajaxurl' => esc_url( admin_url( 'admin-ajax.php' ) ),
			'nonce'   => wp_create_nonce( 'pa-editor' ),
		);

		wp_enqueue_script(
			'pa-editor-handler',
			PREMIUM_ADDONS_URL . 'assets/editor/js/editor-handler.js',
			array( 'elementor-editor' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_localize_script( 'pa-editor-handler', 'paEditorSettings', $data );
	}

	/**
	 * Loads plugin icons font
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function enqueue_editor_styles() {

		$theme = Helper_Functions::get_elementor_ui_theme();

		wp_enqueue_style(
			'pa-editor',
			PREMIUM_ADDONS_URL . 'assets/editor/css/style.css',
			array(),
			PREMIUM_ADDONS_VERSION
		);

		// Enqueue required style for Elementor dark UI Theme.
		if ( 'dark' === $theme ) {

			wp_add_inline_style(
				'pa-editor',
				'.elementor-panel .elementor-control-section_pa_docs .elementor-panel-heading-title.elementor-panel-heading-title,
				.elementor-control-raw-html.editor-pa-doc a {
					color: #e0e1e3 !important;
				}
				[class^="pa-"]::after,
				[class*=" pa-"]::after {
					color: #aaa;
                    opacity: 1 !important;
				}
                .premium-promotion-dialog .premium-promotion-btn {
                    background-color: #202124 !important
                }
				.premium-promote-ctas .elementor-button.premium-promote-upgrade:hover {
					background-color: #999C9E !important;
				}
				.premium-promote-ctas .elementor-button.premium-promote-demo {
                    color: #BFC3C7 !important;
					background-color: #373C41 !important;
					border: 1px solid rgba(154, 157, 160, 0.5) !important;
                }

				.premium-promote-ctas .elementor-button.premium-promote-demo:hover {
					background-color: #2B2E32 !important;
                }'
			);

		}

		$badge_text = Helper_Functions::get_badge();

		$dynamic_css = sprintf( '.elementor-element-wrapper:not(.elementor-element--promotion) [class^="pa-"]::after, .elementor-element-wrapper:not(.elementor-element--promotion) [class*="  pa-"]::after { content: "%s"; }', $badge_text );

		wp_add_inline_style( 'pa-editor', $dynamic_css );
	}

	/**
	 * Enqueue Preview CSS files
	 *
	 * @since 2.9.0
	 * @access public
	 */
	public function enqueue_preview_styles() {

		wp_enqueue_style(
			'pa-preview',
			PREMIUM_ADDONS_URL . 'assets/editor/templates/css/preview.css',
			array(),
			PREMIUM_ADDONS_VERSION,
			'all'
		);

		wp_enqueue_style( 'pa-prettyphoto' );

		wp_enqueue_style( 'premium-addons' );

		wp_enqueue_style( 'pa-slick' );
	}

	/**
	 * Register Widgets Category
	 *
	 * Register a new category for Premium Addons widgets
	 *
	 * @since 4.0.0
	 * @access public
	 *
	 * @param object $elements_manager elements manager.
	 */
	public function register_widgets_category( $elements_manager ) {

		$elements_manager->add_category(
			'premium-elements',
			array(
				'title' => Helper_Functions::get_category(),
			),
			1
		);
	}

	/**
	 * Load widgets require function
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function register_widgets( $widgets_manager ) {

		$enabled_elements = Helper_Functions::get_enabled_widgets();

		foreach ( $enabled_elements as $key ) {

			$class = Helper_Functions::get_widget_class_name( $key );

			if ( ! $class ) {
				continue;
			}

			if ( 'PremiumAddons\Widgets\Premium_Contactform' === $class && ! function_exists( 'wpcf7' ) ) {
				continue;
			}

			$this->load_widget_files( $class );

			$widgets_manager->register( new $class() );

		}
	}

	public function load_widget_files( $class ) {

		if ( 'PremiumAddons\Widgets\Premium_Weather' === $class ) {
			require_once PREMIUM_ADDONS_PATH . 'widgets/dep/pa-weather-handler.php';
		}

		if ( in_array( $class, array( 'PremiumAddons\Widgets\Premium_Pinterest_Feed', 'PremiumAddons\Widgets\Premium_Tiktok_Feed' ), true ) ) {

			if ( 'PremiumAddons\Widgets\Premium_Pinterest_Feed' == $class ) {
				require_once PREMIUM_ADDONS_PATH . 'widgets/dep/pa-pins-handler.php';
			}

			if ( 'PremiumAddons\Widgets\Premium_Tiktok_Feed' == $class ) {
				require_once PREMIUM_ADDONS_PATH . 'widgets/dep/pa-tiktok-handler.php';
			}
		}
	}

	/**
	 * Enqueue editor scripts
	 *
	 * @since 3.2.5
	 * @access public
	 */
	public function before_enqueue_scripts() {

		wp_enqueue_script(
			'pa-editor-behavior',
			PREMIUM_ADDONS_URL . 'assets/editor/js/pa-editor-behavior.min.js',
			array( 'elementor-editor', 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		$map_enabled = isset( self::$modules['premium-maps'] ) ? self::$modules['premium-maps'] : 1;

		if ( $map_enabled ) {

			$premium_maps_api = self::$integrations['premium-map-api'];

			$locale = self::$integrations['premium-map-locale'] ?? 'en';

			$disable_api = self::$integrations['premium-map-disable-api'];

			if ( $disable_api && '1' !== $premium_maps_api ) {

				$api = sprintf( 'https://maps.googleapis.com/maps/api/js?key=%1$s&libraries=marker&language=%2$s&loading=async', $premium_maps_api, $locale );
				wp_enqueue_script(
					'pa-maps-api',
					$api,
					array(),
					PREMIUM_ADDONS_VERSION,
					false
				);

			}

			wp_enqueue_script(
				'pa-maps-finder',
				PREMIUM_ADDONS_URL . 'assets/editor/js/pa-maps-finder.js',
				array( 'jquery' ),
				PREMIUM_ADDONS_VERSION,
				true
			);

		}
	}

	/**
	 * Registers Premium Addons Custom Controls.
	 *
	 * @since 4.2.5
	 * @access public
	 *
	 * @return void
	 */
	public function register_controls() {

		$control_manager = \Elementor\Plugin::instance();

		if ( self::$modules['premium-equal-height'] || self::$modules['premium-pinterest-feed'] ) {

			require_once PREMIUM_ADDONS_PATH . 'includes/controls/premium-select.php';
			$premium_select = __NAMESPACE__ . '\Controls\Premium_Select';
			$control_manager->controls_manager->register( new $premium_select() );

		}

		require_once PREMIUM_ADDONS_PATH . 'includes/controls/premium-post-filter.php';

		$premium_post_filter = __NAMESPACE__ . '\Controls\Premium_Post_Filter';

		$control_manager->controls_manager->register( new $premium_post_filter() );

		if ( self::$modules['premium-blog'] || self::$modules['premium-smart-post-listing'] || self::$modules['premium-tcloud'] ) {

			require_once PREMIUM_ADDONS_PATH . 'includes/controls/premium-tax-filter.php';

			$premium_tax_filter = __NAMESPACE__ . '\Controls\Premium_Tax_Filter';

			$control_manager->controls_manager->register( new $premium_tax_filter() );
		}

		if ( self::$modules['pa-display-conditions'] && class_exists( 'ACF' ) ) {

			require_once PREMIUM_ADDONS_PATH . 'includes/controls/premium-acf-selector.php';
			$premium_acf_selector = __NAMESPACE__ . '\Controls\Premium_Acf_Selector';
			$control_manager->controls_manager->register( new $premium_acf_selector() );

		}

		if ( self::$modules['premium-shape-divider'] ) {

			require_once PREMIUM_ADDONS_PATH . 'includes/controls/pa-image-choose.php';
			$premium_image_choose = __NAMESPACE__ . '\Controls\Premium_Image_Choose';
			$control_manager->controls_manager->register( new $premium_image_choose() );

		}

		$premium_background = __NAMESPACE__ . '\Controls\Premium_Background';

		$control_manager->controls_manager->add_group_control( 'pa-background', new $premium_background() );
	}

	/**
	 * Load PA Extensions
	 *
	 * @since 4.7.0
	 * @access public
	 */
	public function load_extensions() {

		Extras\Live_Editor::get_instance();

		Extras\Angie_Integration::get_instance();

		if ( self::$modules['premium-equal-height'] ) {
			\PremiumAddons\Addons\Equal_Height::get_instance();
		}

		if ( self::$modules['premium-glassmorphism'] ) {
			\PremiumAddons\Addons\Liquid_Glass::get_instance();
		}

		if ( self::$modules['pa-display-conditions'] ) {
			require_once PREMIUM_ADDONS_PATH . 'includes/pa-display-conditions/pa-controls-handler.php';
			\PremiumAddons\Addons\Display_Conditions::get_instance();
		}

		if ( self::$modules['premium-floating-effects'] ) {
			\PremiumAddons\Addons\Floating_Effects::get_instance();
		}

		if ( class_exists( 'woocommerce' ) && ( self::$modules['woo-products'] || self::$modules['woo-categories'] || self::$modules['mini-cart'] || self::$modules['woo-cta'] ) ) {
			Woocommerce::get_instance();
		}

		if ( self::$modules['premium-global-tooltips'] ) {
			\PremiumAddons\Addons\Tooltips::get_instance();
		}

		if ( self::$modules['premium-shape-divider'] ) {
			\PremiumAddons\Addons\Shape_Divider::get_instance();
		}

		if ( self::$modules['premium-wrapper-link'] ) {
			\PremiumAddons\Addons\Wrapper_Link::get_instance();
		}

		if ( self::$modules['premium-cross-domain'] ) {
			Extras\Cross_Copy_Paste::get_instance();
		}

		if ( self::$modules['premium-contactform'] && function_exists( 'wpcf7' ) ) {
			Extras\CF7_Inserter::get_instance();
		}

		if ( ! Helper_Functions::check_papro_version() ) {
			Helpers\PAPRO_Promotion::get_instance();
		}
	}

	/**
	 * Add PAPRO Elements
	 *
	 * @since 4.10.90
	 * @access public
	 *
	 * @param array $config Elementor Config
	 */
	public function add_papro_elements( $config ) {

		$is_papro_active = Helper_Functions::check_papro_version();

		if ( $is_papro_active ) {
			return $config;
		}

		$promotion_widgets = array();

		if ( isset( $config['promotionWidgets'] ) ) {
			$promotion_widgets = $config['promotionWidgets'];
		}

		$pro_elements = Admin_Helper::get_pro_elements();

		$config['promotionWidgets'] = array_merge( $promotion_widgets, $pro_elements );

		$v4_promotions = isset( $config['v4Promotions'] ) ? $config['v4Promotions'] : array();

		$config['v4Promotions'] = array_merge( $v4_promotions, $this->get_papro_promotion_cards( $pro_elements ) );

		return $config;
	}

	/**
	 * Get PAPRO Promotion Cards
	 *
	 * Builds the panel promotion card shown when a free user clicks a PAPRO widget.
	 * Elementor matches `v4Promotions` keys against the widget name with dashes and
	 * underscores stripped, and falls back to its own Elementor Pro card when no key matches.
	 *
	 * @since 4.11.97
	 * @access private
	 *
	 * @param array $elements PAPRO elements.
	 *
	 * @return array
	 */
	private function get_papro_promotion_cards( $elements ) {

		$cards = array();

		foreach ( $elements as $element ) {

			$cards[ $element['name'] ] = array(
				/* translators: %s: Widget title. */
				'title'   => sprintf( __( 'Premium %s', 'premium-addons-for-elementor' ), $element['title'] ),
				/* translators: %s: Widget title. */
				'content' => sprintf( __( 'Get Premium %s widget and more pro widgets and addons to build better sites, faster.', 'premium-addons-for-elementor' ), $element['title'] ),
				'ctaText' => __( 'Get PRO (30% OFF)', 'premium-addons-for-elementor' ),
				'ctaUrl'  => Helper_Functions::get_campaign_link( 'https://premiumaddons.com/pro/#pro-pricing-section', 'panel-' . $element['key'], 'wp-editor', 'get-pro' ),
			);
		}

		return $cards;
	}

	/**
	 *
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
