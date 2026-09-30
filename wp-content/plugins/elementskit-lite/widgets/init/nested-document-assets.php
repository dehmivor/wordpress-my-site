<?php
namespace ElementsKit_Lite\Widgets\Init;

use ElementsKit_Lite\Modules\Header_Footer\Activator;
use ElementsKit_Lite\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Preloads assets for Elementor documents rendered by ElementsKit.
 */
class Nested_Document_Assets {

	/**
	 * Settings keys that hold the ID of an Elementor Library template ElementsKit
	 * renders inline. Unlike the Widget Area keys these resolve to a document ID
	 * directly, so they are matched by key name to keep unrelated numeric settings
	 * (post IDs in a query, attachment IDs, sizes) out of the lookup.
	 *
	 * @since 4.0.4
	 */
	const TEMPLATE_ID_SETTINGS = array(
		'ekit_pc_message_template',
		'ekit_pc_elementor_template',
		'elementor_templates',
	);

	/**
	 * Widget Area documents, keyed by exact mega-menu title match.
	 * @var array|null
	 */
	private $mega_menu_documents;

	/**
	 * Widget Area documents whose titles use the "dynamic-content-widget-{key}-*" prefix form.
	 * Each entry is [ 'suffix' => string after the shared prefix, 'id' => int ].
	 * @var array|null
	 */
	private $widget_area_candidates;

	/**
	 * Per-request cache of resolved widget style/script dependencies, keyed by widget type and settings.
	 * Avoids rebuilding equivalent widget instances that repeat on a page.
	 * @var array
	 */
	private $widget_dependency_cache = array();

	/**
	 * Per-request cache of nav menu items, keyed by menu id/slug/object.
	 * @var array
	 */
	private $nav_menu_items_cache = array();

	/**
	 * Whether Elementor already ran its post-styles pass for this request.
	 * @var bool
	 */
	private static $post_styles_flushed = false;

	/**
	 * Documents announced to Elementor's style pipeline this request, keyed by ID.
	 * @var array
	 */
	private static $declared_documents = array();

	/**
	 * Record that Elementor fired `elementor/frontend/after_enqueue_post_styles` itself.
	 * @since 4.0.4
	 * @return void
	 */
	public static function mark_post_styles_flushed() {
		self::$post_styles_flushed = true;
	}

	/**
	 * Run Elementor's post-styles pass when Elementor itself will not.
	 *
	 * `Elementor\Frontend::enqueue_styles()` - the only place that fires
	 * `elementor/frontend/after_enqueue_post_styles`, and therefore the only place that
	 * writes the Atomic (V4) style files - is hooked only when the queried post is a
	 * singular document built with Elementor. A Header/Footer template or a Widget Area
	 * shown on a blog archive, a shop page, or a classic post never reaches that pass at
	 * all, so its atomic elements lose even the base `display: flex` / `display: grid`.
	 * Fire the pass ourselves in that case, once, and only if we announced something.
	 *
	 * @since 4.0.4
	 * @return void
	 */
	public static function maybe_flush_post_styles() {
		if ( self::$post_styles_flushed || empty( self::$declared_documents ) ) {
			return;
		}

		if ( ! class_exists( '\\Elementor\\Plugin' ) || \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return;
		}

		self::$post_styles_flushed = true;

		do_action( 'elementor/frontend/after_enqueue_post_styles' );
	}

	/**
	 * Announce a document ElementsKit renders to Elementor's style pipeline.
	 *
	 * Elementor builds the CSS file holding an Atomic (V4) element's own styles -
	 * `display`, `flex-direction`, `grid-template-columns`, `gap` and the rest - only for
	 * documents announced through `elementor/post/render`, and it announces just the
	 * queried post. Documents ElementsKit renders itself (Widget Area content inside the
	 * advanced widgets, Mega Menu panels, Header/Footer templates) were never announced,
	 * so on the front end their `local-{id}-{context}-{breakpoint}.css` was never
	 * generated and every atomic flex/grid container fell back to the base `display`.
	 * The editor looked correct because there the nested document is the edited document,
	 * which Elementor announces as usual.
	 *
	 * Post_CSS (`render_elementor_content_css()`) does not cover this: it only holds the
	 * legacy V3 per-element CSS.
	 *
	 * @since 4.0.4
	 * @param int $document_id Elementor document ID.
	 * @return void
	 */
	private function declare_document_rendered( $document_id ) {
		if ( isset( self::$declared_documents[ $document_id ] ) ) {
			return;
		}

		self::$declared_documents[ $document_id ] = true;

		do_action( 'elementor/post/render', $document_id );
	}

