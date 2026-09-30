<?php
/**
 * Get Theme Styles.
 *
 * Reads the color and typography presets the active theme defines, plus the
 * site-wide styles it applies.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Discovery;

use PremiumAddons\Admin\Includes\Admin_Helper;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 */
class Get_Theme_Styles implements Ability_Handler {

	/**
	 * Preset origins reported for every preset group.
	 *
	 * The `blocks` origin is skipped, block-registered presets are noise here.
	 *
	 * @var array
	 */
	const ORIGINS = array( 'theme', 'default', 'custom' );

	/**
	 * Element nodes reported from the styles tree.
	 *
	 * @var array
	 */
	const HEADING_ELEMENTS = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'get-theme-styles';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Get Active Theme Styles', 'premium-addons-for-elementor' ),
			'description'         => __( 'Reads the color palette and typography the active theme defines.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-discovery',
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'Color and typography presets of the active theme, and the styles it applies site-wide.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'source'               => array(
						'type'        => 'string',
						'enum'        => array( 'theme_json', 'theme_supports', 'core_defaults' ),
						'description' => __( 'Where the theme-origin presets come from. theme_json = declared in the theme\'s theme.json file, theme_supports = declared with add_theme_support, core_defaults = the theme declares none and everything reported under the default origin comes from WordPress core. A theme that ships a theme.json file and declares its presets through add_theme_support is reported as theme_json; the values are the same either way.', 'premium-addons-for-elementor' ),
					),
					'has_theme_json'       => array(
						'type'        => 'boolean',
						'description' => __( 'True when the active theme ships a theme.json file. A classic theme can ship one, so this is not the same as is_block_theme.', 'premium-addons-for-elementor' ),
					),
					'is_block_theme'       => array(
						'type'        => 'boolean',
						'description' => __( 'True when the active theme is a block theme.', 'premium-addons-for-elementor' ),
					),
					'has_theme_palette'    => array(
						'type'        => 'boolean',
						'description' => __( 'True when the theme declares its own color palette. When false, the site\'s real colors most likely live in the Elementor kit instead — read them with the get-global-settings ability.', 'premium-addons-for-elementor' ),
					),
					'has_theme_typography' => array(
						'type'        => 'boolean',
						'description' => __( 'True when the theme declares its own font families or font sizes. When false, read the Elementor kit typography with the get-global-settings ability.', 'premium-addons-for-elementor' ),
					),
					'colors'               => array(
						'type'        => 'object',
						'description' => __( 'Color presets, split by origin.', 'premium-addons-for-elementor' ),
						'properties'  => array(
							'palette'   => $this->get_presets_schema(
								'color',
								__( 'Color value, usually a hex code.', 'premium-addons-for-elementor' )
							),
							'gradients' => $this->get_presets_schema(
								'gradient',
								__( 'CSS gradient value.', 'premium-addons-for-elementor' )
							),
						),
					),
					'typography'           => array(
						'type'        => 'object',
						'description' => __( 'Typography presets, split by origin.', 'premium-addons-for-elementor' ),
						'properties'  => array(
							'font_families' => $this->get_presets_schema(
								'font_family',
								__( 'CSS font-family stack.', 'premium-addons-for-elementor' )
							),
							'font_sizes'    => $this->get_presets_schema(
								'size',
								__( 'CSS font-size value, may be a clamp() expression.', 'premium-addons-for-elementor' )
							),
						),
					),
					'styles'               => array(
						'type'        => 'object',
						'description' => __( 'Styles the theme applies site-wide. Preset variables are resolved to their values, so no entry is a var() reference.', 'premium-addons-for-elementor' ),
						'properties'  => array(
							'color'      => array(
								'type'        => 'object',
								'description' => __( 'Site-wide background, text and link colors. Null when the theme sets none.', 'premium-addons-for-elementor' ),
								'properties'  => array(
									'background' => array( 'type' => array( 'string', 'null' ) ),
									'text'       => array( 'type' => array( 'string', 'null' ) ),
									'link'       => array( 'type' => array( 'string', 'null' ) ),
								),
							),
							'typography' => array_merge(
								array( 'description' => __( 'Site-wide base typography.', 'premium-addons-for-elementor' ) ),
								$this->get_typography_schema()
							),
							'elements'   => array(
								'type'        => 'object',
								'description' => __( 'Per-element styles. h1 to h6 carry typography; link carries its color and text decoration.', 'premium-addons-for-elementor' ),
								'properties'  => array(
									'h1'   => $this->get_typography_schema(),
									'h2'   => $this->get_typography_schema(),
									'h3'   => $this->get_typography_schema(),
									'h4'   => $this->get_typography_schema(),
									'h5'   => $this->get_typography_schema(),
									'h6'   => $this->get_typography_schema(),
									'link' => array(
										'type'       => 'object',
										'properties' => array(
											'color' => array( 'type' => array( 'string', 'null' ) ),
											'text_decoration' => array( 'type' => array( 'string', 'null' ) ),
										),
									),
								),
							),
						),
					),
				),
			),
			'permission_callback' => function () {
				return Admin_Helper::check_user_can( 'manage_options' );
			},
			'meta'                => array(
				// Don't want Angie to see this.
				'show_in_rest' => false,
				'mcp'          => array( 'public' => true ),
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		);
	}

	/**
	 * Execute the ability.
	 *
	 * @param array|null $input Ability input.
	 * @return array
	 */
	public function execute( $input = null ) {

		// Read the whole settings tree once. wp_get_global_settings() falls back to the
		// entire tree when the requested path is missing, so per-path calls are unsafe.
		$settings = wp_get_global_settings();

		$styles = wp_get_global_styles( array(), array( 'transforms' => array( 'resolve-variables' ) ) );

		$palette       = $this->get_presets( $settings, 'color', 'palette' );
		$gradients     = $this->get_presets( $settings, 'color', 'gradients' );
		$font_families = $this->get_presets( $settings, 'typography', 'fontFamilies' );
		$font_sizes    = $this->get_presets( $settings, 'typography', 'fontSizes' );

		$has_theme_palette    = ! empty( $palette['theme'] );
		$has_theme_typography = ! empty( $font_families['theme'] ) || ! empty( $font_sizes['theme'] );

		return array(
			'source'               => $this->get_source( $has_theme_palette || $has_theme_typography ),
			'has_theme_json'       => wp_theme_has_theme_json(),
			'is_block_theme'       => wp_is_block_theme(),
			'has_theme_palette'    => $has_theme_palette,
			'has_theme_typography' => $has_theme_typography,
			'colors'               => array(
				'palette'   => $this->format_presets( $palette, 'color', 'color', '--wp--preset--color--' ),
				'gradients' => $this->format_presets( $gradients, 'gradient', 'gradient', '--wp--preset--gradient--' ),
			),
			'typography'           => array(
				'font_families' => $this->format_presets( $font_families, 'fontFamily', 'font_family', '--wp--preset--font-family--' ),
				'font_sizes'    => $this->format_presets( $font_sizes, 'size', 'size', '--wp--preset--font-size--' ),
			),
			'styles'               => $this->format_styles( $styles ),
		);
	}

	/**
	 * Get a preset group from the merged settings tree.
	 *
	 * @param array  $settings Merged global settings.
	 * @param string $group    Settings group, e.g. color.
	 * @param string $key      Preset key, e.g. palette.
	 * @return array Presets keyed by origin.
	 */
	private function get_presets( $settings, $group, $key ) {
		return isset( $settings[ $group ][ $key ] ) && is_array( $settings[ $group ][ $key ] )
			? $settings[ $group ][ $key ]
			: array();
	}

	/**
	 * Get the presets source label.
	 *
	 * @param bool $has_theme_presets Whether the theme origin declares any preset.
	 * @return string
	 */
	private function get_source( $has_theme_presets ) {
		if ( ! $has_theme_presets ) {
			return 'core_defaults';
		}

		return wp_theme_has_theme_json() ? 'theme_json' : 'theme_supports';
	}

	/**
	 * Normalize a preset group into the reported origins.
	 *
	 * Only slug, name, value and the CSS variable survive; theme.json font family
	 * entries carry fontFace URL lists and vendor keys that would bloat the payload.
	 *
	 * @param array  $presets        Presets keyed by origin.
	 * @param string $value_key      Value key in the theme.json entry.
	 * @param string $output_key     Value key in the output entry.
	 * @param string $css_var_prefix CSS custom property prefix.
	 * @return array
	 */
	private function format_presets( $presets, $value_key, $output_key, $css_var_prefix ) {

		$formatted = array_fill_keys( self::ORIGINS, array() );

		foreach ( self::ORIGINS as $origin ) {

			if ( empty( $presets[ $origin ] ) || ! is_array( $presets[ $origin ] ) ) {
				continue;
			}

			foreach ( $presets[ $origin ] as $preset ) {

				if ( ! isset( $preset['slug'] ) ) {
					continue;
				}

				$formatted[ $origin ][] = array(
					'slug'      => $preset['slug'],
					'name'      => isset( $preset['name'] ) ? $preset['name'] : '',
					$output_key => isset( $preset[ $value_key ] ) ? $preset[ $value_key ] : '',
					'css_var'   => $css_var_prefix . $preset['slug'],
				);
			}
		}

		return $formatted;
	}

	/**
	 * Build the reported subset of the styles tree.
	 *
	 * @param array $styles Merged global styles, variables already resolved.
	 * @return array
	 */
	private function format_styles( $styles ) {

		$elements = isset( $styles['elements'] ) && is_array( $styles['elements'] ) ? $styles['elements'] : array();

		$element_styles = array();

		foreach ( self::HEADING_ELEMENTS as $element ) {
			$element_styles[ $element ] = $this->format_typography(
				isset( $elements[ $element ] ) ? $elements[ $element ] : array()
			);
		}

		$element_styles['link'] = array(
			'color'           => isset( $elements['link']['color']['text'] ) ? $elements['link']['color']['text'] : null,
			'text_decoration' => isset( $elements['link']['typography']['textDecoration'] ) ? $elements['link']['typography']['textDecoration'] : null,
		);

		return array(
			'color'      => array(
				'background' => isset( $styles['color']['background'] ) ? $styles['color']['background'] : null,
				'text'       => isset( $styles['color']['text'] ) ? $styles['color']['text'] : null,
				'link'       => isset( $styles['color']['link'] ) ? $styles['color']['link'] : null,
			),
			'typography' => $this->format_typography( $styles ),
			'elements'   => $element_styles,
		);
	}

	/**
	 * Map a styles node typography block to the reported keys.
	 *
	 * @param array $node Styles node holding a typography block.
	 * @return array
	 */
	private function format_typography( $node ) {

		$typography = isset( $node['typography'] ) && is_array( $node['typography'] ) ? $node['typography'] : array();

		return array(
			'font_family' => isset( $typography['fontFamily'] ) ? $typography['fontFamily'] : null,
			'font_size'   => isset( $typography['fontSize'] ) ? $typography['fontSize'] : null,
			'line_height' => isset( $typography['lineHeight'] ) ? $typography['lineHeight'] : null,
			'font_weight' => isset( $typography['fontWeight'] ) ? $typography['fontWeight'] : null,
		);
	}

	/**
	 * Build the schema of a preset group.
	 *
	 * @param string $value_key         Value key in the output entry.
	 * @param string $value_description Value description.
	 * @return array
	 */
	private function get_presets_schema( $value_key, $value_description ) {

		$item = array(
			'type'       => 'object',
			'properties' => array(
				'slug'     => array(
					'type'        => 'string',
					'description' => __( 'Preset slug.', 'premium-addons-for-elementor' ),
				),
				'name'     => array(
					'type'        => 'string',
					'description' => __( 'Preset label shown in the editor.', 'premium-addons-for-elementor' ),
				),
				$value_key => array(
					'type'        => 'string',
					'description' => $value_description,
				),
				'css_var'  => array(
					'type'        => 'string',
					'description' => __( 'CSS custom property carrying this preset, usable as var(<name>) so the value keeps tracking the theme.', 'premium-addons-for-elementor' ),
				),
			),
		);

		$group = array(
			'type'        => 'object',
			'description' => __( 'Presets split by origin. theme = declared by the active theme, default = shipped by WordPress core, custom = saved by the user in the Site Editor. Each key is always present and is an empty array when that origin declares none.', 'premium-addons-for-elementor' ),
			'properties'  => array(),
		);

		foreach ( self::ORIGINS as $origin ) {
			$group['properties'][ $origin ] = array(
				'type'  => 'array',
				'items' => $item,
			);
		}

		return $group;
	}

	/**
	 * Build the schema of a typography node.
	 *
	 * @return array
	 */
	private function get_typography_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'font_family' => array( 'type' => array( 'string', 'null' ) ),
				'font_size'   => array( 'type' => array( 'string', 'null' ) ),
				'line_height' => array( 'type' => array( 'string', 'null' ) ),
				'font_weight' => array( 'type' => array( 'string', 'null' ) ),
			),
		);
	}
}
