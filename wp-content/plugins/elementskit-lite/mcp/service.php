<?php
namespace ElementsKit_Lite\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Shared ElementsKit MCP service layer.
 *
 * This is the single source of truth for reading and mutating ElementsKit
 * widgets/templates on an Elementor document. Every entry point — WP Abilities,
 * the REST proxy (elementskit/v1/mcp-proxy) and the Angie editor tools — delegates
 * here so the business logic is never duplicated.
 *
 * Method contract:
 *   - Success  => associative array containing at least [ 'success' => true, ... ].
 *   - Failure  => \WP_Error, which callers normalise into their own error shape.
 *
 * The class is also aliased to the global \ElementsKit_MCP_Service (see the bottom
 * of this file) so integrations can use the flat name from the spec.
 *
 * @since 3.10.x
 */
class Service {

	/**
	 * Widget name prefixes that identify an ElementsKit (Lite or Pro) widget.
	 *
	 * @var string[]
	 */
	const WIDGET_PREFIXES = [ 'elementskit-', 'ekit-' ];

	/**
	 * ElementsKit Template Library id used to fill a mega menu panel by default
	 * (the "Mega Menu – Mens Fashion" section) so it is never a bare placeholder.
	 * Overridable per call via the create_mega_menu `template_id` input.
	 */
	const DEFAULT_MEGA_MENU_TEMPLATE_ID = '10006762';

	/**
	 * List the ElementsKit widgets currently registered with Elementor.
	 *
	 * Because both Lite and Pro register their widgets through the same Elementor
	 * widgets manager, filtering that registry by the ElementsKit name prefixes
	 * transparently returns free and pro widgets.
	 *
	 * @param array $input Optional { search?: string, category?: string }.
	 * @return array { success: true, widgets: array, count: int }
	 */
	public function list_widgets( array $input = [] ) {
		if ( ! $this->elementor_ready() ) {
			return new \WP_Error( 'ekit_mcp_elementor_missing', __( 'Elementor is not active.', 'elementskit-lite' ), [ 'status' => 400 ] );
		}

		$search   = isset( $input['search'] ) ? strtolower( trim( (string) $input['search'] ) ) : '';
		$category = isset( $input['category'] ) ? (string) $input['category'] : '';

		$widgets = [];

		foreach ( \Elementor\Plugin::instance()->widgets_manager->get_widget_types() as $widget ) {
			$type = $widget->get_name();

			if ( ! $this->is_elementskit_widget( $type ) ) {
				continue;
			}

			$categories = (array) $widget->get_categories();

			if ( '' !== $category && ! in_array( $category, $categories, true ) ) {
				continue;
			}

			$title = $widget->get_title();

			if ( '' !== $search && false === strpos( strtolower( $type . ' ' . $title ), $search ) ) {
				continue;
			}

			$widgets[] = [
				'type'       => $type,
				'title'      => $title,
				'icon'       => $widget->get_icon(),
				'categories' => array_values( $categories ),
				'keywords'   => array_values( (array) $widget->get_keywords() ),
			];
		}

		usort(
			$widgets,
			static function ( $a, $b ) {
				return strcmp( $a['title'], $b['title'] );
			}
		);

		return [
			'success' => true,
			'widgets' => array_values( $widgets ),
			'count'   => count( $widgets ),
		];
	}

	/**
	 * Insert an ElementsKit widget into an Elementor document.
	 *
	 * When $parent_id is "document" (or empty) the widget is wrapped in a flex
	 * container and appended to the document root so the resulting tree stays
	 * valid. Otherwise the widget is appended to the container/section whose id
	 * matches $parent_id.
	 *
	 * @param array $input {
	 *     @type int    $post_id     Target Elementor document id. Required.
	 *     @type string $parent_id   Container id to append to, or "document". Default "document".
	 *     @type string $widget_type ElementsKit widget name, e.g. "elementskit-heading". Required.
	 *     @type array  $settings    Elementor control settings for the widget. Optional.
	 * }
	 * @return array|\WP_Error
	 */
	public function insert_widget( array $input ) {
		$document = $this->resolve_editable_document( $input );

		if ( is_wp_error( $document ) ) {
			return $document;
		}

		$post_id     = (int) $input['post_id'];
		$widget_type = isset( $input['widget_type'] ) ? trim( (string) $input['widget_type'] ) : '';
		$parent_id   = isset( $input['parent_id'] ) ? trim( (string) $input['parent_id'] ) : 'document';
		$settings    = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : [];

		if ( '' === $widget_type ) {
			return new \WP_Error( 'ekit_mcp_missing_widget_type', __( 'A widget_type is required.', 'elementskit-lite' ), [ 'status' => 400 ] );
		}

		if ( ! $this->is_elementskit_widget( $widget_type ) || ! $this->widget_is_registered( $widget_type ) ) {
			return new \WP_Error(
				'ekit_mcp_unknown_widget',
				/* translators: %s: widget type */
				sprintf( __( 'Unknown ElementsKit widget type: %s', 'elementskit-lite' ), $widget_type ),
				[ 'status' => 400 ]
			);
		}

		$widget_node = $this->build_widget_element( $widget_type, $settings );
		$elements    = $document->get_elements_data();

		if ( '' === $parent_id || 'document' === $parent_id ) {
			$container   = $this->build_container_element( [ $widget_node ] );
			$elements[]  = $container;
			$root_ids    = [ $container['id'] ];
		} else {
			$appended = $this->append_to_parent( $elements, $parent_id, $widget_node );

			if ( ! $appended ) {
				return new \WP_Error(
					'ekit_mcp_parent_not_found',
					/* translators: %s: parent element id */
					sprintf( __( 'Parent element "%s" was not found in the document.', 'elementskit-lite' ), $parent_id ),
					[ 'status' => 404 ]
				);
			}

			$root_ids = [ $widget_node['id'] ];
		}

		$saved = $this->save_document( $document, $elements );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return $this->insert_response( $document, $post_id, $widget_node['id'], $root_ids );
	}

