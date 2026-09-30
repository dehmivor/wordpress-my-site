<?php
/**
 * Query Helper Class
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Query_Helper.
 */
class Query_Helper {


	/**
	 * Class instance
	 *
	 * @var instance
	 */
	private static $instance = null;


	/**
	 * Query_Helper constructor.
	 */
	public function __construct() {
	}

	/**
	 * Get authors
	 *
	 * Get posts author array
	 *
	 * @since 3.20.3
	 * @access public
	 *
	 * @return array
	 */
	public static function get_authors() {

		$users = get_users(
			array(
				'role__in' => array( 'administrator', 'editor', 'author', 'contributor' ),
				'fields'   => array( 'ID', 'display_name' ), // Only fetch the necessary fields.
			)
		);

		$options = array();

		foreach ( $users as $user ) {
			if ( 'wp_update_service' === $user->display_name ) {
				continue;
			}

			$options[ $user->ID ] = $user->display_name;
		}

		return $options;
	}

	/**
	 * Get query args
	 *
	 * Get query arguments array
	 *
	 * @since 3.20.3
	 * @access public
	 *
	 * @param array  $settings widget settings.
	 * @param string $target_post_type target post type.
	 *
	 * @return array query args
	 */
	public static function get_query_args( $settings, $target_post_type = '' ) {

		// Non-paginated widgets (e.g. ticker) must not inherit the main query's page number,
		// otherwise they return empty results when the archive is on page 2+.
		$paged     = ( isset( $settings['widget_type'] ) && 'premium-post-ticker' === $settings['widget_type'] ) ? 1 : self::get_paged();
		$tax_count = 0;

		$post_type = $settings['post_type_filter'];
		$post_id   = get_the_ID();

		if ( 'main' === $post_type ) {

			global $wp_query;

			$main_query = clone $wp_query;

			return $main_query->query_vars;

		}

		$post_args = array(
			'post_type'        => ! empty( $target_post_type ) ? $target_post_type : $post_type,
			'posts_per_page'   => empty( $settings['premium_blog_number_of_posts'] ) ? 9999 : $settings['premium_blog_number_of_posts'],
			'paged'            => $paged,
			'post_status'      => 'publish',
			'suppress_filters' => false,
		);

		// If select field control option is enabled in AJAX search, then return because we don't want any other post args.
		if ( ! empty( $target_post_type ) ) {
			return $post_args;
		}

		if ( 'related' === $post_type ) {
			$current_post_type      = get_post_type( $post_id );
			$post_args['post_type'] = $current_post_type;
		}

		$post_args['orderby'] = $settings['premium_blog_order_by'];
		$post_args['order']   = $settings['premium_blog_order'];

		if ( 'meta_value' === $settings['premium_blog_order_by'] ) {
			$post_args['meta_key'] = $settings['premium_blog_meta_key'];
		}

		if ( isset( $settings['posts_from'] ) ) {

			if ( '' !== $settings['posts_from'] ) {
				$last_time = strtotime( '-1 ' . $settings['posts_from'] );

				$start_date = gmdate( 'Y-m-d', $last_time );
				$end_date   = gmdate( 'Y-m-d' );

				$post_args['date_query'] = array(
					array(
						'after'     => $start_date,
						'before'    => $end_date,
						'inclusive' => true,
					),
				);

			}
		}

		$excluded_posts = array();

		if ( ! empty( $settings['premium_blog_posts_exclude'] ) && 'post' === $post_type ) {

			if ( 'premium-search-form' !== $settings['widget_type'] && 'post__in' === $settings['posts_filter_rule'] ) {
				$post_args['post__in'] = $settings['premium_blog_posts_exclude'];
			} else {
				$excluded_posts = $settings['premium_blog_posts_exclude'];
			}
		} elseif ( 'related' === $post_type ) {

			if ( 'product' === $current_post_type ) {

				$post_cats = self::get_product_cats_ids( $post_id );

				$post_args['tax_query'][] = array(
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $post_cats,
					'operator' => 'IN',
				);

			} else {
				$post_cats = wp_get_post_categories( $post_id );

				if ( ! empty( $post_cats ) ) {
					$post_args['category__in'] = $post_cats;
				}
			}
		} elseif ( ! empty( $settings['custom_posts_filter'] ) && ! in_array( $post_type, array( 'post', 'related' ), true ) ) {

			// Optimization: Removed expensive validation that fetched all posts for the current post type.
			// WP_Query already handles post type filtering when using post__in or post__not_in.
			if ( 'post__in' === $settings['posts_filter_rule'] ) {
				$post_args['post__in'] = $settings['custom_posts_filter'];
			} else {
				$excluded_posts = $settings['custom_posts_filter'];
			}
		}

		if ( ! empty( $settings['premium_blog_users'] ) ) {

			$post_args[ $settings['author_filter_rule'] ] = $settings['premium_blog_users'];
		}

		if ( 'related' !== $post_type ) {
			// Get all the taxonomies associated with the post type.
			$taxonomy = self::get_taxnomies( $post_type );

			if ( ! empty( $taxonomy ) && ! is_wp_error( $taxonomy ) ) {

				// Get all taxonomy values under the taxonomy.

				$tax_count = 0;
				foreach ( $taxonomy as $index => $tax ) {

					if ( ! empty( $settings[ 'tax_' . $index . '_' . $post_type . '_filter' ] ) ) {

						$operator = $settings[ $index . '_' . $post_type . '_filter_rule' ];

						$post_args['tax_query'][] = array(
							'taxonomy' => $index,
							'field'    => 'slug',
							'terms'    => $settings[ 'tax_' . $index . '_' . $post_type . '_filter' ],
							'operator' => $operator,
						);
						++$tax_count;
					}
				}
			}
		}

		// needs to be checked.
		if ( '' !== $settings['active_cat'] && '*' !== $settings['active_cat'] && 'related' !== $post_type ) {

			$filter_type = $settings['filter_tabs_type'];

			if ( 'tag' === $settings['filter_tabs_type'] ) {
				$filter_type = 'post_tag';
			}

			$post_args['tax_query'][] = array(
				'taxonomy' => $filter_type,
				'field'    => 'slug',
				'terms'    => $settings['active_cat'],
				'operator' => 'IN',
			);

		}

		if ( isset( $settings['premium_blog_offset'] ) && 0 < $settings['premium_blog_offset'] ) {

			/**
			 * Offset break the pagination. Using WordPress's work around
			 *
			 * @see https://codex.wordpress.org/Making_Custom_Queries_using_Offset_and_Pagination
			 */
			$post_args['offset_to_fix'] = $settings['premium_blog_offset'];

		}

		if ( isset( $settings['ignore_sticky_posts'] ) ) {
			if ( 'yes' === $settings['ignore_sticky_posts'] ) {
				$excluded_posts = array_merge( $excluded_posts, get_option( 'sticky_posts', array() ) );
			} else {
				$post_args['ignore_sticky_posts'] = true;
			}
		}

		if ( ( isset( $settings['query_exclude_current'] ) && 'yes' === $settings['query_exclude_current'] ) || 'related' === $post_type ) {
			$excluded_posts[] = $post_id;
		}

		$post_args['post__not_in'] = $excluded_posts;

		return $post_args;
	}

