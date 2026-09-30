<?php
/**
 * Request class
 *
 * @package EDD
 * @since   3.3.0
 */

namespace EDD\Utils;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Request class
 *
 * @since 3.3.0
 */
class Request {

	/**
	 * What type of request is this?
	 *
	 * @since 3.3.0
	 * @param  string|array $type admin, ajax, cron, cli, frontend, json, API, rest, action_scheduler.
	 * @return bool
	 */
	public static function is_request( $type ) {
		if ( is_string( $type ) ) {
			return self::is_type( $type );
		}

		if ( is_array( $type ) ) {
			foreach ( $type as $t ) {
				if ( self::is_type( $t ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if the request is of a certain type.
	 *
	 * @since 3.3.0
	 * @param  string $type admin, ajax, cron, cli, frontend, json, API, rest, action_scheduler.
	 * @return bool
	 */
	private static function is_type( string $type ) {
		switch ( $type ) {
			case 'admin':
				return is_admin();
			case 'ajax':
				return self::is_ajax_request();
			case 'cron':
				return self::is_cron_request();
			case 'cli':
				return self::is_cli_request();
			case 'action_scheduler':
				return self::is_action_scheduler_executing();
			case 'rest':
				return self::is_rest_api_request();
			case 'frontend':
				return self::is_frontend_request();
			case 'json':
				return wp_is_json_request();
			case 'api':
				return self::is_api_request();
			case 'editor':
				return self::is_editor_request();
			default:
				return false;
		}
	}

	/**
	 * Returns true if the request is a frontend request.
	 *
	 * @since 3.3.0
	 * @return bool
	 */
	private static function is_frontend_request() {
		if ( self::is_cron_request() || self::is_cli_request() || self::is_rest_api_request() || self::is_api_request() ) {
			return false;
		}
		if ( self::is_ajax_request() ) {
			return true;
		}

		return ! is_admin();
	}

	/**
	 * Returns true if the request is an AJAX request.
	 *
	 * @since 3.3.0
	 * @return bool
	 */
	private static function is_ajax_request() {
		return wp_doing_ajax();
	}

	/**
	 * Returns true if the request is a cron request.
	 * Abstraction for cron context checking, covering both WP-Cron and Action Scheduler.
	 *
	 * @since 3.3.0
	 * @return bool
	 */
	private static function is_cron_request() {
		return wp_doing_cron() || self::is_action_scheduler_executing();
	}

	/**
	 * Whether an Action Scheduler job is currently executing.
	 *
	 * `action_scheduler_before_execute` fires before a job and `action_scheduler_after_execute`
	 * after. More befores than afters means we are currently inside one.
	 *
	 * @since 3.7.1
	 * @return bool
	 */
	private static function is_action_scheduler_executing() {
		return did_action( 'action_scheduler_before_execute' ) > did_action( 'action_scheduler_after_execute' );
	}

	/**
	 * Returns true if the request is a WP-CLI request.
	 *
	 * @since 3.7.0
	 * @return bool
	 */
	private static function is_cli_request() {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * Returns true if the request is a non-legacy REST API request.
	 *
	 * Legacy REST requests should still run some extra code for backwards compatibility.
	 *
	 * @todo: replace this function once core WP function is available: https://core.trac.wordpress.org/ticket/42061.
	 * @todo when EDD supports a full CRUD API, update session handling to ensure sessions are started for EDD REST requests.
	 * @since 3.3.0
	 * @return bool
	 */
	private static function is_rest_api_request() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$rest_prefix = trailingslashit( rest_get_url_prefix() );

		return false !== strpos( $_SERVER['REQUEST_URI'], $rest_prefix ); // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	/**
	 * Returns true if the request is an EDD API request.
	 *
	 * @since 3.3.0
	 * @return bool
	 */
	private static function is_api_request() {
		return defined( 'EDD_DOING_API' ) && EDD_DOING_API;
	}

	/**
	 * Returns true if the request is a block editor request.
	 *
	 * @since 3.3.0
	 * @return bool
	 */
	private static function is_editor_request() {
		return function_exists( 'get_current_screen' ) && ! empty( get_current_screen()->is_block_editor );
	}
}
