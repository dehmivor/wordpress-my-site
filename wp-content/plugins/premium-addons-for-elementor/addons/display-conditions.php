<?php
/**
 * Class: Display_Conditions
 * Name : Display Conditions
 * Slug : display-conditions
 */

namespace PremiumAddons\Addons;

// PremiumAddons Classes.
use PremiumAddons\Includes\Helper_Functions;
use PremiumAddons\Includes\PA_Controls_Handler;

// Elementor Classes.
use Elementor\Repeater;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Display_Conditions
 *
 * @since 4.11.58
 * @access public
 * @package PremiumAddons\Addons
 */
class Display_Conditions {

	/**
	 * Load Script
	 *
	 * @var bool|null
	 */
	private static $load_script = null;

	/**
	 * Class object
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Constructor
	 */
	public function __construct() {

		// Enqueue the required JS file.
		add_action( 'elementor/preview/enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		add_action( 'elementor/element/section/section_advanced/after_section_end', array( $this, 'register_controls' ), 10 );
		add_action( 'elementor/element/column/section_advanced/after_section_end', array( $this, 'register_controls' ), 10 );
		add_action( 'elementor/element/common/_section_style/after_section_end', array( $this, 'register_controls' ), 10 );

		add_action( 'elementor/frontend/before_render', array( $this, 'check_script_enqueue' ) );

		add_action( 'elementor/element/container/section_layout/after_section_end', array( $this, 'register_controls' ), 10 );
	}

	/**
	 * Enqueue scripts.
	 *
	 * Registers required dependencies for the extension and enqueues them.
	 *
	 * @since 4.9.21
	 * @access public
	 */
	public function enqueue_scripts() {

		if ( ! wp_script_is( 'pa-dis-conditions', 'enqueued' ) ) {
			wp_enqueue_script( 'pa-dis-conditions' );
		}
	}

	/**
	 * Register PA Display Conditions controls.
	 *
	 * @access public
	 * @param object $element for current element.
	 */
	public function register_controls( $element ) {

		$element->start_controls_section(
			'section_pa_display_conditions',
			array(
				'label' => sprintf( '<i class="pa-extension-icon pa-dash-icon"></i> %s', __( 'Display Conditions', 'premium-addons-for-elementor' ) ),
				'tab'   => Controls_Manager::TAB_ADVANCED,
			)
		);

		$element->add_control(
			'pa_dc_cache_notice',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => __( 'Please note that you need to <b>deactivate</b> the <a href="https://elementor.com/help/element-caching-help/" style="color: var(--e-a-color-warning);" target="_blank">Element Caching</a> feature to avoid any conflicts', 'premium-addons-for-elementor' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
				'condition'       => array(
					'pa_display_conditions_switcher' => 'yes',
				),
			)
		);

		$controls_obj = PA_Controls_Handler::get_instance();

		$options = PA_Controls_Handler::get_conditions();

		$options['acf'] = array(
			'label'   => __( 'ACF (PRO)', 'premium-addons-for-elementor' ),
			'options' => array(
				'acf_choice'  => __( 'Choice', 'premium-addons-for-elementor' ),
				'acf_text'    => __( 'Text', 'premium-addons-for-elementor' ),
				'acf_boolean' => __( 'True/False', 'premium-addons-for-elementor' ),
			),
		);

		$options['woocommerce'] = array(
			'label'   => __( 'WooCommerce (PRO)', 'premium-addons-for-elementor' ),
			'options' => array(
				'woo_cat_page'          => __( 'Current Category Page', 'premium-addons-for-elementor' ),
				'woo_product_cat'       => __( 'Current Product Category', 'premium-addons-for-elementor' ),
				'woo_product_price'     => __( 'Current Product Price', 'premium-addons-for-elementor' ),
				'woo_product_stock'     => __( 'Current Product Stock', 'premium-addons-for-elementor' ),
				'woo_orders'            => __( 'Purchased/In Cart Orders', 'premium-addons-for-elementor' ),
				'woo_category'          => __( 'Purchased/In Cart Categories', 'premium-addons-for-elementor' ),
				'woo_last_purchase'     => __( 'Last Purchase In Cart', 'premium-addons-for-elementor' ),
				'woo_total_price'       => __( 'Amount In Cart', 'premium-addons-for-elementor' ),
				'woo_cart_products'     => __( 'Products In Cart', 'premium-addons-for-elementor' ),
				'woo_purchase_products' => __( 'Purchased Products', 'premium-addons-for-elementor' ),
			),
		);

		$options = apply_filters( 'pa_display_conditions', $options );

		$element->add_control(
			'pa_display_conditions_switcher',
			array(
				'label'              => __( 'Enable Display Conditions', 'premium-addons-for-elementor' ),
				'type'               => Controls_Manager::SWITCHER,
				'return_value'       => 'yes',
				'render_type'        => 'template',
				'prefix_class'       => 'pa-display-conditions-',
				'frontend_available' => true,
			)
		);

		$element->add_control(
			'pa_display_action',
			array(
				'label'     => __( 'Action', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'show',
				'options'   => array(
					'show' => __( 'Show Element', 'premium-addons-for-elementor' ),
					'hide' => __( 'Hide Element', 'premium-addons-for-elementor' ),
				),
				'condition' => array(
					'pa_display_conditions_switcher' => 'yes',
				),
			)
		);

		$element->add_control(
			'pa_display_when',
			array(
				'label'     => __( 'Display When', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'any',
				'options'   => array(
					'any' => __( 'Any Condition is Met', 'premium-addons-for-elementor' ),
					'all' => __( 'All Conditions Are Met', 'premium-addons-for-elementor' ),
				),
				'condition' => array(
					'pa_display_conditions_switcher' => 'yes',
				),
			)
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'pa_condition_key',
			array(
				'label'       => __( 'Type', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SELECT,
				'groups'      => $options,
				'default'     => 'browser',
				'label_block' => true,
			)
		);

		$options_conditions = apply_filters(
			'pa_pro_display_conditions',
			array(
				'url_string',
				'url_referer',
				'shortcode',
				'woo_orders',
				'woo_cat_page',
				'woo_category',
				'woo_product_price',
				'woo_product_stock',
				'woo_product_cat',
				'woo_last_purchase',
				'woo_total_price',
				'woo_purchase_products',
				'woo_cart_products',
				'acf_choice',
				'acf_text',
				'acf_boolean',
			)
		);

		$get_pro = Helper_Functions::get_campaign_link( 'https://premiumaddons.com/pro', 'conditions-addon', 'wp-editor', 'get-pro' );

		$repeater->add_control(
			'display_conditions_notice',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => __( 'This option is available in Premium Addons Pro. ', 'premium-addons-for-elementor' ) . '<a href="' . esc_url( $get_pro ) . '" target="_blank">' . __( 'Upgrade now!', 'premium-addons-for-elementor' ) . '</a>',
				'content_classes' => 'papro-upgrade-notice',
				'condition'       => array(
					'pa_condition_key' => $options_conditions,
				),
			)
		);

		$controls_obj->add_repeater_source_controls( $repeater );

		$repeater->add_control(
			'pa_condition_operator',
			array(
				'type'        => Controls_Manager::SELECT,
				'default'     => 'is',
				'label_block' => true,
				'options'     => array(
					'is'  => __( 'Is', 'premium-addons-for-elementor' ),
					'not' => __( 'Is Not', 'premium-addons-for-elementor' ),
				),
				'condition'   => array(
					'pa_condition_key!' => $options_conditions,
				),
			)
		);

		$repeater->add_control(
			'pa_condition_validate',
			array(
				'label'       => __( 'Compare', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'default',
				'label_block' => true,
				'options'     => array(
					'default' => __( 'Default', 'premium-addons-for-elementor' ),
					'after'   => __( 'After', 'premium-addons-for-elementor' ),
					'before'  => __( 'Before', 'premium-addons-for-elementor' ),
				),
				'condition'   => array(
					'pa_condition_key'      => 'date',
					'pa_condition_operator' => 'is',
				),
			)
		);

		$controls_obj->add_repeater_compare_controls( $repeater );

		$repeater->add_control(
			'pa_condition_timezone',
			array(
				'label'       => 'Timezone',
				'type'        => Controls_Manager::SELECT,
				'default'     => 'server',
				'label_block' => true,
				'options'     => array(
					'local'  => __( 'Local Time', 'premium-addons-for-elementor' ),
					'server' => __( 'Server Timezone', 'premium-addons-for-elementor' ),
				),
				'condition'   => array(
					'pa_condition_key' => array( 'date_range', 'time_range', 'date', 'day' ),
				),
			)
		);

		$values = $repeater->get_controls();

		$element->add_control(
			'pa_condition_repeater',
			array(
				'label'         => __( 'Conditions', 'premium-addons-for-elementor' ),
				'type'          => Controls_Manager::REPEATER,
				'label_block'   => true,
				'fields'        => $values,
				'title_field'   => '<# print( pa_condition_key.replace(/_/g, " ").split(" ").map((s) => s.charAt(0).toUpperCase() + s.substring(1)).join(" ")) #>',
				'prevent_empty' => false,
				'condition'     => array(
					'pa_display_conditions_switcher' => 'yes',
				),
			)
		);

		$this->add_helpful_information( $element );

		$element->end_controls_section();
	}

	/**
	 * Add Help & Docs
	 *
	 * @since 4.9.39
	 * @access private
	 * @param object $element for current element.
	 *
	 * @return void
	 */
	private function add_helpful_information( $element ) {

		$element->add_control(
			'pa_condition_info',
			array(
				'label'     => __( 'Help & Docs', 'premium-addons-for-elementor' ),
				'separator' => 'before',
				'type'      => Controls_Manager::HEADING,
				'condition' => array(
					'pa_display_conditions_switcher' => 'yes',
				),
			)
		);

		$docs = array(
			'https://premiumaddons.com/docs/elementor-display-conditions-tutorial/' => __( 'Getting started »', 'premium-addons-for-elementor' ),
			'https://www.youtube.com/watch?v=ydv343MTf4w/' => __( 'Check the video tutorial »', 'premium-addons-for-elementor' ),
			'https://premiumaddons.com/docs/elementor-editor-not-loading-with-display-conditions/' => __( 'Fix editor not loading with Display Conditions enabled »', 'premium-addons-for-elementor' ),
			'https://premiumaddons.com/docs/how-to-show-hide-element-based-on-browser-elementor-display-conditions/' => __( 'Show/Hide Element Based on Browser »', 'premium-addons-for-elementor' ),
			'https://premiumaddons.com/docs/how-to-show-hide-element-on-specific-time-range-elementor-display-conditions/' => __( 'Show/Hide Element Based on Time Range »', 'premium-addons-for-elementor' ),
			'https://premiumaddons.com/docs/how-to-show-hide-element-with-location-elementor-display-conditions/' => __( 'Show/Hide Element Based on Location »', 'premium-addons-for-elementor' ),
		);

		$doc_index = 1;
		foreach ( $docs as $url => $title ) {

			$doc_url = Helper_Functions::get_campaign_link( $url, 'conditions-addon', 'wp-editor', 'get-support' );

			$element->add_control(
				'pa_condition_doc_' . $doc_index,
				array(
					'type'            => Controls_Manager::RAW_HTML,
					'raw'             => sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $doc_url ), esc_html( $title ) ),
					'content_classes' => 'editor-pa-doc',
					'condition'       => array(
						'pa_display_conditions_switcher' => 'yes',
					),
				)
			);

			++$doc_index;

		}
	}

	/**
	 * Check Script Enqueue
	 *
	 * Check if the script files should be loaded.
	 *
	 * @since 4.9.21
	 * @access public
	 *
	 * @param object $element for current element.
	 */
	public function check_script_enqueue( $element ) {

		if ( self::$load_script ) {
			return;
		}

		$settings = $element->get_active_settings();

		if ( ! empty( $settings['pa_display_conditions_switcher'] ) ) {

			$this->enqueue_scripts();

			self::$load_script = true;

			remove_action( 'elementor/frontend/before_render', array( $this, 'check_script_enqueue' ) );
		}
	}

	/**
	 * Returns an instance of this class.
	 *
	 * @access public
	 */
	public static function get_instance() {

		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