	/**
	 * List available ElementsKit templates (the elementskit_template CPT).
	 *
	 * @param array $input Optional { type?: string, search?: string, per_page?: int }.
	 * @return array { success: true, templates: array, count: int }
	 */
	public function list_templates( array $input = [] ) {
		$type     = isset( $input['type'] ) ? sanitize_text_field( (string) $input['type'] ) : '';
		$search   = isset( $input['search'] ) ? sanitize_text_field( (string) $input['search'] ) : '';
		$per_page = isset( $input['per_page'] ) ? (int) $input['per_page'] : 50;
		$per_page = max( 1, min( 100, $per_page ) );

		$args = [
			'post_type'      => 'elementskit_template',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		];

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		if ( '' !== $type ) {
			$args['meta_query'] = [
				[
					'key'   => 'elementskit_template_type',
					'value' => $type,
				],
			];
		}

		$templates = [];

		foreach ( get_posts( $args ) as $post ) {
			$templates[] = [
				'id'       => (int) $post->ID,
				'title'    => get_the_title( $post ),
				'type'     => (string) get_post_meta( $post->ID, 'elementskit_template_type', true ),
				'edit_url' => get_edit_post_link( $post->ID, 'raw' ),
			];
		}

		return [
			'success'   => true,
			'templates' => $templates,
			'count'     => count( $templates ),
		];
	}

	/**
	 * Insert the contents of an ElementsKit template into an Elementor document.
	 *
	 * The template's element tree is copied with freshly generated ids and appended
	 * to the target document root.
	 *
	 * @param array $input {
	 *     @type int $post_id     Target Elementor document id. Required.
	 *     @type int $template_id Source elementskit_template id. Required.
	 * }
	 * @return array|\WP_Error
	 */
	public function insert_template( array $input ) {
		$document = $this->resolve_editable_document( $input );

		if ( is_wp_error( $document ) ) {
			return $document;
		}

		$post_id     = (int) $input['post_id'];
		$template_id = isset( $input['template_id'] ) ? (int) $input['template_id'] : 0;

		if ( $template_id <= 0 ) {
			return new \WP_Error( 'ekit_mcp_missing_template_id', __( 'A template_id is required.', 'elementskit-lite' ), [ 'status' => 400 ] );
		}

		$template_post = get_post( $template_id );

		if ( ! $template_post || 'elementskit_template' !== $template_post->post_type ) {
			return new \WP_Error(
				'ekit_mcp_unknown_template',
				/* translators: %d: template id */
				sprintf( __( 'Unknown ElementsKit template id: %d', 'elementskit-lite' ), $template_id ),
				[ 'status' => 404 ]
			);
		}

		$template_document = \Elementor\Plugin::instance()->documents->get( $template_id );
		$template_elements = $template_document ? $template_document->get_elements_data() : [];

		if ( empty( $template_elements ) ) {
			return new \WP_Error( 'ekit_mcp_empty_template', __( 'The template has no Elementor content.', 'elementskit-lite' ), [ 'status' => 400 ] );
		}

		$template_elements = $this->regenerate_ids( $template_elements );

		$elements = $document->get_elements_data();
		$root_ids = [];

		foreach ( $template_elements as $node ) {
			$elements[] = $node;
			$root_ids[] = $node['id'];
		}

		$saved = $this->save_document( $document, $elements );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$response                = $this->insert_response( $document, $post_id, $root_ids ? $root_ids[0] : '', $root_ids );
		$response['template_id'] = $template_id;

		return $response;
	}

	/**
	 * Create a new page (or post) built with Elementor and optionally seeded with
	 * an element tree of ElementsKit widgets/containers.
	 *
	 * The new post is published only when the current user can publish this post
	 * type; users who can create but not publish (e.g. contributors) get a draft.
	 *
	 * @param array $input {
	 *     @type string $title     Page title. Optional.
	 *     @type string $post_type "page" or "post". Default "page".
	 *     @type array  $elements  Optional Elementor element tree to seed with.
	 *     @type bool   $canvas    Use the Elementor Canvas template. Default true.
	 * }
	 * @return array|\WP_Error
	 */
	public function create_page( array $input ) {
		if ( ! $this->elementor_ready() ) {
			return new \WP_Error( 'ekit_mcp_elementor_missing', __( 'Elementor is not active.', 'elementskit-lite' ), [ 'status' => 400 ] );
		}

		$post_type = isset( $input['post_type'] ) && 'post' === $input['post_type'] ? 'post' : 'page';
		$pt_object = get_post_type_object( $post_type );

		if ( ! $pt_object || ! current_user_can( $pt_object->cap->edit_posts ) ) {
			return new \WP_Error( 'ekit_mcp_forbidden', __( 'You are not allowed to create this content.', 'elementskit-lite' ), [ 'status' => 403 ] );
		}

		$title = isset( $input['title'] ) && '' !== trim( (string) $input['title'] )
			? sanitize_text_field( $input['title'] )
			: 'ElementsKit Page #' . time();

		// wp_insert_post() does not enforce publish_posts, so gate the status here:
		// contributors can create content but must not be able to publish it.
		$post_status = current_user_can( $pt_object->cap->publish_posts ) ? 'publish' : 'draft';

		$post_id = wp_insert_post(
			[
				'post_title'  => $title,
				'post_status' => $post_status,
				'post_type'   => $post_type,
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );

		if ( ! isset( $input['canvas'] ) || $input['canvas'] ) {
			update_post_meta( $post_id, '_wp_page_template', 'elementor_canvas' );
		}

		$elements = isset( $input['elements'] ) && is_array( $input['elements'] ) ? $this->regenerate_ids( $input['elements'] ) : [];

		$document = \Elementor\Plugin::instance()->documents->get( $post_id );

		if ( $document && ! empty( $elements ) ) {
			$saved = $this->save_document( $document, $elements );

			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}

		$response = [
			'success'     => true,
			'post_id'     => (int) $post_id,
			'post_type'   => $post_type,
			'post_status' => $post_status,
			'edit_url'    => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ),
			'preview_url' => get_permalink( $post_id ),
		];

		if ( 'publish' !== $post_status ) {
			$response['note'] = __( 'Saved as a draft because you do not have permission to publish this content type. An editor or administrator has to publish it.', 'elementskit-lite' );
		}

		return $response;
	}

	/**
	 * Create an ElementsKit header/footer/section template (elementskit_template CPT).
	 *
	 * @param array $input {
	 *     @type string $title      Template title. Optional.
	 *     @type string $type       "header" | "footer" | "section". Default "section".
	 *     @type string $activation "yes" | "no". Default "no".
	 *     @type string $condition  Display condition for header/footer. Optional.
	 *     @type array  $elements   Optional Elementor element tree to seed with.
	 * }
	 * @return array|\WP_Error
	 */
	public function create_template( array $input ) {
		if ( ! $this->elementor_ready() ) {
			return new \WP_Error( 'ekit_mcp_elementor_missing', __( 'Elementor is not active.', 'elementskit-lite' ), [ 'status' => 400 ] );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'ekit_mcp_forbidden', __( 'You are not allowed to create templates.', 'elementskit-lite' ), [ 'status' => 403 ] );
		}