	/**
	 * Get paged
	 *
	 * Returns the paged number for the query.
	 *
	 * @since 3.20.0
	 * @return int
	 */
	public static function get_paged() {

		global $wp_the_query, $paged;

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : false;

		if ( $nonce && wp_verify_nonce( $nonce, 'pa-blog-widget-nonce' ) ) {
			if ( isset( $_POST['page_number'] ) && '' !== $_POST['page_number'] ) {
				return sanitize_text_field( wp_unslash( $_POST['page_number'] ) );
			}
		}

		// Check the 'paged' query var.
		$paged_qv = $wp_the_query->get( 'paged' );

		if ( is_numeric( $paged_qv ) ) {
			return $paged_qv;
		}

		// Check the 'page' query var.
		$page_qv = $wp_the_query->get( 'page' );

		if ( is_numeric( $page_qv ) ) {
			return $page_qv;
		}

		// Check the $paged global?
		if ( is_numeric( $paged ) ) {
			return $paged;
		}

		return 0;
	}

	/**
	 * Retrieves the product's categories IDs.
	 *
	 * @access public
	 * @since 2.8.20
	 *
	 * @param int $prod_id  product id.
	 *
	 * @return array
	 */
	public static function get_product_cats_ids( $prod_id ) {

		$prod_cats = get_the_terms( $prod_id, 'product_cat' );
		$cats_ids  = array();

		foreach ( $prod_cats as $index => $cat ) {
			$cats_ids[] = $cat->term_id;
		}

		return $cats_ids;
	}

	/**
	 * Get posts list
	 *
	 * Used to set Premium_Post_Filter control default settings.
	 *
	 * @param string $post_type  post type.
	 *
	 * @return array
	 */
	public static function get_default_posts_list( $post_type ) {

		// Use WP object cache to avoid repeated expensive database queries for post lists.
		$cache_key = 'pa_posts_list_' . $post_type;
		$options   = wp_cache_get( $cache_key, 'premium_addons' );

		if ( false !== $options ) {
			return $options;
		}

		global $wpdb;

		$list = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title FROM $wpdb->posts WHERE post_type = %s AND post_status = 'publish'",
				$post_type
			)
		); // phpcs:ignore

		$options = array();

		if ( ! empty( $list ) ) {
			foreach ( $list as $post ) {
				$options[ $post->ID ] = $post->post_title;
			}
		}

		wp_cache_set( $cache_key, $options, 'premium_addons', 15 * MINUTE_IN_SECONDS );

		return $options;
	}

	/**
	 * Get taxonomies.
	 *
	 * Get post taxonomies for post type
	 *
	 * @since 3.20.3
	 * @access public
	 *
	 * @param string $type Post type.
	 */
	public static function get_taxnomies( $type ) {

		$taxonomies = get_object_taxonomies( $type, 'objects' );

		$data = array();

		foreach ( $taxonomies as $tax_slug => $tax ) {

			if ( ! $tax->public || ! $tax->show_ui ) {
				continue;
			}

			$data[ $tax_slug ] = (object) array(
				'label' => $tax->label,
			);
		}

		return $data;
	}

	/**
	 * Get posts types
	 *
	 * Get posts types array
	 *
	 * @since 3.20.3
	 * @access public
	 *
	 * @return array
	 */
	public static function get_posts_types() {

		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);

		$options = array();

		foreach ( $post_types as $post_type ) {

			if ( 'attachment' === $post_type->name ) {
				continue;
			}

			$options[ $post_type->name ] = $post_type->label;
		}

		return $options;
	}

	/**
	 * Creates and returns an instance of the class
	 *
	 * @since 1.0.0
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
