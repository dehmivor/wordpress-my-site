<?php
/**
 * PA Display Conditions::Control-Handler.
 * Handles controls used in PA Display Conditions Addon.
 */

namespace PremiumAddons\Includes;

use Elementor\Controls_Manager;
use Elementor\Repeater;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class PA_Controls_Handler
 *
 * @since 4.7.0
 */
class PA_Controls_Handler {

	/**
	 * Contains Conditions Classes.
	 *
	 * @access public
	 * @var array condition classes
	 */
	public static $conditions_classes = array();

	/**
	 * Contains Conditions Keys.
	 *
	 * @access public
	 * @var array condition keys
	 */
	public static $conditions_keys = array();

	/**
	 * Holds all the conditions.
	 * Contains Conditions.
	 *
	 * @access public
	 * @var array condition.
	 */
	public static $conditions = array();

	/**
	 * Holds all the conditions.
	 *
	 * @since 4.7.0
	 * @access protected
	 * @var array condition results holder.
	 */
	protected $conditions_results_holder = array();

	/**
	 * Class object
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Class Constructor.
	 */
	public function __construct() {

		// Only the key list is built here. This constructor runs on every request
		// — front end, admin, AJAX and cron — so the translated label array and
		// the condition objects are resolved on demand instead: the labels are
		// only ever read while registering controls, and at most a handful of
		// condition classes are needed to render a page.
		$this->init_conditions_keys();

		$is_edit_mode = \Elementor\Plugin::$instance->editor->is_edit_mode();

		// Trigger should_rendr filters only on the frontend.
		if ( ! $is_edit_mode ) {
			$this->init_actions();
		}
	}

	/**
	 * The grouped condition labels shown in the control dropdown, built on first
	 * use. Roughly sixty translated strings that are only needed while Elementor
	 * registers controls, so they are not built during the constructor.
	 *
	 * @access public
	 * @since 4.11.90
	 *
	 * @return array
	 */
	public static function get_conditions() {

		if ( empty( static::$conditions ) ) {
			self::init_conditions();
		}

		return static::$conditions;
	}

	/**
	 * Initialize condition classes.
	 *
	 * @access public
	 * @since 4.7.0
	 */
	public static function init_conditions() {

		static::$conditions = array(
			'system'    => array(
				'label'   => __( 'System', 'premium-addons-for-elementor' ),
				'options' => array(
					'browser'          => __( 'Browser', 'premium-addons-for-elementor' ),
					'device'           => __( 'Device', 'premium-addons-for-elementor' ),
					'operating_system' => __( 'Operating System', 'premium-addons-for-elementor' ),
				),
			),

			'time'      => array(
				'label'   => __( 'Date & Time', 'premium-addons-for-elementor' ),
				'options' => array(
					'day'        => __( 'Day', 'premium-addons-for-elementor' ),
					'date'       => __( 'Date', 'premium-addons-for-elementor' ),
					'date_range' => __( 'Date Range', 'premium-addons-for-elementor' ),
					'time_range' => __( 'Time Range', 'premium-addons-for-elementor' ),
				),
			),

			'userdata'  => array(
				'label'   => __( 'User', 'premium-addons-for-elementor' ),
				'options' => array(
					'ip_location'    => __( 'Location', 'premium-addons-for-elementor' ),
					'login_status'   => __( 'Login Status', 'premium-addons-for-elementor' ),
					'user_role'      => __( 'Role', 'premium-addons-for-elementor' ),
					'return_visitor' => __( 'Returning Visitor', 'premium-addons-for-elementor' ),
				),
			),

			'other'     => array(
				'label'   => __( 'Other', 'premium-addons-for-elementor' ),
				'options' => array(
					'lang' => __( 'Site Language', 'premium-addons-for-elementor' ),
				),
			),

			'postdata'  => array(
				'label'   => __( 'Post/Page', 'premium-addons-for-elementor' ),
				'options' => array(
					'post'          => __( 'Post', 'premium-addons-for-elementor' ),
					'post_type'     => __( 'Post Type', 'premium-addons-for-elementor' ),
					'post_format'   => __( 'Post Format', 'premium-addons-for-elementor' ),
					'post_category' => __( 'Post Category', 'premium-addons-for-elementor' ),
					'page'          => __( 'Page', 'premium-addons-for-elementor' ),
					'static_page'   => __( 'Website Static Pages', 'premium-addons-for-elementor' ),
				),
			),

			'urlparams' => array(
				'label'   => __( 'URL (PRO)', 'premium-addons-for-elementor' ),
				'options' => array(
					'url_string'  => __( 'String in URL', 'premium-addons-for-elementor' ),
					'url_referer' => __( 'URL Parameters', 'premium-addons-for-elementor' ),
				),
			),

			'misc'      => array(
				'label'   => __( 'Misc (PRO)', 'premium-addons-for-elementor' ),
				'options' => array(
					'shortcode' => __( 'Shortcode', 'premium-addons-for-elementor' ),
				),
			),

		);
	}

