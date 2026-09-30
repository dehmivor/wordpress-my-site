<?php
/**
 * One-Time Login Link Send Rate Limiting
 *
 * Provides rate limiting for one-time login link send requests,
 * covering per-user checks via email logs and per-IP sliding-window
 * throttling via transients.
 *
 * @package     EDD\Users\LoginLink\Send
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.7
 */

namespace EDD\Users\LoginLink\Send;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Validate class.
 *
 * @since 3.6.7
 */
class Validate {

	/**
	 * Cooldown period in seconds between requests for the same user.
	 *
	 * @since 3.6.7
	 * @var int
	 */
	const COOLDOWN_SECONDS = 120;

	/**
	 * Maximum number of one-time login link emails that can be sent to a single user.
	 *
	 * Once reached, no more emails are sent until the window expires.
	 *
	 * @since 3.6.7
	 * @var int
	 */
	const MAX_EMAILS = 3;

	/**
	 * Maximum number of one-time login link requests per IP within the window.
	 *
	 * @since 3.6.7
	 * @var int
	 */
	const IP_LIMIT = 3;

	/**
	 * Sliding window in seconds for per-IP request counting.
	 *
	 * @since 3.6.7
	 * @var int
	 */
	const IP_WINDOW = HOUR_IN_SECONDS;

	/**
	 * Sliding window in seconds for per-user email log lookups.
	 *
	 * Kept separate from IP_WINDOW so each can be tuned independently.
	 *
	 * @since 3.6.7
	 * @var int
	 */
	const USER_WINDOW = HOUR_IN_SECONDS;

	/**
	 * Checks per-user rate limits (cooldown and max emails).
	 *
	 * @since 3.6.7
	 *
	 * @param int $user_id User ID.
	 * @return true|\WP_Error True if allowed, WP_Error if rate-limited.
	 */
	public static function check_user( int $user_id ) {
		$recent_logs = self::get_recent_logs( $user_id );

		// Cooldown: reject if the most recent email was sent too recently.
		if ( ! empty( $recent_logs ) && self::COOLDOWN_SECONDS > 0 ) {
			$last_sent = strtotime( $recent_logs[0]->date_created );
			if ( $last_sent && ( time() - $last_sent ) < self::COOLDOWN_SECONDS ) {
				return new \WP_Error( 'rate_limited', __( 'Please wait before requesting another login link.', 'easy-digital-downloads' ) );
			}
		}

		// Max emails: reject if too many were sent within the window.
		if ( count( $recent_logs ) >= self::MAX_EMAILS ) {
			return new \WP_Error( 'max_emails_reached', __( 'Maximum login link emails sent. Please try again later.', 'easy-digital-downloads' ) );
		}

		return true;
	}

	/**
	 * Checks per-IP sliding-window rate limits.
	 *
	 * @since 3.6.7
	 *
	 * @return true|\WP_Error True if allowed, WP_Error if rate-limited.
	 */
	public static function check_ip() {
		$client_ip = edd_get_ip();
		if ( empty( $client_ip ) ) {
			return new \WP_Error( 'rate_limited', __( 'Please wait before requesting another login link.', 'easy-digital-downloads' ) );
		}

		$key = 'edd_login_link_ip_' . md5( $client_ip );

		$requests = get_transient( $key );
		$requests = is_array( $requests ) ? $requests : array();
		$cutoff   = time() - self::IP_WINDOW;
		$requests = array_values(
			array_filter(
				$requests,
				static function ( $timestamp ) use ( $cutoff ) {
					return (int) $timestamp >= $cutoff;
				}
			)
		);

		if ( count( $requests ) >= self::IP_LIMIT ) {
			return new \WP_Error( 'rate_limited', __( 'Please wait before requesting another login link.', 'easy-digital-downloads' ) );
		}

		return true;
	}

	/**
	 * Records a successful one-time login link send for rate limiting.
	 *
	 * Appends the current timestamp to the per-IP request log.
	 * Per-user rate limiting is derived from email log entries
	 * created by the Email class on send.
	 *
	 * @since 3.6.7
	 *
	 * @return void
	 */
	public static function record(): void {
		$client_ip = edd_get_ip();
		if ( empty( $client_ip ) ) {
			return;
		}

		$key        = 'edd_login_link_ip_' . md5( $client_ip );
		$requests   = get_transient( $key );
		$requests   = is_array( $requests ) ? $requests : array();
		$cutoff     = time() - self::IP_WINDOW;
		$requests   = array_values(
			array_filter(
				$requests,
				static function ( $timestamp ) use ( $cutoff ) {
					return (int) $timestamp >= $cutoff;
				}
			)
		);
		$requests[] = time();
		set_transient( $key, $requests, self::IP_WINDOW );
	}

	/**
	 * Retrieves recent login link email log entries for a user.
	 *
	 * Returns entries within the USER_WINDOW, ordered newest first.
	 * The result is used for both cooldown and max-email checks.
	 *
	 * @since 3.6.7
	 *
	 * @param int $user_id User ID.
	 * @return array Array of log entry objects.
	 */
	private static function get_recent_logs( int $user_id ): array {
		$log_query = new \EDD\Database\Queries\LogEmail();

		$results = $log_query->query(
			array(
				'email_id'    => 'login_link',
				'object_id'   => $user_id,
				'object_type' => 'user',
				'number'      => self::MAX_EMAILS,
				'date_query'  => array(
					'after' => edd_get_utc_date_string( '-' . self::USER_WINDOW . ' seconds' ),
				),
				'orderby'     => 'date_created',
				'order'       => 'DESC',
			)
		);

		return is_array( $results ) ? $results : array();
	}
}
