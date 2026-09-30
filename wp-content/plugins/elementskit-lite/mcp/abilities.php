<?php
namespace ElementsKit_Lite\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Registers ElementsKit's WP Abilities.
 *
 * The abilities are the public backend MCP contract. Each one is a thin wrapper
 * whose execute_callback delegates to the shared {@see Service} — no business
 * logic lives here.
 *
 * @since 3.10.x
 */
class Abilities {

	const CATEGORY = 'elementskit';

	/**
	 * Wire the ability + category registration onto the Abilities API hooks.
	 */
	public function hooks() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
	}

	/**
	 * Fully-qualified ability ids exposed by ElementsKit, in a stable order.
	 *
	 * @return string[]
	 */
	public static function ability_ids() {
		return [
			'elementskit/list-widgets',
			'elementskit/insert-widget',
			'elementskit/list-templates',
			'elementskit/insert-template',
			'elementskit/create-page',
			'elementskit/create-template',
			'elementskit/create-header-footer',
			'elementskit/list-custom-widgets',
			'elementskit/create-custom-widget',
			'elementskit/list-mega-menus',
			'elementskit/enable-mega-menu',
			'elementskit/update-mega-menu-item',
			'elementskit/create-mega-menu',
			'elementskit/list-library-templates',
			'elementskit/import-library-template',
		];
	}

	public function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			[
				'label'       => __( 'ElementsKit', 'elementskit-lite' ),
				'description' => __( 'Read and modify ElementsKit widgets and templates for Elementor.', 'elementskit-lite' ),
			]
		);
	}

	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'elementskit/list-widgets',
			[
				'label'               => __( 'List ElementsKit widgets', 'elementskit-lite' ),
				'description'         => __( 'List the ElementsKit widgets (Lite and Pro) available in Elementor.', 'elementskit-lite' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'search'   => [
							'type'        => 'string',
							'description' => __( 'Optional case-insensitive filter matched against the widget type and title.', 'elementskit-lite' ),
						],
						'category' => [
							'type'        => 'string',
							'description' => __( 'Optional Elementor category slug to filter by.', 'elementskit-lite' ),
						],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success' => [ 'type' => 'boolean' ],
						'count'   => [ 'type' => 'integer' ],
						'widgets' => [ 'type' => 'array' ],
					],
				],
				'execute_callback'    => static function ( $input ) {
					return ( new Service() )->list_widgets( is_array( $input ) ? $input : [] );
				},
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
			]
		);

		wp_register_ability(
			'elementskit/insert-widget',
			[
				'label'               => __( 'Insert ElementsKit widget', 'elementskit-lite' ),
				'description'         => __( 'Insert an ElementsKit widget into an Elementor document.', 'elementskit-lite' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'post_id', 'widget_type' ],
					'properties' => [
						'post_id'     => [
							'type'        => 'integer',
							'description' => __( 'Target Elementor document id.', 'elementskit-lite' ),
						],
						'parent_id'   => [
							'type'        => 'string',
							'description' => __( 'Container id to append to, or "document" for the document root.', 'elementskit-lite' ),
							'default'     => 'document',
						],
						'widget_type' => [
							'type'        => 'string',
							'description' => __( 'ElementsKit widget name, e.g. "elementskit-heading".', 'elementskit-lite' ),
						],
						'settings'    => [
							'type'        => 'object',
							'description' => __( 'Elementor control settings for the widget.', 'elementskit-lite' ),
						],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success'           => [ 'type' => 'boolean' ],
						'post_id'           => [ 'type' => 'integer' ],
						'element_id'        => [ 'type' => 'string' ],
						'inserted_root_ids' => [ 'type' => 'array' ],
						'preview_url'       => [ 'type' => 'string' ],
						'version'           => [ 'type' => 'string' ],
					],
				],
				'execute_callback'    => static function ( $input ) {
					return ( new Service() )->insert_widget( is_array( $input ) ? $input : [] );
				},
				'permission_callback' => static function ( $input ) {
					$post_id = is_array( $input ) && isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;

					return current_user_can( 'edit_posts' ) && $post_id > 0 && current_user_can( 'edit_post', $post_id );
				},
			]
		);

		wp_register_ability(
			'elementskit/list-templates',
			[
				'label'               => __( 'List ElementsKit templates', 'elementskit-lite' ),
				'description'         => __( 'List the available ElementsKit templates.', 'elementskit-lite' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'type'     => [
							'type'        => 'string',
							'description' => __( 'Optional template type filter (e.g. header, footer).', 'elementskit-lite' ),
						],
						'search'   => [
							'type'        => 'string',
							'description' => __( 'Optional title search term.', 'elementskit-lite' ),
						],
						'per_page' => [
							'type'        => 'integer',
							'description' => __( 'Maximum number of templates to return (1-100).', 'elementskit-lite' ),
						],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success'   => [ 'type' => 'boolean' ],
						'count'     => [ 'type' => 'integer' ],
						'templates' => [ 'type' => 'array' ],
					],
				],
				'execute_callback'    => static function ( $input ) {
					return ( new Service() )->list_templates( is_array( $input ) ? $input : [] );
				},
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
			]
		);

		wp_register_ability(
			'elementskit/insert-template',
			[
				'label'               => __( 'Insert ElementsKit template', 'elementskit-lite' ),
				'description'         => __( 'Insert an ElementsKit template into an Elementor document.', 'elementskit-lite' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'post_id', 'template_id' ],
					'properties' => [
						'post_id'     => [
							'type'        => 'integer',
							'description' => __( 'Target Elementor document id.', 'elementskit-lite' ),
						],
						'template_id' => [
							'type'        => 'integer',
							'description' => __( 'Source ElementsKit template id.', 'elementskit-lite' ),
						],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success'           => [ 'type' => 'boolean' ],
						'post_id'           => [ 'type' => 'integer' ],
						'template_id'       => [ 'type' => 'integer' ],
						'inserted_root_ids' => [ 'type' => 'array' ],
						'preview_url'       => [ 'type' => 'string' ],
						'version'           => [ 'type' => 'string' ],
					],
				],
				'execute_callback'    => static function ( $input ) {
					return ( new Service() )->insert_template( is_array( $input ) ? $input : [] );
				},
				'permission_callback' => static function ( $input ) {
					$post_id = is_array( $input ) && isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;

					return current_user_can( 'edit_posts' ) && $post_id > 0 && current_user_can( 'edit_post', $post_id );
				},
			]
		);

		$this->register_build_abilities();
		$this->register_mega_menu_abilities();
		$this->register_library_abilities();
	}

	/**
	 * Create-page / create-template / custom-widget abilities.
	 */
	private function register_build_abilities() {
		wp_register_ability(
			'elementskit/create-page',
			[
				'label'               => __( 'Create page with ElementsKit', 'elementskit-lite' ),
				'description'         => __( 'Create a new Elementor page or post, optionally seeded with an element tree of ElementsKit widgets and containers.', 'elementskit-lite' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'title'     => [ 'type' => 'string', 'description' => __( 'Page title.', 'elementskit-lite' ) ],
						'post_type' => [ 'type' => 'string', 'enum' => [ 'page', 'post' ], 'default' => 'page' ],
						'canvas'    => [ 'type' => 'boolean', 'description' => __( 'Use the Elementor Canvas template.', 'elementskit-lite' ), 'default' => true ],
						'elements'  => [ 'type' => 'array', 'description' => __( 'Elementor element tree to seed the page with.', 'elementskit-lite' ) ],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success'     => [ 'type' => 'boolean' ],
						'post_id'     => [ 'type' => 'integer' ],
						'post_type'   => [ 'type' => 'string' ],
						'post_status' => [ 'type' => 'string', 'description' => __( 'Published when the user can publish this post type, otherwise draft.', 'elementskit-lite' ) ],
						'edit_url'    => [ 'type' => 'string' ],
						'preview_url' => [ 'type' => 'string' ],
						'note'        => [ 'type' => 'string' ],
					],
				],
				'execute_callback'    => static function ( $input ) {
					return ( new Service() )->create_page( is_array( $input ) ? $input : [] );
				},
				'permission_callback' => static function () {
					return current_user_can( 'edit_pages' ) || current_user_can( 'edit_posts' );
				},
			]
		);

		wp_register_ability(
			'elementskit/create-template',
			[
				'label'               => __( 'Create ElementsKit template', 'elementskit-lite' ),
				'description'         => __( 'Create an ElementsKit header, footer, or section template, optionally seeded with an Elementor element tree.', 'elementskit-lite' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'title'      => [ 'type' => 'string' ],
						'type'       => [ 'type' => 'string', 'enum' => [ 'header', 'footer', 'section' ], 'default' => 'section' ],
						'activation' => [ 'type' => 'string', 'enum' => [ 'yes', 'no' ], 'default' => 'no' ],
						'condition'  => [ 'type' => 'string', 'description' => __( 'Display condition for header/footer templates.', 'elementskit-lite' ) ],
						'elements'   => [ 'type' => 'array' ],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success'  => [ 'type' => 'boolean' ],
						'id'       => [ 'type' => 'integer' ],
						'type'     => [ 'type' => 'string' ],
						'edit_url' => [ 'type' => 'string' ],
					],
				],
				'execute_callback'    => static function ( $input ) {
					return ( new Service() )->create_template( is_array( $input ) ? $input : [] );
				},
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		wp_register_ability(
			'elementskit/create-header-footer',
			[
				'label'               => __( 'Create ElementsKit header/footer', 'elementskit-lite' ),
				'description'         => __( 'Create an ElementsKit header or footer, activated site-wide so it displays. Optionally build it from a ready-made ElementsKit Template Library template (pass template_id from list-library-templates).', 'elementskit-lite' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'type'        => [ 'type' => 'string', 'enum' => [ 'header', 'footer' ], 'default' => 'header' ],
						'title'       => [ 'type' => 'string' ],
						'condition'   => [ 'type' => 'string', 'description' => __( 'Display condition. Defaults to entire_site.', 'elementskit-lite' ), 'default' => 'entire_site' ],
						'activation'  => [ 'type' => 'string', 'enum' => [ 'yes', 'no' ], 'default' => 'yes' ],
						'template_id' => [ 'type' => 'string', 'description' => __( 'Library template id to build the header/footer from.', 'elementskit-lite' ) ],
						'elements'    => [ 'type' => 'array', 'description' => __( 'Element tree to seed with (ignored if template_id is given).', 'elementskit-lite' ) ],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success'     => [ 'type' => 'boolean' ],
						'id'          => [ 'type' => 'integer' ],
						'type'        => [ 'type' => 'string' ],
						'activated'   => [ 'type' => 'boolean' ],
						'condition'   => [ 'type' => 'string' ],
						'edit_url'    => [ 'type' => 'string' ],
						'preview_url' => [ 'type' => 'string' ],
					],
				],
				'execute_callback'    => static function ( $input ) {
					return ( new Service() )->create_header_footer( is_array( $input ) ? $input : [] );
				},
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		wp_register_ability(
			'elementskit/list-custom-widgets',
			[
				'label'               => __( 'List ElementsKit custom widgets', 'elementskit-lite' ),
				'description'         => __( 'List custom widgets built with the ElementsKit Widget Builder.', 'elementskit-lite' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [ 'type' => 'object', 'properties' => [] ],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success' => [ 'type' => 'boolean' ],
						'count'   => [ 'type' => 'integer' ],
						'widgets' => [ 'type' => 'array' ],
					],
				],
				'execute_callback'    => static function ( $input ) {
					return ( new Service() )->list_custom_widgets( is_array( $input ) ? $input : [] );
				},
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		wp_register_ability(
			'elementskit/create-custom-widget',
			[
				'label'               => __( 'Create ElementsKit custom widget', 'elementskit-lite' ),
				'description'         => __( 'Build a custom Elementor widget with the ElementsKit Widget Builder (title, icon, categories, markup, CSS, JS and control tabs). Pass an id to update an existing one.', 'elementskit-lite' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'title' ],
					'properties' => [
						'id'         => [ 'type' => 'integer', 'description' => __( 'Existing elementskit_widget id to update.', 'elementskit-lite' ) ],
						'title'      => [ 'type' => 'string' ],
						'icon'       => [ 'type' => 'string', 'default' => 'eicon-cog' ],
						'categories' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
						'markup'     => [ 'type' => 'string' ],
						'css'        => [ 'type' => 'string' ],
						'js'         => [ 'type' => 'string' ],
						'tabs'       => [ 'type' => 'object', 'additionalProperties' => true ],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success' => [ 'type' => 'boolean' ],
						'id'      => [ 'type' => 'integer' ],
					],
				],
				'execute_callback'    => static function ( $input ) {
					return ( new Service() )->create_custom_widget( is_array( $input ) ? $input : [] );
				},
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			]
		);
	}

	/**
	 * Mega menu abilities.
	 */
	private function register_mega_menu_abilities() {
		wp_register_ability(
			'elementskit/list-mega-menus',
			[
				'label'               => __( 'List mega menus', 'elementskit-lite' ),
				'description'         => __( 'List WordPress nav menus with their ElementsKit Mega Menu enable state and items. Use it to resolve a menu id / menu-item id.', 'elementskit-lite' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [ 'type' => 'object', 'properties' => [] ],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success' => [ 'type' => 'boolean' ],
						'menus'   => [ 'type' => 'array' ],
					],
				],
				'execute_callback'    => static function ( $input ) {
					return ( new Service() )->list_mega_menus( is_array( $input ) ? $input : [] );
				},
				'permission_callback' => static function () {
					return current_user_can( 'edit_theme_options' );
				},
			]
		);

		wp_register_ability(
			'elementskit/enable-mega-menu',
			[
				'label'               => __( 'Enable mega menu for a menu', 'elementskit-lite' ),
				'description'         => __( 'Enable or disable ElementsKit Mega Menu for an entire nav menu location (the menu-wide switch).', 'elementskit-lite' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'menu_id' ],
					'properties' => [
						'menu_id' => [ 'type' => 'integer', 'description' => __( 'The nav menu term id.', 'elementskit-lite' ) ],
						'enabled' => [ 'type' => 'boolean', 'default' => true ],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success' => [ 'type' => 'boolean' ],
						'menu_id' => [ 'type' => 'integer' ],
						'enabled' => [ 'type' => 'boolean' ],
					],
				],
				'execute_callback'    => static function ( $input ) {
					return ( new Service() )->enable_mega_menu( is_array( $input ) ? $input : [] );
				},
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		wp_register_ability(
			'elementskit/update-mega-menu-item',
			[
				'label'               => __( 'Update mega menu item', 'elementskit-lite' ),
				'description'         => __( 'Enable and configure the ElementsKit Mega Menu panel on a nav-menu item, and optionally set the Elementor element tree shown as the panel content.', 'elementskit-lite' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'menu_item_id' ],
					'properties' => [
						'menu_item_id' => [ 'type' => 'integer', 'description' => __( 'The nav_menu_item post id.', 'elementskit-lite' ) ],
						'settings'     => [ 'type' => 'object', 'additionalProperties' => true ],
						'elements'     => [ 'type' => 'array', 'description' => __( 'Elementor element tree for the panel content.', 'elementskit-lite' ) ],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success'      => [ 'type' => 'boolean' ],
						'menu_item_id' => [ 'type' => 'integer' ],
					],
				],
				'execute_callback'    => static function ( $input ) {
					return ( new Service() )->update_mega_menu_item( is_array( $input ) ? $input : [] );
				},
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		wp_register_ability(
			'elementskit/create-mega-menu',
			[
				'label'               => __( 'Create ElementsKit mega menu', 'elementskit-lite' ),
				'description'         => __( 'One-shot mega menu creation: create (or reuse) a nav menu, add a top-level item, switch Mega Menu on and enable + configure the panel on that item, optionally seeded with an Elementor element tree.', 'elementskit-lite' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'menu_name'  => [ 'type' => 'string', 'description' => __( 'Name for a new nav menu (used when menu_id is absent).', 'elementskit-lite' ) ],
						'menu_id'    => [ 'type' => 'integer', 'description' => __( 'Existing nav menu term id to add the item to.', 'elementskit-lite' ) ],
						'item_title'  => [ 'type' => 'string', 'description' => __( 'Label of the top-level item that opens the panel.', 'elementskit-lite' ) ],
						'item_url'    => [ 'type' => 'string', 'description' => __( 'Link for the item. Defaults to "#".', 'elementskit-lite' ) ],
						'template_id' => [ 'type' => 'string', 'description' => __( 'ElementsKit Template Library id to fill the panel with (from list-library-templates). The panel is populated on the backend so it never previews blank.', 'elementskit-lite' ) ],
						'settings'    => [ 'type' => 'object', 'additionalProperties' => true, 'description' => __( 'Mega menu item settings (menu_icon, menu_badge_text, megamenu_width_type, ...).', 'elementskit-lite' ) ],
						'elements'    => [ 'type' => 'array', 'description' => __( 'Elementor element tree for the panel content (used when template_id is absent).', 'elementskit-lite' ) ],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success'      => [ 'type' => 'boolean' ],
						'menu_id'      => [ 'type' => 'integer' ],
						'menu_item_id' => [ 'type' => 'integer' ],
						'menu_name'    => [ 'type' => 'string' ],
						'manage_url'   => [ 'type' => 'string' ],
					],
				],
				'execute_callback'    => static function ( $input ) {
					return ( new Service() )->create_mega_menu( is_array( $input ) ? $input : [] );
				},
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' ) && current_user_can( 'edit_theme_options' );
				},
			]
		);
	}

	/**
	 * Template-library abilities.
	 */
	private function register_library_abilities() {
		wp_register_ability(
			'elementskit/list-library-templates',
			[
				'label'               => __( 'List template library', 'elementskit-lite' ),
				'description'         => __( 'List ready-made templates, sections and pages from the ElementsKit Template Library.', 'elementskit-lite' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [ 'type' => 'object', 'properties' => [] ],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success' => [ 'type' => 'boolean' ],
						'library' => [ 'type' => 'array' ],
					],
				],
				'execute_callback'    => static function ( $input ) {
					return ( new Service() )->list_library_templates( is_array( $input ) ? $input : [] );
				},
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
			]
		);

		wp_register_ability(
			'elementskit/import-library-template',
			[
				'label'               => __( 'Import template from library', 'elementskit-lite' ),
				'description'         => __( 'Import a ready-made template, section or page from the ElementsKit Template Library into an Elementor document.', 'elementskit-lite' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'       => 'object',
					'required'   => [ 'post_id', 'template_id' ],
					'properties' => [
						'post_id'     => [ 'type' => 'integer', 'description' => __( 'Target Elementor document id.', 'elementskit-lite' ) ],
						'template_id' => [ 'type' => 'string', 'description' => __( 'Library template id.', 'elementskit-lite' ) ],
					],
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success'           => [ 'type' => 'boolean' ],
						'post_id'           => [ 'type' => 'integer' ],
						'template_id'       => [ 'type' => 'string' ],
						'inserted_root_ids' => [ 'type' => 'array' ],
						'preview_url'       => [ 'type' => 'string' ],
					],
				],
				'execute_callback'    => static function ( $input ) {
					return ( new Service() )->import_library_template( is_array( $input ) ? $input : [] );
				},
				'permission_callback' => static function ( $input ) {
					$post_id = is_array( $input ) && isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;

					return current_user_can( 'edit_posts' ) && $post_id > 0 && current_user_can( 'edit_post', $post_id );
				},
			]
		);
	}
}