	/**
	 * Initialize condition classes.
	 *
	 * @access public
	 * @since 4.7.0
	 */
	public function init_conditions_keys() {

		self::$conditions_keys = apply_filters(
			'pa_display_conditions_keys',
			array(
				'browser',
				'device',
				'day',
				'date',
				'date_range',
				'time_range',
				'ip_location',
				'lang',
				'login_status',
				'return_visitor',
				'post',
				'post_type',
				'post_format',
				'post_category',
				'page',
				'static_page',
				'operating_system',
				'user_role',
			)
		);
	}

	/**
	 * Resolve a single condition object, including its file on first use.
	 *
	 * Loading every condition up front cost one file_exists plus one include_once
	 * per key — eighteen by default, up to thirty-four once PRO, ACF and
	 * WooCommerce extend the list — on every request, admin, AJAX and cron
	 * included. A page only needs the conditions its own elements use.
	 *
	 * @access public
	 * @since 4.11.90
	 *
	 * @param string $condition_key Condition key.
	 * @return object|null Condition object, or null when the key has no class.
	 */
	public static function get_condition_class( $condition_key ) {

		if ( isset( static::$conditions_classes[ $condition_key ] ) ) {
			return static::$conditions_classes[ $condition_key ];
		}

		if ( ! in_array( $condition_key, self::$conditions_keys, true ) ) {
			return null;
		}

		$base = __NAMESPACE__ . '\PA_Display_Conditions\Conditions\Condition';

		if ( ! class_exists( $base ) ) {
			include_once PREMIUM_ADDONS_PATH . 'includes/pa-display-conditions/conditions/condition.php';
		}

		$file_name = str_replace( '_', '-', strtolower( $condition_key ) );
		$file_path = PREMIUM_ADDONS_PATH . 'includes/pa-display-conditions/conditions/' . $file_name . '.php';

		if ( file_exists( $file_path ) ) {
			include_once $file_path;
		}

		$class_name = str_replace( '-', ' ', $condition_key );
		$class_name = str_replace( ' ', '', ucwords( $class_name ) );
		$class_name = __NAMESPACE__ . '\PA_Display_Conditions\Conditions\\' . $class_name;

		if ( ! class_exists( $class_name ) ) {
			return null;
		}

		static::$conditions_classes[ $condition_key ] = new $class_name();

		return static::$conditions_classes[ $condition_key ];
	}

	/**
	 * Set render function to action filter.
	 *
	 * @access public
	 * @since 4.7.0
	 */
	public function init_actions() {

		add_filter( 'elementor/frontend/widget/should_render', array( $this, 'should_render' ), 10, 2 );
		add_filter( 'elementor/frontend/column/should_render', array( $this, 'should_render' ), 10, 2 );
		add_filter( 'elementor/frontend/section/should_render', array( $this, 'should_render' ), 10, 2 );

		add_filter( 'elementor/frontend/container/should_render', array( $this, 'should_render' ), 10, 2 );
	}

	/**
	 * Adds repeater source controls
	 *
	 * @since 4.7.0
	 * @access public
	 *
	 * @param object $repeater Elementor Repeater Object.
	 */
	public function add_repeater_source_controls( $repeater ) {

		$additional_ids = array( 'pa_condition_shortcode', 'pa_condition_acf_text', 'pa_condition_acf_boolean', 'pa_condition_acf_choice', 'pa_condition_woo_orders', 'pa_condition_woo_category', 'pa_condition_woo_total_price', 'pa_condition_time_range' );

		foreach ( self::$conditions_keys as $condition_class_name ) {

			$control_id = 'pa_condition_' . $condition_class_name;

			if ( ! in_array( $control_id, $additional_ids, true ) ) {
				continue;
			}

			$condition_obj = self::get_condition_class( $condition_class_name );

			if ( null === $condition_obj ) {
				continue;
			}

			$repeater->add_control(
				'pa_condition_val' . $condition_class_name,
				$condition_obj->add_value_control()
			);
		}
	}

