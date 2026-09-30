<?php
/**
 * Premium Title.
 */

namespace PremiumAddons\Widgets;

// Elementor Classes.
use Elementor\Plugin;
use Elementor\Widget_Base;
use Elementor\Utils;
use Elementor\Icons_Manager;
use Elementor\Controls_Manager;
use Elementor\Control_Media;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Box_Shadow;
use PremiumAddons\Includes\Controls\Premium_Background;

// PremiumAddons Classes.
use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Includes\Helper_Functions;
use PremiumAddons\Includes\Controls\Premium_Post_Filter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // If this file is called directly, abort.
}

/**
 * Class Premium_Title
 */
class Premium_Title extends Widget_Base {

	/**
	 * Check if the icon draw is enabled.
	 *
	 * @since 4.9.26
	 * @access private
	 *
	 * @var bool
	 */
	private $is_draw_enabled = null;

	/**
	 * Check Icon Draw Option.
	 *
	 * @since 4.9.26
	 * @access public
	 */
	public function check_icon_draw() {

		if ( null === $this->is_draw_enabled ) {
			$this->is_draw_enabled = Admin_Helper::check_svg_draw( 'premium-title' );
		}

		return $this->is_draw_enabled;
	}

	/**
	 * Retrieve Widget Name.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function get_name() {
		return 'premium-addon-title';
	}

	/**
	 * Retrieve Widget Title.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function get_title() {
		return __( 'Heading', 'premium-addons-for-elementor' );
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
		return 'pa-title';
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

			$draw_scripts = $this->check_icon_draw() ? array( 'pa-tweenmax', 'pa-motionpath' ) : array();

			$scripts = array_merge( $draw_scripts, array( 'pa-glass', 'lottie-js' ) );

			if ( Helper_Functions::check_papro_version() ) {
				$scripts[] = 'pa-clip-scroll';
			}
		} else {
			$settings = $this->get_settings();

			if ( 'yes' === $settings['draw_svg'] ) {
				$scripts[] = 'pa-tweenmax';
				$scripts[] = 'pa-motionpath';
			}

			if ( 'animation' === $settings['icon_type'] ) {
				$scripts[] = 'lottie-js';
			}

			if ( 'none' !== $settings['heading_lq_effect'] ) {
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
		return 'yes' === $settings['premium_title_desc_switcher'] && 'yes' === $settings['desc_clip_scroll'];
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
			&& ! in_array( $settings['premium_title_style'], array( 'style8', 'style9' ), true )
			&& 'yes' !== $settings['noise']
			&& 'yes' !== $settings['stroke_switcher']
			&& 'yes' !== $settings['mask_switcher'];
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
	 * Retrieve Widget Dependent CSS.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return array CSS style handles.
	 */
	public function get_keywords() {
		return array( 'pa', 'premium', 'premium heading', 'title', 'text', 'headline' );
	}

	protected function is_dynamic_content(): bool {
		return false;
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
		return ! Helper_Functions::check_elementor_experiment( 'e_optimized_markup' );
	}

	/**
	 * Register Title controls.
	 *
	 * @since  1.0.0
	 * @access protected
	 */
	protected function register_controls() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore

		$draw_icon = $this->check_icon_draw();

		$this->start_controls_section(
			'premium_title_content',
			array(
				'label' => __( 'Title', 'premium-addons-for-elementor' ),
			)
		);

		$demo = Helper_Functions::get_campaign_link( 'https://premiumaddons.com/heading-widget-for-elementor-page-builder/', 'heading', 'wp-editor', 'demo' );
		Helper_Functions::add_templates_controls( $this, 'heading', $demo );

