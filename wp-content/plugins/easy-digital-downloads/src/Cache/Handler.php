<?php
/**
 * Cache Handler
 *
 * Prevents caching on EDD's dynamic pages (checkout, success) and invalidates
 * the cached URI list when settings, page slugs, or permalink structure change.
 *
 * @package     EDD\Cache
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.7
 */

declare(strict_types=1);

namespace EDD\Cache;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\Subscriber;

/**
 * Cache Handler subscriber.
 *
 * @since 3.6.7
 */
class Handler extends Subscriber {

	/**
	 * Transient key used to store excluded page URIs.
	 *
	 * @since 3.6.7
	 * @var string
	 */
	const TRANSIENT = 'edd_cache_excluded_ids';

	/**
	 * Returns the events this subscriber listens to.
	 *
	 * @since 3.6.7
	 *
	 * @return array
	 */
	public static function get_subscribed_events(): array {
		return array(
			'template_redirect'           => array( 'check_request', 0 ),
			'admin_notices'               => 'notices',
			'update_option_edd_settings'  => 'flush_page_ids',
			'save_post'                   => array( 'maybe_flush_page_ids', 10, 1 ),
			'permalink_structure_changed' => 'flush_page_ids',
		);
	}

	/**
	 * Prevents caching on the checkout and success pages.
	 * Sets no-cache headers on EDD dynamic pages using template tags and page ID matching.
	 *
	 * @since 3.6.7
	 * @return void
	 */
	public function check_request(): void {
		if ( edd_is_checkout() || edd_is_success_page() ) {
			NoCache::set_headers(
				array( 'Referrer-Policy' => '' )
			);
			return;
		}

		$dynamic_page_ids = get_transient( self::TRANSIENT );
		if ( false === $dynamic_page_ids ) {
			$dynamic_page_ids = array_map( 'intval', array_filter( $this->get_dynamic_page_ids() ) );
			set_transient( self::TRANSIENT, $dynamic_page_ids, WEEK_IN_SECONDS );
		}

		if ( ! empty( $dynamic_page_ids ) && is_page( $dynamic_page_ids ) ) {
			NoCache::set_headers(
				array( 'Referrer-Policy' => '' )
			);
		}
	}

	/**
	 * Displays an admin notice if W3 Total Cache database caching is misconfigured.
	 *
	 * EDD stores session data in the custom edd_sessions table. If W3 Total Cache
	 * database caching is enabled without excluding edd_sessions queries, cached
	 * SELECT results may serve stale cart or checkout session data.
	 *
	 * @since 1.7
	 * @since 3.6.7 Updated to check for edd_sessions instead of the deprecated _wp_session_ option key.
	 *
	 * @return void
	 */
	public static function notices(): void {
		if ( ! function_exists( 'w3tc_pgcache_flush' ) || ! function_exists( 'w3_instance' ) ) {
			return;
		}

		$config = w3_instance( 'W3_Config' );
		if ( ! $config->get_integer( 'dbcache.enabled' ) ) {
			return;
		}

		$settings = $config->get_array( 'dbcache.reject.sql' );
		foreach ( $settings as $pattern ) {
			if ( false !== strpos( $pattern, 'edd_sessions' ) ) {
				return;
			}
		}

		?>
		<div class="notice edd-notice notice-error">
			<p>
				<?php
				$link = add_query_arg( 'page', 'w3tc_dbcache', admin_url( 'admin.php' ) );
				printf(
					/* translators: 1. The query stem to ignore, 2. The link to the W3 Total Cache settings, 3. The closing link tag */
					esc_html__( 'In order for database caching to work with Easy Digital Downloads, you must add %1$s to the "Ignored query stems" option in %2$sW3 Total Cache settings%3$s.', 'easy-digital-downloads' ),
					'<code>edd_sessions</code>',
					'<a href="' . esc_url( $link ) . '">',
					'</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Deletes the cached page ID list.
	 *
	 * Hooked to settings updates and permalink structure changes so the list
	 * is rebuilt on the next request with current page ids.
	 *
	 * @since 3.6.7
	 *
	 * @return void
	 */
	public static function flush_page_ids(): void {
		delete_transient( self::TRANSIENT );
		// Delete the legacy transient.
		delete_transient( 'edd_cache_excluded_uris' );
	}

	/**
	 * Flushes the cached page ID list when a relevant post is saved.
	 *
	 * Only fires when the saved post is the configured checkout or success page,
	 * so slug edits are reflected immediately.
	 *
	 * @since 3.6.7
	 *
	 * @param int $post_id The ID of the post being saved.
	 * @return void
	 */
	public function maybe_flush_page_ids( int $post_id ): void {
		$dynamic_pages = $this->get_dynamic_page_ids();
		$dynamic_pages = array_map( 'intval', array_filter( $dynamic_pages ) );
		if ( in_array( (int) $post_id, $dynamic_pages, true ) ) {
			self::flush_page_ids();
		}
	}

	/**
	 * Retrieves the dynamic page IDs.
	 *
	 * @since 3.6.7
	 * @return array
	 */
	private function get_dynamic_page_ids(): array {

		/**
		 * Filters the dynamic page IDs.
		 *
		 * @since 3.6.7
		 * @param array $dynamic_page_ids The dynamic page IDs.
		 * @return array
		 */
		return apply_filters(
			'edd_cache_dynamic_page_ids',
			array_filter(
				array(
					'purchase_page'     => edd_get_option( 'purchase_page', '' ),
					'success_page'      => edd_get_option( 'success_page', '' ),
					'confirmation_page' => edd_get_option( 'confirmation_page', '' ),
				)
			)
		);
	}

	/**
	 * Initializes the cache handler.
	 *
	 * @since 1.7
	 * @since 3.6.7 Deprecated.
	 * @return void
	 */
	public function init(): void {
		_edd_deprecated_function( __FUNCTION__, '3.6.7', 'EDD\Cache\Handler' );
		$this->check_request();
	}
}
