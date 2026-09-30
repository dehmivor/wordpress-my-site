<?php
/**
 * Search functionality for downloads.
 *
 * @package     EDD
 */

namespace EDD\Downloads;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Search class.
 */
class Search {

	/**
	 * Retrieve a downloads drop down
	 *
	 * @since 3.1.0.5 Copied from `edd_ajax_download_search`
	 *
	 * @return void
	 */
	public function ajax_search() {

		if ( ! edd_doing_ajax() ) {
			return;
		}

		echo wp_json_encode( $this->search() );

		edd_die();
	}

	/**
	 * Search for downloads.
	 *
	 * @since 3.3.6
	 * @return array
	 */
	public function search() {
		$search = $this->get_results();

		// Update the transient.
		set_transient( $this->get_transient_key(), $search, 30 );

		return $search['results'];
	}

	/**
	 * Removes items which are not the caller's to see.
	 *
	 * Publicly viewable products stay visible to everyone. Anything else has to be readable by
	 * the current user, which for a product's own author includes their drafts.
	 *
	 * @since 3.7.1
	 *
	 * @param array $items The items returned by the query.
	 * @return array
	 */
	public static function filter_by_readability( $items ) {
		if ( empty( $items ) || self::can_read_all_products() ) {
			return $items;
		}

		return array_values(
			array_filter(
				$items,
				function ( $item ) {
					// Anything else takes WP_Post's own property defaults, which describe a
					// published post, and an ID of 0 resolves to whatever the global post is.
					if ( ! $item instanceof \WP_Post || empty( $item->ID ) ) {
						return false;
					}

					if ( is_post_publicly_viewable( $item ) ) {
						return true;
					}

					return current_user_can( 'read_post', $item->ID );
				}
			)
		);
	}

	/**
	 * Whether the current user may read every product, whatever its status or author.
	 *
	 * @since 3.7.1
	 *
	 * @return bool
	 */
	public static function can_read_all_products() {
		return current_user_can( 'edit_others_products' ) && current_user_can( 'read_private_products' );
	}

	/**
	 * Get the search results.
	 *
	 * @since 3.3.6
	 * @return array
	 */
	private function get_results() {

		// An empty search always returns nothing, regardless of anything else about the request.
		// Checked against '' specifically: empty() would also catch the valid search term '0'.
		$new_search = $this->get_search();
		if ( '' === $new_search ) {
			return array(
				'text'    => '',
				'shape'   => null,
				'results' => array(),
			);
		}

		// We store the last search in a transient for 30 seconds. This _might_
		// result in a race condition if 2 users are looking at the exact same time,
		// but we'll worry about that later if that situation ever happens.
		$args = get_transient( $this->get_transient_key() );

		// Parse args.
		$search = wp_parse_args(
			(array) $args,
			array(
				'text'    => '',
				'shape'   => null,
				'results' => array(),
			)
		);

		$new_shape = $this->get_request_shape();

		// Bail early if neither the search text nor its shape have changed.
		if ( $search['text'] === $new_search && $search['shape'] === $new_shape ) {
			return $search;
		}

		// Set the local static search variable and clear the results.
		$search = array(
			'text'    => $new_search,
			'shape'   => $new_shape,
			'results' => array(),
		);

		// Default query arguments.
		$args = array(
			'orderby'          => 'title',
			'order'            => 'ASC',
			'post_type'        => 'download',
			'posts_per_page'   => 50,
			'post_status'      => $this->get_status(),
			'post__not_in'     => $this->get_exclusions(),
			's'                => $new_search,
			'suppress_filters' => false,
		);

		$items = self::filter_by_readability( $this->get_items( $args ) );

		if ( empty( $items ) ) {
			return $search;
		}

		// Are we excluding bundles?
		$no_bundles = $this->get_flag( 'no_bundles' );

		// Are we including variations?
		$variations = $this->get_flag( 'variations' );

		$variations_only = $this->get_flag( 'variations_only' );

		$items = wp_list_pluck( $items, 'post_title', 'ID' );

		// Loop through all items...
		foreach ( $items as $post_id => $title ) {
			// Skip bundles if we're excluding them.
			if ( true === $no_bundles && 'bundle' === edd_get_download_type( $post_id ) ) {
				continue;
			}
			$product_title = $title;

			// Look for variable pricing.
			$prices = edd_get_variable_prices( $post_id );

			if ( ! empty( $prices ) && ( false === $variations || ! $variations_only ) ) {
				$title .= ' (' . __( 'All Price Options', 'easy-digital-downloads' ) . ')';
			}

			if ( empty( $prices ) || false === $variations || ! $variations_only ) {
				// Add item to results array.
				$search['results'][] = array(
					'id'   => $post_id,
					'name' => $title,
				);
			}

			// Maybe include variable pricing.
			if ( ! empty( $variations ) && ! empty( $prices ) ) {
				foreach ( $prices as $key => $value ) {
					$name = ! empty( $value['name'] ) ? $value['name'] : '';

					if ( ! empty( $name ) ) {
						$search['results'][] = array(
							'id'   => $post_id . '_' . $key,
							'name' => esc_html( $product_title . ': ' . $name ),
						);
					}
				}
			}
		}

		return $search;
	}