		$type = ! empty( $input['type'] ) ? sanitize_key( $input['type'] ) : 'section';

		if ( ! in_array( $type, [ 'header', 'footer', 'section' ], true ) ) {
			return new \WP_Error( 'ekit_mcp_invalid_type', __( 'Template type must be header, footer, or section.', 'elementskit-lite' ), [ 'status' => 400 ] );
		}

		$title = isset( $input['title'] ) && '' !== trim( (string) $input['title'] )
			? sanitize_text_field( $input['title'] )
			: 'ElementsKit Template #' . time();

		$activation = ( isset( $input['activation'] ) && 'yes' === $input['activation'] ) ? 'yes' : 'no';
		$condition  = ( 'section' === $type || empty( $input['condition'] ) ) ? '' : sanitize_text_field( $input['condition'] );

		$post_id = wp_insert_post(
			[
				'post_title'  => $title,
				'post_status' => 'publish',
				'post_type'   => 'elementskit_template',
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, '_wp_page_template', 'elementor_canvas' );
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $post_id, 'elementskit_template_activation', $activation );
		update_post_meta( $post_id, 'elementskit_template_type', $type );
		update_post_meta( $post_id, 'elementskit_template_condition_a', $condition );
		update_post_meta( $post_id, 'elementskit_template_condition_singular', '' );
		update_post_meta( $post_id, 'elementskit_template_condition_singular_id', '' );

		if ( ! empty( $input['elements'] ) && is_array( $input['elements'] ) ) {
			$document = \Elementor\Plugin::instance()->documents->get( $post_id );

			if ( $document ) {
				$saved = $this->save_document( $document, $this->regenerate_ids( $input['elements'] ) );

				if ( is_wp_error( $saved ) ) {
					return $saved;
				}
			}
		}