	/**
	 * Enqueue dependencies before wp_head prints styles.
	 * @since 4.0.3
	 * @return void
	 */
	public function enqueue() {
		if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
			return;
		}

		$document_ids = array_filter( array( get_queried_object_id() ) );

		if ( class_exists( Activator::class ) ) {
			$document_ids = array_merge( $document_ids, (array) Activator::template_ids() );
		}

		$checked = array();
		$queue   = array_values( array_unique( array_map( 'absint', array_filter( $document_ids ) ) ) );

		while ( ! empty( $queue ) ) {
			$document_id = array_shift( $queue );
			if ( isset( $checked[ $document_id ] ) ) {
				continue;
			}

			$checked[ $document_id ] = true;
			$elements                = $this->get_document_elements( $document_id );
			if ( empty( $elements ) ) {
				continue;
			}

			Utils::render_elementor_content_css( $document_id );
			$this->declare_document_rendered( $document_id );
			$widget_area_keys = array();
			$template_ids     = array();
			$this->enqueue_element_dependencies( $elements, $widget_area_keys, $template_ids );

			$nested_ids = array_merge( $this->get_widget_area_document_ids( $widget_area_keys ), $template_ids );

			foreach ( $nested_ids as $nested_id ) {
				if ( ! isset( $checked[ $nested_id ] ) ) {
					$queue[] = $nested_id;
				}
			}
		}
	}

	/**
	 * Get the saved Elementor element tree for a document.
	 * @since 4.0.3
	 * @param int $document_id Elementor document ID.
	 * @return array
	 */
	private function get_document_elements( $document_id ) {
		$data = get_post_meta( $document_id, '_elementor_data', true );
		if ( is_string( $data ) ) {
			$data = json_decode( $data, true );
		}

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Walk an Elementor tree and enqueue every registered widget dependency.
	 * @since 4.0.3
	 * @param array $elements         Elementor elements.
	 * @param array $widget_area_keys Dynamic-content lookup keys.
	 * @param array $template_ids     Elementor Library document IDs rendered inline.
	 * @return void
	 */
	private function enqueue_element_dependencies( $elements, &$widget_area_keys, &$template_ids = array() ) {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( ! empty( $element['id'] ) ) {
				$widget_area_keys[] = (string) $element['id'];
			}

			if ( ! empty( $element['widgetType'] ) ) {
				$this->enqueue_widget_dependencies( $element );
			}

			if ( ! empty( $element['settings']['elementskit_nav_menu'] ) ) {
				foreach ( $this->get_nav_menu_items( $element['settings']['elementskit_nav_menu'] ) as $menu_item ) {
					if ( ! is_object( $menu_item ) || empty( $menu_item->ID ) ) {
						continue;
					}

					$widget_area_keys[] = 'megamenu-menuitem' . absint( $menu_item->ID );
				}
			}

			if ( ! empty( $element['settings'] ) ) {
				array_walk_recursive(
					$element['settings'],
					static function ( $value ) use ( &$widget_area_keys ) {
						if ( is_string( $value ) && preg_match( '/^([A-Za-z0-9_-]+)(?:\\*\\*\\*|$)/', $value, $matches ) ) {
							$widget_area_keys[] = $matches[1];
						}
					}
				);

				$this->collect_template_ids( $element['settings'], $template_ids );
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$this->enqueue_element_dependencies( $element['elements'], $widget_area_keys, $template_ids );
			}
		}
	}

	/**
	 * Collect Elementor Library document IDs from a widget's settings.
	 *
	 * Recurses so repeater rows are covered - Stacked Cards keeps one template per
	 * card - and matches on the setting key rather than the value, so an unrelated
	 * numeric setting can never pull a stranger's stylesheet onto the page.
	 *
	 * @since 4.0.4
	 * @param array $settings     Saved widget settings, or a nested part of them.
	 * @param array $template_ids Collected document IDs.
	 * @return void
	 */
	private function collect_template_ids( $settings, &$template_ids ) {
		foreach ( $settings as $key => $value ) {
			if ( is_array( $value ) ) {
				$this->collect_template_ids( $value, $template_ids );
				continue;
			}

			if ( in_array( $key, self::TEMPLATE_ID_SETTINGS, true ) && absint( $value ) ) {
				$template_ids[] = absint( $value );
			}
		}
	}

	/**
	 * Resolve and enqueue a widget instance's style/script dependencies, caching
	 * the lookup for widgets with the same type and settings.
	 * @since 4.0.3
	 * @param array $element Saved Elementor widget data.
	 * @return void
	 */
	private function enqueue_widget_dependencies( $element) {
		//empty check for widgetType, because some widgets are not registered in Elementor and they don't have widgetType. So we need to check if widgetType is empty or not.
		if ( empty( $element['widgetType'] ) ) {
			return;
		}

		$widget_type = $element['widgetType'];
		$settings    = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
		$cache_key   = $widget_type . ':' . md5( wp_json_encode( $settings ) );

		if ( ! isset( $this->widget_dependency_cache[ $cache_key ] ) ) {
			$dependencies = array(
				'styles'  => array(),
				'scripts' => array(),
			);

			/*
			* Elementor's registered widget objects are prototypes and may not
			* contain initialized settings. Create a real instance from the saved
			* Elementor element data before resolving conditional dependencies.
			*/
			$element['elType']   = 'widget';
			$element['settings'] = $settings;
			$widget              = \Elementor\Plugin::$instance->elements_manager->create_element_instance( $element );
			if ( $widget instanceof \Elementor\Widget_Base ) {

				$dependencies['styles']  = (array) $widget->get_style_depends();
				$dependencies['scripts'] = (array) $widget->get_script_depends();
			}

			$this->widget_dependency_cache[ $cache_key ] = $dependencies;
		}

		foreach ( $this->widget_dependency_cache[ $cache_key ]['styles'] as $handle ) {
			if ( ! empty( $handle ) ) {
				wp_enqueue_style( $handle );
			}
		}

		foreach ( $this->widget_dependency_cache[ $cache_key ]['scripts'] as $handle ) {
			if ( ! empty( $handle ) ) {
				wp_enqueue_script( $handle );
			}
		}
	}

	/**
	 * Get nav menu items for a menu, caching per menu so the same menu
	 * referenced multiple times in a tree is only fetched once.
	 * @since 4.0.3
	 * @param mixed $menu Menu ID, slug, or object.
	 * @return array
	 */
	private function get_nav_menu_items( $menu ) {
		$cache_key = is_scalar( $menu ) ? (string) $menu : md5( wp_json_encode( $menu ) );

		if ( ! isset( $this->nav_menu_items_cache[ $cache_key ] ) ) {
			$menu_items = wp_get_nav_menu_items( $menu );
			$this->nav_menu_items_cache[ $cache_key ] = is_array( $menu_items )
				? array_filter( $menu_items, 'is_object' )
				: array();
		}
		return $this->nav_menu_items_cache[ $cache_key ];
	}
	
	/**
	 * Resolve saved Widget Area and Mega Menu documents linked by the element tree.
	 * @since 4.0.3
	 * @param array $keys Widget IDs and dynamic-content keys.
	 * @return int[]
	 */
	private function get_widget_area_document_ids( $keys ) {
		$keys = array_unique( array_filter( array_map( 'sanitize_key', $keys ) ) );
		if ( empty( $keys ) ) {
			return array();
		}

		$this->load_widget_area_documents();

		$ids = array();

		// Mega menu titles are exact matches, so this is an O(1) lookup per key
		// instead of a linear scan over every candidate document.
		foreach ( $keys as $key ) {
			if ( isset( $this->mega_menu_documents[ 'dynamic-content-' . $key ] ) ) {
				$ids[] = $this->mega_menu_documents[ 'dynamic-content-' . $key ];
			}
		}

		// Widget area titles carry an arbitrary suffix after the key, so a prefix
		// check is still required, but it now only scans the smaller, pre-filtered
		// widget-area candidate list rather than every elementskit_content post.
		foreach ( $this->widget_area_candidates as $candidate ) {
			foreach ( $keys as $key ) {
				if ( 0 === strpos( $candidate['suffix'], $key . '-' ) ) {
					$ids[] = $candidate['id'];
					break;
				}
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Load and index elementskit_content documents once per request.
	 *
	 * Skips found-row counting and meta/term cache priming (unneeded for this
	 * lookup) and splits results into an exact-match hash map for mega menus
	 * and a smaller candidate list for widget-area prefix matching.
	 * @since 4.0.3
	 * @return void
	 */
	private function load_widget_area_documents() {
		if ( null !== $this->mega_menu_documents ) {
			return;
		}

		$this->mega_menu_documents    = array();
		$this->widget_area_candidates = array();

		$rows = get_posts(
			array(
				'post_type'              => 'elementskit_content',
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$widget_prefix        = 'dynamic-content-widget-';
		$widget_prefix_length = strlen( $widget_prefix );

		foreach ( (array) $rows as $row ) {
			$id    = (int) $row->ID;
			$title = (string) $row->post_title;

			if ( 0 === strpos( $title, $widget_prefix ) ) {
				$this->widget_area_candidates[] = array(
					'suffix' => substr( $title, $widget_prefix_length ),
					'id'     => $id,
				);
			} else {
				$this->mega_menu_documents[ $title ] = $id;
			}
		}
	}
}