	/**
	 * Gets the items.
	 *
	 * @since 3.2.8
	 * @param array $args The array of arguments for WP_Query.
	 * @return array
	 */
	private function get_items( $args ) {
		add_filter(
			'post_search_columns',
			function () {
				return array( 'post_title' );
			}
		);

		$items = get_posts( $args );

		/**
		 * Filter the download search results.
		 *
		 * Allows extensions to add additional items (e.g., from taxonomy searches)
		 * or modify the results before they are processed.
		 *
		 * @since 3.6.5
		 * @param array $items The array of post objects from the search.
		 * @param array $args  The query arguments used for the search.
		 */
		return $this->sort_by_relevance(
			apply_filters( 'edd_download_search_items', $items, $args ),
			$args['s']
		);
	}

	/**
	 * Gets the search string.
	 *
	 * @since 3.2.7
	 * @return string
	 */
	private function get_search() {
		return isset( $_GET['s'] )
			? sanitize_text_field( urldecode( $_GET['s'] ) )
			: '';
	}

	/**
	 * Gets the excluded downloads.
	 *
	 * @since 3.2.8
	 * @return array
	 */
	private function get_exclusions() {
		$excludes = ! empty( $_GET['current_id'] )
			? array_map( 'absint', (array) $_GET['current_id'] )
			: array();

		if ( ! empty( $_GET['exclusions'] ) ) {
			$excludes = array_merge( $excludes, array_map( 'absint', explode( ',', $_GET['exclusions'] ) ) );
		}

		return array_unique( array_filter( $excludes ) );
	}

	/**
	 * Get the download statuses to query.
	 *
	 * @since 3.3.6
	 * @return array
	 */
	private function get_status() {
		if ( ! current_user_can( 'edit_products' ) ) {
			return apply_filters( 'edd_product_dropdown_status_nopriv', array( 'publish' ) );
		}

		return apply_filters( 'edd_product_dropdown_status', array( 'publish', 'draft', 'private', 'future' ) );
	}

