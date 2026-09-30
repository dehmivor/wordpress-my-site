<?php
/**
 * Premium Templates catalog client.
 *
 * Talks to the premiumtemplates.io REST API for the templates abilities. The
 * editor's Templates Library keeps its own client — that subsystem is
 * editor-only and never loads on MCP/REST requests.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Templates;

use PremiumAddons\Includes\Helper_Functions;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Class Templates_Api.
 *
 * @since 4.11.100
 */
class Templates_Api {

	/**
	 * Catalog REST base.
	 */
	const BASE_URL = 'https://premiumtemplates.io/wp-json/patemp/v2/';

	/**
	 * The catalog tab the abilities browse.
	 */
	const TAB = 'premium_section';

	/**
	 * Category/keyword slugs cache.
	 */
	const TERMS_TRANSIENT = 'pa_templates_terms';

	/**
	 * Marker remembering a failed terms fetch.
	 */
	const TERMS_FAILED_TRANSIENT = 'pa_templates_terms_failed';

	/**
	 * Per-template catalog metadata index, filled by query().
	 */
	const META_INDEX_TRANSIENT = 'pa_templates_meta_index';

	/**
	 * Get the catalog category and keyword slugs.
	 *
	 * Cached for a day. A failed fetch is remembered for 15 minutes so ability
	 * registration never blocks twice in a row on an unreachable server.
	 *
	 * @return array|null { categories: string[], keywords: string[] }, or null when unavailable.
	 */
	public static function get_terms() {

		$terms = get_transient( self::TERMS_TRANSIENT );

		if ( is_array( $terms ) ) {
			return $terms;
		}

		if ( get_transient( self::TERMS_FAILED_TRANSIENT ) ) {
			return null;
		}

		$categories = self::fetch_terms( 'categories' );
		$keywords   = self::fetch_terms( 'keywords' );

		if ( null === $categories || null === $keywords ) {

			set_transient( self::TERMS_FAILED_TRANSIENT, 1, 15 * MINUTE_IN_SECONDS );

			return null;
		}

		$terms = array(
			'categories' => $categories,
			'keywords'   => $keywords,
		);

		set_transient( self::TERMS_TRANSIENT, $terms, DAY_IN_SECONDS );

		return $terms;
	}

	/**
	 * Query the templates listing.
	 *
	 * Responses are cached per query for half a day, and every listing that
	 * passes through — cached or fresh — feeds the metadata index that
	 * insert-premium-template reads.
	 *
	 * @param array $args { category?: string[], keyword?: string[], pro?: bool, per_page?: int, page?: int }.
	 *
	 * @return array|\WP_Error The decoded listing envelope.
	 */
	public static function query( $args ) {

		$params = array( 'source' => 'mcp' );

		foreach ( array( 'category', 'keyword' ) as $filter ) {
			if ( ! empty( $args[ $filter ] ) ) {
				$params[ $filter ] = implode( ',', array_map( 'sanitize_title', (array) $args[ $filter ] ) );
			}
		}

		if ( isset( $args['pro'] ) && null !== $args['pro'] ) {
			$params['pro'] = $args['pro'] ? 'true' : 'false';
		}

		$params['per_page'] = isset( $args['per_page'] ) ? min( 50, max( 1, (int) $args['per_page'] ) ) : 20;
		$params['page']     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;

		$cache_key = 'pa_templates_query_' . md5( wp_json_encode( $params ) );
		$envelope  = get_transient( $cache_key );

		if ( ! is_array( $envelope ) ) {

			$envelope = self::fetch_json( add_query_arg( $params, self::BASE_URL . 'templates/' . self::TAB ), 15 );

			if ( is_wp_error( $envelope ) ) {
				return $envelope;
			}

			if ( empty( $envelope['success'] ) || ! isset( $envelope['templates'] ) || ! is_array( $envelope['templates'] ) ) {
				return self::unavailable_error();
			}

			set_transient( $cache_key, $envelope, 12 * HOUR_IN_SECONDS );
		}

		self::index_meta( $envelope['templates'] );

		return $envelope;
	}