		$this->add_control(
			'premium_title_text',
			array(
				'label'       => __( 'Title', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Premium {{Heading}}', 'premium-addons-for-elementor' ),
				'description' => __( 'Focused Word style tab settings will be applied on words wrapped by {{}}', 'premium-addons-for-elementor' ),
				'label_block' => true,
				'dynamic'     => array( 'active' => true ),
			)
		);

		$this->add_control(
			'premium_title_tag',
			array(
				'label'   => __( 'HTML Tag', 'premium-addons-for-elementor' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'h1'   => 'H1',
					'h2'   => 'H2',
					'h3'   => 'H3',
					'h4'   => 'H4',
					'h5'   => 'H5',
					'h6'   => 'H6',
					'div'  => 'div',
					'span' => 'span',
				),
				'default' => 'h2',
			)
		);

		$this->add_control(
			'premium_title_style',
			array(
				'label'       => __( 'Style', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'style1',
				'options'     => array(
					'style1' => __( 'Style 1', 'premium-addons-for-elementor' ),
					'style2' => __( 'Style 2', 'premium-addons-for-elementor' ),
					'style3' => __( 'Style 3', 'premium-addons-for-elementor' ),
					'style4' => __( 'Style 4', 'premium-addons-for-elementor' ),
					'style5' => __( 'Style 5', 'premium-addons-for-elementor' ),
					'style6' => __( 'Style 6', 'premium-addons-for-elementor' ),
					'style7' => __( 'Style 7', 'premium-addons-for-elementor' ),
					'style8' => __( 'Style 8', 'premium-addons-for-elementor' ),
					'style9' => __( 'Style 9', 'premium-addons-for-elementor' ),
				),
				'separator'   => 'before',
				'label_block' => true,
			)
		);

		$this->add_control(
			'style_notice',
			array(
				'raw'             => __( 'Please note that this style removes any styling given to focused word.', 'premium-addons-for-elementor' ),
				'type'            => Controls_Manager::RAW_HTML,
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
				'condition'       => array(
					'premium_title_style' => 'style9',
				),
			)
		);

		$inline_flex = array( 'style1', 'style2', 'style5', 'style6', 'style7', 'style8', 'style9' );

		$this->add_responsive_control(
			'premium_title_align',
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
				'toggle'    => false,
				'selectors' => array(
					'{{WRAPPER}} .premium-title-container' => 'text-align: {{VALUE}};',
				),
				'condition' => array(
					'premium_title_style' => $inline_flex,
				),
			)
		);

		$this->add_control(
			'premium_title_align_flex',
			array(
				'label'        => __( 'Alignment', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::CHOOSE,
				'prefix_class' => 'premium-title-',
				'options'      => array(
					'flex-start' => array(
						'title' => __( 'Left', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'     => array(
						'title' => __( 'Center', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-text-align-center',
					),
					'flex-end'   => array(
						'title' => __( 'Right', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default'      => 'flex-start',
				'selectors'    => array(
					'{{WRAPPER}}:not(.premium-title-icon-column) .premium-title-header' => 'justify-content: {{VALUE}}',
					'{{WRAPPER}}.premium-title-icon-column .premium-title-header' => 'align-items: {{VALUE}}',
				),
				'toggle'       => false,
				'condition'    => array(
					'premium_title_style' => array( 'style3', 'style4' ),
				),
			)
		);

		$this->add_control(
			'alignment_notice',
			array(
				'raw'             => __( 'Please note that left/right alignment is reversed when Icon Position is set to After.', 'premium-addons-for-elementor' ),
				'type'            => Controls_Manager::RAW_HTML,
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
				'condition'       => array(
					'premium_title_icon_switcher' => 'yes',
					'icon_position'               => 'row-reverse',
					'premium_title_style'         => array( 'style3', 'style4' ),
				),
			)
		);

		$this->add_control(
			'link_selection',
			array(
				'label'       => __( 'Link Type', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => array(
					'url'  => __( 'URL', 'premium-addons-for-elementor' ),
					'link' => __( 'Existing Page', 'premium-addons-for-elementor' ),
				),
				'default'     => 'url',
				'label_block' => true,
			)
		);

		$this->add_control(
			'custom_link',
			array(
				'label'       => __( 'Link', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::URL,
				'dynamic'     => array( 'active' => true ),
				'label_block' => true,
				'condition'   => array(
					'link_selection' => 'url',
				),
			)
		);

		$this->add_control(
			'existing_link',
			array(
				'label'       => __( 'Existing Page', 'premium-addons-for-elementor' ),
				'type'        => Premium_Post_Filter::TYPE,
				'label_block' => true,
				'multiple'    => false,
				'source'      => array( 'post', 'page' ),
				'condition'   => array(
					'link_selection' => 'link',
				),
			)
		);

		$this->add_control(
			'clip_scroll',
			array(
				'label'        => apply_filters( 'pa_pro_label', __( 'Fill on Scroll (Pro)', 'premium-addons-for-elementor' ) ),
				'description'  => __( 'Fill the title words one by one while scrolling. The After Fill Color is set from the Style tab.', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'prefix_class' => 'premium-fill-on-scroll-',
				'separator'    => 'before',
				'render_type'  => 'template',
				'condition'    => array(
					'premium_title_style!' => array( 'style8', 'style9' ),
					'noise!'               => 'yes',
					'mask_switcher!'       => 'yes',
					'stroke_switcher!'     => 'yes',
				),
			)
		);

		do_action(
			'pa_fill_on_scroll_controls',
			$this,
			array(
				'condition' => array(
					'clip_scroll'          => 'yes',
					'premium_title_style!' => array( 'style8', 'style9' ),
					'noise!'               => 'yes',
					'mask_switcher!'       => 'yes',
					'stroke_switcher!'     => 'yes',
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
			'stripe_section',
			array(
				'label'     => __( 'Stripe', 'premium-addons-for-elementor' ),
				'condition' => array(
					'premium_title_style' => 'style7',
				),
			)
		);

		$this->add_control(
			'premium_title_stripe_pos',
			array(
				'label'                => __( 'Stripe Position', 'premium-addons-for-elementor' ),
				'type'                 => Controls_Manager::SELECT,
				'options'              => array(
					'top'    => __( 'Top', 'premium-addons-for-elementor' ),
					'bottom' => __( 'Bottom', 'premium-addons-for-elementor' ),
				),
				'selectors_dictionary' => array(
					'top'    => 'initial',
					'bottom' => '2',
				),
				'default'              => 'top',
				'label_block'          => true,
				'separator'            => 'before',
				'selectors'            => array(
					'{{WRAPPER}} .premium-title-style7-stripe-wrap' => 'order: {{VALUE}}',
				),
			)
		);

		$this->add_responsive_control(
			'premium_title_style7_strip_width',
			array(
				'label'       => __( 'Stripe Width (PX)', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => array( 'px', '%', 'em', 'vw' ),
				'default'     => array(
					'unit' => 'px',
					'size' => '120',
				),
				'selectors'   => array(
					'{{WRAPPER}} .premium-title-style7-stripe' => 'width: {{SIZE}}{{UNIT}};',
				),
				'label_block' => true,
			)
		);

		$this->add_responsive_control(
			'premium_title_style7_strip_height',
			array(
				'label'       => __( 'Stripe Height (PX)', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => array( 'px', 'em' ),
				'default'     => array(
					'unit' => 'px',
					'size' => '5',
				),
				'label_block' => true,
				'selectors'   => array(
					'{{WRAPPER}} .premium-title-style7-stripe' => 'height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'premium_title_style7_strip_top_spacing',
			array(
				'label'       => __( 'Stripe Top Spacing (PX)', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => array( 'px', '%', 'em' ),
				'selectors'   => array(
					'{{WRAPPER}} .premium-title-style7-stripe-wrap' => 'margin-top: {{SIZE}}{{UNIT}};',
				),
				'label_block' => true,
			)
		);

		$this->add_responsive_control(
			'premium_title_style7_strip_bottom_spacing',
			array(
				'label'       => __( 'Stripe Bottom Spacing (PX)', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SLIDER,
				'size_units'  => array( 'px', '%', 'em' ),
				'label_block' => true,
				'selectors'   => array(
					'{{WRAPPER}} .premium-title-style7-stripe-wrap' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'premium_title_style7_strip_align',
			array(
				'label'     => __( 'Stripe Alignment', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array(
						'title' => __( 'Left', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'     => array(
						'title' => __( 'Center', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-text-align-center',
					),
					'flex-end'   => array(
						'title' => __( 'Right', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'toggle'    => false,
				'default'   => 'center',
				'selectors' => array(
					'{{WRAPPER}}:not(.premium-title-icon-column) .premium-title-style7-stripe-wrap' => 'justify-content: {{VALUE}}',
					'{{WRAPPER}}.premium-title-icon-column .premium-title-style7-stripe-wrap' => 'align-self: {{VALUE}}',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'icon_section',
			array(
				'label' => __( 'Icon', 'premium-addons-for-elementor' ),
			)
		);

		$this->add_control(
			'premium_title_icon_switcher',
			array(
				'label' => __( 'Icon', 'premium-addons-for-elementor' ),
				'type'  => Controls_Manager::SWITCHER,
			)
		);

		$this->add_control(
			'icon_type',
			array(
				'label'     => __( 'Icon Type', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'icon'      => array(
						'title' => __( 'Icon', 'premium-addons-for-elementor' ),
						'icon'  => 'divider-type-icon',
					),
					'image'     => array(
						'title' => __( 'Image', 'premium-addons-for-elementor' ),
						'icon'  => 'divider-type-image',
					),
					'animation' => array(
						'title' => __( 'Lottie Animation', 'premium-addons-for-elementor' ),
						'icon'  => 'divider-type-lottie',
					),
					'svg'       => array(
						'title' => __( 'SVG Code', 'premium-addons-for-elementor' ),
						'icon'  => 'divider-type-code',
					),
				),
				'default'   => 'icon',
				'condition' => array(
					'premium_title_icon_switcher' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'icon_position',
			array(
				'label'        => __( 'Icon Position', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::SELECT,
				'options'      => array(
					'row'            => __( 'Before', 'premium-addons-for-elementor' ),
					'row-reverse'    => __( 'After', 'premium-addons-for-elementor' ),
					'column'         => __( 'Top', 'premium-addons-for-elementor' ),
					'column-reverse' => __( 'Bottom', 'premium-addons-for-elementor' ),
				),
				'default'      => 'row',
				'toggle'       => false,
				'render_type'  => 'template',
				'prefix_class' => 'premium-title-icon-',
				'selectors'    => array(
					'{{WRAPPER}} .premium-title-header:not(.premium-title-style7), {{WRAPPER}} .premium-title-style7-inner' => 'flex-direction: {{VALUE}}',
				),
				'condition'    => array(
					'premium_title_icon_switcher' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'top_icon_align',
			array(
				'label'     => __( 'Icon Alignment', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array(
						'title' => __( 'Left', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'     => array(
						'title' => __( 'Center', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-text-align-center',
					),
					'flex-end'   => array(
						'title' => __( 'Right', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default'   => 'center',
				'toggle'    => false,
				'selectors' => array(
					'{{WRAPPER}}.premium-title-icon-column .premium-title-header:not(.premium-title-style7)' => 'align-items: {{VALUE}}',
					'{{WRAPPER}}.premium-title-icon-column .premium-title-style7 .premium-title-icon'      => 'align-self: {{VALUE}}',
				),
				'condition' => array(
					'premium_title_icon_switcher' => 'yes',
					'icon_position'               => 'column',
					'premium_title_style!'        => array( 'style3', 'style4' ),
				),
			)
		);

		$common_conditions = array(
			'premium_title_icon_switcher' => 'yes',
		);

		$this->add_control(
			'premium_title_icon_updated',
			array(
				'label'                  => __( 'Font Awesome Icon', 'premium-addons-for-elementor' ),
				'type'                   => Controls_Manager::ICONS,
				'fa4compatibility'       => 'premium_title_icon',
				'default'                => array(
					'value'   => 'fas fa-bars',
					'library' => 'fa-solid',
				),
				'exclude_inline_options' => 'none',
				'skin'                   => 'inline',
				'label_block'            => false,
				'condition'              => array_merge(
					$common_conditions,
					array(
						'icon_type' => 'icon',
					)
				),
			)
		);

		$this->add_control(
			'image_upload',
			array(
				'label'     => __( 'Upload Image', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::MEDIA,
				'default'   => array(
					'url' => Utils::get_placeholder_image_src(),
				),
				'condition' => array_merge(
					$common_conditions,
					array(
						'icon_type' => 'image',
					)
				),
			)
		);

		$this->add_control(
			'custom_svg',
			array(
				'label'       => __( 'SVG Code', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::TEXTAREA,
				'description' => 'You can use these sites to create SVGs: <a href="https://danmarshall.github.io/google-font-to-svg-path/" target="_blank">Google Fonts</a> and <a href="https://boxy-svg.com/" target="_blank">Boxy SVG</a>',
				'condition'   => array_merge(
					$common_conditions,
					array(
						'icon_type' => 'svg',
					)
				),
				'ai'          => array(
					'active' => false,
				),
			)
		);

		$this->add_control(
			'lottie_url',
			array(
				'label'       => __( 'Animation JSON URL', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => array( 'active' => true ),
				'description' => 'Get JSON code URL from <a href="https://lottiefiles.com/" target="_blank">here</a>',
				'label_block' => true,
				'condition'   => array_merge(
					$common_conditions,
					array(
						'icon_type' => 'animation',
					)
				),
				'ai'          => array(
					'active' => false,
				),
			)
		);

		$animation_conds = array(
			'terms' => array(
				array(
					'name'  => 'premium_title_icon_switcher',
					'value' => 'yes',
				),
				array(
					'relation' => 'or',
					'terms'    => array(
						array(
							'name'  => 'icon_type',
							'value' => 'animation',
						),
						array(
							'terms' => array(
								array(
									'relation' => 'or',
									'terms'    => array(
										array(
											'name'  => 'icon_type',
											'value' => 'icon',
										),
										array(
											'name'  => 'icon_type',
											'value' => 'svg',
										),
									),
								),
								array(
									'name'  => 'draw_svg',
									'value' => 'yes',
								),
							),
						),
					),
				),
			),
		);

		$this->add_control(
			'draw_svg',
			array(
				'label'       => __( 'Draw Icon', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SWITCHER,
				'description' => __( 'Enable this option to make the icon drawable. See ', 'premium-addons-for-elementor' ) . '<a href="https://www.youtube.com/watch?v=ZLr0bRe0RAY" target="_blank">tutorial</a>',
				'classes'     => $draw_icon ? '' : 'editor-pa-control-disabled',
				'condition'   => array_merge(
					$common_conditions,
					array(
						'icon_type' => array( 'icon', 'svg' ),
						'premium_title_icon_updated[library]!' => 'svg',
					)
				),
			)
		);

		if ( $draw_icon ) {

			$this->add_control(
				'path_width',
				array(
					'label'     => __( 'Path Thickness', 'premium-addons-for-elementor' ),
					'type'      => Controls_Manager::SLIDER,
					'range'     => array(
						'px' => array(
							'min'  => 0,
							'max'  => 50,
							'step' => 0.1,
						),
					),
					'condition' => array_merge(
						$common_conditions,
						array(
							'icon_type' => array( 'icon', 'svg' ),
						)
					),
					'selectors' => array(
						'{{WRAPPER}} .premium-title-header svg *' => 'stroke-width: {{SIZE}}',
					),
				)
			);

			$this->add_control(
				'svg_sync',
				array(
					'label'     => __( 'Draw All Paths Together', 'premium-addons-for-elementor' ),
					'type'      => Controls_Manager::SWITCHER,
					'condition' => array_merge(
						$common_conditions,
						array(
							'icon_type' => array( 'icon', 'svg' ),
							'draw_svg'  => 'yes',
						)
					),
				)
			);

			$this->add_control(
				'frames',
				array(
					'label'       => __( 'Speed', 'premium-addons-for-elementor' ),
					'type'        => Controls_Manager::NUMBER,
					'description' => __( 'Larger value means longer animation duration.', 'premium-addons-for-elementor' ),
					'default'     => 5,
					'min'         => 1,
					'max'         => 100,
					'condition'   => array_merge(
						$common_conditions,
						array(
							'icon_type' => array( 'icon', 'svg' ),
							'draw_svg'  => 'yes',
						)
					),
				)
			);

		} else {

			Helper_Functions::get_draw_svg_notice(
				$this,
				'heading',
				array_merge(
					$common_conditions,
					array(
						'icon_type' => array( 'icon', 'svg' ),
						'premium_title_icon_updated[library]!' => 'svg',
					)
				)
			);

		}

		$this->add_control(
			'lottie_loop',
			array(
				'label'        => __( 'Loop', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'true',
				'default'      => 'true',
				'conditions'   => $animation_conds,
			)
		);

		$this->add_control(
			'lottie_reverse',
			array(
				'label'        => __( 'Reverse', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'true',
				'conditions'   => $animation_conds,
			)
		);

		if ( $draw_icon ) {
			$this->add_control(
				'start_point',
				array(
					'label'       => __( 'Start Point (%)', 'premium-addons-for-elementor' ),
					'type'        => Controls_Manager::SLIDER,
					'description' => __( 'Set the point that the SVG should start from.', 'premium-addons-for-elementor' ),
					'default'     => array(
						'unit' => '%',
						'size' => 0,
					),
					'condition'   => array_merge(
						$common_conditions,
						array(
							'icon_type'       => array( 'icon', 'svg' ),
							'draw_svg'        => 'yes',
							'lottie_reverse!' => 'true',
						)
					),
				)
			);

			$this->add_control(
				'end_point',
				array(
					'label'       => __( 'End Point (%)', 'premium-addons-for-elementor' ),
					'type'        => Controls_Manager::SLIDER,
					'description' => __( 'Set the point that the SVG should end at.', 'premium-addons-for-elementor' ),
					'default'     => array(
						'unit' => '%',
						'size' => 0,
					),
					'condition'   => array_merge(
						$common_conditions,
						array(
							'icon_type'      => array( 'icon', 'svg' ),
							'draw_svg'       => 'yes',
							'lottie_reverse' => 'true',
						)
					),

				)
			);

			$this->add_control(
				'svg_hover',
				array(
					'label'        => __( 'Only Play on Hover', 'premium-addons-for-elementor' ),
					'type'         => Controls_Manager::SWITCHER,
					'return_value' => 'true',
					'condition'    => array_merge(
						$common_conditions,
						array(
							'icon_type' => array( 'icon', 'svg' ),
							'draw_svg'  => 'yes',
						)
					),
				)
			);

			$this->add_control(
				'svg_yoyo',
				array(
					'label'     => __( 'Yoyo Effect', 'premium-addons-for-elementor' ),
					'type'      => Controls_Manager::SWITCHER,
					'condition' => array_merge(
						$common_conditions,
						array(
							'icon_type'   => array( 'icon', 'svg' ),
							'draw_svg'    => 'yes',
							'lottie_loop' => 'true',
						)
					),
				)
			);
		}

		$this->end_controls_section();

		$this->start_controls_section(
			'background_text_section',
			array(
				'label' => __( 'Background Text', 'premium-addons-for-elementor' ),
			)
		);

		$this->add_control(
			'background_text_switcher',
			array(
				'label' => __( 'Background Text', 'premium-addons-for-elementor' ),
				'type'  => Controls_Manager::SWITCHER,
			)
		);

		$this->add_control(
			'background_text',
			array(
				'label'     => __( 'Text', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::TEXT,
				'condition' => array(
					'background_text_switcher' => 'yes',
				),

			)
		);

		$this->add_control(
			'bg_stroke_switcher',
			array(
				'label'        => __( 'Stroke', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'prefix_class' => 'premium-title-bg-stroke-',
				'condition'    => array(
					'background_text_switcher' => 'yes',
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
					'{{WRAPPER}} .premium-title-bg-text:before' => 'width: {{VALUE}}',
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
					'{{WRAPPER}} .premium-title-bg-text:before' => 'left: {{SIZE}}{{UNIT}}',
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
					'{{WRAPPER}} .premium-title-bg-text:before' => 'top: {{SIZE}}{{UNIT}}',
				),
				'condition'  => array(
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
					'{{WRAPPER}} .premium-title-bg-text:before' => 'transform: rotate({{SIZE}}{{UNIT}})',
				),
				'condition'  => array(
					'background_text_switcher' => 'yes',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'display_option_section',
			array(
				'label' => __( 'Display Options', 'premium-addons-for-elementor' ),
			)
		);

		$this->add_control(
			'hover_effect',
			array(
				'label'       => __( 'Underline Hover Effect', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => array(
					'none'  => __( 'None', 'premium-addons-for-elementor' ),
					'line1' => __( 'Effect 1', 'premium-addons-for-elementor' ),
					'line2' => __( 'Effect 2', 'premium-addons-for-elementor' ),
					'line3' => __( 'Effect 3', 'premium-addons-for-elementor' ),
					'line4' => __( 'Effect 4', 'premium-addons-for-elementor' ),
					'line5' => __( 'Effect 5', 'premium-addons-for-elementor' ),
					'line7' => __( 'Effect 6', 'premium-addons-for-elementor' ),
				),
				'default'     => 'none',
				'label_block' => true,
			)
		);

		$this->add_control(
			'stroke_switcher',
			array(
				'label'        => __( 'Apply Stroke', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'prefix_class' => 'premium-title-stroke-',

			)
		);

		$this->add_control(
			'mask_switcher',
			array(
				'label'        => __( 'Minimal Mask Effect', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'description'  => __( 'Note: This effect takes place once the element is in the viewport', 'premium-addons-for-elementor' ),
				'render_type'  => 'template',
				'prefix_class' => 'premium-mask-',
				'condition'    => array(
					'premium_title_style!'    => array( 'style8', 'style9' ),
					'gradient_text_switcher!' => 'yes',
				),
			)
		);

		$this->add_control(
			'mask_title_color',
			array(
				'label'       => __( 'Mask Color', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::COLOR,
				'render_type' => 'template',
				'selectors'   => array(
					'{{WRAPPER}}.premium-mask-yes .premium-mask-span::after'   => 'background: {{VALUE}};',
				),
				'condition'   => array(
					'mask_switcher'           => 'yes',
					'premium_title_style!'    => array( 'style8', 'style9' ),
					'gradient_text_switcher!' => 'yes',
				),
			)
		);

		$this->add_control(
			'mask_title_dir',
			array(
				'label'        => __( 'Direction', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::CHOOSE,
				'options'      => array(
					'tr' => array(
						'title' => esc_html__( 'To Right', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-arrow-right',
					),
					'tl' => array(
						'title' => esc_html__( 'To Left', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-arrow-left',
					),
					'tt' => array(
						'title' => esc_html__( 'To Top', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-arrow-up',
					),
					'tb' => array(
						'title' => esc_html__( 'To Bottom', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-arrow-down',
					),
				),
				'default'      => 'tr',
				'prefix_class' => 'premium-mask-',
				'render_type'  => 'template',
				'condition'    => array(
					'mask_switcher'           => 'yes',
					'premium_title_style!'    => array( 'style8', 'style9' ),
					'gradient_text_switcher!' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'mask_title_padding',
			array(
				'label'      => __( 'Words Padding', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-mask-span' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition'  => array(
					'mask_switcher'           => 'yes',
					'premium_title_style!'    => array( 'style8', 'style9' ),
					'gradient_text_switcher!' => 'yes',
				),
			)
		);

		$this->add_control(
			'noise',
			array(
				'label'        => __( 'Glitch Effect', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'prefix_class' => 'premium-title-noise-',
				'separator'    => 'before',
				'render_type'  => 'template',
				'condition'    => array(
					'premium_title_style!'    => array( 'style8', 'style9' ),
					'mask_switcher!'          => 'yes',
					'gradient_text_switcher!' => 'yes',
					'background_style'        => 'color',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'premium_title_description_section',
			array(
				'label' => __( 'Description', 'premium-addons-for-elementor' ),
			)
		);

		$this->add_control(
			'premium_title_desc_switcher',
			array(
				'label' => __( 'Description', 'premium-addons-for-elementor' ),
				'type'  => Controls_Manager::SWITCHER,
			)
		);

		$this->add_control(
			'premium_title_desc_text',
			array(
				'label'     => __( 'Description', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::WYSIWYG,
				'dynamic'   => array( 'active' => true ),
				'default'   => esc_html__( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Quis ipsum suspendisse ultrices gravida. Risus commodo viverra maecenas accumsan lacus vel facilisis.', 'premium-addons-for-elementor' ),
				'condition' => array(
					'premium_title_desc_switcher' => 'yes',
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
					'premium_title_desc_switcher' => 'yes',
				),
			)
		);

		do_action(
			'pa_fill_on_scroll_controls',
			$this,
			array(
				'prefix'    => 'desc_',
				'condition' => array(
					'premium_title_desc_switcher' => 'yes',
					'desc_clip_scroll'            => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'premium_title_desc_display',
			array(
				'label'        => __( 'Display', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::CHOOSE,
				'separator'    => 'before',
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
				'prefix_class' => 'premium-title-desc-display-',
				'toggle'       => false,
				'selectors'    => array(
					'{{WRAPPER}} .premium-title-wrapper' => 'flex-direction: {{VALUE}};',
				),
				'condition'    => array(
					'premium_title_desc_switcher' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'premium_title_desc_order',
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
					'{{WRAPPER}} .premium-title-description' => 'order: {{VALUE}};',
				),
				'condition' => array(
					'premium_title_desc_switcher' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'premium_title_desc_v_align',
			array(
				'label'       => __( 'Vertical Alignment', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::CHOOSE,
				'description' => __( 'Effective when the display is set to <b>"inline</b>"', 'premium-addons-for-elementor' ),
				'options'     => array(
					'start'   => array(
						'title' => __( 'Start', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-align-start-v',
					),
					'center'  => array(
						'title' => __( 'Center', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-align-center-v',
					),
					'end'     => array(
						'title' => __( 'End', 'premium-addons-for-elementor' ),
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
					'{{WRAPPER}} .premium-title-description' => 'align-self: {{VALUE}};',
				),
				'condition'   => array(
					'premium_title_desc_switcher' => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'premium_title_desc_align',
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
					'{{WRAPPER}} .premium-title-description' => 'text-align: {{VALUE}};',
				),
				'condition' => array(
					'premium_title_desc_switcher' => 'yes',
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

		$doc1_url = Helper_Functions::get_campaign_link( 'https://premiumaddons.com/docs/heading-widget-tutorial/', 'heading-widget', 'wp-editor', 'get-support' );

		$this->add_control(
			'doc_1',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => sprintf( '<a href="%s" target="_blank">%s</a>', $doc1_url, __( 'Getting started »', 'premium-addons-for-elementor' ) ),
				'content_classes' => 'editor-pa-doc',
			)
		);

		Helper_Functions::register_element_feedback_controls( $this );

		$this->end_controls_section();

		Helper_Functions::register_papro_promotion_controls( $this, 'heading' );

		$this->start_controls_section(
			'premium_title_style_section',
			array(
				'label' => __( 'Title', 'premium-addons-for-elementor' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'premium_title_color',
			array(
				'label'     => __( 'Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_PRIMARY,
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-title-header, {{WRAPPER}}.premium-title-noise-yes .premium-title-noise-word:not(.premium-title__focused-word)::before, {{WRAPPER}}.premium-title-noise-yes .premium-title-noise-word:not(.premium-title__focused-word)::after' => 'color: {{VALUE}}',
					'{{WRAPPER}}.premium-title-stroke-yes .premium-title-text' => '-webkit-text-fill-color: {{VALUE}}',
					'{{WRAPPER}} .premium-title-style8 .premium-title-text[data-animation="shiny"]' => '--base-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'title_after_clip_color',
			array(
				'label'     => __( 'After Fill Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .premium-title-text' => '--pa-clip-after: {{VALUE}};',
				),
				'condition' => array(
					'clip_scroll'          => 'yes',
					'premium_title_style!' => array( 'style8', 'style9' ),
					'noise!'               => 'yes',
					'mask_switcher!'       => 'yes',
					'stroke_switcher!'     => 'yes',
				),
			)
		);

		$this->add_control(
			'premium_title_blur_color',
			array(
				'label'     => __( 'Blur Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#000',
				'selectors' => array( '{{WRAPPER}} .premium-title-header' => '--shadow-color: {{VALUE}};' ),
				'condition' => array(
					'premium_title_style' => 'style9',
				),
			)
		);

		$this->add_control(
			'shining_shiny_color_title',
			array(
				'label'     => __( 'Shiny Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#fff',
				'condition' => array(
					'premium_title_style' => 'style8',
				),
				'selectors' => array( '{{WRAPPER}} .premium-title-style8 .premium-title-text[data-animation="shiny"]' => '--shiny-color: {{VALUE}}' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				),
				'selector' => '{{WRAPPER}} .premium-title-header',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'      => 'style_one_border',
				'selector'  => '{{WRAPPER}} .premium-title-style1',
				'condition' => array(
					'premium_title_style' => 'style1',
				),
			)
		);

		$this->add_control(
			'background_style',
			array(
				'label'        => __( 'Background Style', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::SELECT,
				'description'  => __( 'Choose ‘Normal’ to add a background behind the text and ‘Clipped’ so the background will be clipped on the text.', 'premium-addons-for-elementor' ),
				'options'      => array(
					'color'   => __( 'Normal', 'premium-addons-for-elementor' ),
					'clipped' => __( 'Clipped', 'premium-addons-for-elementor' ),
				),
				'prefix_class' => 'premium-title-',
				'default'      => 'color',
				'label_block'  => true,
				'separator'    => 'before',
				'condition'    => array(
					'premium_title_style!' => array( 'style8', 'style9' ),
					'clip_scroll!'         => 'yes',
				),
			)
		);

		$this->add_group_control(
			Premium_Background::get_type(),
			array(
				'name'      => 'title_background',
				'types'     => array( 'classic', 'gradient' ),
				'condition' => array(
					'premium_title_style!' => array( 'style8', 'style9' ),
					'background_style!'    => '',
				),
				'selector'  => '{{WRAPPER}}:not(.premium-title-clipped) .premium-title-header, {{WRAPPER}}.premium-title-clipped .premium-title-text',
			)
		);

		$this->add_control(
			'premium_title_style2_background_color',
			array(
				'label'     => __( 'Background Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_SECONDARY,
				),
				'selectors' => array(
					'{{WRAPPER}}:not(.premium-title-clipped) .premium-title-style2, {{WRAPPER}}.premium-title-clipped .premium-title-text' => 'background-color: {{VALUE}};',
				),
				'condition' => array(
					'premium_title_style' => 'style2',
					'background_style'    => '',
				),
			)
		);

		$this->add_control(
			'premium_title_style3_background_color',
			array(
				'label'     => __( 'Background Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_SECONDARY,
				),
				'selectors' => array(
					'{{WRAPPER}}:not(.premium-title-clipped) .premium-title-style3, {{WRAPPER}}.premium-title-clipped .premium-title-text' => 'background-color: {{VALUE}};',
				),
				'condition' => array(
					'premium_title_style' => 'style3',
					'background_style'    => '',
				),
			)
		);

		$this->add_control(
			'heading_lq_effect',
			array(
				'label'       => __( 'Liquid Glass Effect', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SELECT,
				'description' => sprintf(
					/* translators: 1: `<a>` opening tag, 2: `</a>` closing tag. */
					esc_html__( 'Important: Make sure this element has a semi-transparent background color to see the effect. See all presets from %1$shere%2$s.', 'premium-addons-for-elementor' ),
					'<a href="https://premiumaddons.com/liquid-glass/" target="_blank">',
					'</a>'
				),
				'options'     => array(
					'none'   => __( 'None', 'premium-addons-for-elementor' ),
					'glass1' => __( 'Preset 01', 'premium-addons-for-elementor' ),
					'glass2' => __( 'Preset 02', 'premium-addons-for-elementor' ),
					'glass3' => apply_filters( 'pa_pro_label', __( 'Preset 03 (Pro)', 'premium-addons-for-elementor' ) ),
					'glass4' => apply_filters( 'pa_pro_label', __( 'Preset 04 (Pro)', 'premium-addons-for-elementor' ) ),
					'glass5' => apply_filters( 'pa_pro_label', __( 'Preset 05 (Pro)', 'premium-addons-for-elementor' ) ),
					'glass6' => apply_filters( 'pa_pro_label', __( 'Preset 06 (Pro)', 'premium-addons-for-elementor' ) ),
				),
				'default'     => 'none',
				'label_block' => true,
				'condition'   => array(
					'background_style'     => 'color',
					'premium_title_style!' => array( 'style8', 'style9' ),
				),
			)
		);

		$this->add_control(
			'premium_title_style5_header_line_color',
			array(
				'label'     => __( 'Line Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_PRIMARY,
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-title-style5' => 'border-bottom: 2px solid {{VALUE}};',
				),
				'condition' => array(
					'premium_title_style' => 'style5',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'      => 'style_five_border',
				'selector'  => '{{WRAPPER}} .premium-title-container',
				'condition' => array(
					'premium_title_style' => array( 'style2', 'style4', 'style5', 'style6' ),
				),
			)
		);

		$this->add_control(
			'premium_title_style6_header_line_color',
			array(
				'label'     => __( 'Line Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_PRIMARY,
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-title-style6' => 'border-bottom: 2px solid {{VALUE}};',
				),
				'condition' => array(
					'premium_title_style' => 'style6',
				),
			)
		);

		$this->add_control(
			'premium_title_style6_triangle_color',
			array(
				'label'     => __( 'Triangle Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_PRIMARY,
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-title-style6:before' => 'border-bottom-color: {{VALUE}};',
				),
				'condition' => array(
					'premium_title_style' => 'style6',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'label'          => __( 'Shadow', 'premium-addons-for-elementor' ),
				'name'           => 'premium_title_text_shadow',
				// 'selector' => '{{WRAPPER}} .premium-title-header',
				'fields_options' => array(
					'text_shadow' => array(
						'selectors' => array(
							'{{WRAPPER}}:not(.premium-title-gradient-yes, .premium-fill-on-scroll-yes) .premium-title-header' => 'text-shadow: {{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{COLOR}};',
							'{{WRAPPER}}.premium-title-gradient-yes .premium-title-header, {{WRAPPER}}.premium-fill-on-scroll-yes .premium-title-header .pa-clip-word' => 'filter: drop-shadow({{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{COLOR}})',
						),
					),
				),
			)
		);

		$this->add_control(
			'premium_title_shadow_value',
			array(
				'label'     => esc_html__( 'Blur Shadow Value (px)', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::NUMBER,
				'min'       => '10',
				'max'       => '500',
				'step'      => '10',
				'dynamic'   => array( 'active' => true ),
				'selectors' => array( '{{WRAPPER}} .premium-title-header' => '--shadow-value: {{VALUE}}px;' ),
				'default'   => '120',
				'condition' => array(
					'premium_title_style' => 'style9',
				),
			)
		);

		$this->add_control(
			'premium_title_delay',
			array(
				'label'       => esc_html__( 'Animation Delay (s)', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::NUMBER,
				'min'         => '1',
				'max'         => '30',
				'step'        => 0.5,
				'condition'   => array(
					'premium_title_style' => array( 'style8', 'style9' ),
				),
				'render_type' => 'template',
				'dynamic'     => array( 'active' => true ),
				'default'     => '2',
			)
		);

		$this->add_control(
			'shining_animation_duration',
			array(
				'label'              => __( 'Animation Duration (s)', 'premium-addons-for-elementor' ),
				'type'               => Controls_Manager::NUMBER,
				'default'            => '1',
				'step'               => 0.5,
				'condition'          => array(
					'premium_title_style' => 'style8',
				),
				'frontend_available' => true,
				'render_type'        => 'template',
				'selectors'          => array(
					'{{WRAPPER}} .premium-title-style8 .premium-title-text[data-animation="shiny"]' => '--animation-speed: {{VALUE}}s ',
				),
			)
		);

		$this->add_responsive_control(
			'premium_title_margin',
			array(
				'label'      => __( 'Margin', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%', 'vw', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-title-text' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'premium_title_padding',
			array(
				'label'      => __( 'Padding', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%', 'vw', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-title-header, {{WRAPPER}}.premium-title-noise-yes .premium-title-noise-word::before, {{WRAPPER}}.premium-title-noise-yes .premium-title-noise-word::after' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'gradient_text_switcher',
			array(
				'label'        => __( 'Animated Gradient', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'prefix_class' => 'premium-title-gradient-',
				'separator'    => 'before',
				'condition'    => array(
					'premium_title_style!' => array( 'style8', 'style9' ),
					'mask_switcher!'       => 'yes',
					'stroke_switcher!'     => 'yes',
					'background_style'     => 'color',
					'clip_scroll!'         => 'yes',
				),
			)
		);

		$this->add_control(
			'animation_transition_speed',
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
					'{{WRAPPER}}.premium-title-gradient-yes .premium-title-text, {{WRAPPER}}.premium-title-gradient-yes .premium-title-icon' => 'animation-duration: {{SIZE}}s ',
				),
				'condition' => array(
					'gradient_text_switcher' => 'yes',
					'premium_title_style!'   => array( 'style8', 'style9' ),
					'mask_switcher!'         => 'yes',
					'stroke_switcher!'       => 'yes',
					'background_style'       => 'color',
					'clip_scroll!'           => 'yes',
				),
			)
		);

		$this->add_group_control(
			Premium_Background::get_type(),
			array(
				'name'      => 'text_gradient',
				'types'     => array( 'gradient' ),
				'selector'  => '{{WRAPPER}}.premium-title-gradient-yes .premium-title-text, {{WRAPPER}}.premium-title-gradient-yes .premium-title-icon',
				'condition' => array(
					'gradient_text_switcher' => 'yes',
					'premium_title_style!'   => array( 'style8', 'style9' ),
					'mask_switcher!'         => 'yes',
					'stroke_switcher!'       => 'yes',
					'background_style'       => 'color',
					'clip_scroll!'           => 'yes',
				),
			)
		);

		$this->add_control(
			'noise_heading',
			array(
				'label'     => __( 'Glitch Effect', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::HEADING,
				'condition' => array(
					'noise'                   => 'yes',
					'premium_title_style!'    => array( 'style8', 'style9' ),
					'mask_switcher!'          => 'yes',
					'gradient_text_switcher!' => 'yes',
					'background_style'        => 'color',
				),
			)
		);

		$this->add_control(
			'noise_first_color',
			array(
				'label'     => __( 'Color #1', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => array(
					'noise'                   => 'yes',
					'premium_title_style!'    => array( 'style8', 'style9' ),
					'mask_switcher!'          => 'yes',
					'gradient_text_switcher!' => 'yes',
					'background_style'        => 'color',
				),
				'selectors' => array(
					'{{WRAPPER}}.premium-title-noise-yes .premium-title-noise-word::before' => 'text-shadow: 1px 0 {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'noise_second_color',
			array(
				'label'     => __( 'Color #2', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => array(
					'noise'                   => 'yes',
					'premium_title_style!'    => array( 'style8', 'style9' ),
					'mask_switcher!'          => 'yes',
					'gradient_text_switcher!' => 'yes',
					'background_style'        => 'color',
				),
				'selectors' => array(
					'{{WRAPPER}}.premium-title-noise-yes .premium-title-noise-word::after' => 'text-shadow: -1px 0 {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'stroke_style_section',
			array(
				'label'     => __( 'Stroke', 'premium-addons-for-elementor' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'stroke_switcher'      => 'yes',
					'premium_title_style!' => 'style9',
					'background_style'     => 'color',
				),
			)
		);

		$this->add_control(
			'stroke_text_color',
			array(
				'label'     => __( 'Stroke Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => array(
					'stroke_switcher' => 'yes',
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-title-text' => '-webkit-text-stroke-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'stroke_width',
			array(
				'label'     => __( 'Stroke Fill Width', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::SLIDER,
				'condition' => array(
					'stroke_switcher' => 'yes',
				),
				'default'   => array(
					'size' => 1,
					'unit' => 'px',
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-title-text' => '-webkit-text-stroke-width: {{SIZE}}px',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'focused_word_style_section',
			array(
				'label' => __( 'Focused Word', 'premium-addons-for-elementor' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'focused_word_color',
			array(
				'label'     => __( 'Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-title-text .premium-title__focused-word' => 'color: {{VALUE}}',
					'{{WRAPPER}}.premium-title-noise-yes .premium-title__focused-word::before, {{WRAPPER}}.premium-title-noise-yes .premium-title__focused-word::after' => 'color: {{VALUE}}',
					'{{WRAPPER}}.premium-title-stroke-yes .premium-title__focused-word' => '-webkit-text-fill-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'focused_after_clip_color',
			array(
				'label'     => __( 'After Fill Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .premium-title-text .premium-title__focused-word' => '--pa-clip-after: {{VALUE}};',
				),
				'condition' => array(
					'clip_scroll'          => 'yes',
					'premium_title_style!' => array( 'style8', 'style9' ),
					'noise!'               => 'yes',
					'mask_switcher!'       => 'yes',
					'stroke_switcher!'     => 'yes',
				),
			)
		);

		$this->add_responsive_control(
			'focused_stroke_width',
			array(
				'label'     => __( 'Stroke Fill Width', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => array(
					'size' => 1,
					'unit' => 'px',
				),
				'selectors' => array(
					'{{WRAPPER}}.premium-title-stroke-yes .premium-title__focused-word' => '-webkit-text-stroke-width: {{SIZE}}px',
				),
				'condition' => array(
					'stroke_switcher' => 'yes',
				),
			)
		);

		$this->add_control(
			'focused_stroke_text_color',
			array(
				'label'     => __( 'Stroke Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}.premium-title-stroke-yes .premium-title__focused-word' => '-webkit-text-stroke-color: {{VALUE}};',
				),
				'condition' => array(
					'stroke_switcher' => 'yes',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'focused_word_typography',
				'selector' => '{{WRAPPER}} .premium-title-text .premium-title__focused-word',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'focused_word_border',
				'selector' => '{{WRAPPER}} .premium-title-text .premium-title__focused-word',
			)
		);

		$this->add_control(
			'focused_word_border_radius',
			array(
				'label'      => __( 'Border Radius', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-title-text .premium-title__focused-word' => 'border-radius: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'icon_adv_radius!' => 'yes',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'name'           => 'focused_word_shadow',
				'fields_options' => array(
					'text_shadow' => array(
						'selectors' => array(
							'{{WRAPPER}} .premium-title-text:not(.pa-clipped-scroll) .premium-title__focused-word' => 'text-shadow: {{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{COLOR}};',
							'{{WRAPPER}} .premium-title-text.pa-clipped-scroll .premium-title__focused-word .pa-clip-word' => 'filter: drop-shadow({{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{COLOR}})',
						),
					),
				),
			)
		);

		$this->add_responsive_control(
			'focused_word_margin',
			array(
				'label'              => __( 'Margin', 'premium-addons-for-elementor' ),
				'type'               => Controls_Manager::DIMENSIONS,
				'size_units'         => array( 'px', 'em', '%', 'custom' ),
				'allowed_dimensions' => array( 'left', 'right' ),
				'placeholder'        => array(
					'top'    => '',
					'right'  => '',
					'bottom' => '',
					'left'   => '',
				),
				'selectors'          => array(
					'{{WRAPPER}} .premium-title-text .premium-title__focused-word' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'focused_word_padding',
			array(
				'label'      => __( 'Padding', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-title-text .premium-title__focused-word' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'premium_title_icon_style_section',
			array(
				'label'     => __( 'Icon', 'premium-addons-for-elementor' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'premium_title_icon_switcher' => 'yes',
				),
			)
		);

		$this->add_control(
			'premium_title_icon_color',
			array(
				'label'     => __( 'Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_PRIMARY,
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-title-icon' => 'color: {{VALUE}}',
					'{{WRAPPER}} .premium-drawable-icon *, {{WRAPPER}} svg:not([class*="premium-"])' => 'fill: {{VALUE}};',
				),
				'condition' => array(
					'icon_type' => array( 'icon', 'svg' ),
				),
			)
		);

		if ( $draw_icon ) {
			$this->add_control(
				'stroke_color',
				array(
					'label'     => __( 'Stroke Color', 'premium-addons-for-elementor' ),
					'type'      => Controls_Manager::COLOR,
					'global'    => array(
						'default' => Global_Colors::COLOR_ACCENT,
					),
					'condition' => array(
						'icon_type' => array( 'icon', 'svg' ),
					),
					'selectors' => array(
						'{{WRAPPER}} .premium-drawable-icon *, {{WRAPPER}} svg:not([class*="premium-"])' => 'stroke: {{VALUE}};',
					),
				)
			);

			$this->add_control(
				'svg_color',
				array(
					'label'     => __( 'After Draw Fill Color', 'premium-addons-for-elementor' ),
					'type'      => Controls_Manager::COLOR,
					'global'    => false,
					'separator' => 'after',
					'condition' => array(
						'icon_type' => array( 'icon', 'svg' ),
						'draw_svg'  => 'yes',
					),
				)
			);
		}

		$this->add_responsive_control(
			'premium_title_icon_size',
			array(
				'label'      => __( 'Size', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', '%', 'vw', 'custom' ),
				'range'      => array(
					'px' => array(
						'min' => 10,
						'max' => 300,
					),
					'em' => array(
						'min' => 1,
						'max' => 30,
					),
				),
				'condition'  => array(
					'icon_type!' => 'svg',
				),
				'selectors'  => array(
					'{{WRAPPER}} .premium-title-header i' => 'font-size: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}} .premium-title-header svg, {{WRAPPER}} .premium-title-header img' => 'width: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important',
				),
			)
		);

		$this->add_responsive_control(
			'svg_icon_width',
			array(
				'label'      => __( 'Width', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', '%', 'vw', 'custom' ),
				'range'      => array(
					'px' => array(
						'min' => 1,
						'max' => 600,
					),
					'em' => array(
						'min' => 1,
						'max' => 30,
					),
				),
				'default'    => array(
					'size' => 100,
					'unit' => 'px',
				),
				'condition'  => array(
					'icon_type' => 'svg',
				),
				'selectors'  => array(
					'{{WRAPPER}} .premium-title-header svg' => 'width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'svg_icon_height',
			array(
				'label'      => __( 'Height', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'custom' ),
				'range'      => array(
					'px' => array(
						'min' => 1,
						'max' => 300,
					),
					'em' => array(
						'min' => 1,
						'max' => 30,
					),
				),
				'condition'  => array(
					'icon_type' => 'svg',
				),
				'selectors'  => array(
					'{{WRAPPER}} .premium-title-header svg' => 'height: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_group_control(
			Premium_Background::get_type(),
			array(
				'name'     => 'premium_title_icon_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .premium-title-icon',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'premium_title_icon_border',
				'selector' => '{{WRAPPER}} .premium-title-icon',
			)
		);

		$this->add_control(
			'premium_title_icon_border_radius',
			array(
				'label'      => __( 'Border Radius', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-title-icon' => 'border-radius: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'icon_adv_radius!' => 'yes',
				),
			)
		);

		$this->add_control(
			'icon_adv_radius',
			array(
				'label'       => __( 'Advanced Border Radius', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SWITCHER,
				'description' => __( 'Apply custom radius values. Get the radius value from ', 'premium-addons-for-elementor' ) . '<a href="https://9elements.github.io/fancy-border-radius/" target="_blank">here</a>' . __( '. See ', 'premium-addons-for-elementor' ) . '<a href="https://www.youtube.com/watch?v=S0BJazLHV-M" target="_blank">tutorial</a>',
			)
		);

		$this->add_control(
			'icon_adv_radius_value',
			array(
				'label'     => __( 'Border Radius', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::TEXT,
				'dynamic'   => array( 'active' => true ),
				'selectors' => array(
					'{{WRAPPER}} .premium-title-icon' => 'border-radius: {{VALUE}};',
				),
				'condition' => array(
					'icon_adv_radius' => 'yes',
				),
				'ai'        => array(
					'active' => false,
				),
			)
		);

		$this->add_responsive_control(
			'premium_title_icon_margin',
			array(
				'label'      => __( 'Margin', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%', 'vw', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-title-icon' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				),
			)
		);

		$this->add_responsive_control(
			'premium_title_icon_padding',
			array(
				'label'      => __( 'Padding', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%', 'vw', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-title-icon' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'label'     => __( 'Icon Shadow', 'premium-addons-for-elementor' ),
				'name'      => 'premium_title_icon_text_shadow',
				'selector'  => '{{WRAPPER}} .premium-title-icon',
				'condition' => array(
					'icon_type' => 'icon',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'premium_title_desc_style_section',
			array(
				'label'     => __( 'Description', 'premium-addons-for-elementor' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'premium_title_desc_switcher' => 'yes',
				),
			)
		);

		$this->add_control(
			'premium_title_desc_color',
			array(
				'label'     => __( 'Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .premium-title-description, {{WRAPPER}} .premium-title-description *' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'desc_after_clip_color',
			array(
				'label'     => __( 'After Fill Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .premium-title-description' => '--pa-clip-after: {{VALUE}};',
				),
				'condition' => array(
					'desc_clip_scroll' => 'yes',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'premium_title_desc_typography',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				),
				'selector' => '{{WRAPPER}} .premium-title-description',
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'name'           => 'premium_title_desc_text_shadow',
				'fields_options' => array(
					'text_shadow' => array(
						'selectors' => array(
							'{{WRAPPER}} .premium-title-description:not(.pa-clipped-scroll)' => 'text-shadow: {{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{COLOR}};',
							'{{WRAPPER}} .premium-title-description.pa-clipped-scroll .pa-clip-word' => 'filter: drop-shadow({{HORIZONTAL}}px {{VERTICAL}}px {{BLUR}}px {{COLOR}})',
						),
					),
				),
			)
		);

		$this->add_group_control(
			Premium_Background::get_type(),
			array(
				'name'     => 'premium_title_desc_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .premium-title-description',
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'premium_title_desc_box_shadow',
				'selector' => '{{WRAPPER}} .premium-title-description',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'premium_title_desc_border',
				'selector' => '{{WRAPPER}} .premium-title-description',
			)
		);

		$this->add_responsive_control(
			'premium_title_desc_border_radius',
			array(
				'label'      => __( 'Border Radius', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-title-description' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'premium_title_desc_margin',
			array(
				'label'      => __( 'Margin', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-title-description' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'premium_title_desc_padding',
			array(
				'label'      => __( 'Padding', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-title-description' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'stripe_style_section',
			array(
				'label'     => __( 'Stripe', 'premium-addons-for-elementor' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'premium_title_style' => 'style7',
				),
			)
		);

		$this->add_control(
			'premium_title_style7_strip_color',
			array(
				'label'     => __( 'Stripe Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_PRIMARY,
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-title-style7-stripe' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'stripe_margin',
			array(
				'label'      => __( 'Margin', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-title-style7-stripe' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
					'{{WRAPPER}} .premium-title-bg-text:before' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'bg_stroke_text_color',
			array(
				'label'     => __( 'Stroke Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => array(
					'bg_stroke_switcher' => 'yes',
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-title-bg-text::before' => '-webkit-text-stroke-color: {{VALUE}};',
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
				'selector' => '{{WRAPPER}} .premium-title-bg-text:before',
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'name'     => 'background_text_shadow',
				'selector' => '{{WRAPPER}} .premium-title-bg-text:before',
			)
		);

		$this->add_responsive_control(
			'bg_stroke_width',
			array(
				'label'     => __( 'Stroke Fill Width', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => array(
					'size' => 1,
					'unit' => 'px',
				),
				'condition' => array(
					'bg_stroke_switcher' => 'yes',
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-title-bg-text::before' => '-webkit-text-stroke-width: {{SIZE}}px',
				),
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
					'overlay'     => 'Overlay',
					'darken'      => 'Darken',
					'lighten'     => 'Lighten',
					'color-dodge' => 'Color Dodge',
					'saturation'  => 'Saturation',
					'color'       => 'Color',
					'luminosity'  => 'Luminosity',
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-title-bg-text:before' => 'mix-blend-mode: {{VALUE}}',
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
					'{{WRAPPER}} .premium-title-bg-text:before' => 'z-index: {{VALUE}}',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'underline_style_section',
			array(
				'label'     => __( 'Underline', 'premium-addons-for-elementor' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'hover_effect!' => 'none',
				),
			)
		);

		$this->add_control(
			'underline_color',
			array(
				'label'     => __( 'Line Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_SECONDARY,
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-btn-svg' => 'stroke: {{VALUE}};',
					'{{WRAPPER}} .premium-button-line2::before,  {{WRAPPER}} .premium-button-line4::before, {{WRAPPER}} .premium-button-line5::before, {{WRAPPER}} .premium-button-line5::after, {{WRAPPER}} .premium-button-line7::before' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'line_width',
			array(
				'label'     => __( 'Line Width (%)', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::SLIDER,
				'condition' => array(
					'hover_effect' => array( 'line1', 'line3', 'line5' ),
				),
				'default'   => array(
					'size' => 100,
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-btn-svg, {{WRAPPER}} .premium-button-line5::before, {{WRAPPER}} .premium-button-line5::after' => 'width: {{SIZE}}%',
				),
			)
		);

		$this->add_responsive_control(
			'line_height',
			array(
				'label'     => __( 'Line Height (PX)', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::SLIDER,
				'condition' => array(
					'hover_effect!' => array( 'line1', 'line3', 'line4' ),
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-button-line2::before, {{WRAPPER}} .premium-button-line5::before, {{WRAPPER}} .premium-button-line5::after, {{WRAPPER}} .premium-button-line7::before' => 'height: {{SIZE}}px',
				),
			)
		);

		$this->add_responsive_control(
			'line_h_position',
			array(
				'label'     => __( 'Line Horizontal Position (%)', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::SLIDER,
				'condition' => array(
					'hover_effect' => array( 'line3', 'line5' ),
				),
				'selectors' => array(
					'{{WRAPPER}} .premium-btn-line-wrap, {{WRAPPER}} .premium-button-line5::before, {{WRAPPER}} .premium-button-line5::after' => 'left: {{SIZE}}%',
				),
			)
		);

		$this->add_responsive_control(
			'line_v_position',
			array(
				'label'     => __( 'Line Vertical Position (%)', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::SLIDER,
				'selectors' => array(
					'{{WRAPPER}} .premium-btn-line-wrap, {{WRAPPER}} .premium-button-line2::before, {{WRAPPER}} .premium-button-line5::before, {{WRAPPER}} .premium-button-line7::before' => 'top: {{SIZE}}%',
					'{{WRAPPER}} .premium-button-line5::after' => 'top: calc( ( {{SIZE}}% + 2px ) + {{line_height.SIZE}}px )',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render title widget output on the frontend.
	 *
	 * Written in PHP and used to generate the final HTML.
	 *
	 * @since  1.0.0
	 * @access protected
	 */
	protected function render() {

		$settings = $this->get_settings_for_display();

		$this->add_render_attribute( 'premium_title_text', 'class', 'premium-title-text' );

		$title_tag = Helper_Functions::validate_html_tag( $settings['premium_title_tag'] );

		$selected_style = $settings['premium_title_style'];

		$this->add_render_attribute( 'container', 'class', 'premium-title-container' );

		$this->add_render_attribute( 'title', 'class', array( 'premium-title-header', 'premium-title-' . $selected_style ) );

		if ( 'none' !== $settings['heading_lq_effect'] ) {
			$this->add_render_attribute( 'title', 'class', 'premium-con-lq__' . $settings['heading_lq_effect'] );
		}

		if ( 'none' !== $settings['hover_effect'] ) {
			$this->add_render_attribute( 'title', 'class', 'premium-button-' . $settings['hover_effect'] );
		}

		if ( 'style8' === $selected_style ) {

			$this->add_render_attribute(
				'premium_title_text',
				array(
					'data-shiny-delay' => $settings['premium_title_delay'],
					'data-shiny-dur'   => $settings['shining_animation_duration'],
				)
			);

		} elseif ( 'style9' === $selected_style ) {

			$this->add_render_attribute( 'title', 'data-blur-delay', $settings['premium_title_delay'] );

		}

		$icon_position = '';

		if ( 'yes' === $settings['premium_title_icon_switcher'] ) {

			$icon_type = $settings['icon_type'];

			$icon_position = $settings['icon_position'];

			if ( 'icon' === $icon_type || 'svg' === $icon_type ) {

				$this->add_render_attribute( 'icon', 'class', 'premium-drawable-icon' );

				if ( ( 'yes' === $settings['draw_svg'] && 'icon' === $icon_type ) || 'svg' === $icon_type ) {
					$this->add_render_attribute( 'icon', 'class', 'premium-title-icon' );
				}

				if ( 'yes' === $settings['draw_svg'] ) {

					$this->add_render_attribute(
						'container',
						'class',
						array(
							'elementor-invisible',
							'premium-drawer-hover',
						)
					);

					$this->add_render_attribute(
						'icon',
						array(
							'class'            => 'premium-svg-drawer',
							'data-svg-reverse' => $settings['lottie_reverse'],
							'data-svg-loop'    => $settings['lottie_loop'],
							'data-svg-sync'    => $settings['svg_sync'],
							'data-svg-hover'   => $settings['svg_hover'],
							'data-svg-fill'    => $settings['svg_color'],
							'data-svg-frames'  => $settings['frames'],
							'data-svg-yoyo'    => $settings['svg_yoyo'],
							'data-svg-point'   => $settings['lottie_reverse'] ? $settings['end_point']['size'] : $settings['start_point']['size'],
						)
					);

				} else {
					$this->add_render_attribute( 'icon', 'class', 'premium-svg-nodraw' );
				}
			} elseif ( 'animation' === $icon_type ) {
				$this->add_render_attribute(
					'title_lottie',
					array(
						'class'               => array(
							'premium-title-icon',
							'premium-lottie-animation',
						),
						'data-lottie-url'     => $settings['lottie_url'],
						'data-lottie-loop'    => $settings['lottie_loop'],
						'data-lottie-reverse' => $settings['lottie_reverse'],
					)
				);
			} else {

				$src = $settings['image_upload']['url'];

				$alt = Control_Media::get_image_alt( $settings['image_upload'] );

				$this->add_render_attribute(
					'title_img',
					array(
						'class' => 'premium-title-icon',
						'src'   => $src,
						'alt'   => $alt,
					)
				);
			}
		}

		$link = '';
		if ( 'link' === $settings['link_selection'] ) {

			$link = ! empty( $settings['existing_link'] ) ? get_permalink( $settings['existing_link'] ) : '';
			$this->add_render_attribute( 'link', 'href', $link );

		} else {
			$link = $settings['custom_link']['url'];
			$this->add_link_attributes( 'link', $settings['custom_link'] );

		}

		if ( ! empty( $settings['background_text'] ) ) {
			$this->add_render_attribute(
				'container',
				array(
					'class'           => 'premium-title-bg-text',
					'data-background' => $settings['background_text'],
				)
			);
		}

		$show_desc = 'yes' === $settings['premium_title_desc_switcher'] && ! empty( $settings['premium_title_desc_text'] );

		if ( $show_desc ) {
			$this->add_render_attribute( 'title_description', 'class', 'premium-title-description' );
		}

		$clip_on_scroll = $this->fills_on_scroll( $settings );
		$desc_clip      = $show_desc && $this->description_fills_on_scroll( $settings );

		if ( ( $clip_on_scroll || $desc_clip ) && ! Helper_Functions::check_papro_version() ) {
			?>
			<div class="premium-error-notice">
				<?php echo wp_kses_post( __( 'This option is available in <b>Premium Addons Pro</b>.', 'premium-addons-for-elementor' ) ); ?>
			</div>
			<?php
			return false;
		}

		if ( $clip_on_scroll ) {
			$this->add_render_attribute( 'premium_title_text', 'class', 'pa-clipped-scroll' );
			$this->add_render_attribute( 'container', 'data-clip-speed', $settings['clip_scroll_speed']['size'] );
		}

		if ( $desc_clip ) {
			// The words are wrapped by the handler, so the rich text keeps its markup here.
			$this->add_render_attribute(
				'title_description',
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

		<div class="premium-title-wrapper">
			<div <?php $this->print_render_attribute_string( 'container' ); ?>>
				<<?php echo wp_kses_post( $title_tag . ' ' . $this->get_render_attribute_string( 'title' ) ); ?>>
					<?php if ( 'style7' === $selected_style ) : ?>
						<?php if ( 'column' !== $icon_position ) : ?>
							<span class="premium-title-style7-stripe-wrap">
								<span class="premium-title-style7-stripe"></span>
							</span>
						<?php endif; ?>
						<div class="premium-title-style7-inner">
					<?php endif; ?>

					<?php if ( 'yes' === $settings['premium_title_icon_switcher'] ) : ?>
						<?php if ( 'icon' === $icon_type ) : ?>
							<?php
							if ( 'yes' !== $settings['draw_svg'] ) :
								Icons_Manager::render_icon(
									$settings['premium_title_icon_updated'],
									array(
										'class'       => array( 'premium-title-icon', 'premium-svg-nodraw', 'premium-drawable-icon' ),
										'aria-hidden' => 'true',
									)
								);
							else :

								echo Helper_Functions::get_svg_by_icon( $settings['premium_title_icon_updated'], $this->get_render_attribute_string( 'icon' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_svg_by_icon() returns sanitized inline SVG/icon markup.

							endif;
							?>

						<?php elseif ( 'svg' === $icon_type ) : ?>
							<div <?php $this->print_render_attribute_string( 'icon' ); ?>>
								<?php echo Helper_Functions::sanitize_svg( $this->get_settings_for_display( 'custom_svg' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitize_svg passes through wp_kses with a strict SVG allowlist. ?>
							</div>
						<?php elseif ( 'animation' === $icon_type ) : ?>
							<div <?php $this->print_render_attribute_string( 'title_lottie' ); ?>></div>
						<?php else : ?>
							<?php if ( 'yes' === $settings['mask_switcher'] ) : ?>
								<span class="premium-title-img">
							<?php endif; ?>
								<img <?php $this->print_render_attribute_string( 'title_img' ); ?>>
							<?php if ( 'yes' === $settings['mask_switcher'] ) : ?>
								</span>
							<?php endif; ?>
						<?php endif; ?>
					<?php endif; ?>

					<?php if ( 'style7' === $selected_style ) : ?>
						<?php if ( 'column' === $icon_position ) : ?>
							<span class="premium-title-style7-stripe-wrap">
								<span class="premium-title-style7-stripe"></span>
							</span>
						<?php endif; ?>
					<?php endif; ?>
					<?php
					if ( 'style9' !== $selected_style ) :
						if ( 'yes' === $settings['noise'] ) :
							$segments = preg_split( '/(\{\{.+?\}\})/u', $settings['premium_title_text'], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
							$text     = '';
							foreach ( $segments as $segment ) :
								$is_focused = preg_match( '/^\{\{(.+)\}\}$/u', $segment, $matches );
								$content    = $is_focused ? $matches[1] : $segment;

								// Keep connective whitespace outside the glitch span so each ghost aligns tightly to its word.
								preg_match( '/^(\s*)(.*?)(\s*)$/su', $content, $pieces );

								$text .= esc_html( $pieces[1] );

								if ( '' !== $pieces[2] ) :
									$word_class = $is_focused ? 'premium-title__focused-word premium-title-noise-word' : 'premium-title-noise-word';
									$text      .= '<span class="' . $word_class . '" data-text="' . esc_attr( $pieces[2] ) . '">' . esc_html( $pieces[2] ) . '</span>';
								endif;

								$text .= esc_html( $pieces[3] );
							endforeach;
						elseif ( $clip_on_scroll ) :
							$segments = preg_split( '/(\{\{.+?\}\})/u', $settings['premium_title_text'], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
							$text     = '';

							foreach ( $segments as $segment ) :
								$is_focused = preg_match( '/^\{\{(.+)\}\}$/u', $segment, $matches );
								$words_html = $this->get_clipped_words( $is_focused ? $matches[1] : $segment );

								if ( '' === $words_html ) :
									continue;
								endif;

								// The focused word keeps its own wrapper so its style tab controls still apply.
								$text .= $is_focused ? '<span class="premium-title__focused-word">' . $words_html . '</span> ' : $words_html . ' ';
							endforeach;

							$text = trim( $text );
						else :
							$text = str_replace( array( '{{', '}}' ), array( '<span class="premium-title__focused-word">', '</span>' ), $settings['premium_title_text'] );
						endif;
						?>
					<span <?php $this->print_render_attribute_string( 'premium_title_text' ); ?>>
						<?php echo wp_kses_post( $text ); ?>
					</span>
						<?php
					else :
							$letters_html = '<span class="premium-letters-container"' . $this->get_render_attribute_string( 'premium_title_text' ) . '>';
							$title_array  = preg_split( '//u', $settings['premium_title_text'], -1, PREG_SPLIT_NO_EMPTY );
						foreach ( $title_array as $key => $letter ) :
							$letters_html .= '<span class="premium-title-style9-letter" data-letter-index="' . esc_attr( $key + 1 ) . '" data-letter="' . esc_attr( $letter ) . '">' . $letter . '</span>';
						endforeach;
							$the_title = $letters_html . '</span>';
							echo wp_kses_post( $the_title );
						?>
					<?php endif; ?>

					<?php if ( 'style7' === $selected_style ) : ?>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $link ) ) : ?>
						<a <?php $this->print_render_attribute_string( 'link' ); ?>></a>
					<?php endif; ?>

					<?php if ( 'none' !== $settings['hover_effect'] ) : ?>
						<?php echo Helper_Functions::get_btn_svgs( $settings['hover_effect'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_btn_svgs() returns sanitized inline SVG markup. ?>
					<?php endif; ?>
				</<?php echo wp_kses_post( $title_tag ); ?>>

			</div>

			<?php if ( $show_desc ) : ?>
				<div <?php $this->print_render_attribute_string( 'title_description' ); ?>>
					<?php echo wp_kses_post( $settings['premium_title_desc_text'] ); ?>
				</div>
			<?php endif; ?>
		</div>

		<?php
	}
}