	/**
	 * Adds repeater compare controls
	 *
	 * @since 4.7.0
	 * @access public
	 *
	 * @param object $repeater Elementor Repeater Object.
	 */
	public function add_repeater_compare_controls( $repeater ) {

		foreach ( self::$conditions_keys as $condition_class_name ) {

			$condition_obj = self::get_condition_class( $condition_class_name );

			if ( null === $condition_obj ) {
				continue;
			}

			$repeater->add_control(
				'pa_condition_' . $condition_class_name,
				$condition_obj->get_control_options()
			);

		}
	}

	/**
	 * Determines whether the element content should be rendered.
	 *
	 * @param bool   $should_render      should render.
	 * @param object $element Elementor  Repeater Object.
	 *
	 * @since 4.7.0
	 * @access public
	 */
	public function should_render( $should_render, $element ) {

		$settings = $element->get_settings_for_display();

		if ( 'yes' === $element->get_settings_for_display( 'pa_display_conditions_switcher' ) ) {

			$element_id      = $element->get_id();
			$conditions_list = $settings['pa_condition_repeater'];
			$action          = $settings['pa_display_action'];

			$this->store_condition_results( $settings, $element_id, $conditions_list );

			return $this->check_visiblity( $element_id, $settings['pa_display_when'], $action );

		}

		return $should_render;
	}

	/**
	 * Store conditions results
	 *
	 * @since 4.7.0
	 * @access protected
	 *
	 * @param array  $settings    elements settings.
	 * @param string $element_id  elements id.
	 * @param array  $lists       conditions.
	 */
	protected function store_condition_results( $settings, $element_id, $lists = array() ) {

		if ( ! $lists ) {
			return;
		}

		foreach ( $lists as $key => $list ) {

			if ( ! in_array( $list['pa_condition_key'], self::$conditions_keys, true ) ) {
				continue;
			}

			$class = self::get_condition_class( $list['pa_condition_key'] );

			if ( null === $class ) {
				continue;
			}

			$operator = $list['pa_condition_operator'];
			$item_key = 'pa_condition_' . $list['pa_condition_key'];
			$value    = isset( $list[ $item_key ] ) ? $list[ $item_key ] : '';

			$compare_val = isset( $list[ 'pa_condition_val' . $list['pa_condition_key'] ] ) ? $list[ 'pa_condition_val' . $list['pa_condition_key'] ] : '';

			if ( 'shortcode' !== $list['pa_condition_key'] ) {
				$compare_val = esc_html( $compare_val );
			}

			$id        = $item_key . '_' . $list['_id'];
			$time_zone = in_array( $list['pa_condition_key'], array( 'date_range', 'time_range', 'date', 'day' ), true ) ? $list['pa_condition_timezone'] : false;

			if ( 'ip_location' !== $list['pa_condition_key'] ) {

				// If ACF Text or Time Range, comparison must triggered.
				$check = ( in_array( $list['pa_condition_key'], array( 'time_range', 'acf_text' ) ) || '' !== $value ) ? $class->compare_value( $settings, $operator, $value, $compare_val, $time_zone ) : true;
			} else {

				$check = $class->compare_location( $settings, $operator, $value, $compare_val, $time_zone );
			}

			$this->conditions_results_holder[ $element_id ][ $id ] = $check;
		}
	}

	/**
	 * Check Element Visibility
	 *
	 * @since 4.7.0
	 * @access public
	 *
	 * @param string $element_id    element id.
	 * @param string $relation      condition relation.
	 * @param string $action        action to make if the conditions are met.
	 *
	 * @return bool
	 */
	public function check_visiblity( $element_id, $relation, $action ) {
		$result        = true;
		$should_render = true;

		// Nothing was evaluated for this element, so there is no reason to hide it.
		if ( ! array_key_exists( $element_id, $this->conditions_results_holder ) ) {
			return $should_render;
		}

		if ( 'all' === $relation ) {

			$result = in_array( false, $this->conditions_results_holder[ $element_id ], true ) ? false : true;
		} else {

			$result = in_array( true, $this->conditions_results_holder[ $element_id ], true ) ? true : false;
		}

		if ( ( 'show' === $action && $result ) || ( 'hide' === $action && false === $result ) ) {
			$should_render = true;
		} elseif ( ( 'show' === $action && false === $result ) || ( 'hide' === $action && $result ) ) {

			$should_render = false;
		}

		return $should_render;
	}

	/**
	 * Returns an instance of this class.
	 *
	 * @since 4.11.94
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
