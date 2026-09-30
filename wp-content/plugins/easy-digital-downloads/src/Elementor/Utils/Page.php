<?php
/**
 * Elementor Page Utility
 *
 * @package     EDD\Elementor\Utils
 * @copyright   Copyright (c) 2025, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.0
 */

namespace EDD\Elementor\Utils;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use Elementor\Plugin as ElementorPlugin;

/**
 * Page utility class.
 *
 * @since 3.6.0
 */
class Page {

	/**
	 * The post meta key holding Elementor's serialized element tree.
	 *
	 * @since 3.7.1
	 * @var string
	 */
	private const DATA_META_KEY = '_elementor_data';

	/**
	 * Decoded element data for this request, keyed by post ID.
	 *
	 * @since 3.7.1
	 * @var array
	 */
	private static $page_data = array();

	/**
	 * Get the page data.
	 *
	 * @since 3.6.0
	 *
	 * @param int|null $current_page Current page ID.
	 * @return array|false
	 */
	public static function get_page_data( $current_page = null ) {

		if ( is_null( $current_page ) ) {
			$current_page = self::get_id();
		}

		// Resolve the post before keying the memo: the editor preview reaches this class with no
		// id of its own, and get_post( 0 ) answers from the global post.
		$post = get_post( $current_page );
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		$post_id = (int) $post->ID;
		$raw     = self::get_raw_data( $post_id );

		// Reuse the decode only while the tree it came from is byte-identical. PHP compares the
		// unchanged string by pointer, so this costs nothing on the common path and cannot go stale.
		if ( isset( self::$page_data[ $post_id ] ) && self::$page_data[ $post_id ]['raw'] === $raw ) {
			return self::$page_data[ $post_id ]['elements'];
		}

		self::$page_data[ $post_id ] = array(
			'raw'      => $raw,
			'elements' => self::read_page_data( $post ),
		);

		return self::$page_data[ $post_id ]['elements'];
	}

	/**
	 * Check if the page has a widget.
	 *
	 * @since 3.6.0
	 *
	 * @param string $widget_type Widget type.
	 * @param array  $elements Page elements data.
	 * @return bool
	 */
	public static function has_widget( string $widget_type, $elements = null ): bool {
		return ! empty( self::get_widget_data( $widget_type, $elements ) );
	}