	/**
	 * Get the cached catalog metadata for one template.
	 *
	 * The index is filled by query() — the listing is the only source of the
	 * notice and dependency fields.
	 *
	 * @param int $template_id The template id.
	 *
	 * @return array|null { title, pro, notice, dependencies }, or null when not indexed yet.
	 */
	public static function get_meta( $template_id ) {

		$index = get_transient( self::META_INDEX_TRANSIENT );

		return isset( $index[ $template_id ] ) ? $index[ $template_id ] : null;
	}

	/**
	 * Fetch one template's content.
	 *
	 * The server withholds content for a pro template unless the license query
	 * arg carries a valid Premium Addons Pro key for this site.
	 *
	 * @param int $template_id The template id.
	 *
	 * @return array|\WP_Error The decoded template body.
	 */
	public static function get_template( $template_id ) {

		$params = array( 'url' => rawurlencode( home_url( '/' ) ) );

		$license_key = get_option( 'papro_license_key' );

		if ( is_string( $license_key ) && '' !== $license_key ) {
			$params['license'] = rawurlencode( $license_key );
		}

		return self::fetch_json( add_query_arg( $params, self::BASE_URL . 'template/' . absint( $template_id ) ), 30 );
	}

	/**
	 * Whether the site holds a valid Premium Addons Pro license.
	 *
	 * @return bool
	 */
	public static function is_license_valid() {
		return Helper_Functions::check_papro_version() && 'valid' === get_option( 'papro_license_status' );
	}

	/**
	 * The shared catalog-unreachable error.
	 *
	 * @return \WP_Error
	 */
	public static function unavailable_error() {
		return new \WP_Error(
			'premium_addons_catalog_data_unavailable',
			__( 'The Premium Templates catalog could not be reached. Try again shortly.', 'premium-addons-for-elementor' )
		);
	}

	/**
	 * Fetch a term-list endpoint.
	 *
	 * @param string $endpoint categories or keywords.
	 *
	 * @return array|null Slugs, or null on failure.
	 */
	private static function fetch_terms( $endpoint ) {

		$body = self::fetch_json( self::BASE_URL . $endpoint . '/' . self::TAB, 5 );

		if ( is_wp_error( $body ) || empty( $body['terms'] ) || ! is_array( $body['terms'] ) ) {
			return null;
		}

		$slugs = array();

		// The endpoint returns a { slug: label } map.
		foreach ( $body['terms'] as $slug => $term ) {

			if ( is_string( $slug ) ) {
				$slugs[] = $slug;
			} elseif ( is_string( $term ) ) {
				$slugs[] = $term;
			}
		}

		return empty( $slugs ) ? null : $slugs;
	}

	/**
	 * Fetch and decode a catalog URL.
	 *
	 * @param string $url     The request URL.
	 * @param int    $timeout Request timeout in seconds.
	 *
	 * @return array|\WP_Error
	 */
	private static function fetch_json( $url, $timeout ) {

		$response = wp_safe_remote_get( $url, array( 'timeout' => $timeout ) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return self::unavailable_error();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $body ) ? $body : self::unavailable_error();
	}

	/**
	 * Merge listing rows into the per-template metadata index.
	 *
	 * @param array $templates Listing rows.
	 *
	 * @return void
	 */
	private static function index_meta( $templates ) {

		$index = get_transient( self::META_INDEX_TRANSIENT );
		$index = is_array( $index ) ? $index : array();

		foreach ( $templates as $template ) {

			if ( empty( $template['template_id'] ) ) {
				continue;
			}

			$index[ (int) $template['template_id'] ] = array(
				'title'        => isset( $template['title'] ) ? $template['title'] : '',
				'pro'          => ! empty( $template['pro'] ),
				'notice'       => isset( $template['notice'] ) ? (array) $template['notice'] : array(),
				'dependencies' => isset( $template['dependencies'] ) ? (array) $template['dependencies'] : array(),
			);
		}

		set_transient( self::META_INDEX_TRANSIENT, $index, DAY_IN_SECONDS );
	}
}