	/**
	 * Gets the transient key for the current caller.
	 *
	 * Results depend on who is asking, so the cache cannot be shared between callers. The key
	 * stays bounded to one entry per caller: this endpoint is open to anonymous requests, and
	 * folding the request shape in here too would let a caller mint an unbounded number of
	 * transients just by varying it.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	private function get_transient_key() {
		return sprintf(
			'edd_download_search_%d_%s',
			get_current_user_id(),
			implode( '_', array_map( 'sanitize_key', $this->get_status() ) )
		);
	}

	/**
	 * Gets the request parameters that shape the cached results, beyond the search text itself.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	private function get_request_shape() {
		return array(
			'exclusions'      => $this->get_exclusions(),
			'no_bundles'      => $this->get_flag( 'no_bundles' ),
			'variations'      => $this->get_flag( 'variations' ),
			'variations_only' => $this->get_flag( 'variations_only' ),
		);
	}

	/**
	 * Gets a boolean request flag.
	 *
	 * Read through here rather than from the superglobal directly, so that the value which
	 * shapes the results is the same one the cache key is built from.
	 *
	 * @since 3.7.1
	 *
	 * @param string $key The request key to read.
	 * @return bool
	 */
	private function get_flag( $key ) {
		return isset( $_GET[ $key ] )
			? filter_var( $_GET[ $key ], FILTER_VALIDATE_BOOLEAN )
			: false;
	}

	/**
	 * Sorts results by relevance, prioritizing exact title matches.
	 *
	 * @since 3.6.5
	 * @param array  $items       The items to sort.
	 * @param string $search_term The search term.
	 * @return array
	 */
	private function sort_by_relevance( $items, $search_term ) {
		if ( empty( $search_term ) || empty( $items ) ) {
			return $items;
		}

		$search_lower = mb_strtolower( trim( $search_term ) );

		// Filter out invalid items FIRST (before sorting).
		$items = array_filter(
			$items,
			function ( $item ) {
				return $item instanceof \WP_Post;
			}
		);

		// Assign relevance scores and sort.
		usort(
			$items,
			function ( $a, $b ) use ( $search_lower ) {
				$title_a = mb_strtolower( $a->post_title );
				$title_b = mb_strtolower( $b->post_title );

				// Exact match gets highest priority.
				$exact_a = ( $title_a === $search_lower ) ? 1 : 0;
				$exact_b = ( $title_b === $search_lower ) ? 1 : 0;

				if ( $exact_a !== $exact_b ) {
					return $exact_b - $exact_a;
				}

				// Starts with search term gets second priority.
				$starts_a = ( 0 === mb_strpos( $title_a, $search_lower ) ) ? 1 : 0;
				$starts_b = ( 0 === mb_strpos( $title_b, $search_lower ) ) ? 1 : 0;

				if ( $starts_a !== $starts_b ) {
					return $starts_b - $starts_a;
				}

				// Fall back to alphabetical order.
				return strcmp( $title_a, $title_b );
			}
		);

		return $items;
	}

	/**
	 * Filters the WHERE SQL query for the edd_download_search.
	 * This searches the download titles only, not the excerpt/content.
	 *
	 * @since 3.1.0.2
	 * @since 3.1.0.5 Moved to EDD\Downloads\Ajax.
	 * @deprecated 3.3.6
	 * @param string $where The WHERE clause of the query.
	 * @return string
	 */
	public function filter_where( $where ) {
		return $where;
	}

	/**
	 * Parses the search terms to allow for a "fuzzy" search.
	 *
	 * @since 3.1.0.5
	 * @deprecated 3.3.6
	 * @param string $search The search string.
	 * @return array
	 */
	protected function parse_search_terms( $search ) {
		$terms      = explode( ' ', $search );
		$strtolower = function_exists( 'mb_strtolower' ) ? 'mb_strtolower' : 'strtolower';
		$checked    = array();

		foreach ( $terms as $term ) {
			// Keep before/after spaces when term is for exact match.
			if ( preg_match( '/^".+"$/', $term ) ) {
				$term = trim( $term, "\"'" );
			} else {
				$term = trim( $term, "\"' " );
			}

			// Avoid single A-Z and single dashes.
			if ( ! $term || ( 1 === strlen( $term ) && preg_match( '/^[a-z\-]$/i', $term ) ) ) {
				continue;
			}

			$checked[] = $term;
		}

		return $checked;
	}
}
