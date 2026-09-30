<?php
/**
 * Premium Tags Cloud.
 */

namespace PremiumAddons\Widgets;

// Elementor Classes.
use Elementor\Modules\DynamicTags\Module as TagsModule;
use Elementor\Plugin;
use Elementor\Widget_Base;
use Elementor\Repeater;
use Elementor\Utils;
use Elementor\Control_Media;
use Elementor\Controls_Manager;
use Elementor\Icons_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Css_Filter;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Typography;

// PremiumAddons Classes.
use PremiumAddons\Includes\Premium_Template_Tags as Blog_Helper;
use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Includes\Helper_Functions;
use PremiumAddons\Includes\Controls\Premium_Tax_Filter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // If this file is called directly, abort.
}

/**
 * Class Premium_Tcloud
 */
class Premium_Tcloud extends Widget_Base {

	/**
	 * Retrieve Widget Name.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function get_name() {
		return 'premium-tcloud';
	}

	/**
	 * Retrieve Widget Title.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function get_title() {
		return __( 'Tags Cloud', 'premium-addons-for-elementor' );
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

			$scripts = array( 'pa-glass', 'pa-awesomecloud', 'pa-tagcanvas', 'pa-matter' );

		} else {
			$settings = $this->get_settings();

			if ( 'none' !== $settings['term_lq_effect'] ) {
				$scripts[] = 'pa-glass';
			}

			if ( 'sphere' === $settings['words_order'] ) {
				$scripts[] = 'pa-tagcanvas';
			} elseif ( 'shape' === $settings['words_order'] ) {
				$scripts[] = 'pa-awesomecloud';
			} elseif ( 'default' === $settings['words_order'] && 'yes' === $settings['throwable_effect'] ) {
				$scripts[] = 'pa-matter';
			}
		}

		$scripts[] = 'pa-tcloud';

		return $scripts;
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
		return 'pa-tags-cloud';
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
	 * Retrieve Widget Keywords.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return array Widget keywords.
	 */
	public function get_keywords() {
		return array( 'pa', 'premium', 'premium tags cloud', 'tag', 'cat', 'product', 'woo', 'query', 'cpt' );
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
	 * Register Image Controls controls.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function register_controls() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore

		$papro_activated = Helper_Functions::check_papro_version();

		$this->start_controls_section(
			'display_options_section',
			array(
				'label' => __( 'Display Options', 'premium-addons-for-elementor' ),
			)
		);

		$options = apply_filters(
			'pa_tcloud_layouts',
			array(
				'layouts'          => array(
					'default' => __( 'Default', 'premium-addons-for-elementor' ),
					'ribbon'  => __( 'Label', 'premium-addons-for-elementor' ),
					'shape'   => __( 'Shape (Pro)', 'premium-addons-for-elementor' ),
					'sphere'  => __( 'Sphere (Pro)', 'premium-addons-for-elementor' ),
				),
				'order_condition'  => array( 'shape', 'sphere' ),
				'source_condition' => array(
					'post_type_filter' => 'post',
				),
			)
		);

		$terms_condition = array_merge( $options['source_condition'], array( 'source' => 'terms' ) );

		$this->add_control(
			'words_order',
			array(
				'label'              => __( 'Words Order', 'premium-addons-for-elementor' ),
				'type'               => Controls_Manager::SELECT,
				'options'            => $options['layouts'],
				'default'            => 'default',
				'frontend_available' => true,
			)
		);

		$this->add_responsive_control(
			'circle_position',
			array(
				'label'              => __( 'Circle Position', 'premium-addons-for-elementor' ),
				'type'               => Controls_Manager::SLIDER,
				'size_units'         => array( 'px', 'em', '%', 'custom' ),
				'selectors'          => array(
					'{{WRAPPER}} .premium-tcloud-term::after' => 'left: {{SIZE}}{{UNIT}}',
				),
				'frontend_available' => true,
				'condition'          => array(
					'words_order' => 'ribbon',
				),
			)
		);

		$this->add_control(
			'words_remove',
			array(
				'label'       => __( 'Words to Remove', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::TEXT,
				'description' => __( 'Add a list of words separated by comma to be removed. For example, word1,word2, etc.', 'premium-addons-for-elementor' ),
				'label_block' => true,
				'condition'   => array(
					'words_order!' => $options['order_condition'],
					'source'       => 'terms',
				),
			)
		);

		$this->add_control(
			'words_number',
			array(
				'label'       => __( 'Max Number of Words', 'premium-addons-for-elementor' ),
				'description' => __( 'Use this option to strip term to specific number of words.', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::NUMBER,
				'condition'   => array(
					'words_order!' => $options['order_condition'],
					'source'       => 'terms',
				),
			)
		);

		$this->add_control(
			'show_posts_number',
			array(
				'label'     => __( 'Show Posts Number', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'condition' => array(
					'words_order'       => array( 'default', 'ribbon' ),
					'get_from_current!' => 'yes',
					'source'            => 'terms',
				),
			)
		);

		$this->add_control(
			'suffix_word',
			array(
				'label'       => __( 'Replace With', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::TEXT,
				'description' => __( 'Use this option if the number of words is larger than a specific number.', 'premium-addons-for-elementor' ),
				'label_block' => true,
				'condition'   => array(
					'words_order!'  => $options['order_condition'],
					'words_number!' => '',
					'source'        => 'terms',
				),
			)
		);

		do_action( 'pa_tcloud_shape_controls', $this );

		$this->add_control(
			'new_tab',
			array(
				'label'              => __( 'Open Links in New Tab', 'premium-addons-for-elementor' ),
				'type'               => Controls_Manager::SWITCHER,
				'default'            => 'yes',
				'frontend_available' => true,
				'condition'          => array(
					'words_order!' => $options['order_condition'],
					'source'       => 'terms',
				),
			)
		);

		$this->add_control(
			'colors_select',
			array(
				'label'              => __( 'Words Colors', 'premium-addons-for-elementor' ),
				'type'               => Controls_Manager::SELECT,
				'options'            => array(
					'random-light' => __( 'Random Light', 'premium-addons-for-elementor' ),
					'random-dark'  => __( 'Random Dark', 'premium-addons-for-elementor' ),
					'custom'       => __( 'Custom', 'premium-addons-for-elementor' ),
				),
				'default'            => 'random-light',
				'frontend_available' => true,
				'condition'          => array(
					'words_order!' => $options['order_condition'],
				),
			)
		);

		$this->add_control(
			'words_colors',
			array(
				'label'              => __( 'Colors to Select From', 'premium-addons-for-elementor' ),
				'type'               => Controls_Manager::TEXTAREA,
				'description'        => __( 'Add the colors you want to select from. Each color in a separate line. You can use this ', 'premium-addons-for-elementor' ) . '<a href="https://www.w3schools.com/colors/colors_names.asp" target="_blank">link</a>' . __( ' to get colors.', 'premium-addons-for-elementor' ),

				'label_block'        => true,
				'frontend_available' => true,
				'condition'          => array(
					'words_order!'  => $options['order_condition'],
					'colors_select' => 'custom',
				),
				'ai'                 => array(
					'active' => false,
				),
			)
		);

		do_action( 'pa_tcloud_sphere_controls', $this );

		$this->add_responsive_control(
			'direction',
			array(
				'label'        => __( 'Direction', 'premium-addons-for-elementor' ),
				'type'         => Controls_Manager::CHOOSE,
				'options'      => array(
					'row'    => array(
						'title' => __( 'Row', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-arrow-right',
					),
					'column' => array(
						'title' => __( 'Column', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-arrow-down',
					),
				),
				'default'      => 'row',
				'toggle'       => false,
				'prefix_class' => 'premium-tcloud__',
				'selectors'    => array(
					'{{WRAPPER}} .premium-tcloud-canvas-container' => 'flex-direction: {{VALUE}};',
				),
				'condition'    => array(
					'words_order!' => $options['order_condition'],
				),
				'conditions'   => array(
					'relation' => 'or',
					'terms'    => array(
						array(
							'name'     => 'words_order',
							'operator' => '!==',
							'value'    => 'default',
						),
						array(
							'name'     => 'throwable_effect',
							'operator' => '!==',
							'value'    => 'yes',
						),
					),
				),
			)
		);

		$this->add_responsive_control(
			'align',
			array(
				'label'      => __( 'Alignment', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::CHOOSE,
				'options'    => array(
					'flex-start' => array(
						'title' => __( 'Start', 'premium-addons-for-elementor' ),
						'icon'  => is_rtl() ? 'eicon-text-align-right' : 'eicon-text-align-left',
					),
					'center'     => array(
						'title' => __( 'Center', 'premium-addons-for-elementor' ),
						'icon'  => 'eicon-text-align-justify',
					),
					'flex-end'   => array(
						'title' => __( 'End', 'premium-addons-for-elementor' ),
						'icon'  => is_rtl() ? 'eicon-text-align-left' : 'eicon-text-align-right',
					),
				),
				'default'    => 'flex-start',
				'toggle'     => false,
				'selectors'  => array(
					'{{WRAPPER}}.premium-tcloud__row .premium-tcloud-canvas-container' => 'justify-content: {{VALUE}};',
					'{{WRAPPER}}.premium-tcloud__column .premium-tcloud-canvas-container' => 'align-items: {{VALUE}};',
				),
				'condition'  => array(
					'words_order!' => $options['order_condition'],
				),
				'conditions' => array(
					'relation' => 'or',
					'terms'    => array(
						array(
							'name'     => 'words_order',
							'operator' => '!==',
							'value'    => 'default',
						),
						array(
							'name'     => 'throwable_effect',
							'operator' => '!==',
							'value'    => 'yes',
						),
					),
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'throwable_section',
			array(
				'label'     => __( 'Throwable Effect', 'premium-addons-for-elementor' ),
				'condition' => array(
					'words_order' => 'default',
				),
			)
		);

		$this->add_control(
			'throwable_effect',
			array(
				'label'              => apply_filters( 'pa_pro_label', __( 'Throwable Effect (Pro)', 'premium-addons-for-elementor' ) ),
				'description'        => __( 'Terms fall into place and can be dragged and thrown.', 'premium-addons-for-elementor' ),
				'type'               => Controls_Manager::SWITCHER,
				'render_type'        => 'template',
				'frontend_available' => true,
			)
		);

		do_action( 'pa_tcloud_throwable_controls', $this );

		$this->end_controls_section();

		$this->start_controls_section(
			'query_section',
			array(
				'label'     => __( 'Query', 'premium-addons-for-elementor' ),
				'condition' => array(
					'words_order!' => $options['order_condition'],
				),
			)
		);

		$this->add_control(
			'source',
			array(
				'label'       => __( 'Source', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SELECT,
				'label_block' => true,
				'options'     => array(
					'terms'  => __( 'Taxonomy Terms', 'premium-addons-for-elementor' ),
					'custom' => __( 'Custom Words', 'premium-addons-for-elementor' ),
				),
				'default'     => 'terms',
			)
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'word',
			array(
				'label'       => __( 'Word', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'dynamic'     => array(
					'active' => true,
				),
			)
		);

		$repeater->add_control(
			'link',
			array(
				'label'       => __( 'Link', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::URL,
				'label_block' => true,
				'placeholder' => 'https://premiumaddons.com/',
				'dynamic'     => array(
					'active' => true,
				),
			)
		);

		$repeater->add_control(
			'weight',
			array(
				'label'       => __( 'Weight', 'premium-addons-for-elementor' ),
				'description' => __( 'Higher weight makes the word larger. Works with Font Size Scale, Shape and Sphere layouts.', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SLIDER,
				'range'       => array(
					'px' => array(
						'min'  => 1,
						'max'  => 10,
						'step' => 1,
					),
				),
				'default'     => array(
					'size' => 1,
				),
			)
		);

		$this->add_control(
			'custom_words',
			array(
				'label'         => __( 'Words', 'premium-addons-for-elementor' ),
				'type'          => Controls_Manager::REPEATER,
				'fields'        => $repeater->get_controls(),
				'default'       => array(
					array(
						'word'   => __( 'Design', 'premium-addons-for-elementor' ),
						'weight' => array(
							'size' => 3,
						),
					),
					array(
						'word'   => __( 'Marketing', 'premium-addons-for-elementor' ),
						'weight' => array(
							'size' => 2,
						),
					),
					array(
						'word'   => __( 'Development', 'premium-addons-for-elementor' ),
						'weight' => array(
							'size' => 1,
						),
					),
				),
				'title_field'   => '{{{ word }}}',
				'prevent_empty' => false,
				'condition'     => array(
					'source' => 'custom',
				),
			)
		);

		$post_types = Blog_Helper::get_posts_types();

		foreach ( $post_types as $id => $label ) {

			if ( 'post' !== $id ) {
				$post_types[ $id ] .= apply_filters( 'pa_pro_label', __( ' (Pro)', 'premium-addons-for-elementor' ) );
			}
		}

		$this->add_control(
			'post_type_filter',
			array(
				'label'       => __( 'Post Type', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SELECT,
				'label_block' => true,
				'options'     => $post_types,
				'default'     => 'post',
				'condition'   => array(
					'source' => 'terms',
				),
			)
		);

		$this->add_control(
			'filter_tabs_type',
			array(
				'label'     => __( 'Taxonomy', 'premium-addons-for-elementor' ),
				'type'      => Premium_Tax_Filter::TYPE,
				'default'   => 'category',
				'condition' => $terms_condition,
			)
		);

		$this->add_control(
			'no_tax_notice',
			array(
				'raw'             => __( 'This post type has no taxonomies to show.', 'premium-addons-for-elementor' ),
				'type'            => Controls_Manager::RAW_HTML,
				'classes'         => 'premium-live-temp-title control-hidden',
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
				'condition'       => $terms_condition,
			)
		);

		$this->add_control(
			'get_from_current',
			array(
				'label'       => __( 'Get from Current Page', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SWITCHER,
				'description' => __( 'Use this to get the terms of the current page. Useful when building a Single Post/Product template.', 'premium-addons-for-elementor' ),
				'condition'   => $terms_condition,
			)
		);

		$this->add_control(
			'show_parents_only',
			array(
				'label'     => __( 'Show Parent Terms Only', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::SWITCHER,
				'condition' => $terms_condition,
			)
		);

		$this->add_control(
			'terms_number',
			array(
				'label'     => __( 'Number of Terms to Show', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::NUMBER,
				'condition' => $terms_condition,
			)
		);

		$this->add_control(
			'order_by',
			array(
				'label'       => __( 'Order By', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SELECT,
				'separator'   => 'before',
				'label_block' => true,
				'options'     => array(
					'none'        => __( 'None', 'premium-addons-for-elementor' ),
					'name'        => __( 'Name Alphabetically', 'premium-addons-for-elementor' ),
					'slug'        => __( 'Slug Alphabetically', 'premium-addons-for-elementor' ),
					'description' => __( 'Description Alphabetically', 'premium-addons-for-elementor' ),
					'ID'          => __( 'Term ID', 'premium-addons-for-elementor' ),
					'count'       => __( 'Posts Number', 'premium-addons-for-elementor' ),
				),
				'default'     => 'none',
				'condition'   => $terms_condition,
			)
		);

		$this->add_control(
			'order',
			array(
				'label'       => __( 'Order', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SELECT,
				'label_block' => true,
				'options'     => array(
					'ASC'  => __( 'Ascending', 'premium-addons-for-elementor' ),
					'DESC' => __( 'Descending', 'premium-addons-for-elementor' ),
				),
				'default'     => 'ASC',
				'condition'   => $terms_condition,
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_pa_docs',
			array(
				'label' => __( 'Help & Docs', 'premium-addons-for-elementor' ),
			)
		);

		$docs = array(
			'https://premiumaddons.com/docs/elementor-tags-cloud-widget/' => __( 'Getting started »', 'premium-addons-for-elementor' ),
		);

		$doc_index = 1;
		foreach ( $docs as $url => $title ) {

			$doc_url = Helper_Functions::get_campaign_link( $url, 'tcloud-widget', 'wp-editor', 'get-support' );

			$this->add_control(
				'doc_' . $doc_index,
				array(
					'type'            => Controls_Manager::RAW_HTML,
					'raw'             => sprintf( '<a href="%s" target="_blank">%s</a>', $doc_url, $title ),
					'content_classes' => 'editor-pa-doc',
				)
			);

			++$doc_index;

		}

		Helper_Functions::register_element_feedback_controls( $this );

		$this->end_controls_section();

		$this->start_controls_section(
			'term_style',
			array(
				'label'     => __( 'Term', 'premium-addons-for-elementor' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'words_order' => array( 'default', 'ribbon' ),
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'term_typo',
				'selector' => '{{WRAPPER}} .premium-tcloud-term-link',
			)
		);

		$this->add_control(
			'fsize_scale',
			array(
				'label'              => __( 'Font Size Scale (px)', 'premium-addons-for-elementor' ),
				'type'               => Controls_Manager::SLIDER,
				'description'        => __( 'This option is used to increase the font size of each term based on the number of posts in it.', 'premium-addons-for-elementor' ),
				'range'              => array(
					'px' => array(
						'min'  => 0,
						'max'  => 5,
						'step' => 0.1,
					),
				),
				'default'            => array(
					'size' => 0,
				),
				'frontend_available' => true,
			)
		);

		$this->start_controls_tabs( 'term_style_tabs' );

		$this->start_controls_tab(
			'term_style_normal',
			array(
				'label' => __( 'Normal', 'premium-addons-for-elementor' ),
			)
		);

		$this->add_control(
			'term_color',
			array(
				'label'     => __( 'Text Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .premium-tcloud-term-link' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'circle_color',
			array(
				'label'     => __( 'Circle Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .premium-tcloud-term::after' => 'background-color: {{VALUE}}',
				),
				'condition' => array(
					'words_order' => 'ribbon',
				),
			)
		);

		$this->add_control(
			'term_background_color',
			array(
				'label'     => __( 'Background Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .premium-tcloud-term' => 'background-color: {{VALUE}} !important',
					'{{WRAPPER}} .premium-tcloud-ribbon .premium-tcloud-term::before' => 'border-right-color: {{VALUE}} !important',
				),
			)
		);

		$this->add_control(
			'term_lq_effect',
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
				'render_type' => 'template',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'      => 'term_border',
				'selector'  => '{{WRAPPER}} .premium-tcloud-term',
				'condition' => array(
					'words_order' => 'default',
				),
			)
		);

		$this->add_control(
			'term_border_radius',
			array(
				'label'      => __( 'Border Radius', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-tcloud-term' => 'border-radius: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'words_order' => 'default',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'name'     => 'term_text_shadow',
				'selector' => '{{WRAPPER}} .premium-tcloud-term',
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'term_shadow',
				'selector' => '{{WRAPPER}} .premium-tcloud-term',
			)
		);

		$this->add_responsive_control(
			'term_spacing',
			array(
				'label'      => __( 'Margin', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-tcloud-term-wrap' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'term_padding',
			array(
				'label'      => __( 'Padding', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-tcloud-term' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'term_style_hover',
			array(
				'label' => __( 'Hover', 'premium-addons-for-elementor' ),
			)
		);

		$this->add_control(
			'term_hover_color',
			array(
				'label'     => __( 'Text Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .premium-tcloud-term:hover .premium-tcloud-term-link' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'circle_hover_color',
			array(
				'label'     => __( 'Circle Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .premium-tcloud-term:hover::after' => 'background-color: {{VALUE}}',
				),
				'condition' => array(
					'words_order' => 'ribbon',
				),
			)
		);

		$this->add_control(
			'term_hover_background_color',
			array(
				'label'     => __( 'Background Color', 'premium-addons-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .premium-tcloud-term:hover' => 'background-color: {{VALUE}} !important',
					'{{WRAPPER}} .premium-tcloud-ribbon .premium-tcloud-term:hover::before' => 'border-right-color: {{VALUE}} !important',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'      => 'term_hover_border',
				'selector'  => '{{WRAPPER}} .premium-tcloud-term:hover',
				'condition' => array(
					'words_order' => 'default',
				),
			)
		);

		$this->add_control(
			'term_hover_border_radius',
			array(
				'label'      => __( 'Border Radius', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-tcloud-term:hover' => 'border-radius: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array(
					'words_order' => 'default',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'name'     => 'term_hover_text_shadow',
				'selector' => '{{WRAPPER}} .premium-tcloud-term:hover',
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'term_hover_shadow',
				'selector' => '{{WRAPPER}} .premium-tcloud-term:hover',
			)
		);

		$this->add_responsive_control(
			'term_hover_spacing',
			array(
				'label'      => __( 'Margin', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-tcloud-term-wrap:hover' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'term_hover_padding',
			array(
				'label'      => __( 'Padding', 'premium-addons-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .premium-tcloud-term:hover' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Get Term Words.
	 *
	 * Builds the words array from the queried taxonomy terms.
	 *
	 * @since 4.11.103
	 * @access private
	 *
	 * @param array $settings widget settings.
	 *
	 * @return array $words_array words.
	 */
	private function get_term_words( $settings ) {

		$tax = $settings['filter_tabs_type'];

		$terms = $this->get_taxs( $tax );

		$target = 'yes' === $settings['new_tab'] ? '_blank' : '_top';

		$words_array = array();

		foreach ( $terms as $term ) {

			// get_terms() keys can have gaps (empty parents are unset), so the position comes from the array being built.
			$index = count( $words_array );

			$term_id = $term->term_id;

			$name = $term->name;

			$full_name = $name;

			if ( ! empty( $settings['words_remove'] ) ) {

				$words_to_remove = explode( ',', $settings['words_remove'] );

				$cleaned_string = str_ireplace( $words_to_remove, '', $name );

				$name = $cleaned_string;

			}

			$should_suffix = false;
			if ( ! empty( $settings['words_number'] ) ) {

				$name_array = explode( ' ', $term->name );

				if ( count( $name_array ) > 1 && count( $name_array ) > $settings['words_number'] ) {

					$should_suffix = true;

					$new_name = '';

					for ( $i = 0; $i < $settings['words_number']; $i++ ) {

						if ( $i < $settings['words_number'] - 2 ) {
							$new_name .= $name_array[ $i ] . ' ';
						} else {
							$new_name .= $name_array[ $i ];
						}
					}

					$name = $new_name;

				}
			}

			if ( ! empty( $settings['suffix_word'] ) && $should_suffix ) {
				$name .= $settings['suffix_word'];
			}

			if ( 'yes' === $settings['get_from_current'] ) {
				$term->count = 1;
			}

			$child_terms_count = 'yes' === $settings['show_parents_only'] ? $this->get_child_terms_count( $tax, $term_id ) : 0;

			$weight = $term->count + $child_terms_count;

			$link = get_term_link( $term_id, $tax );

			$this->add_render_attribute( 'term-wrap-' . $index, 'class', 'premium-tcloud-term-wrap' );

			$this->add_render_attribute(
				'term-link-' . $index,
				array(
					'class'       => 'premium-tcloud-term-link',
					'data-weight' => $weight,
					'href'        => $link,
					'title'       => $full_name,
					'target'      => $target,
				)
			);

			$words_array[] = array( $name, $weight, $link, $full_name, $weight, $target );

		}

		return $words_array;
	}

	/**
	 * Get Custom Words.
	 *
	 * Builds the words array from the Custom Words repeater.
	 *
	 * @since 4.11.103
	 * @access private
	 *
	 * @param array $settings widget settings.
	 *
	 * @return array $words_array words.
	 */
	private function get_custom_words( $settings ) {

		$words_array = array();

		foreach ( $settings['custom_words'] as $item ) {

			if ( '' === $item['word'] ) {
				continue;
			}

			$index = count( $words_array );

			// A cleared slider saves an empty size, which Shape layout would draw at zero size.
			$weight = max( 1, (int) $item['weight']['size'] );

			$target = ! empty( $item['link']['is_external'] ) ? '_blank' : '_top';

			$this->add_render_attribute(
				'term-wrap-' . $index,
				'class',
				array(
					'premium-tcloud-term-wrap',
					'elementor-repeater-item-' . $item['_id'],
				)
			);

			$this->add_render_attribute(
				'term-link-' . $index,
				array(
					'class'       => 'premium-tcloud-term-link',
					'data-weight' => $weight,
					'title'       => $item['word'],
				)
			);

			$this->add_link_attributes( 'term-link-' . $index, $item['link'] );

			$words_array[] = array( $item['word'], $weight, $item['link']['url'], $item['word'], $weight, $target );
		}

		return $words_array;
	}

	/**
	 * Transform Word Case.
	 *
	 * @since 4.11.103
	 * @access private
	 *
	 * @param string $word word.
	 * @param string $transform uppercase, lowercase or capitalize.
	 *
	 * @return string $word transformed word.
	 */
	private function transform_word_case( $word, $transform ) {

		switch ( $transform ) {

			case 'uppercase':
				return strtoupper( $word );

			case 'lowercase':
				return strtolower( $word );

			case 'capitalize':
				return ucwords( $word );

		}

		return $word;
	}

	/**
	 * Render Tags Cloud widget output on the frontend.
	 *
	 * Written in PHP and used to generate the final HTML.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function render() {

		$settings = $this->get_settings_for_display();

		$papro_activated = Helper_Functions::check_papro_version();

		$queries_pro_post_type = 'custom' !== $settings['source'] && 'post' !== $settings['post_type_filter'];

		if ( ! $papro_activated && ( 'shape' === $settings['words_order'] || 'yes' === $settings['throwable_effect'] || $queries_pro_post_type ) ) {

			?>
				<div class="premium-error-notice">
					<?php
						$message = __( 'This option is available in <b>Premium Addons Pro</b>.', 'premium-addons-for-elementor' );
						echo wp_kses_post( $message );
					?>
				</div>
				<?php
				return false;
		}

		$id = $this->get_id();

		$words_array = 'custom' === $settings['source'] ? $this->get_custom_words( $settings ) : $this->get_term_words( $settings );

		if ( in_array( $settings['words_order'], array( 'shape', 'sphere' ), true ) && ! empty( $settings['text_transform'] ) ) {
			foreach ( $words_array as $index => $word ) {
				$words_array[ $index ][0] = $this->transform_word_case( $word[0], $settings['text_transform'] );
			}
		}

		$chart_settings = array(
			'wordsArr' => $words_array,
		);

		$this->add_render_attribute(
			'container',
			array(
				'class'      => array(
					'premium-tcloud-container',
					'premium-tcloud-' . $settings['words_order'],
				),
				'data-chart' => wp_json_encode( $chart_settings ),
			)
		);

		if ( 'yes' === $settings['throwable_effect'] ) {
			$this->add_render_attribute( 'container', 'class', 'premium-tcloud-throwable' );
		}

		if ( in_array( $settings['words_order'], array( 'shape', 'sphere' ), true ) ) {

			$this->add_render_attribute( 'container', 'class', 'premium-tcloud-hidden' );

			$this->add_render_attribute(
				'canvas',
				array(
					'id'     => 'premium-tcloud-canvas-' . $id,
					'class'  => 'premium-tcloud-canvas',
					'width'  => 1170,
					'height' => 760,
				)
			);
		}

		if ( 'shape' !== $settings['words_order'] ) {
			$this->add_render_attribute( 'term', 'class', 'premium-tcloud-term' );

			if ( 'none' !== $settings['term_lq_effect'] ) {
				$this->add_render_attribute( 'term', 'class', 'premium-con-lq__' . $settings['term_lq_effect'] );
			}
		}

		?>

			<div <?php $this->print_render_attribute_string( 'container' ); ?>>

				<div class="premium-tcloud-canvas-container">

					<?php if ( in_array( $settings['words_order'], array( 'shape', 'sphere' ), true ) ) : ?>
						<span class="font-loader"></span>
						<canvas <?php $this->print_render_attribute_string( 'canvas' ); ?>>
						</canvas>
					<?php endif; ?>


					<?php if ( 'shape' !== $settings['words_order'] ) : ?>

						<?php if ( 'sphere' === $settings['words_order'] ) : ?>
							<div id="<?php echo esc_attr( 'premium-tcloud-terms-container-' . $id ); ?>" class="premium-tcloud-terms-container">
						<?php endif; ?>

						<?php foreach ( $words_array as $index => $word ) : ?>

							<?php if ( '' !== $word[0] ) : ?>

								<div <?php $this->print_render_attribute_string( 'term-wrap-' . $index ); ?>>

									<span <?php $this->print_render_attribute_string( 'term' ); ?>>
										<a <?php $this->print_render_attribute_string( 'term-link-' . $index ); ?>><?php echo wp_kses_post( $word[0] ); ?><?php if ( 'terms' === $settings['source'] && in_array( $settings['words_order'], array( 'default', 'ribbon' ), true ) && 'yes' === $settings['show_posts_number'] ) : ?>
											<span class="premium-tcloud-number">(<?php echo wp_kses_post( $word[4] ); ?>)</span><?php endif; ?></a>
									</span>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>

						<?php if ( 'sphere' === $settings['words_order'] ) : ?>
							</div>
						<?php endif; ?>
					<?php endif; ?>

				</div>

			</div>

		<?php
	}

	/**
	 * Get Taxs
	 *
	 * Used to get terms.
	 *
	 * @access private
	 *
	 * @return array $taxs taxonomies.
	 */
	private function get_taxs( $term ) {

		$settings = $this->get_settings_for_display();

		$args = array(
			'number'  => $settings['terms_number'],
			'orderby' => $settings['order_by'],
			'order'   => $settings['order'],
		);

		if ( 'yes' === $settings['show_parents_only'] ) {
			$args = array_merge(
				$args,
				array(
					'parent' => 0,
				)
			);
		}

		// Get the terms based on filter source.
		if ( 'yes' === $settings['get_from_current'] ) {
			$taxs = wp_get_post_terms( get_the_ID(), $term, $args );
		} else {

			$args = array_merge(
				$args,
				array(
					'taxonomy' => $term,
				)
			);

			$taxs = get_terms( $args );
		}

		if ( is_wp_error( $taxs ) ) {
			return array();
		}

		return $taxs;
	}

	/**
	 * Get Child Terms Count
	 *
	 * Used to get child terms count.
	 *
	 * @access private
	 *
	 * @return int $child_posts_count child terms count.
	 */
	private function get_child_terms_count( $tax, $id ) {

		$term_children = get_terms(
			array(
				'taxonomy'   => $tax,
				'parent'     => $id,
				'hide_empty' => true,
				'fields'     => 'ids',
			)
		);

		if ( empty( $term_children ) ) {
			return 0;
		}

		$child_posts_count = 0;

		foreach ( $term_children as $child_id ) {

			$term = get_term( $child_id, $tax );

			if ( $term && ! is_wp_error( $term ) ) {

				$child_posts_count += $term->count;

			}
		}

		return $child_posts_count;
	}
}