	/**
	 * Get the widget data.
	 *
	 * @since 3.6.0
	 * @since 3.7.0 Added elType match for container elements (e.g. edd-checkout-box).
	 *
	 * @param string $widget_type Widget type (widgetType) or container element type (elType).
	 * @param array  $elements    Page elements data.
	 * @param int    $occurrence  Occurrence number.
	 * @return array
	 */
	public static function get_widget_data( string $widget_type, $elements = null, int $occurrence = 1 ): array {
		if ( is_null( $elements ) ) {
			$post = get_post( self::get_id() );
			if ( ! $post instanceof \WP_Post ) {
				return array();
			}

			if ( ! self::data_may_contain( (int) $post->ID, $widget_type ) ) {
				return array();
			}

			$elements = self::get_page_data( $post->ID );
		}

		if ( ! is_array( $elements ) ) {
			return array();
		}

		$found = 0;
		foreach ( $elements as $element ) {
			// Match widget by widgetType, or a container element by elType.
			$matches_widget    = isset( $element['widgetType'] ) && $widget_type === $element['widgetType'];
			$matches_container = isset( $element['elType'] ) && $widget_type === $element['elType'];

			if ( $matches_widget || $matches_container ) {
				++$found;
				if ( $found === $occurrence ) {
					return $element;
				}
			}

			if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$data = self::get_widget_data( $widget_type, $element['elements'] );
				if ( ! empty( $data ) ) {
					return $data;
				}
			}
		}
		return array();
	}

	/**
	 * Check if the page is in edit mode.
	 *
	 * @since 3.6.0
	 *
	 * @return bool
	 */
	public static function is_edit_mode(): bool {
		$editor = ElementorPlugin::$instance->editor ?? null;
		if ( $editor && $editor->is_edit_mode() ) {
			return true;
		}

		// is_preview_mode() validates the ?elementor-preview=<id> param, User::is_current_user_can_edit() and a post-id match, so a forged URL from a user without edit rights returns false.
		$preview = ElementorPlugin::$instance->preview ?? null;
		if ( $preview && $preview->is_preview_mode() ) {
			return true;
		}

		// The edit_posts capability check keeps an unauthenticated ajax request from forging edit mode through the referer.
		if ( edd_doing_ajax() && current_user_can( 'edit_posts' ) && false !== strpos( (string) wp_get_referer(), 'elementor-preview' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if Elementor's Container experiment is active.
	 *
	 * The composable checkout (edd-checkout-box container and its section
	 * widgets) requires Elementor containers. When the experiment is
	 * unreachable we default to true, because containers are the norm on
	 * current Elementor installs and the composable path is the default.
	 *
	 * @since 3.7.0
	 *
	 * @return bool
	 */
	public static function is_container_active(): bool {
		if ( is_null( ElementorPlugin::$instance ) || empty( ElementorPlugin::$instance->experiments ) ) {
			return true;
		}

		return ElementorPlugin::$instance->experiments->is_feature_active( 'container' );
	}

	/**
	 * Clear the static cache.
	 *
	 * Mirrors EDD\Admin\Utils\Page::clear_cache(), so a test which swaps the document without
	 * touching _elementor_data does not read the previous test's tree.
	 *
	 * @since 3.7.1
	 * @return void
	 */
	public static function clear_cache() {
		self::$page_data = array();
	}

	/**
	 * Check whether a post's stored element data could contain a given type.
	 *
	 * A substring test on the raw meta, so a false positive falls through to the exact
	 * tree match below while a miss skips the decode and the document lookup entirely.
	 *
	 * @since 3.7.1
	 *
	 * @param int    $post_id     A resolved post ID.
	 * @param string $widget_type Widget type (widgetType) or container element type (elType).
	 * @return bool
	 */
	private static function data_may_contain( int $post_id, string $widget_type ): bool {
		$data = self::get_raw_data( $post_id );

		// Anything but a string cannot be tested, so defer to the decode rather than guess.
		if ( ! is_string( $data ) ) {
			return ! empty( $data );
		}

		return false !== strpos( $data, $widget_type );
	}

	/**
	 * Get a post's raw, still-encoded element data.
	 *
	 * @since 3.7.1
	 *
	 * @param int $post_id Post ID.
	 * @return mixed The stored meta value, or an empty string for an unresolved post.
	 */
	private static function get_raw_data( int $post_id ) {
		if ( $post_id <= 0 ) {
			return '';
		}

		return get_post_meta( $post_id, self::DATA_META_KEY, true );
	}

	/**
	 * Read a post's element data from Elementor.
	 *
	 * @since 3.7.1
	 *
	 * @param \WP_Post $post The resolved post.
	 * @return array|false
	 */
	private static function read_page_data( \WP_Post $post ) {
		if ( is_null( \Elementor\Plugin::$instance ) ) {
			return false;
		}

		$document = \Elementor\Plugin::$instance->documents->get( $post->ID );
		if ( ! $document || ! $document->is_built_with_elementor() ) {
			return false;
		}

		return $document->get_elements_data();
	}

	/**
	 * Get the current page ID.
	 *
	 * @since 3.6.0
	 * @since 3.7.0 Added a singular queried-post fallback so suppression engages on a normal front-end pageview.
	 * @return int The page ID, or 0 if not found.
	 */
	private static function get_id(): int {
		$id = self::get_current_page_by_elementor_request();
		if ( $id > 0 ) {
			return $id;
		}

		if ( edd_doing_ajax() && ! empty( $_POST['current_page'] ) ) {
			return absint( $_POST['current_page'] );
		}

		// On a normal front-end pageview no Elementor request or ajax params exist, so fall back to the queried post. The is_singular() guard keeps an archive or taxonomy term id from reaching get_post().
		if ( is_singular() ) {
			return get_queried_object_id();
		}

		return 0;
	}

	/**
	 * Get the current page ID from Elementor request parameters.
	 *
	 * @since 3.6.0
	 * @return int The page ID, or 0 if not found.
	 */
	private static function get_current_page_by_elementor_request(): int {
		// Check for elementor-preview parameter.
		$preview_id = $_REQUEST['elementor-preview'] ?? null;
		if ( is_numeric( $preview_id ) && (int) $preview_id > 0 ) {
			return absint( $preview_id );
		}

		// Check for elementor action with post parameter.
		$action  = $_REQUEST['action'] ?? '';
		$post_id = $_REQUEST['post'] ?? null;

		if ( 'elementor' === $action && $post_id && is_numeric( $post_id ) && (int) $post_id > 0 ) {
			return (int) $post_id;
		}

		return 0;
	}
}
