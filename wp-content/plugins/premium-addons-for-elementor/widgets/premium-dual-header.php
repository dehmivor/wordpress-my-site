<?php
/**
 * Premium Dual Heading.
 */

namespace PremiumAddons\Widgets;

// Elementor Classes.
use PremiumAddons\Includes;
use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Box_Shadow;

// PremiumAddons Classes.
use PremiumAddons\Includes\Helper_Functions;
use PremiumAddons\Includes\Controls\Premium_Background;
use PremiumAddons\Includes\Controls\Premium_Post_Filter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // If this file is called directly, abort.
}

/**
 * Class Premium_Dual_Header
 */
class Premium_Dual_Header extends Widget_Base {

	/**
	 * Retrieve Widget Name.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function get_name() {
		return 'premium-addon-dual-header';
	}

	/**
	 * Retrieve Widget Title.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function get_title() {
		return __( 'Dual Heading', 'premium-addons-for-elementor' );
	}

	/**
	 * Retrieve Widget Dependent CSS.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return array CSS style handles.
	 */
	public function get_style_depends() {
		return array(
			'pa-glass',
			'pa-btn',
			'premium-addons',
		);
	}

	/**
	 * Retrieve Widget Dependent JS.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return array JS script handles.
	 */
	public function get_script_depends() {

		$is_edit = Helper_Functions::is_edit_mode();

		$scripts = array();
		if ( $is_edit ) {
			$scripts[] = 'pa-glass';

			if ( Helper_Functions::check_papro_version() ) {
				$scripts[] = 'pa-clip-scroll';
			}
		} else {

			$settings = $this->get_settings();

			if ( 'none' !== $settings['first_lq_effect'] || 'none' !== $settings['second_lq_effect'] ) {
				$scripts[] = 'pa-glass';
			}

			if ( Helper_Functions::check_papro_version() && ( $this->fills_on_scroll( $settings ) || $this->description_fills_on_scroll( $settings ) ) ) {
				$scripts[] = 'pa-clip-scroll';
			}
		}

		$scripts[] = 'premium-addons';

		return $scripts;
	}

	/**
	 * Check if the description runs the fill on scroll effect.
	 *
	 * @since 4.11.105
	 * @access private
	 *
	 * @param array $settings widget settings.
	 *
	 * @return bool
	 */
	private function description_fills_on_scroll( $settings ) {
		return 'yes' === $settings['premium_dual_header_desc_switcher'] && 'yes' === $settings['desc_clip_scroll'];
	}

	/**
	 * Check if the fill on scroll effect runs.
	 *
	 * Re-checks the control conditions because Elementor keeps stale values.
	 *
	 * @since 4.11.105
	 * @access private
	 *
	 * @param array $settings widget settings.
	 *
	 * @return bool
	 */
	private function fills_on_scroll( $settings ) {
		return 'yes' === $settings['clip_scroll']
			&& 'yes' !== $settings['mask_switcher']
			&& 'yes' !== $settings['noise_first']
			&& 'yes' !== $settings['noise_second'];
	}

	/**
	 * Wrap each word in its own span so the fill effect can run word by word.
	 *
	 * @since 4.11.105
	 * @access private
	 *
	 * @param string $text heading text.
	 *
	 * @return string words markup.
	 */
	private function get_clipped_words( $text ) {

		$words = preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );

		if ( empty( $words ) ) {
			return '';
		}

		$words_html = '';

		foreach ( $words as $word ) {
			$words_html .= '<span class="pa-clip-word">' . esc_html( $word ) . '</span> ';
		}

