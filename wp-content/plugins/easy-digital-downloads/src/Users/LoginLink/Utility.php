<?php
/**
 * Login Link Utility Class
 *
 * General-purpose helpers for one-time login link: feature status, context/policy
 * checks, user resolution, URL building, and redirect logic.
 *
 * @package     EDD\Users\LoginLink
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.7
 */

namespace EDD\Users\LoginLink;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Utility class.
 *
 * @since 3.6.7
 */
class Utility {

	/**
	 * Determines if login link is enabled.
	 *
	 * @since 3.6.7
	 * @return bool
	 */
	public static function enabled(): bool {
		return (bool) edd_get_option( 'login_link', false );
	}

	/**
	 * Determines if login link is available for the current visitor.
	 *
	 * Returns true only when the feature is enabled AND the
	 * current user is not logged in.
	 *
	 * @since 3.6.7
	 * @return bool
	 */
	public static function is_available(): bool {
		return self::enabled() && ! is_user_logged_in();
	}

	/**
	 * Gets the centralized policy for login link visibility and messaging.
	 *
	 * @since 3.6.7
	 * @return array Policy array.
	 */
	public static function get_policy(): array {
		$registration_mode  = edd_get_option( 'logged_in_only', '' );
		$show_register_form = edd_get_option( 'show_register_form', 'none' );
		$is_logged_in       = is_user_logged_in();
		$enabled            = (bool) edd_get_option( 'login_link', false );

		$policy = array(
			'enabled'          => $enabled,
			'registrationMode' => $registration_mode,
			'showRegisterForm' => $show_register_form,
			'contexts'         => array(
				'checkout-email' => array(
					'enabled'        => false,
					'default_hidden' => true,
					'visibility'     => 'existing_account',
					'message'        => __( 'This email is already in use. Log in to your account or request a one-time login link instead.', 'easy-digital-downloads' ),
				),
				'checkout-login' => array(
					'enabled'        => false,
					'default_hidden' => false,
					'visibility'     => 'always',
					'message'        => __( 'Prefer a one-time login link instead?', 'easy-digital-downloads' ),
				),
			),
		);

		if ( $enabled && ! $is_logged_in ) {
			$has_login_form = in_array( $show_register_form, array( 'login', 'both' ), true );

			$policy['contexts']['checkout-email']['enabled'] = true;
			$policy['contexts']['checkout-login']['enabled'] = $has_login_form;
		}

		return $policy;
	}

	/**
	 * Gets the login link policy for a single context.
	 *
	 * @since 3.6.7
	 * @param string $context Login link context.
	 * @return array Context policy array.
	 */
	public static function get_context_policy( string $context ): array {
		$policy  = self::get_policy();
		$default = array(
			'enabled'        => false,
			'default_hidden' => false,
			'visibility'     => 'always',
			'message'        => '',
		);

		if ( empty( $policy['contexts'][ $context ] ) || ! is_array( $policy['contexts'][ $context ] ) ) {
			return $default;
		}

		return wp_parse_args( $policy['contexts'][ $context ], $default );
	}

	/**
	 * Checks if a login link context is enabled.
	 *
	 * @since 3.6.7
	 * @param string $context Login link context.
	 * @return bool
	 */
	public static function context_enabled( string $context ): bool {
		$context_policy = self::get_context_policy( $context );

		return ! empty( $context_policy['enabled'] );
	}

	/**
	 * Gets the configured message for a login link context.
	 *
	 * @since 3.6.7
	 * @param string $context Login link context.
	 * @return string
	 */
	public static function get_context_message( string $context ): string {
		$context_policy = self::get_context_policy( $context );

		return ! empty( $context_policy['message'] ) ? (string) $context_policy['message'] : '';
	}

	/**
	 * Determines whether a user is allowed to log in via login link.
	 *
	 * By default, users with administrative capabilities are excluded because
	 * the login link bypasses the password and could be used to silently
	 * authenticate as a high-privilege user if their email is compromised.
	 *
	 * Both manage_options (WordPress administrators) and manage_shop_settings
	 * (EDD shop managers) are checked, since either represents a level of
	 * privilege that should not bypass password authentication.
	 *
	 * @since 3.6.7
	 *
	 * @param \WP_User $user User object.
	 * @return bool True if the user is allowed to use login link.
	 */
	public static function user_allowed( \WP_User $user ): bool {
		if ( $user->has_cap( 'manage_options' ) || $user->has_cap( 'manage_shop_settings' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Clears the verification failure counter after a successful login.
	 *
	 * Only the verify-failure transient is removed. The per-IP send-rate
	 * transient is intentionally left intact so that a successful login
	 * does not reset the send window and allow immediate re-requesting
	 * of more login links from the same IP.
	 *
	 * @since 3.6.7
	 *
	 * @return void
	 */
	public static function cleanup_rate_limits(): void {
		$client_ip = edd_get_ip();
		if ( empty( $client_ip ) ) {
			return;
		}

		delete_transient( 'edd_login_link_fail_' . md5( $client_ip ) );
	}

	/**
	 * Resolves a WP_User from an email address or username.
	 *
	 * @since 3.6.7
	 *
	 * @param string $login Email address or username.
	 * @return \WP_User|false User object on success, false on failure.
	 */
	public static function get_user_from_login( string $login ) {
		$login = trim( $login );
		if ( empty( $login ) ) {
			return false;
		}

		if ( is_email( $login ) ) {
			$user = get_user_by( 'email', $login );
			if ( $user ) {
				return $user;
			}
		}

		return get_user_by( 'login', $login );
	}
}