		return [
			'success'  => true,
			'id'       => (int) $post_id,
			'type'     => $type,
			'edit_url' => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ),
			'note'     => sprintf(
				/* translators: %d: post id */
				__( 'This is an ElementsKit template (elementskit_template post %d), not a WordPress page. To open it in the Elementor editor use the elementskit-open-in-editor tool with post_id %d — do NOT use the generic Elementor "navigate to editor"/pages tools, which only work for pages.', 'elementskit-lite' ),
				(int) $post_id,
				(int) $post_id
			),
		];
	}

	/**
	 * Create an ElementsKit header or footer, activated site-wide so it displays,
	 * and optionally build it from a ready-made ElementsKit Template Library
	 * template (e.g. "create a header from the library").
	 *
	 * Composes create_template() (which writes the header/footer CPT + display
	 * condition metas) and import_library_template() so no logic is duplicated.
	 *
	 * @param array $input {
	 *     @type string     $type        "header" | "footer". Default "header".
	 *     @type string     $title       Template title. Optional.
	 *     @type string     $condition   Display condition. Default "entire_site".
	 *     @type string     $activation  "yes" | "no". Default "yes" (so it displays).
	 *     @type int|string $template_id Optional library template to import content from.
	 *     @type array      $elements    Optional element tree to seed with (ignored if template_id given).
	 * }
	 * @return array|\WP_Error
	 */
	public function create_header_footer( array $input ) {
		$type       = isset( $input['type'] ) && 'footer' === $input['type'] ? 'footer' : 'header';
		$condition  = isset( $input['condition'] ) && '' !== trim( (string) $input['condition'] ) ? sanitize_text_field( $input['condition'] ) : 'entire_site';
		$activation = isset( $input['activation'] ) && 'no' === $input['activation'] ? 'no' : 'yes';

		$created = $this->create_template(
			[
				'title'      => isset( $input['title'] ) ? $input['title'] : 'ElementsKit ' . ucfirst( $type ) . ' #' . time(),
				'type'       => $type,
				'activation' => $activation,
				'condition'  => $condition,
				'elements'   => isset( $input['elements'] ) && is_array( $input['elements'] ) ? $input['elements'] : [],
			]
		);

		if ( is_wp_error( $created ) ) {
			return $created;
		}

		$post_id = (int) $created['id'];

		$response = [
			'success'     => true,
			'id'          => $post_id,
			'type'        => $type,
			'activated'   => 'yes' === $activation,
			'condition'   => $condition,
			'edit_url'    => $created['edit_url'],
			'preview_url' => get_permalink( $post_id ),
			'note'        => sprintf(
				/* translators: 1: header/footer, 2: post id */
				__( 'Created an ElementsKit %1$s (elementskit_template post %2$d), activated site-wide. It is NOT a WordPress page. To open it in the Elementor editor use the elementskit-open-in-editor tool with post_id %2$d — do NOT use the generic Elementor "navigate to editor"/pages tools.', 'elementskit-lite' ),
				$type,
				(int) $post_id
			),
		];

		// Optionally build the header/footer from a template-library template.
		if ( ! empty( $input['template_id'] ) ) {
			$import = $this->import_library_template(
				[
					'post_id'     => $post_id,
					'template_id' => $input['template_id'],
				]
			);

			if ( is_wp_error( $import ) ) {
				$response['import_error'] = $import->get_error_message();
			} else {
				$response['imported_template_id'] = $input['template_id'];
				$response['inserted_root_ids']    = isset( $import['inserted_root_ids'] ) ? $import['inserted_root_ids'] : [];
			}
		}

		return $response;
	}

	/**
	 * List custom widgets built with the ElementsKit Widget Builder.
	 *
	 * @param array $input Unused.
	 * @return array
	 */
	public function list_custom_widgets( array $input = [] ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'ekit_mcp_forbidden', __( 'You are not allowed to view custom widgets.', 'elementskit-lite' ), [ 'status' => 403 ] );
		}

		$widgets = [];

		foreach ( get_posts(
			[
				'post_type'      => 'elementskit_widget',
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			]
		) as $post ) {
			$widgets[] = [
				'id'     => (int) $post->ID,
				'title'  => $post->post_title,
				'status' => $post->post_status,
			];
		}

		return [
			'success' => true,
			'widgets' => $widgets,
			'count'   => count( $widgets ),
		];
	}

	/**
	 * Create (or update) a custom Elementor widget via the ElementsKit Widget Builder.
	 *
	 * @param array $input {
	 *     @type int    $id         Existing elementskit_widget id to update. Optional.
	 *     @type string $title      Widget title. Required.
	 *     @type string $icon       Elementor icon class. Default "eicon-cog".
	 *     @type array  $categories Elementor category slugs. Default ["basic"].
	 *     @type string $markup     Widget markup template.
	 *     @type string $css        Widget CSS.
	 *     @type string $js         Widget JS.
	 *     @type array  $tabs       Control definitions grouped into content/style/advanced.
	 * }
	 * @return array|\WP_Error
	 */
	public function create_custom_widget( array $input ) {
		if ( ! current_user_can( 'manage_options' ) || ( is_multisite() && ! is_super_admin() ) ) {
			return new \WP_Error( 'ekit_mcp_forbidden', __( 'You are not allowed to create custom widgets.', 'elementskit-lite' ), [ 'status' => 403 ] );
		}

		if ( ! class_exists( '\ElementsKit_Lite\Modules\Widget_Builder\Widget_File' ) ) {
			return new \WP_Error( 'ekit_mcp_widget_builder_unavailable', __( 'The Widget Builder module is not available.', 'elementskit-lite' ), [ 'status' => 500 ] );
		}

		$title = isset( $input['title'] ) && '' !== trim( (string) $input['title'] )
			? sanitize_text_field( $input['title'] )
			: 'ElementsKit Custom Widget #' . time();

		$tabs = isset( $input['tabs'] ) && is_array( $input['tabs'] ) ? $input['tabs'] : [];
		$tabs = wp_parse_args( $tabs, [ 'content' => [], 'style' => [], 'advanced' => [] ] );

		$config = [
			'title'        => $title,
			'icon'         => ! empty( $input['icon'] ) ? sanitize_text_field( $input['icon'] ) : 'eicon-cog',
			'categories'   => ! empty( $input['categories'] ) && is_array( $input['categories'] )
				? array_values( array_map( 'sanitize_text_field', $input['categories'] ) )
				: [ 'basic' ],
			'markup'       => isset( $input['markup'] ) ? (string) $input['markup'] : '',
			'css'          => isset( $input['css'] ) ? (string) $input['css'] : '',
			'js'           => isset( $input['js'] ) ? (string) $input['js'] : '',
			'css_includes' => isset( $input['css_includes'] ) ? (string) $input['css_includes'] : '',
			'js_includes'  => isset( $input['js_includes'] ) ? (string) $input['js_includes'] : '',
			'tabs'         => $tabs,
		];

		// The admin flow persists the builder config as a nested object.
		$data = json_decode( wp_json_encode( $config ) );

		$post_id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;

		if ( $post_id && get_post( $post_id ) ) {
			wp_update_post(
				[
					'ID'          => $post_id,
					'post_title'  => $title,
					'post_status' => 'publish',
					'post_type'   => 'elementskit_widget',
				]
			);
		} else {
			$post_id = wp_insert_post(
				[
					'post_title'  => $title,
					'post_status' => 'publish',
					'post_type'   => 'elementskit_widget',
				],
				true
			);

			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}
		}

		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $post_id, '_wp_page_template', 'elementor_canvas' );

		$data->push_id = $post_id;
		update_post_meta( $post_id, 'elementskit_custom_widget_data', $data );

		\ElementsKit_Lite\Modules\Widget_Builder\Widget_File::instance()->create( $data, $post_id );

		return [
			'success' => true,
			'id'      => (int) $post_id,
		];
	}

	/**
	 * List WordPress nav menus with their ElementsKit Mega Menu state and items.
	 *
	 * @param array $input Unused.
	 * @return array
	 */
	public function list_mega_menus( array $input = [] ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new \WP_Error( 'ekit_mcp_forbidden', __( 'You are not allowed to view menus.', 'elementskit-lite' ), [ 'status' => 403 ] );
		}

		$has_module        = class_exists( '\ElementsKit_Lite\Modules\Megamenu\Init' ) && class_exists( '\ElementsKit_Lite\Libs\Framework\Attr' );
		$location_settings = [];

		if ( $has_module ) {
			$location_settings = (array) \ElementsKit_Lite\Libs\Framework\Attr::instance()->utils->get_option(
				\ElementsKit_Lite\Modules\Megamenu\Init::$megamenu_settings_key,
				[]
			);
		}

		$menus = [];

		foreach ( wp_get_nav_menus() as $menu ) {
			$is_enabled = ! empty( $location_settings[ 'menu_location_' . $menu->term_id ]['is_enabled'] );
			$items      = [];

			foreach ( wp_get_nav_menu_items( $menu->term_id ) ?: [] as $item ) {
				$raw      = $has_module ? get_post_meta( $item->ID, \ElementsKit_Lite\Modules\Megamenu\Init::$menuitem_settings_key, true ) : '';
				$settings = $raw ? (array) json_decode( $raw, true ) : [];

				$items[] = [
					'id'                => (int) $item->ID,
					'title'             => $item->title,
					'mega_menu_enabled' => ! empty( $settings['menu_enable'] ),
				];
			}

			$menus[] = [
				'id'                => (int) $menu->term_id,
				'name'              => $menu->name,
				'mega_menu_enabled' => $is_enabled,
				'items'             => $items,
			];
		}

		return [
			'success' => true,
			'menus'   => $menus,
		];
	}

	/**
	 * Enable or disable ElementsKit Mega Menu for an entire nav menu location.
	 *
	 * @param array $input { @type int $menu_id, @type bool $enabled }
	 * @return array|\WP_Error
	 */
	public function enable_mega_menu( array $input ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'ekit_mcp_forbidden', __( 'You are not allowed to edit mega menus.', 'elementskit-lite' ), [ 'status' => 403 ] );
		}

		if ( ! class_exists( '\ElementsKit_Lite\Modules\Megamenu\Init' ) || ! class_exists( '\ElementsKit_Lite\Libs\Framework\Attr' ) ) {
			return new \WP_Error( 'ekit_mcp_megamenu_unavailable', __( 'The Mega Menu module is not available.', 'elementskit-lite' ), [ 'status' => 500 ] );
		}

		$menu_id = isset( $input['menu_id'] ) ? absint( $input['menu_id'] ) : 0;

		if ( ! $menu_id || ! is_nav_menu( $menu_id ) ) {
			return new \WP_Error( 'ekit_mcp_invalid_menu_id', __( 'A valid menu_id (nav menu term id) is required.', 'elementskit-lite' ), [ 'status' => 400 ] );
		}

		$enabled = ! isset( $input['enabled'] ) || (bool) $input['enabled'];

		$data                                = (array) \ElementsKit_Lite\Libs\Framework\Attr::instance()->utils->get_option( \ElementsKit_Lite\Modules\Megamenu\Init::$megamenu_settings_key, [] );
		$data[ 'menu_location_' . $menu_id ] = [ 'is_enabled' => $enabled ? 1 : 0 ];
		\ElementsKit_Lite\Libs\Framework\Attr::instance()->utils->save_option( \ElementsKit_Lite\Modules\Megamenu\Init::$megamenu_settings_key, $data );

		return [
			'success' => true,
			'menu_id' => $menu_id,
			'enabled' => $enabled,
		];
	}

	/**
	 * Enable and configure the Mega Menu panel on a single nav-menu item, and
	 * optionally set the Elementor element tree that renders as the panel.
	 *
	 * @param array $input { @type int $menu_item_id, @type array $settings, @type array $elements }
	 * @return array|\WP_Error
	 */
	public function update_mega_menu_item( array $input ) {
		if ( ! current_user_can( 'manage_options' ) || ( is_multisite() && ! is_super_admin() ) ) {
			return new \WP_Error( 'ekit_mcp_forbidden', __( 'You are not allowed to edit mega menus.', 'elementskit-lite' ), [ 'status' => 403 ] );
		}

		if ( ! class_exists( '\ElementsKit_Lite\Modules\Megamenu\Init' ) ) {
			return new \WP_Error( 'ekit_mcp_megamenu_unavailable', __( 'The Mega Menu module is not available.', 'elementskit-lite' ), [ 'status' => 500 ] );
		}

		$menu_item_id = isset( $input['menu_item_id'] ) ? absint( $input['menu_item_id'] ) : 0;

		if ( ! $menu_item_id || ! get_post( $menu_item_id ) ) {
			return new \WP_Error( 'ekit_mcp_invalid_menu_item_id', __( 'A valid menu_item_id is required.', 'elementskit-lite' ), [ 'status' => 400 ] );
		}

		$provided = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : [];
		$settings = $this->sanitize_menuitem_settings( $provided, $menu_item_id );

		update_post_meta(
			$menu_item_id,
			\ElementsKit_Lite\Modules\Megamenu\Init::$menuitem_settings_key,
			wp_json_encode( $settings, JSON_UNESCAPED_UNICODE )
		);

		$response = [
			'success'      => true,
			'menu_item_id' => $menu_item_id,
		];

		// The panel is rendered from the elementskit_content post
		// "dynamic-content-megamenu-menuitem{id}" (see megamenu/walker-nav-menu.php),
		// NOT from _elementor_data on the nav menu item — write content there.
		if ( ! empty( $input['elements'] ) && is_array( $input['elements'] ) ) {
			$content_post_id = $this->resolve_megamenu_content_post( $menu_item_id );

			if ( is_wp_error( $content_post_id ) ) {
				return $content_post_id;
			}

			$saved = $this->save_megamenu_content( $content_post_id, $input['elements'] );

			if ( is_wp_error( $saved ) ) {
				return $saved;
			}

			$response['content_post_id'] = $content_post_id;
		}

		return $response;
	}

	/**
	 * One-shot mega menu creation from a prompt: create (or reuse) a nav menu, add
	 * a top-level item, switch Mega Menu on for the menu, and enable + configure
	 * the mega menu panel on that item (optionally seeded with an Elementor tree).
	 *
	 * Composes create/enable/update helpers so no logic is duplicated.
	 *
	 * @param array $input {
	 *     @type string $menu_name  Name for a new nav menu (used when menu_id is absent).
	 *     @type int    $menu_id    Existing nav menu term id to add the item to. Optional.
	 *     @type string $item_title Label of the top-level item that opens the panel. Default "Mega Menu".
	 *     @type string $item_url   Link for the item. Default "#".
	 *     @type array  $settings   Mega menu item settings (menu_icon, menu_badge_text, megamenu_width_type, ...).
	 *     @type array  $elements   Optional Elementor element tree for the panel content.
	 * }
	 * @return array|\WP_Error
	 */
	public function create_mega_menu( array $input ) {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_theme_options' ) ) {
			return new \WP_Error( 'ekit_mcp_forbidden', __( 'You are not allowed to create mega menus.', 'elementskit-lite' ), [ 'status' => 403 ] );
		}

		if ( ! class_exists( '\ElementsKit_Lite\Modules\Megamenu\Init' ) ) {
			return new \WP_Error( 'ekit_mcp_megamenu_unavailable', __( 'The Mega Menu module is not available.', 'elementskit-lite' ), [ 'status' => 500 ] );
		}

		// 1. Resolve the nav menu: reuse an existing one, or create a new menu.
		$menu_id     = isset( $input['menu_id'] ) ? absint( $input['menu_id'] ) : 0;
		$created_menu = false;

		if ( $menu_id && is_nav_menu( $menu_id ) ) {
			$menu = wp_get_nav_menu_object( $menu_id );
		} else {
			$menu_name = isset( $input['menu_name'] ) && '' !== trim( (string) $input['menu_name'] )
				? sanitize_text_field( $input['menu_name'] )
				: 'ElementsKit Mega Menu';

			// Avoid a duplicate-name collision.
			if ( wp_get_nav_menu_object( $menu_name ) ) {
				$menu_name .= ' ' . wp_rand( 100, 999 );
			}

			$menu_id = wp_create_nav_menu( $menu_name );

			if ( is_wp_error( $menu_id ) ) {
				return $menu_id;
			}

			$menu_id      = (int) $menu_id;
			$created_menu = true;
			$menu         = wp_get_nav_menu_object( $menu_id );
		}

		// 2. Add the top-level item that will carry the mega menu panel.
		$item_title = isset( $input['item_title'] ) && '' !== trim( (string) $input['item_title'] )
			? sanitize_text_field( $input['item_title'] )
			: 'Mega Menu';
		$item_url = isset( $input['item_url'] ) && '' !== trim( (string) $input['item_url'] )
			? esc_url_raw( $input['item_url'] )
			: '#';

		$menu_item_id = wp_update_nav_menu_item(
			$menu_id,
			0,
			[
				'menu-item-title'  => $item_title,
				'menu-item-url'    => $item_url,
				'menu-item-type'   => 'custom',
				'menu-item-status' => 'publish',
			]
		);

		if ( is_wp_error( $menu_item_id ) ) {
			return $menu_item_id;
		}

		$menu_item_id = (int) $menu_item_id;

		// 3. Switch Mega Menu on for the whole menu location.
		$enabled = $this->enable_mega_menu( [ 'menu_id' => $menu_id, 'enabled' => true ] );

		if ( is_wp_error( $enabled ) ) {
			return $enabled;
		}

		// 4. Resolve the elementskit_content post that actually renders the panel.
		$content_post_id = $this->resolve_megamenu_content_post( $menu_item_id );

		if ( is_wp_error( $content_post_id ) ) {
			return $content_post_id;
		}

		// 5. Resolve panel content on the backend so the mega menu is never blank:
		// a template-library section (defaulting to "Mega Menu - Mens Fashion"),
		// an explicit element tree, then a minimal default section as a fallback.
		$panel_elements = [];
		$content_source = 'default';
		$import_error   = null;
		$has_elements   = isset( $input['elements'] ) && is_array( $input['elements'] ) && ! empty( $input['elements'] );
		$template_id    = isset( $input['template_id'] ) && '' !== (string) $input['template_id'] ? $input['template_id'] : '';

		if ( '' === $template_id && ! $has_elements ) {
			$template_id = self::DEFAULT_MEGA_MENU_TEMPLATE_ID;
		}

		if ( '' !== $template_id ) {
			$content = $this->get_library_content( $template_id, $content_post_id );

			if ( is_wp_error( $content ) ) {
				$import_error = $content->get_error_message();
			} else {
				$panel_elements = $content;
				$content_source = 'library';
			}
		} elseif ( $has_elements ) {
			$panel_elements = $input['elements'];
			$content_source = 'elements';
		}

		if ( empty( $panel_elements ) ) {
			$panel_elements = $this->default_mega_menu_panel( $item_title );
			$content_source = 'default';
		}

		// 6. Write the panel content to the content post.
		$saved = $this->save_megamenu_content( $content_post_id, $panel_elements );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		// 7. Enable + configure the panel settings on the nav menu item.
		$provided_settings                   = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : [];
		$provided_settings['menu_enable']    = 1;
		$provided_settings['menu_has_child'] = isset( $provided_settings['menu_has_child'] ) ? $provided_settings['menu_has_child'] : '1';

		$panel = $this->update_mega_menu_item(
			[
				'menu_item_id' => $menu_item_id,
				'settings'     => $provided_settings,
			]
		);

		if ( is_wp_error( $panel ) ) {
			return $panel;
		}

		$response = [
			'success'         => true,
			'menu_id'         => $menu_id,
			'menu_name'       => $menu ? $menu->name : '',
			'menu_created'    => $created_menu,
			'menu_item_id'    => $menu_item_id,
			'content_post_id' => $content_post_id,
			'item_title'      => $item_title,
			'content_source'  => $content_source,
			'panel_filled'    => true,
			'manage_url'      => admin_url( 'nav-menus.php?action=edit&menu=' . $menu_id ),
			'note'            => sprintf(
				/* translators: 1: menu id, 2: content source */
				__( 'Mega Menu is enabled and the panel content was written on the backend (source: %2$s) to its elementskit_content post, so it renders instead of previewing blank — no editor step needed. Assign menu %1$d to a theme location or use the ElementsKit Nav Menu widget for it to appear on the site.', 'elementskit-lite' ),
				$menu_id,
				$content_source
			),
		];

		if ( '' !== $template_id ) {
			$response['template_id'] = $template_id;
		}
		if ( null !== $import_error ) {
			$response['import_error'] = $import_error;
		}

		return $response;
	}

	/**
	 * List ready-made templates from the ElementsKit remote Template Library.
	 *
	 * @param array $input Unused (the remote API returns the full catalogue).
	 * @return array|\WP_Error
	 */
	public function list_library_templates( array $input = [] ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'ekit_mcp_forbidden', __( 'You are not allowed to browse the template library.', 'elementskit-lite' ), [ 'status' => 403 ] );
		}

		if ( ! method_exists( '\ElementsKit_Lite', 'api_url' ) || ! method_exists( '\ElementsKit_Lite', 'license_data' ) ) {
			return new \WP_Error( 'ekit_mcp_library_unavailable', __( 'The template library is not available.', 'elementskit-lite' ), [ 'status' => 500 ] );
		}

		$query = array_merge( (array) \ElementsKit_Lite::license_data(), [ 'action' => 'get_layout_list' ] );

		$response = wp_remote_get(
			\ElementsKit_Lite::api_url() . 'layout-manager-api/?' . http_build_query( $query ),
			[
				'timeout' => 30,
				'headers' => [ 'Content-Type' => 'application/json' ],
			]
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'ekit_mcp_library_request_failed', $response->get_error_message(), [ 'status' => 502 ] );
		}

		$library = json_decode( wp_remote_retrieve_body( $response ), true );

		return [
			'success' => true,
			'library' => null === $library ? [] : $library,
		];
	}

	/**
	 * Import a ready-made template/section/page from the ElementsKit Template
	 * Library into an existing Elementor document.
	 *
	 * @param array $input { @type int $post_id, @type int|string $template_id }
	 * @return array|\WP_Error
	 */
	public function import_library_template( array $input ) {
		$document = $this->resolve_editable_document( $input );

		if ( is_wp_error( $document ) ) {
			return $document;
		}

		$post_id     = (int) $input['post_id'];
		$template_id = isset( $input['template_id'] ) ? $input['template_id'] : '';

		if ( empty( $template_id ) ) {
			return new \WP_Error( 'ekit_mcp_missing_template_id', __( 'A template_id is required.', 'elementskit-lite' ), [ 'status' => 400 ] );
		}

		$content = $this->get_library_content( $template_id, $post_id );

		if ( is_wp_error( $content ) ) {
			return $content;
		}

		// Library_Source::get_data already regenerates element ids for the target document.
		$elements = $document->get_elements_data();
		$root_ids = [];

		foreach ( $content as $node ) {
			if ( empty( $node['id'] ) ) {
				$node['id'] = $this->generate_id();
			}
			$elements[] = $node;
			$root_ids[] = $node['id'];
		}

		$saved = $this->save_document( $document, $elements );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$response                = $this->insert_response( $document, $post_id, $root_ids ? $root_ids[0] : '', $root_ids );
		$response['template_id'] = $template_id;

		return $response;
	}

	/* --------------------------------------------------------------------- *
	 * Internal helpers
	 * --------------------------------------------------------------------- */

	/**
	 * Fetch an ElementsKit Template Library template's element tree, with ids
	 * already regenerated for the target post. Shared by import_library_template
	 * and create_mega_menu.
	 *
	 * @param int|string $template_id
	 * @param int        $editor_post_id Target post the content is being imported into.
	 * @return array|\WP_Error The element tree, or an error.
	 */
	private function get_library_content( $template_id, $editor_post_id ) {
		if ( empty( $template_id ) ) {
			return new \WP_Error( 'ekit_mcp_missing_template_id', __( 'A template_id is required.', 'elementskit-lite' ), [ 'status' => 400 ] );
		}

		if ( ! class_exists( '\ElementsKit_Lite\Modules\Layout_Manager\Library_Source' ) ) {
			return new \WP_Error( 'ekit_mcp_library_unavailable', __( 'The template library is not available.', 'elementskit-lite' ), [ 'status' => 500 ] );
		}

		try {
			$source = new \ElementsKit_Lite\Modules\Layout_Manager\Library_Source();
			$data   = $source->get_data(
				[
					'template_id'    => $template_id,
					'editor_post_id' => (int) $editor_post_id,
				]
			);
		} catch ( \Exception $e ) {
			return new \WP_Error( 'ekit_mcp_import_failed', $e->getMessage(), [ 'status' => 502 ] );
		}

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$content = is_array( $data ) && ! empty( $data['content'] ) && is_array( $data['content'] ) ? $data['content'] : null;

		if ( null === $content ) {
			return new \WP_Error( 'ekit_mcp_import_empty', __( 'The library template returned no content.', 'elementskit-lite' ), [ 'status' => 502 ] );
		}

		return $content;
	}

	/**
	 * A minimal, valid Elementor element tree used to seed an otherwise-empty
	 * mega menu panel so it never previews blank.
	 *
	 * @param string $title
	 * @return array
	 */
	private function default_mega_menu_panel( $title ) {
		return [
			[
				'id'       => $this->generate_id(),
				'elType'   => 'container',
				'settings' => (object) [ 'content_width' => 'full' ],
				'elements' => [
					[
						'id'         => $this->generate_id(),
						'elType'     => 'widget',
						'widgetType' => 'heading',
						'settings'   => (object) [ 'title' => $title ],
						'elements'   => [],
					],
				],
			],
		];
	}

	/**
	 * Resolve (creating if needed) the elementskit_content post that holds and
	 * renders a mega menu item's panel. Mirrors ElementsKit's own dynamic-content
	 * flow (modules/dynamic-content/cpt-api.php) so the panel behaves identically
	 * to one built through the admin UI.
	 *
	 * @param int $menu_item_id
	 * @return int|\WP_Error elementskit_content post id.
	 */
	private function resolve_megamenu_content_post( $menu_item_id ) {
		$title = 'dynamic-content-megamenu-menuitem' . (int) $menu_item_id;

		if ( class_exists( '\ElementsKit_Lite\Utils' ) && method_exists( '\ElementsKit_Lite\Utils', 'get_page_by_title' ) ) {
			$existing = \ElementsKit_Lite\Utils::get_page_by_title( $title, 'elementskit_content' );
		} else {
			$existing = get_page_by_title( $title, OBJECT, 'elementskit_content' );
		}

		if ( $existing ) {
			return (int) $existing->ID;
		}

		$post_id = wp_insert_post(
			[
				'post_content' => '',
				'post_title'   => $title,
				'post_name'    => sanitize_title( $title ),
				'post_status'  => 'publish',
				'post_type'    => 'elementskit_content',
				'post_author'  => get_current_user_id(),
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, '_wp_page_template', 'elementor_canvas' );
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );

		return (int) $post_id;
	}

	/**
	 * Write an element tree as the content of a mega menu panel's
	 * elementskit_content post.
	 *
	 * @param int   $content_post_id
	 * @param array $elements
	 * @return true|\WP_Error
	 */
	private function save_megamenu_content( $content_post_id, array $elements ) {
		$elements = $this->regenerate_ids( $elements );

		if ( $this->elementor_ready() ) {
			$document = \Elementor\Plugin::instance()->documents->get( (int) $content_post_id );

			if ( $document ) {
				return $this->save_document( $document, $elements );
			}
		}

		update_post_meta( $content_post_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $content_post_id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );

		return true;
	}

	/**
	 * Mirror of Megamenu_Api::sanitize_menuitem_settings (modules/megamenu/api.php).
	 *
	 * @param array $settings
	 * @param int   $menu_item_id
	 * @return array
	 */
	private function sanitize_menuitem_settings( array $settings, $menu_item_id ) {
		return [
			'menu_id'                         => (int) $menu_item_id,
			'menu_has_child'                  => isset( $settings['menu_has_child'] ) ? sanitize_text_field( $settings['menu_has_child'] ) : '',
			'menu_enable'                     => isset( $settings['menu_enable'] ) ? ( empty( $settings['menu_enable'] ) ? 0 : 1 ) : 1,
			'menu_icon'                       => isset( $settings['menu_icon'] ) ? $this->sanitize_icon_class( $settings['menu_icon'] ) : '',
			'menu_icon_color'                 => isset( $settings['menu_icon_color'] ) ? $this->sanitize_color( $settings['menu_icon_color'] ) : '',
			'menu_badge_text'                 => isset( $settings['menu_badge_text'] ) ? sanitize_text_field( $settings['menu_badge_text'] ) : '',
			'menu_badge_color'                => isset( $settings['menu_badge_color'] ) ? $this->sanitize_color( $settings['menu_badge_color'] ) : '',
			'menu_badge_background'           => isset( $settings['menu_badge_background'] ) ? $this->sanitize_color( $settings['menu_badge_background'] ) : '',
			'mobile_submenu_content_type'     => isset( $settings['mobile_submenu_content_type'] ) ? sanitize_key( $settings['mobile_submenu_content_type'] ) : 'builder_content',
			'vertical_megamenu_position_type' => isset( $settings['vertical_megamenu_position_type'] ) ? sanitize_key( $settings['vertical_megamenu_position_type'] ) : 'relative_position',
			'vertical_menu_width'             => isset( $settings['vertical_menu_width'] ) ? sanitize_text_field( $settings['vertical_menu_width'] ) : '',
			'megamenu_width_type'             => isset( $settings['megamenu_width_type'] ) ? sanitize_key( $settings['megamenu_width_type'] ) : 'default_width',
			'megamenu_ajax_load'              => isset( $settings['megamenu_ajax_load'] ) && 'yes' === $settings['megamenu_ajax_load'] ? 'yes' : 'no',
		];
	}

	private function sanitize_icon_class( $icon_class ) {
		$classes = preg_split( '/\s+/', sanitize_text_field( (string) $icon_class ) );
		$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );

		return implode( ' ', $classes );
	}

	private function sanitize_color( $color ) {
		$color = sanitize_text_field( (string) $color );

		return sanitize_hex_color( $color ) ? $color : '';
	}

	/**
	 * Validate post_id + permissions and return the editable Elementor document.
	 *
	 * @param array $input
	 * @return \Elementor\Core\Base\Document|\WP_Error
	 */
	private function resolve_editable_document( array $input ) {
		if ( ! $this->elementor_ready() ) {
			return new \WP_Error( 'ekit_mcp_elementor_missing', __( 'Elementor is not active.', 'elementskit-lite' ), [ 'status' => 400 ] );
		}

		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;

		if ( $post_id <= 0 ) {
			return new \WP_Error( 'ekit_mcp_missing_post_id', __( 'A valid post_id is required.', 'elementskit-lite' ), [ 'status' => 400 ] );
		}

		if ( ! get_post( $post_id ) ) {
			return new \WP_Error( 'ekit_mcp_post_not_found', __( 'The target post was not found.', 'elementskit-lite' ), [ 'status' => 404 ] );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'ekit_mcp_forbidden', __( 'You are not allowed to edit this post.', 'elementskit-lite' ), [ 'status' => 403 ] );
		}

		$document = \Elementor\Plugin::instance()->documents->get( $post_id );

		if ( ! $document ) {
			return new \WP_Error( 'ekit_mcp_no_document', __( 'The target post is not an Elementor document.', 'elementskit-lite' ), [ 'status' => 400 ] );
		}

		return $document;
	}

	/**
	 * Build a normalised success payload after a mutation.
	 *
	 * @param \Elementor\Core\Base\Document $document
	 * @param int                          $post_id
	 * @param string                       $element_id
	 * @param string[]                     $root_ids
	 * @return array
	 */
	private function insert_response( $document, $post_id, $element_id, array $root_ids ) {
		$preview_url = '';

		try {
			$preview_url = $document->get_preview_url();
		} catch ( \Exception $e ) {
			$preview_url = get_permalink( $post_id );
		}

		$version = (string) get_post_meta( $post_id, '_elementor_version', true );

		if ( '' === $version && defined( 'ELEMENTOR_VERSION' ) ) {
			$version = ELEMENTOR_VERSION;
		}

		return [
			'success'           => true,
			'post_id'           => (int) $post_id,
			'element_id'        => (string) $element_id,
			'inserted_root_ids' => array_values( $root_ids ),
			'preview_url'       => (string) $preview_url,
			'version'           => $version,
		];
	}

	/**
	 * Persist modified element data back onto the document.
	 *
	 * @param \Elementor\Core\Base\Document $document
	 * @param array                         $elements
	 * @return true|\WP_Error
	 */
	private function save_document( $document, array $elements ) {
		try {
			$document->save( [ 'elements' => $elements ] );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'ekit_mcp_save_failed', $e->getMessage(), [ 'status' => 500 ] );
		}

		return true;
	}

	/**
	 * @param string $widget_type
	 * @param array  $settings
	 * @return array
	 */
	private function build_widget_element( $widget_type, array $settings ) {
		return [
			'id'         => $this->generate_id(),
			'elType'     => 'widget',
			'settings'   => (object) $settings,
			'elements'   => [],
			'widgetType' => $widget_type,
		];
	}

	/**
	 * @param array $children
	 * @return array
	 */
	private function build_container_element( array $children ) {
		return [
			'id'       => $this->generate_id(),
			'elType'   => 'container',
			'settings' => (object) [],
			'elements' => array_values( $children ),
		];
	}

	/**
	 * Recursively append $child to the element whose id matches $parent_id.
	 *
	 * @param array  $elements  Passed by reference — mutated in place.
	 * @param string $parent_id
	 * @param array  $child
	 * @return bool True when the parent was found and the child appended.
	 */
	private function append_to_parent( array &$elements, $parent_id, array $child ) {
		foreach ( $elements as &$node ) {
			if ( isset( $node['id'] ) && $node['id'] === $parent_id ) {
				if ( ! isset( $node['elements'] ) || ! is_array( $node['elements'] ) ) {
					$node['elements'] = [];
				}
				$node['elements'][] = $child;
				return true;
			}

			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				if ( $this->append_to_parent( $node['elements'], $parent_id, $child ) ) {
					return true;
				}
			}
		}
		unset( $node );

		return false;
	}

	/**
	 * Deep-copy an element tree, assigning every node a fresh id.
	 *
	 * @param array $elements
	 * @return array
	 */
	private function regenerate_ids( array $elements ) {
		return array_map(
			function ( $node ) {
				if ( ! is_array( $node ) ) {
					return $node;
				}

				$node['id'] = $this->generate_id();

				if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
					$node['elements'] = $this->regenerate_ids( $node['elements'] );
				}

				return $node;
			},
			$elements
		);
	}

	/**
	 * @return string 7-char Elementor-style element id.
	 */
	private function generate_id() {
		if ( class_exists( '\Elementor\Utils' ) && method_exists( '\Elementor\Utils', 'generate_random_string' ) ) {
			return \Elementor\Utils::generate_random_string();
		}

		return substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 7 );
	}

	/**
	 * @param string $type
	 * @return bool
	 */
	private function is_elementskit_widget( $type ) {
		foreach ( self::WIDGET_PREFIXES as $prefix ) {
			if ( 0 === strpos( $type, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $type
	 * @return bool
	 */
	private function widget_is_registered( $type ) {
		if ( ! $this->elementor_ready() ) {
			return false;
		}

		return (bool) \Elementor\Plugin::instance()->widgets_manager->get_widget_types( $type );
	}

	/**
	 * @return bool
	 */
	private function elementor_ready() {
		return did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' );
	}
}

if ( ! class_exists( '\ElementsKit_MCP_Service' ) ) {
	class_alias( __NAMESPACE__ . '\\Service', 'ElementsKit_MCP_Service' );
}