		return trim( $words_html );
	}

	/**
	 * Retrieve Widget Icon.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return string widget icon.
	 */
	public function get_icon() {
		return 'pa-dual-header';
	}

	/**
	 * Retrieve Widget Keywords.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return array Widget keywords.
	 */
	public function get_keywords() {
		return array( 'pa', 'premium', 'premium dual heading', 'advanced', 'title', 'heading', 'multi', 'text' );
	}

	protected function is_dynamic_content(): bool {
		return false;
	}

	/**
	 * Retrieve Widget Categories.
	 *
	 * @since 1.5.1
	 * @access public
	 *
	 * @return array Widget categories.
	 */
	public function get_categories() {
		return array( 'premium-elements' );
	}

	/**
	 * Retrieve Widget Support URL.
	 *
	 * @access public
	 *
	 * @return string support URL.
	 */
	public function get_custom_help_url() {
		return 'https://premiumaddons.com/support/';
	}

	public function has_widget_inner_wrapper(): bool {
		return false;
	}

	/**
	 * Register Dual Heading controls.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function register_controls() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore

		/*Start General Section*/
		$this->start_controls_section(
			'premium_dual_header_general_settings',
			array(
				'label' => __( 'Dual Heading', 'premium-addons-for-elementor' ),
			)
		);

		$demo = Helper_Functions::get_campaign_link( 'https://premiumaddons.com/dual-header-widget-for-elementor-page-builder/', 'dual', 'wp-editor', 'demo' );
		Helper_Functions::add_templates_controls( $this, 'dual-heading', $demo );

		/*First Header*/
		$this->add_control(
			'premium_dual_header_first_header_text',
			array(
				'label'       => __( 'First Heading', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'default'     => __( 'Premium', 'premium-addons-for-elementor' ),
				'label_block' => true,
			)
		);

		/*Second Header*/
		$this->add_control(
			'premium_dual_header_second_header_text',
			array(
				'label'       => __( 'Second Heading', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'default'     => __( 'Addons', 'premium-addons-for-elementor' ),
				'label_block' => true,
			)
		);

		/*Title Tag*/
		$this->add_control(
			'premium_dual_header_first_header_tag',
			array(
				'label'       => __( 'HTML Tag', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => array(
					'h1'   => 'H1',
					'h2'   => 'H2',
					'h3'   => 'H3',
					'h4'   => 'H4',
					'h5'   => 'H5',
					'h6'   => 'H6',
					'span' => 'span',
					'p'    => 'p',
				),
				'default'     => 'h2',
				'label_block' => true,
			)
		);

		$this->add_responsive_control(
			'premium_dual_header_position',
			array(
				'label'        => __( 'Display', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::SELECT,
				'options'      => array(
					'inline' => __( 'Inline', 'premium-addons-for-elementor' ),
					'block'  => __( 'Block', 'premium-addons-for-elementor' ),
				),
				'default'      => 'inline',
				'prefix_class' => 'premium-header-',
				'selectors'    => array(
					'{{WRAPPER}} .premium-dual-header-first-span, {{WRAPPER}} .premium-dual-header-second-header' => 'display: {{VALUE}}',
				),
				'label_block'  => true,
			)
		);

		$this->add_control(
			'premium_dual_header_link_switcher',
			array(
				'label'       => __( 'Link', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SWITCHER,
				'separator'   => 'before',
				'description' => __( 'Enable or disable link', 'premium-addons-for-elementor' ),
			)
		);

		$this->add_control(
			'premium_dual_heading_link_selection',
			array(
				'label'       => __( 'Link Type', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => array(
					'url'  => __( 'URL', 'premium-addons-for-elementor' ),
					'link' => __( 'Existing Page', 'premium-addons-for-elementor' ),
				),
				'default'     => 'url',
				'label_block' => true,
				'condition'   => array(
					'premium_dual_header_link_switcher' => 'yes',
				),
			)
		);

		$this->add_control(
			'premium_dual_heading_link',
			array(
				'label'       => __( 'Link', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::URL,
				'dynamic'     => array( 'active' => true ),
				'default'     => array(
					'url' => '#',
				),
				'placeholder' => 'https://premiumaddons.com/',
				'label_block' => true,
				'separator'   => 'after',
				'condition'   => array(
					'premium_dual_header_link_switcher'   => 'yes',
					'premium_dual_heading_link_selection' => 'url',
				),
			)
		);

		$this->add_control(
			'premium_dual_heading_existing_link',
			array(
				'label'       => __( 'Existing Page', 'premium-addons-for-elementor' ),
				'type'        => Premium_Post_Filter::TYPE,
				'label_block' => true,
				'multiple'    => false,
				'separator'   => 'after',
				'source'      => array( 'post', 'page' ),
				'condition'   => array(
					'premium_dual_header_link_switcher'   => 'yes',
					'premium_dual_heading_link_selection' => 'link',
				),
			)
		);

		$this->add_responsive_control(
			'premium_dual_header_text_align',
			array(
				'label'     => __( 'Alignment', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::CHOOSE,
				'separator' => 'before',
				'options'   => array(
					'left'   => array(
						'title' => __( 'Left', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => __( 'Center', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => __( 'Right', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default'   => 'center',
				'selectors' => array(
					'{{WRAPPER}}' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'first_rotate',
			array(
				'label'     => __( 'First Heading Rotation (deg)', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::NUMBER,
				'separator' => 'before',
				'min'       => -180,
				'max'       => 180,
				'selectors' => array(
					'{{WRAPPER}}.premium-header-inline .premium-dual-header-first-span' => 'display:inline-block !important; transform: rotate({{VALUE}}deg);',
					'{{WRAPPER}}.premium-header-block .premium-dual-header-first-span' => 'transform: rotate({{VALUE}}deg);',
				),
			)
		);

		$this->add_responsive_control(
			'first_transform_origin_x',
			array(
				'label'       => __( 'X Anchor Point', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::CHOOSE,
				'default'     => 'center',
				'options'     => array(
					'left'   => array(
						'title' => __( 'Left', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-h-align-left',
					),
					'center' => array(
						'title' => __( 'Center', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-h-align-center',
					),
					'right'  => array(
						'title' => __( 'Right', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'label_block' => false,
				'toggle'      => false,
				'render_type' => 'ui',
				'condition'   => array(
					'first_rotate!' => '',
				),
			)
		);

		$this->add_responsive_control(
			'first_transform_origin_y',
			array(
				'label'       => __( 'Y Anchor Point', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::CHOOSE,
				'default'     => 'center',
				'options'     => array(
					'top'    => array(
						'title' => __( 'Top', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-v-align-top',
					),
					'center' => array(
						'title' => __( 'Center', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-v-align-middle',
					),
					'bottom' => array(
						'title' => __( 'Bottom', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-v-align-bottom',
					),
				),
				'selectors'   => array(
					'{{WRAPPER}} .premium-dual-header-first-span' => 'transform-origin: {{first_transform_origin_x.VALUE}} {{VALUE}}',
				),
				'label_block' => false,
				'toggle'      => false,
				'condition'   => array(
					'first_rotate!' => '',
				),
			)
		);

		$this->add_responsive_control(
			'second_rotate',
			array(
				'label'     => __( 'Second Heading Rotation (deg)', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::NUMBER,
				'min'       => -180,
				'max'       => 180,
				'selectors' => array(
					'{{WRAPPER}}.premium-header-inline .premium-dual-header-second-header' => 'display:inline-block !important; transform: rotate({{VALUE}}deg);',
					'{{WRAPPER}}.premium-header-block .premium-dual-header-second-header' => 'transform: rotate({{VALUE}}deg);',
				),
			)
		);

		$this->add_responsive_control(
			'second_transform_origin_x',
			array(
				'label'       => __( 'X Anchor Point', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::CHOOSE,
				'default'     => 'center',
				'options'     => array(
					'left'   => array(
						'title' => __( 'Left', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-h-align-left',
					),
					'center' => array(
						'title' => __( 'Center', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-h-align-center',
					),
					'right'  => array(
						'title' => __( 'Right', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'label_block' => false,
				'toggle'      => false,
				'render_type' => 'ui',
				'condition'   => array(
					'second_rotate!' => '',
				),
			)
		);

		$this->add_responsive_control(
			'second_transform_origin_y',
			array(
				'label'       => __( 'Y Anchor Point', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::CHOOSE,
				'default'     => 'center',
				'options'     => array(
					'top'    => array(
						'title' => __( 'Top', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-v-align-top',
					),
					'center' => array(
						'title' => __( 'Center', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-v-align-middle',
					),
					'bottom' => array(
						'title' => __( 'Bottom', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-v-align-bottom',
					),
				),
				'selectors'   => array(
					'{{WRAPPER}} .premium-dual-header-second-header' => 'transform-origin: {{second_transform_origin_x.VALUE}} {{VALUE}}',
				),
				'label_block' => false,
				'toggle'      => false,
				'condition'   => array(
					'second_rotate!' => '',
				),
			)
		);

		$this->add_control(
			'background_text_switcher',
			array(
				'label'        => __( 'Background Text', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'separator'    => 'before',
				'prefix_class' => 'premium-bg-text-',
			)
		);

		$this->add_control(
			'background_text',
			array(
				'label'     => __( 'Text', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'Awesome Title', 'premium-addons-for-elementor' ),
				'condition' => array(
					'background_text_switcher' => 'yes',
				),
				'selectors' => array(
					'{{WRAPPER}}.premium-bg-text-yes .elementor-widget-container:before' => 'content: "{{VALUE}}"',
				),

			)
		);

		$this->add_control(
			'background_text_width',
			array(
				'label'       => __( 'Width', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => array(
					'auto' => __( 'Auto', 'premium-addons-for-elementor' ),
					'100%' => __( 'Full Width', 'premium-addons-for-elementor' ),
				),
				'default'     => 'auto',
				'label_block' => true,
				'selectors'   => array(
					'{{WRAPPER}}.premium-bg-text-yes .elementor-widget-container:before' => 'width: {{VALUE}}',
				),
				'condition'   => array(
					'background_text_switcher' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'background_text_left',
			array(
				'label'      => __( 'Horizontal Offset', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', '%', 'custom' ),
				'range'      => array(
					'px' => array(
						'min' => -500,
						'max' => 500,
					),
					'em' => array(
						'min' => -50,
						'max' => 50,
					),
					'%'  => array(
						'min' => -100,
						'max' => 100,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}.premium-bg-text-yes .elementor-widget-container:before' => 'left: {{SIZE}}{{UNIT}}',
				),
				'condition'  => array(
					'background_text_switcher' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'background_text_top',
			array(
				'label'      => __( 'Vertical Offset', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', '%', 'custom' ),
				'range'      => array(
					'px' => array(
						'min' => -500,
						'max' => 500,
					),
					'em' => array(
						'min' => -50,
						'max' => 50,
					),
					'%'  => array(
						'min' => -100,
						'max' => 100,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}}.premium-bg-text-yes .elementor-widget-container:before' => 'top: {{SIZE}}{{UNIT}}',
				),
				'condition'  => array(
					'background_text_switcher' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'background_text_align',
			array(
				'label'     => __( 'Alignment', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array(
						'title' => __( 'Left', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => __( 'Center', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => __( 'Right', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default'   => 'left',
				'selectors' => array(
					'{{WRAPPER}}.premium-bg-text-yes .elementor-widget-container:before' => 'text-align: {{VALUE}};',
				),
				'condition' => array(
					'background_text_switcher' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'background_text_rotate',
			array(
				'label'      => __( 'Rotate (degrees)', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'deg' ),
				'default'    => array(
					'unit' => 'deg',
					'size' => 0,
				),
				'selectors'  => array(
					'{{WRAPPER}}.premium-bg-text-yes .elementor-widget-container:before' => 'transform: rotate({{SIZE}}{{UNIT}})',
				),
				'condition'  => array(
					'background_text_switcher' => 'yes',
				),
			)
		);

		$this->add_control(
			'mask_switcher',
			array(
				'label'        => __( 'Minimal Mask Effect', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'separator'    => 'before',
				'render_type'  => 'template',
				'prefix_class' => 'premium-mask-',
				'description'  => __( 'Please note That this effect takes place once the element is in the viewport', 'premium-addons-for-elementor' ),
			)
		);

		$this->add_control(
			'mask_color',
			array(
				'label'       => __( 'Mask Color', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::COLOR,
				'render_type' => 'template',
				'selectors'   => array(
					'{{WRAPPER}}.premium-mask-yes .premium-dual-header-first-header span::after'   => 'background: {{VALUE}};',
				),
				'condition'   => array(
					'mask_switcher' => 'yes',
				),
			)
		);

		$this->add_control(
			'premium_mask_dir',
			array(
				'label'        => __( 'Direction', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::SELECT,
				'default'      => 'tr',
				'prefix_class' => 'premium-mask-',
				'render_type'  => 'template',
				'options'      => array(
					'tr' => __( 'To Right', 'premium-addons-for-elementor' ),
					'tl' => __( 'To Left', 'premium-addons-for-elementor' ),
					'tt' => __( 'To Top', 'premium-addons-for-elementor' ),
					'tb' => __( 'To Bottom', 'premium-addons-for-elementor' ),
				),
				'condition'    => array(
					'mask_switcher' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'mask_padding',
			array(
				'label'      => __( 'Words Padding', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-mask-span' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition'  => array(
					'mask_switcher' => 'yes',
				),
			)
		);

		$this->add_control(
			'noise_first',
			array(
				'label'        => __( 'First Heading Noise Effect', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'separator'    => 'before',
				'prefix_class' => 'premium-title-first-noise-',
				'render_type'  => 'template',
				'condition'    => array(
					'mask_switcher!'                      => 'yes',
					'premium_dual_header_first_back_clip' => 'color',
				),
			)
		);

		$this->add_control(
			'noise_second',
			array(
				'label'        => __( 'Second Heading Noise Effect', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'prefix_class' => 'premium-title-second-noise-',
				'render_type'  => 'template',
				'condition'    => array(
					'mask_switcher!'                       => 'yes',
					'premium_dual_header_second_back_clip' => 'color',
				),
			)
		);

		$this->add_control(
			'clip_scroll',
			array(
				'label'       => apply_filters( 'pa_pro_label', __( 'Fill on Scroll (Pro)', 'premium-addons-for-elementor' ) ),
				'description' => __( 'Fill the heading words one by one while scrolling. The After Fill Colors are set from the Style tab.', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SWITCHER,
				'separator'   => 'before',
				'render_type' => 'template',
				'condition'   => array(
					'mask_switcher!' => 'yes',
					'noise_first!'   => 'yes',
					'noise_second!'  => 'yes',
				),
			)
		);

		do_action(
			'pa_fill_on_scroll_controls',
			$this,
			array(
				'condition' => array(
					'clip_scroll'    => 'yes',
					'mask_switcher!' => 'yes',
					'noise_first!'   => 'yes',
					'noise_second!'  => 'yes',
				),
				'lock'      => array(
					'relation' => 'or',
					'terms'    => array(
						array(
							'name'  => 'clip_scroll',
							'value' => 'yes',
						),
						array(
							'name'  => 'desc_clip_scroll',
							'value' => 'yes',
						),
					),
				),
				'notice'    => __( 'The section stays pinned until both the title and the description finish filling.', 'premium-addons-for-elementor' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'premium_dual_header_description_section',
			array(
				'label' => __( 'Description', 'premium-addons-for-elementor' ),
			)
		);

		$this->add_control(
			'premium_dual_header_desc_switcher',
			array(
				'label'   => __( 'Description', 'premium-addons-for-elementor' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => '',
			)
		);

		$this->add_control(
			'premium_dual_header_desc_text',
			array(
				'label'     => __( 'Description', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::WYSIWYG,
				'dynamic'   => array( 'active' => true ),
				'default'   => __( 'Extend Your Elementor Website with Numerous Widgets, Global Addons, and Features.', 'premium-addons-for-elementor' ),
				'condition' => array(
					'premium_dual_header_desc_switcher' => 'yes',
				),
			)
		);

		$this->add_control(
			'desc_clip_scroll',
			array(
				'label'       => apply_filters( 'pa_pro_label', __( 'Fill on Scroll (Pro)', 'premium-addons-for-elementor' ) ),
				'description' => __( 'Fill the description words one by one while scrolling. The After Fill Color is set from the Style tab.', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SWITCHER,
				'render_type' => 'template',
				'condition'   => array(
					'premium_dual_header_desc_switcher' => 'yes',
				),
			)
		);

		do_action(
			'pa_fill_on_scroll_controls',
			$this,
			array(
				'prefix'    => 'desc_',
				'condition' => array(
					'premium_dual_header_desc_switcher' => 'yes',
					'desc_clip_scroll'                  => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'premium_dual_header_desc_display',
			array(
				'label'        => __( 'Display', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::CHOOSE,
				'options'      => array(
					'row'    => array(
						'title' => __( 'Inline', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-ellipsis-h',
					),
					'column' => array(
						'title' => __( 'Block', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-ellipsis-v',
					),
				),
				'default'      => 'column',
				'prefix_class' => 'premium-dh-desc-display-',
				'toggle'       => false,
				'selectors'    => array(
					'{{WRAPPER}} .premium-dual-header-wrapper' => 'flex-direction: {{VALUE}};',
				),
				'condition'    => array(
					'premium_dual_header_desc_switcher' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'premium_dual_header_desc_order',
			array(
				'label'     => __( 'Order', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'0' => array(
						'title' => __( 'Before Heading', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-order-start',
					),
					'2' => array(
						'title' => __( 'After Heading', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-order-end',
					),
				),
				'default'   => '2',
				'toggle'    => false,
				'selectors' => array(
					'{{WRAPPER}} .premium-dh-description' => 'order: {{VALUE}};',
				),
				'condition' => array(
					'premium_dual_header_desc_switcher' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'v_align',
			array(
				'label'       => __( 'Vertical Alignment', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::CHOOSE,
				'description' => __( 'Effective when the display is set to <b>"inline</b>"', 'premium-addons-for-elementor' ),
				'options'     => array(
					'start'   => array(
						'title' => __( 'Top', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-align-start-v',
					),
					'center'  => array(
						'title' => __( 'Center', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-align-center-v',
					),
					'end'     => array(
						'title' => __( 'Bottom', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-align-end-v',
					),
					'stretch' => array(
						'title' => __( 'Stretch', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-align-stretch-v',
					),
				),
				'default'     => 'center',
				'toggle'      => false,
				'selectors'   => array(
					'{{WRAPPER}} .premium-dh-description' => 'align-self: {{VALUE}};',
				),
				'condition'   => array(
					'premium_dual_header_desc_switcher' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'premium_dual_header_desc_align',
			array(
				'label'     => __( 'Text Alignment', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::CHOOSE,
				'toggle'    => false,
				'options'   => array(
					'left'    => array(
						'title' => __( 'Left', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'  => array(
						'title' => __( 'Center', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'   => array(
						'title' => __( 'Right', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-text-align-right',
					),
					'justify' => array(
						'title' => __( 'Justify', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-text-align-justify',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-dh-description' => 'text-align: {{VALUE}};',
				),
				'condition' => array(
					'premium_dual_header_desc_switcher' => 'yes',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_pa_docs',
			array(
				'label' => __( 'Help & Docs', 'premium-addons-for-elementor' ),
			)
		);

		$doc1_url = Helper_Functions::get_campaign_link( 'https://premiumaddons.com/docs/dual-heading-widget-tutorial', 'dual-h-widget', 'wp-editor', 'get-support' );

		$this->add_control(
			'doc_1',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => sprintf( '<a href="%s" target="_blank">%s</a>', $doc1_url, __( 'Getting started »', 'premium-addons-for-elementor' ) ),
				'content_classes' => 'editor-pa-doc',
			)
		);

		$doc2_url = Helper_Functions::get_campaign_link( 'https://premiumaddons.com/docs/how-to-add-an-outlined-heading-to-my-website', 'dual-h-widget', 'wp-editor', 'get-support' );

		$this->add_control(
			'doc_2',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => sprintf( '<a href="%s" target="_blank">%s</a>', $doc2_url, __( 'How to add an outlined heading using Dual Heading widget »', 'premium-addons-for-elementor' ) ),
				'content_classes' => 'editor-pa-doc',
			)
		);

		Helper_Functions::register_element_feedback_controls( $this );

		$this->end_controls_section();

		Helper_Functions::register_papro_promotion_controls( $this, 'dual' );

		$this->start_controls_section(
			'premium_dual_header_first_style',
			array(
				'label' => __( 'First Heading', 'premium-addons-for-elementor' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'first_header_typography',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				),
				'selector' => '{{WRAPPER}} .premium-dual-header-first-span',
			)
		);

		$this->add_control(
			'premium_dual_header_first_animated',
			array(
				'label'     => __( 'Animated Background', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::SWITCHER,
				'condition' => array(
					'clip_scroll!' => 'yes',
				),
			)
		);

		$this->add_control(
			'premium_dual_header_first_back_clip',
			array(
				'label'       => __( 'Background Style', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'color',
				'description' => __( 'Choose ‘Normal’ style to put a background behind the text. Choose ‘Clipped’ style so the background will be clipped on the text.', 'premium-addons-for-elementor' ),
				'options'     => array(
					'color'   => __( 'Normal', 'premium-addons-for-elementor' ),
					'clipped' => __( 'Clipped', 'premium-addons-for-elementor' ),
				),
				'label_block' => true,
				'condition'   => array(
					'clip_scroll!' => 'yes',
				),
			)
		);

		$this->add_control(
			'premium_dual_header_first_color',
			array(
				'label'      => __( 'Text Color', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::COLOR,
				'global'     => array(
					'default' => Global_Colors::COLOR_PRIMARY,
				),
				'conditions' => array(
					'relation' => 'or',
					'terms'    => array(
						array(
							'name'  => 'premium_dual_header_first_back_clip',
							'value' => 'color',
						),
						array(
							'name'  => 'clip_scroll',
							'value' => 'yes',
						),
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .premium-dual-header-first-span'   => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'first_after_clip_color',
			array(
				'label'     => __( 'After Fill Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .premium-dual-header-first-span' => '--pa-clip-after: {{VALUE}};',
				),
				'condition' => array(
					'clip_scroll'    => 'yes',
					'mask_switcher!' => 'yes',
					'noise_first!'   => 'yes',
					'noise_second!'  => 'yes',
				),
			)
		);

		$this->add_group_control(
			Premium_Background::get_type(),
			array(
				'name'      => 'premium_dual_header_first_background',
				'types'     => array( 'classic', 'gradient' ),
				'condition' => array(
					'premium_dual_header_first_back_clip' => 'color',
				),
				'selector'  => '{{WRAPPER}} .premium-dual-header-first-span',
			)
		);

		$this->add_control(
			'first_lq_effect',
			array(
				'label'        => __( 'Liquid Glass Effect', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::SELECT,
				'description'  => sprintf(
					/* translators: 1: `<a>` opening tag, 2: `</a>` closing tag. */
					esc_html__( 'Important: Make sure this element has a semi-transparent background color to see the effect. See all presets from %1$shere%2$s.', 'premium-addons-for-elementor' ),
					'<a href="https://premiumaddons.com/liquid-glass/" target="_blank">',
					'</a>'
				),
				'options'      => array(
					'none'   => __( 'None', 'premium-addons-for-elementor' ),
					'glass1' => __( 'Preset 01', 'premium-addons-for-elementor' ),
					'glass2' => __( 'Preset 02', 'premium-addons-for-elementor' ),
					'glass3' => apply_filters( 'pa_pro_label', __( 'Preset 03 (Pro)', 'premium-addons-for-elementor' ) ),
					'glass4' => apply_filters( 'pa_pro_label', __( 'Preset 04 (Pro)', 'premium-addons-for-elementor' ) ),
					'glass5' => apply_filters( 'pa_pro_label', __( 'Preset 05 (Pro)', 'premium-addons-for-elementor' ) ),
					'glass6' => apply_filters( 'pa_pro_label', __( 'Preset 06 (Pro)', 'premium-addons-for-elementor' ) ),
				),
				'prefix_class' => 'premium-lq__',
				'default'      => 'none',
				'label_block'  => true,
				'render_type'  => 'template',
				'condition'    => array(
					'premium_dual_header_first_back_clip' => 'color',
				),
			)
		);

		$this->add_control(
			'premium_dual_header_first_stroke',
			array(
				'label'     => __( 'Stroke', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::SWITCHER,
				'condition' => array(
					'premium_dual_header_first_back_clip' => 'clipped',
					'clip_scroll!'                        => 'yes',
				),
			)
		);

		$this->add_control(
			'premium_dual_header_first_stroke_text_color',
			array(
				'label'     => __( 'Stroke Text Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => array(
					'premium_dual_header_first_back_clip' => 'clipped',
					'premium_dual_header_first_stroke'    => 'yes',
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-dual-header-first-clip.stroke .premium-dual-header-first-span'   => '-webkit-text-stroke-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'premium_dual_header_first_stroke_color',
			array(
				'label'     => __( 'Stroke Fill Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => array(
					'premium_dual_header_first_back_clip' => 'clipped',
					'premium_dual_header_first_stroke'    => 'yes',
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-dual-header-first-clip.stroke .premium-dual-header-first-span'   => '-webkit-text-fill-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'premium_dual_header_first_stroke_width',
			array(
				'label'     => __( 'Stroke Fill Width', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::SLIDER,
				'condition' => array(
					'premium_dual_header_first_back_clip' => 'clipped',
					'premium_dual_header_first_stroke'    => 'yes',
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-dual-header-first-clip.stroke .premium-dual-header-first-span'   => '-webkit-text-stroke-width: {{SIZE}}px;',
				),
			)
		);

		$this->add_group_control(
			Premium_Background::get_type(),
			array(
				'name'      => 'premium_dual_header_first_clipped_background',
				'types'     => array( 'classic', 'gradient' ),
				'condition' => array(
					'premium_dual_header_first_back_clip' => 'clipped',
					'premium_dual_header_first_stroke!'   => 'yes',
				),
				'selector'  => '{{WRAPPER}} .premium-dual-header-first-span',
			)
		);

		$this->add_control(
			'first_wave_switcher',
			array(
				'label'        => __( 'Wave Effect', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'prefix_class' => 'premium-title-first-wave-',
				'condition'    => array(
					'premium_dual_header_first_back_clip' => 'clipped',
					'premium_dual_header_first_stroke!'   => 'yes',
					'clip_scroll!'                        => 'yes',
				),
			)
		);

		$this->add_control(
			'first_animation_speed',
			array(
				'label'     => __( 'Animation Speed (sec)', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array(
					'px' => array(
						'min'  => 0,
						'max'  => 10,
						'step' => .1,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-dual-header-first-span' => 'animation-duration: {{SIZE}}s ',
				),
				'condition' => array(
					'premium_dual_header_first_animated' => 'yes',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'      => 'first_header_border',
				'selector'  => '{{WRAPPER}} .premium-dual-header-first-span',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'premium_dual_header_first_border_radius',
			array(
				'label'      => __( 'Border Radius', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-dual-header-first-span' => 'border-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'label'          => __( 'Shadow', 'premium-addons-for-elementor' ),
				'name'           => 'premium_dual_header_first_text_shadow',
				'fields_options' => array(
					'text_shadow' => array(
						'selectors' => array(
							'{{WRAPPER}} .premium-dual-header-first-span:not(.pa-clipped-scroll)' => 'text-shadow: {{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{COLOR}};',
							'{{WRAPPER}} .premium-dual-header-first-span.pa-clipped-scroll .pa-clip-word' => 'filter: drop-shadow({{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{COLOR}})',
						),
					),
				),
			)
		);

		$this->add_responsive_control(
			'premium_dual_header_first_margin',
			array(
				'label'      => __( 'Margin', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'separator'  => 'before',
				'selectors'  => array(
					'{{WRAPPER}} .premium-dual-header-first-span' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'premium_dual_header_first_padding',
			array(
				'label'      => __( 'Padding', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-dual-header-first-span, {{WRAPPER}}.premium-title-first-noise-yes .premium-dual-header-first-span::before, {{WRAPPER}}.premium-title-first-noise-yes .premium-dual-header-first-span::after' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'first_noise_heading',
			array(
				'label'     => __( 'Glitch Effect', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::HEADING,
				'condition' => array(
					'noise_first' => 'yes',
				),
			)
		);

		$this->add_control(
			'noise_first_color',
			array(
				'label'     => __( 'Color #1', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => array(
					'noise_first' => 'yes',
				),
				'selectors' => array(
					'{{WRAPPER}}.premium-title-first-noise-yes .premium-dual-header-first-span::before' => 'text-shadow: 1px 0 {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'noise_second_color',
			array(
				'label'     => __( 'Color #2', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => array(
					'noise_first' => 'yes',
				),
				'selectors' => array(
					'{{WRAPPER}}.premium-title-first-noise-yes .premium-dual-header-first-span::after' => 'text-shadow: -1px 0 {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'premium_dual_header_second_style',
			array(
				'label' => __( 'Second Heading', 'premium-addons-for-elementor' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'second_header_typography',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				),
				'selector' => '{{WRAPPER}} .premium-dual-header-second-header',
			)
		);

		$this->add_control(
			'premium_dual_header_second_animated',
			array(
				'label'     => __( 'Animated Background', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::SWITCHER,
				'condition' => array(
					'clip_scroll!' => 'yes',
				),
			)
		);

		$this->add_control(
			'premium_dual_header_second_back_clip',
			array(
				'label'       => __( 'Background Style', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'color',
				'description' => __( 'Choose ‘Normal’ style to put a background behind the text. Choose ‘Clipped’ style so the background will be clipped on the text.', 'premium-addons-for-elementor' ),
				'options'     => array(
					'color'   => __( 'Normal', 'premium-addons-for-elementor' ),
					'clipped' => __( 'Clipped', 'premium-addons-for-elementor' ),
				),
				'label_block' => true,
				'condition'   => array(
					'clip_scroll!' => 'yes',
				),
			)
		);

		$this->add_control(
			'premium_dual_header_second_color',
			array(
				'label'      => __( 'Text Color', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::COLOR,
				'global'     => array(
					'default' => Global_Colors::COLOR_SECONDARY,
				),
				'conditions' => array(
					'relation' => 'or',
					'terms'    => array(
						array(
							'name'  => 'premium_dual_header_second_back_clip',
							'value' => 'color',
						),
						array(
							'name'  => 'clip_scroll',
							'value' => 'yes',
						),
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .premium-dual-header-second-header'   => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'second_after_clip_color',
			array(
				'label'     => __( 'After Fill Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .premium-dual-header-second-header' => '--pa-clip-after: {{VALUE}};',
				),
				'condition' => array(
					'clip_scroll'    => 'yes',
					'mask_switcher!' => 'yes',
					'noise_first!'   => 'yes',
					'noise_second!'  => 'yes',
				),
			)
		);

		$this->add_group_control(
			Premium_Background::get_type(),
			array(
				'name'      => 'premium_dual_header_second_background',
				'types'     => array( 'classic', 'gradient' ),
				'condition' => array(
					'premium_dual_header_second_back_clip' => 'color',
				),
				'selector'  => '{{WRAPPER}} .premium-dual-header-second-header',
			)
		);

		$this->add_control(
			'second_lq_effect',
			array(
				'label'        => __( 'Liquid Glass Effect', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::SELECT,
				'description'  => sprintf(
					/* translators: 1: `<a>` opening tag, 2: `</a>` closing tag. */
					esc_html__( 'Important: Make sure this element has a semi-transparent background color to see the effect. See all presets from %1$shere%2$s.', 'premium-addons-for-elementor' ),
					'<a href="https://premiumaddons.com/liquid-glass/" target="_blank">',
					'</a>'
				),
				'options'      => array(
					'none'   => __( 'None', 'premium-addons-for-elementor' ),
					'glass1' => __( 'Preset 01', 'premium-addons-for-elementor' ),
					'glass2' => __( 'Preset 02', 'premium-addons-for-elementor' ),
					'glass3' => apply_filters( 'pa_pro_label', __( 'Preset 03 (Pro)', 'premium-addons-for-elementor' ) ),
					'glass4' => apply_filters( 'pa_pro_label', __( 'Preset 04 (Pro)', 'premium-addons-for-elementor' ) ),
					'glass5' => apply_filters( 'pa_pro_label', __( 'Preset 05 (Pro)', 'premium-addons-for-elementor' ) ),
					'glass6' => apply_filters( 'pa_pro_label', __( 'Preset 06 (Pro)', 'premium-addons-for-elementor' ) ),
				),
				'prefix_class' => 'premium-box-lq__',
				'default'      => 'none',
				'label_block'  => true,
				'render_type'  => 'template',
				'condition'    => array(
					'premium_dual_header_second_back_clip' => 'color',
				),
			)
		);

		$this->add_control(
			'premium_dual_header_second_stroke',
			array(
				'label'     => __( 'Stroke', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::SWITCHER,
				'condition' => array(
					'premium_dual_header_second_back_clip' => 'clipped',
					'clip_scroll!'                         => 'yes',
				),
			)
		);

		$this->add_control(
			'premium_dual_header_second_stroke_text_color',
			array(
				'label'     => __( 'Stroke Text Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => array(
					'premium_dual_header_second_back_clip' => 'clipped',
					'premium_dual_header_second_stroke'    => 'yes',
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-dual-header-second-clip.stroke'   => '-webkit-text-stroke-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'premium_dual_header_second_stroke_color',
			array(
				'label'     => __( 'Stroke Fill Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => array(
					'premium_dual_header_second_back_clip' => 'clipped',
					'premium_dual_header_second_stroke'    => 'yes',
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-dual-header-second-clip.stroke'   => '-webkit-text-fill-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'premium_dual_header_second_stroke_width',
			array(
				'label'     => __( 'Stroke Fill Width', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::SLIDER,
				'condition' => array(
					'premium_dual_header_second_back_clip' => 'clipped',
					'premium_dual_header_second_stroke'    => 'yes',
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-dual-header-second-clip.stroke'   => '-webkit-text-stroke-width: {{SIZE}}px;',
				),
			)
		);

		$this->add_group_control(
			Premium_Background::get_type(),
			array(
				'name'      => 'premium_dual_header_second_clipped_background',
				'types'     => array( 'classic', 'gradient' ),
				'condition' => array(
					'premium_dual_header_second_back_clip' => 'clipped',
					'premium_dual_header_second_stroke!'   => 'yes',
				),
				'selector'  => '{{WRAPPER}} .premium-dual-header-second-header',
			)
		);

		$this->add_control(
			'second_wave_switcher',
			array(
				'label'        => __( 'Wave Effect', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'prefix_class' => 'premium-title-second-wave-',
				'condition'    => array(
					'premium_dual_header_second_back_clip' => 'clipped',
					'premium_dual_header_second_stroke!'   => 'yes',
					'clip_scroll!'                         => 'yes',
				),
			)
		);

		$this->add_control(
			'second_animation_speed',
			array(
				'label'     => __( 'Animation Speed (sec)', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array(
					'px' => array(
						'min'  => 0,
						'max'  => 10,
						'step' => .1,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-dual-header-second-header' => 'animation-duration: {{SIZE}}s ',
				),
				'condition' => array(
					'premium_dual_header_second_animated' => 'yes',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'      => 'second_header_border',
				'selector'  => '{{WRAPPER}} .premium-dual-header-second-header',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'premium_dual_header_second_border_radius',
			array(
				'label'      => __( 'Border Radius', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-dual-header-second-header' => 'border-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'label'          => __( 'Shadow', 'premium-addons-for-elementor' ),
				'name'           => 'premium_dual_header_second_text_shadow',
				'fields_options' => array(
					'text_shadow' => array(
						'selectors' => array(
							'{{WRAPPER}} .premium-dual-header-second-header:not(.pa-clipped-scroll)' => 'text-shadow: {{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{COLOR}};',
							'{{WRAPPER}} .premium-dual-header-second-header.pa-clipped-scroll .pa-clip-word' => 'filter: drop-shadow({{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{COLOR}})',
						),
					),
				),
			)
		);

		$this->add_responsive_control(
			'premium_dual_header_second_margin',
			array(
				'label'      => __( 'Margin', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'separator'  => 'before',
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-dual-header-second-header' => 'margin : {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'premium_dual_header_second_padding',
			array(
				'label'      => __( 'Padding', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-dual-header-second-header, {{WRAPPER}}.premium-title-second-noise-yes .premium-dual-header-second-header::before, {{WRAPPER}}.premium-title-second-noise-yes .premium-dual-header-second-header::after' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'second_noise_heading',
			array(
				'label'     => __( 'Glitch Effect', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::HEADING,
				'condition' => array(
					'noise_second' => 'yes',
				),
			)
		);

		$this->add_control(
			'noise_third_color',
			array(
				'label'     => __( 'Color #1', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => array(
					'noise_second' => 'yes',
				),
				'selectors' => array(
					'{{WRAPPER}}.premium-title-second-noise-yes .premium-dual-header-second-header::before' => 'text-shadow: 1px 0 {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'noise_fourth_color',
			array(
				'label'     => __( 'Color #2', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => array(
					'noise_second' => 'yes',
				),
				'selectors' => array(
					'{{WRAPPER}}.premium-title-second-noise-yes .premium-dual-header-second-header::after' => 'text-shadow: -1px 0 {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'premium_dual_header_desc_style_section',
			array(
				'label'     => __( 'Description', 'premium-addons-for-elementor' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'premium_dual_header_desc_switcher' => 'yes',
				),
			)
		);

		$this->add_control(
			'premium_dual_header_desc_color',
			array(
				'label'     => __( 'Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .premium-dh-description, {{WRAPPER}} .premium-dh-description *' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'desc_after_clip_color',
			array(
				'label'     => __( 'After Fill Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .premium-dh-description' => '--pa-clip-after: {{VALUE}};',
				),
				'condition' => array(
					'desc_clip_scroll' => 'yes',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'premium_dual_header_desc_typography',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				),
				'selector' => '{{WRAPPER}} .premium-dh-description',
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'name'           => 'premium_dual_header_desc_text_shadow',
				'fields_options' => array(
					'text_shadow' => array(
						'selectors' => array(
							'{{WRAPPER}} .premium-dh-description:not(.pa-clipped-scroll)' => 'text-shadow: {{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{COLOR}};',
							'{{WRAPPER}} .premium-dh-description.pa-clipped-scroll .pa-clip-word' => 'filter: drop-shadow({{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{COLOR}})',
						),
					),
				),
			)
		);

		$this->add_group_control(
			Premium_Background::get_type(),
			array(
				'name'     => 'premium_dual_header_desc_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .premium-dh-description',
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'premium_dual_header_desc_box_shadow',
				'selector' => '{{WRAPPER}} .premium-dh-description',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'premium_dual_header_desc_border',
				'selector' => '{{WRAPPER}} .premium-dh-description',
			)
		);

		$this->add_responsive_control(
			'premium_dual_header_desc_border_radius',
			array(
				'label'      => __( 'Border Radius', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-dh-description' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'premium_dual_header_desc_margin',
			array(
				'label'      => __( 'Margin', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-dh-description' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'premium_dual_header_desc_padding',
			array(
				'label'      => __( 'Padding', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-dh-description' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'background_text_style_section',
			array(
				'label'     => __( 'Background Text', 'premium-addons-for-elementor' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'background_text_switcher' => 'yes',
				),
			)
		);

		$this->add_control(
			'background_text_color',
			array(
				'label'     => __( 'Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_PRIMARY,
				),
				'selectors' => array(
					'{{WRAPPER}}.premium-bg-text-yes .elementor-widget-container:before' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'background_text_typography',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				),
				'selector' => '{{WRAPPER}}.premium-bg-text-yes .elementor-widget-container:before',
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'name'     => 'background_text_shadow',
				'selector' => '{{WRAPPER}}.premium-bg-text-yes .elementor-widget-container:before',
			)
		);

		$this->add_control(
			'background_text_mix_blend',
			array(
				'label'     => __( 'Blend Mode', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					''            => __( 'Normal', 'premium-addons-for-elementor' ),
					'multiply'    => 'Multiply',
					'screen'      => 'Screen',
					'overlay'     => 'Overlay            ',
					'darken'      => 'Darken',
					'lighten'     => 'Lighten',
					'color-dodge' => 'Color Dodge',
					'saturation'  => 'Saturation',
					'color'       => 'Color',
					'luminosity'  => 'Luminosity',
				),
				'separator' => 'before',
				'selectors' => array(
					'{{WRAPPER}}.premium-bg-text-yes .elementor-widget-container:before' => 'mix-blend-mode: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'background_text_zindex',
			array(
				'label'     => __( 'z-Index', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::NUMBER,
				'min'       => -10,
				'max'       => 20,
				'step'      => 1,
				'selectors' => array(
					'{{WRAPPER}}.premium-bg-text-yes .elementor-widget-container:before' => 'z-index: {{VALUE}}',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render Dual Heading widget output on the frontend.
	 *
	 * Written in PHP and used to generate the final HTML.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function render() {

		$settings = $this->get_settings_for_display();

		$this->add_inline_editing_attributes( 'premium_dual_header_first_header_text' );

		$this->add_inline_editing_attributes( 'premium_dual_header_second_header_text' );

		$first_title_tag = Helper_Functions::validate_html_tag( $settings['premium_dual_header_first_header_tag'] );

		$first_title_text = $settings['premium_dual_header_first_header_text'] . ' ';

		$second_title_text = $settings['premium_dual_header_second_header_text'];

		$clip_on_scroll = $this->fills_on_scroll( $settings );
		$desc_clip      = $this->description_fills_on_scroll( $settings ) && ! empty( $settings['premium_dual_header_desc_text'] );

		if ( ( $clip_on_scroll || $desc_clip ) && ! Helper_Functions::check_papro_version() ) {
			?>
			<div class="premium-error-notice">
				<?php echo wp_kses_post( __( 'This option is available in <b>Premium Addons Pro</b>.', 'premium-addons-for-elementor' ) ); ?>
			</div>
			<?php
			return false;
		}

		$first_clip = '';

		$second_clip = '';

		$first_stroke = '';

		$second_stroke = '';

		// Fill on scroll owns the same spans, so the effects that paint on them are dropped.
		if ( ! $clip_on_scroll && 'clipped' === $settings['premium_dual_header_first_back_clip'] ) {
			$first_clip = 'premium-dual-header-first-clip';
		}

		if ( ! $clip_on_scroll && 'clipped' === $settings['premium_dual_header_second_back_clip'] ) {
			$second_clip = 'premium-dual-header-second-clip';
		}

		if ( ! empty( $first_clip ) && 'yes' === $settings['premium_dual_header_first_stroke'] ) {
			$first_stroke = ' stroke';
		}

		if ( ! empty( $second_clip ) && 'yes' === $settings['premium_dual_header_second_stroke'] ) {
			$second_stroke = ' stroke';
		}

		$first_animation  = $settings['premium_dual_header_first_animated'];
		$first_wave       = $settings['first_wave_switcher'];
		$second_animation = $settings['premium_dual_header_second_animated'];
		$second_wave      = $settings['second_wave_switcher'];

		$first_grad = ( ! $clip_on_scroll && 'yes' === $first_animation && 'yes' !== $first_wave ) ? ' gradient' : '';

		$second_grad = ( ! $clip_on_scroll && 'yes' === $second_animation && 'yes' !== $second_wave ) ? ' gradient' : '';

		$first_noise = 'yes' === $settings['noise_first'] ? 'data-text="' . $first_title_text . '"' : '';

		$second_noise = 'yes' === $settings['noise_second'] ? 'data-text="' . $second_title_text . '"' : '';

		$first_span_class  = 'premium-dual-header-first-span';
		$second_span_class = 'premium-dual-header-second-header ' . $second_clip . $second_stroke . $second_grad;

		if ( $clip_on_scroll ) {
			$first_span_class  .= ' pa-clipped-scroll';
			$second_span_class .= ' pa-clipped-scroll';

			$first_title_text  = $this->get_clipped_words( $first_title_text ) . ' ';
			$second_title_text = $this->get_clipped_words( $second_title_text );

			$this->add_render_attribute( 'container', 'data-clip-speed', $settings['clip_scroll_speed']['size'] );
		}

		$full_title = '<' . $first_title_tag . ' class="premium-dual-header-first-header ' . $first_clip . $first_stroke . $first_grad . '"><span class="' . $first_span_class . '" ' . $first_noise . ' >' . $first_title_text . '</span>';

		if ( ! empty( $second_title_text ) ) {
			$full_title .= '<span class="' . $second_span_class . '" ' . $second_noise . ' >' . $second_title_text . '</span>';
		}

		$full_title .= '</' . $first_title_tag . '> ';

		if ( 'yes' === $settings['premium_dual_header_link_switcher'] ) {

			if ( 'url' === $settings['premium_dual_heading_link_selection'] ) {

				$this->add_link_attributes( 'link', $settings['premium_dual_heading_link'] );
			} else {

				$this->add_render_attribute( 'link', 'href', get_permalink( $settings['premium_dual_heading_existing_link'] ) );
			}
		}

		$this->add_render_attribute( 'container', 'class', 'premium-dual-header-wrapper' );

		$show_desc = 'yes' === $settings['premium_dual_header_desc_switcher'] && ! empty( $settings['premium_dual_header_desc_text'] );

		if ( $show_desc ) {
			$this->add_render_attribute( 'dh_description', 'class', 'premium-dh-description' );
		}

		if ( $desc_clip ) {
			// The words are wrapped by the handler, so the rich text keeps its markup here.
			$this->add_render_attribute(
				'dh_description',
				array(
					'class'           => 'pa-clipped-scroll',
					'data-clip-speed' => $settings['desc_clip_scroll_speed']['size'],
					'data-clip-split' => 'true',
				)
			);
		}

		if ( ( $clip_on_scroll || $desc_clip ) && 'yes' === $settings['clip_scroll_lock'] ) {
			$this->add_render_attribute( 'container', 'data-clip-lock', 'true' );
		}

		?>

		<div class="elementor-widget-container">
		<div <?php $this->print_render_attribute_string( 'container' ); ?>>
		<?php if ( 'yes' === $settings['premium_dual_header_link_switcher'] ) : ?>
		<a <?php $this->print_render_attribute_string( 'link' ); ?>>
		<?php endif; ?>
			<?php echo wp_kses_post( $full_title ); ?>
		<?php if ( 'yes' === $settings['premium_dual_header_link_switcher'] ) : ?>
		</a>
		<?php endif; ?>
		<?php if ( $show_desc ) : ?>
		<div <?php $this->print_render_attribute_string( 'dh_description' ); ?>>
			<?php echo wp_kses_post( $settings['premium_dual_header_desc_text'] ); ?>
		</div>
		<?php endif; ?>
		</div>
		</div>

		<?php
	}

	/**
	 * Render Dual Heading widget output in the editor.
	 *
	 * Written as a Backbone JavaScript template and used to generate the live preview.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function content_template() {
		?>
		<#

			view.addInlineEditingAttributes('premium_dual_header_first_header_text');

			view.addInlineEditingAttributes('premium_dual_header_second_header_text');

			var firstTag = elementor.helpers.validateHTMLTag( settings.premium_dual_header_first_header_tag ),

			firstText = settings.premium_dual_header_first_header_text + ' ',

			secondText = settings.premium_dual_header_second_header_text,

			firstClip = '',

			secondClip = '',

			firstStroke = '',

			secondStroke = '';

			var paProActive = <?php echo Helper_Functions::check_papro_version() ? 'true' : 'false'; ?>;

			// Fill on scroll owns the same spans, so the effects that paint on them are dropped.
			var clipOnScroll = 'yes' === settings.clip_scroll
				&& 'yes' !== settings.mask_switcher
				&& 'yes' !== settings.noise_first
				&& 'yes' !== settings.noise_second;

			var descClip = 'yes' === settings.premium_dual_header_desc_switcher
				&& 'yes' === settings.desc_clip_scroll
				&& '' !== settings.premium_dual_header_desc_text;

			// Kept before the words are split so the noise effect still reads the plain text.
			var firstRawText = firstText,
				secondRawText = secondText;

			view.addRenderAttribute( 'description', 'class', [ 'premium-dh-description', descClip ? 'pa-clipped-scroll' : '' ] );

			if ( descClip ) {
				view.addRenderAttribute( 'description', 'data-clip-speed', settings.desc_clip_scroll_speed && settings.desc_clip_scroll_speed.size ? settings.desc_clip_scroll_speed.size : 0.5 );
				view.addRenderAttribute( 'description', 'data-clip-split', 'true' );
			}

			if( ! clipOnScroll && 'clipped' === settings.premium_dual_header_first_back_clip )
				firstClip = "premium-dual-header-first-clip";

			if( ! clipOnScroll && 'clipped' === settings.premium_dual_header_second_back_clip )
				secondClip = "premium-dual-header-second-clip";

			if( 'yes' === settings.premium_dual_header_first_stroke )
				firstStroke = "stroke";

			if( 'yes' === settings.premium_dual_header_second_stroke )
				secondStroke = "stroke";

			var firstAnimation = settings.premium_dual_header_first_animated,
				firstWave = settings.first_wave_switcher,
				secondAnimation = settings.premium_dual_header_second_animated,
				secondWave = settings.second_wave_switcher;

			var firstGrad = ( ! clipOnScroll && 'yes' === firstAnimation && 'yes' !== firstWave)  ? ' gradient' : '',
				secondGrad = ( ! clipOnScroll && 'yes' === secondAnimation && 'yes' !== secondWave) ? ' gradient' : '';

			// Each word gets its own span so the fill effect can run word by word.
			var clipWords = function( text ) {

				var wordsHTML = '';

				( text || '' ).trim().split( /\s+/ ).forEach( function( word ) {
					if( '' !== word )
						wordsHTML += '<span class="pa-clip-word">' + _.escape( word ) + '</span> ';
				} );

				return wordsHTML.trim();
			};

			if( clipOnScroll ) {

				firstText = clipWords( firstText ) + ' ';

				secondText = clipWords( secondText );

				var clipSpeed = settings.clip_scroll_speed && settings.clip_scroll_speed.size ? settings.clip_scroll_speed.size : 0.5;

				view.addRenderAttribute('container', 'data-clip-speed', clipSpeed );
			}

			if ( ( clipOnScroll || descClip ) && 'yes' === settings.clip_scroll_lock ) {
				view.addRenderAttribute( 'container', 'data-clip-lock', 'true' );
			}

				view.addRenderAttribute('first_span', 'class', ['premium-dual-header-first-span', clipOnScroll ? 'pa-clipped-scroll' : '' ] );
				view.addRenderAttribute('first_span', 'data-text', firstRawText );

				view.addRenderAttribute('first_title', 'class', ['premium-dual-header-first-header', firstClip, firstGrad, firstStroke ] );
				view.addRenderAttribute('second_title', 'class', ['premium-dual-header-second-header', secondClip, secondGrad, secondStroke, clipOnScroll ? 'pa-clipped-scroll' : '' ] );

				view.addRenderAttribute('second_title', 'data-text', 'yes' === settings.noise_second ? secondRawText : '' );

			var link = '';
			if( 'yes' === settings.premium_dual_header_link_switcher ) {

				if( 'link' === settings.premium_dual_heading_link_selection ) {

					link = settings.premium_dual_heading_existing_link;

				} else {

					link = settings.premium_dual_heading_link.url;

				}
			}

			view.addRenderAttribute('container', 'class', 'premium-dual-header-wrapper' );


			view.addRenderAttribute( 'button', 'href', link );


		#>

		<# if ( ( clipOnScroll || descClip ) && ! paProActive ) { #>
			<div class="premium-error-notice">
				<?php echo wp_kses_post( __( 'This option is available in <b>Premium Addons Pro</b>.', 'premium-addons-for-elementor' ) ); ?>
			</div>
		<# } else { #>
		<div class="elementor-widget-container">
			<div {{{ view.getRenderAttributeString('container') }}}>
				<# if( 'yes' === settings.premium_dual_header_link_switcher && '' !== link ) { #>
					<a {{{ view.getRenderAttributeString('button') }}}>
				<# } #>
				<{{{firstTag}}} {{{ view.getRenderAttributeString('first_title') }}}>
					<span {{{ view.getRenderAttributeString('first_span') }}}>{{{ firstText }}}</span>
					<# if ( '' != secondText ) { #>
						<span {{{ view.getRenderAttributeString('second_title') }}}>{{{ secondText }}}</span>
					<# } #>
				</{{{firstTag}}}>
				<# if( 'yes' == settings.premium_dual_header_link_switcher && '' !== link ) { #>
					</a>
				<# } #>
				<# if ( 'yes' === settings.premium_dual_header_desc_switcher && '' !== settings.premium_dual_header_desc_text ) { #>
					<div {{{ view.getRenderAttributeString('description') }}}>{{{ settings.premium_dual_header_desc_text }}}</div>
				<# } #>
			</div>
		</div>
		<# } #>

		<?php
	}
}
