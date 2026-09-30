<?php
/**
 * PA MCP News.
 */

namespace PremiumAddons\Admin\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MCP_News
 *
 * Remote "What's New" feed shown in the MCP Config & AI Abilities tab.
 * Feed content is remote English copy — never translated, escaped on output.
 *
 * @since 4.11.102
 */
class MCP_News {

	// Sidebar and submenu dot stay hidden until the MCP & AI Abilities video
	// tutorials are published; the feed drew too little traffic on its own.
	const ENABLED = false;

	const ENDPOINT = 'https://premiumaddons.com/wp-json/mcp-news/v2/get';

	const FEED_OPTION = 'pa_mcp_news_feed';

	const SEEN_OPTION = 'pa_mcp_news_seen';

	const FRESH_TRANSIENT = 'pa_mcp_news_fresh';

	const CACHE_TTL = 2 * DAY_IN_SECONDS;

	const RETRY_TTL = 6 * HOUR_IN_SECONDS;

	const RENDER_LIMIT = 10;

	/**
	 * Get the latest feed entries, refreshing the cache when it expired.
	 *
	 * @since 4.11.102
	 * @access public
	 *
	 * @return array
	 */
	public static function get_entries() {

		if ( false === get_transient( self::FRESH_TRANSIENT ) ) {
			self::refresh();
		}

		return array_slice( self::get_cached_entries(), 0, self::RENDER_LIMIT );
	}

	/**
	 * Get the cached entries without triggering a remote fetch.
	 *
	 * @since 4.11.102
	 * @access public
	 *
	 * @return array
	 */
	public static function get_cached_entries() {

		$entries = get_option( self::FEED_OPTION, array() );

		return is_array( $entries ) ? $entries : array();
	}

	/**
	 * Whether the cached feed holds an entry newer than the last-seen marker.
	 * Reads the cache only — rendering the admin menu must never fetch.
	 *
	 * @since 4.11.102
	 * @access public
	 *
	 * @return bool
	 */
	public static function has_unread() {

		$entries = self::get_cached_entries();

		if ( empty( $entries ) ) {
			return false;
		}

		return $entries[0]['date'] > get_option( self::SEEN_OPTION, '' );
	}

	/**
	 * Mark the cached feed as seen.
	 *
	 * @since 4.11.102
	 * @access public
	 */
	public static function mark_seen() {

		$entries = self::get_cached_entries();

		if ( ! empty( $entries ) ) {
			update_option( self::SEEN_OPTION, $entries[0]['date'], false );
		}
	}

	/**
	 * Fetch the remote feed. On failure the last cached copy stays in place and
	 * the retry is postponed, so the dashboard never waits on the feed twice.
	 *
	 * @since 4.11.102
	 * @access private
	 */
	private static function refresh() {

		$response = wp_remote_get( self::ENDPOINT, array( 'timeout' => 3 ) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( self::FRESH_TRANSIENT, 1, self::RETRY_TTL );
			return;
		}

		$entries = self::sanitize_entries( json_decode( wp_remote_retrieve_body( $response ), true ) );

		update_option( self::FEED_OPTION, $entries, false );
		set_transient( self::FRESH_TRANSIENT, 1, self::CACHE_TTL );
	}

	/**
	 * Keep only complete entries, newest first.
	 *
	 * @since 4.11.102
	 * @access private
	 *
	 * @param mixed $data decoded response body.
	 *
	 * @return array
	 */
	private static function sanitize_entries( $data ) {

		if ( ! is_array( $data ) ) {
			return array();
		}

		$fields  = array( 'id', 'date', 'title', 'description', 'link', 'type' );
		$entries = array();

		foreach ( $data as $entry ) {

			if ( ! is_array( $entry ) ) {
				continue;
			}

			$clean = array();

			foreach ( $fields as $field ) {

				if ( empty( $entry[ $field ] ) || ! is_string( $entry[ $field ] ) ) {
					continue 2;
				}

				$clean[ $field ] = 'link' === $field ? esc_url_raw( $entry[ $field ] ) : sanitize_text_field( $entry[ $field ] );
			}

			$entries[] = $clean;
		}

		usort(
			$entries,
			function ( $a, $b ) {
				return strcmp( $b['date'], $a['date'] );
			}
		);

		return $entries;
	}
}
