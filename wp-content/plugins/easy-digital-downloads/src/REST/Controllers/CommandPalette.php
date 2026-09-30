<?php
/**
 * Command Palette REST Controller.
 *
 * Aggregates every registered EDD command palette source, running each source
 * the current user is permitted to access and returning the combined results.
 *
 * @package     EDD\REST\Controllers
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\REST\Controllers;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\CommandPalette\Registry;

/**
 * CommandPalette Controller class.
 *
 * @since 3.7.1
 */
class CommandPalette {

	/**
	 * The maximum number of results returned per source.
	 *
	 * Enforced here, and the value EDD's own sources bound their queries to.
	 *
	 * @since 3.7.1
	 * @var int
	 */
	const MAX_RESULTS_PER_SOURCE = 10;

	/**
	 * Search all authorized command palette sources.
	 *
	 * @since 3.7.1
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function search( $request ) {
		$term    = (string) $request->get_param( 'search' );
		$results = array();

		foreach ( $this->get_source_groups( $term ) as $sources ) {
			$results = $this->search_sources( $sources, $term );
			if ( ! empty( $results ) ) {
				break;
			}
		}

		return new \WP_REST_Response(
			array( 'results' => $results ),
			200
		);
	}

	/**
	 * Group the registered sources into the order they should be tried in.
	 *
	 * A bare number is nearly always a record ID, and the sources that cannot
	 * match one exactly search their text columns with an unanchored `LIKE`,
	 * which turns any number into a pile of coincidental matches. So a numeric
	 * term tries the sources that understand IDs on their own first, and only
	 * falls back to the rest when they find nothing.
	 *
	 * @since 3.7.1
	 *
	 * @param string $term The search term.
	 * @return \WP_Ability[][] Groups of sources, tried in order until one matches.
	 */
	private function get_source_groups( $term ) {
		$numeric = array();
		$other   = array();

		foreach ( Registry::get_sources() as $ability ) {
			if ( $ability->get_meta_item( Registry::META_NUMERIC ) ) {
				$numeric[] = $ability;
			} else {
				$other[] = $ability;
			}
		}

		if ( is_numeric( trim( $term ) ) ) {
			return array( $numeric, $other );
		}

		return array( array_merge( $other, $numeric ) );
	}

	/**
	 * Run a set of sources and collect their results.
	 *
	 * @since 3.7.1
	 *
	 * @param \WP_Ability[] $sources The sources to run.
	 * @param string        $term    The search term.
	 * @return array[]
	 */
	private function search_sources( array $sources, $term ) {
		$results = array();

		foreach ( $sources as $ability ) {
			// Skip sources the current user cannot access, silently.
			if ( true !== $ability->check_permissions( $term ) ) {
				continue;
			}

			$found = $ability->execute( $term );
			if ( is_wp_error( $found ) || empty( $found ) || ! is_array( $found ) ) {
				continue;
			}

			$keywords = $ability->get_label();
			$index    = 0;
			foreach ( array_slice( $found, 0, self::MAX_RESULTS_PER_SOURCE ) as $result ) {
				if ( empty( $result['label'] ) || empty( $result['url'] ) ) {
					continue;
				}

				// Sources are arbitrary callbacks, so keep only valid URLs.
				$url = esc_url_raw( $result['url'] );
				if ( empty( $url ) ) {
					continue;
				}

				$results[] = array(
					'name'     => $ability->get_name() . '/' . $index,
					'label'    => $result['label'],
					'url'      => $url,
					'keywords' => $keywords,
				);
				++$index;
			}
		}

		return $results;
	}
}
