<?php
/**
 * Get Theme Info.
 *
 * Reads the active theme's identity, parent/child relation and capabilities.
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
class Get_Theme_Info implements Ability_Handler {

	/**
	 * Theme support features probed for the output.
	 *
	 * A fixed list keeps the output stable and comparable across sites.
	 *
	 * @var array
	 */
	const PROBED_SUPPORTS = array(
		'post-thumbnails',
		'custom-logo',
		'editor-styles',
		'wp-block-styles',
		'align-wide',
		'responsive-embeds',
		'custom-background',
		'html5',
	);

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'get-theme-info';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Get Active Theme Info', 'premium-addons-for-elementor' ),
			'description'         => __( "Reads the active theme's name, version, parent theme and capabilities.", 'premium-addons-for-elementor' ),
			'category'            => 'pa-discovery',
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'Identity and capabilities of the currently active theme.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'active'         => array(
						'type'        => 'string',
						'description' => __( 'Stylesheet slug of the active theme. This is the child theme slug when a child theme is active.', 'premium-addons-for-elementor' ),
					),
					'parent'         => array(
						'type'        => 'string',
						'description' => __( 'Template slug of the parent theme. Same as active when the active theme is not a child theme.', 'premium-addons-for-elementor' ),
					),
					'is_child'       => array(
						'type'        => 'boolean',
						'description' => __( 'True when the active theme is a child theme.', 'premium-addons-for-elementor' ),
					),
					'is_block_theme' => array(
						'type'        => 'boolean',
						'description' => __( 'True when the active theme is a block theme, so its styles come from theme.json and block templates.', 'premium-addons-for-elementor' ),
					),
					'name'           => array(
						'type'        => 'string',
						'description' => __( 'Display name of the active theme.', 'premium-addons-for-elementor' ),
					),
					'version'        => array(
						'type'        => 'string',
						'description' => __( 'Version of the active theme.', 'premium-addons-for-elementor' ),
					),
					'stylesheet_dir' => array(
						'type'        => 'string',
						'description' => __( 'Absolute server path of the active theme directory.', 'premium-addons-for-elementor' ),
					),
					'template_dir'   => array(
						'type'        => 'string',
						'description' => __( 'Absolute server path of the parent theme directory. Same as stylesheet_dir when the active theme is not a child theme.', 'premium-addons-for-elementor' ),
					),
					'supports'       => array(
						'type'        => 'array',
						'description' => __( 'Which of the probed theme features the active theme supports.', 'premium-addons-for-elementor' ),
						'items'       => array(
							'type' => 'string',
						),
					),
					'menu_locations' => array(
						'type'                 => 'object',
						'description'          => __( 'Navigation menu locations registered by the theme, keyed by location slug with the location description as value. Empty when the theme registers none.', 'premium-addons-for-elementor' ),
						'additionalProperties' => array(
							'type' => 'string',
						),
					),
					'has_child'      => array(
						'type'        => 'boolean',
						'description' => __( 'True when an installed theme is a child of the parent theme, whether or not that child is the active one.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'permission_callback' => function () {
				return Admin_Helper::check_user_can( 'manage_options' );
			},
			'meta'                => array(
				'show_in_rest' => true,
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

		$stylesheet = get_stylesheet();
		$template   = get_template();
		$theme      = wp_get_theme();

		$supports = array();

		foreach ( self::PROBED_SUPPORTS as $feature ) {
			if ( current_theme_supports( $feature ) ) {
				$supports[] = $feature;
			}
		}

		return array(
			'active'         => $stylesheet,
			'parent'         => $template,
			'is_child'       => $stylesheet !== $template,
			'is_block_theme' => wp_is_block_theme(),
			'name'           => $theme->get( 'Name' ),
			'version'        => $theme->get( 'Version' ),
			'stylesheet_dir' => get_stylesheet_directory(),
			'template_dir'   => get_template_directory(),
			'supports'       => $supports,
			'menu_locations' => get_registered_nav_menus(),
			'has_child'      => $this->has_child_theme( $stylesheet, $template ),
		);
	}

	/**
	 * Check whether an installed theme is a child of the parent theme.
	 *
	 * @param string $stylesheet Active theme slug.
	 * @param string $template   Parent theme slug.
	 * @return bool
	 */
	private function has_child_theme( $stylesheet, $template ) {

		foreach ( wp_get_themes() as $slug => $installed_theme ) {
			if ( $slug !== $stylesheet && $installed_theme->get_template() === $template ) {
				return true;
			}
		}

		return false;
	}
}
