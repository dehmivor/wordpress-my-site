<?php
/**
 * OAuth Authorize.
 *
 * The `/pa-oauth/authorize` endpoint — validates the authorization request,
 * gates it behind a WordPress login + administrator consent, and on approval
 * issues a single-use, PKCE-bound authorization code before redirecting back
 * to the client. Served as a front-end request so browser cookie auth applies.
 *
 * Client and redirect-URI validation happens before anything is echoed or
 * redirected. That ordering is the open-redirect control — keep it.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\OAuth;

use PremiumAddons\Admin\Includes\Admin_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Class Authorize.
 *
 * @since 4.11.90
 */
class Authorize {

	/**
	 * The browser-facing authorize path.
	 *
	 * @var string
	 */
	const PATH = '/pa-oauth/authorize';

	/**
	 * Consent form nonce action.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'pa_oauth_consent';

	/**
	 * Capability required to approve a connection.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Serve the authorize endpoint when the request path matches; no-op
	 * otherwise. Hooked on parse_request (priority 0) only when OAuth is
	 * registered.
	 *
	 * @return void
	 */
	public static function maybe_serve() {

		if ( Bootstrap::home_path() . self::PATH !== Bootstrap::request_path() ) {
			return;
		}

		self::security_headers();

		if ( 'POST' === strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- HTTP verb, compared against a literal.
			self::handle_post();
		} else {
			self::handle_get();
		}
	}

	/**
	 * Anti-framing + no-cache headers, sent before any output on every path
	 * through this endpoint. A front-end request gets neither admin_init nor
	 * login_init, so the framing protection wp-admin pages inherit for free
	 * must be sent by hand — without it the consent screen is clickjackable.
	 *
	 * @return void
	 */
	private static function security_headers() {

		if ( headers_sent() ) {
			return;
		}

		header( 'X-Frame-Options: DENY' );
		header( "Content-Security-Policy: frame-ancestors 'none'" );
		nocache_headers();
	}

	/**
	 * GET: validate, gate behind login + capability, render the consent screen.
	 *
	 * @return void
	 */
	private static function handle_get() {

		$params       = self::request_params( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public authorization endpoint; params are validated below and nothing mutates on GET.
		$client_id    = isset( $params['client_id'] ) ? $params['client_id'] : '';
		$redirect_uri = isset( $params['redirect_uri'] ) ? $params['redirect_uri'] : '';
		$client       = '' === $client_id ? null : Store::get_client( $client_id );

		// Client + redirect must validate before redirect_uri is trusted as a target.
		if ( null === $client || '' === $redirect_uri || ! self::redirect_registered( $client, $redirect_uri ) ) {
			self::error_page( __( 'Invalid client or redirect URI for this connection request.', 'premium-addons-for-elementor' ) );
		}

		$state = isset( $params['state'] ) ? $params['state'] : '';
		$valid = self::validate_params( $params );

		if ( is_wp_error( $valid ) ) {
			self::redirect_error( $redirect_uri, $valid->get_error_code(), $state );
		}

		if ( ! is_user_logged_in() ) {
			wp_redirect( wp_login_url( self::current_url() ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- login URL built by core from the current request.
			exit;
		}

		if ( ! Admin_Helper::check_user_can( self::CAPABILITY ) ) {
			self::error_page( __( 'Only administrators can authorize an MCP connection on this site.', 'premium-addons-for-elementor' ) );
		}

		self::render_consent( array_merge( $valid, array( 'client_name' => $client['client_name'] ) ) );
	}

	/**
	 * POST: record the approve/deny decision. Everything is re-checked — login,
	 * capability, nonce, client lookup, redirect match — nothing is taken from
	 * the hidden fields on trust.
	 *
	 * @return void
	 */
	private static function handle_post() {

		if ( ! is_user_logged_in() || ! Admin_Helper::check_user_can( self::CAPABILITY ) ) {
			self::error_page( __( 'You are not allowed to authorize this connection.', 'premium-addons-for-elementor' ) );
		}

		$params = self::request_params( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified immediately below.
		$nonce  = isset( $params['_pa_oauth_nonce'] ) ? $params['_pa_oauth_nonce'] : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			self::error_page( __( 'Security check failed. Please start the connection again from your AI client.', 'premium-addons-for-elementor' ) );
		}

		$client_id    = isset( $params['client_id'] ) ? $params['client_id'] : '';
		$redirect_uri = isset( $params['redirect_uri'] ) ? $params['redirect_uri'] : '';
		$client       = '' === $client_id ? null : Store::get_client( $client_id );

		if ( null === $client || '' === $redirect_uri || ! self::redirect_registered( $client, $redirect_uri ) ) {
			self::error_page( __( 'Invalid client or redirect URI for this connection request.', 'premium-addons-for-elementor' ) );
		}

		$state = isset( $params['state'] ) ? $params['state'] : '';
		$valid = self::validate_params( $params );

		if ( is_wp_error( $valid ) ) {
			self::redirect_error( $redirect_uri, $valid->get_error_code(), $state );
		}

		if ( 'approve' !== ( isset( $params['decision'] ) ? $params['decision'] : '' ) ) {
			self::redirect_error( $redirect_uri, 'access_denied', $state );
		}


		$code = Store::issue_code(
			array(
				'client_id'      => $client['client_id'],
				'user_id'        => get_current_user_id(),
				'redirect_uri'   => $redirect_uri,
				'code_challenge' => $valid['code_challenge'],
				'scopes'         => Bootstrap::SCOPE,
			)
		);

		if ( is_wp_error( $code ) ) {
			self::redirect_error( $redirect_uri, 'server_error', $state );
		}

		wp_redirect( // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- redirect target validated against the client registration above.
			self::build_redirect(
				$redirect_uri,
				array(
					'code'  => $code,
					'state' => $state,
				)
			)
		);
		exit;
	}

	/**
	 * Validate the non-client authorization parameters.
	 *
	 * @param array $params Request params.
	 * @return array|\WP_Error Normalized params, or an error whose code is an
	 *                         OAuth error slug (safe to return to redirect_uri).
	 */
	public static function validate_params( array $params ) {

		if ( 'code' !== ( isset( $params['response_type'] ) ? $params['response_type'] : '' ) ) {
			return new \WP_Error( 'unsupported_response_type', 'Only response_type=code is supported.' );
		}

		if ( 'S256' !== ( isset( $params['code_challenge_method'] ) ? $params['code_challenge_method'] : '' ) || '' === ( isset( $params['code_challenge'] ) ? $params['code_challenge'] : '' ) ) {
			return new \WP_Error( 'invalid_request', 'PKCE with S256 is required.' );
		}

		return array(
			'client_id'             => $params['client_id'],
			'redirect_uri'          => $params['redirect_uri'],
			'response_type'         => 'code',
			'code_challenge'        => $params['code_challenge'],
			'code_challenge_method' => 'S256',
			'state'                 => isset( $params['state'] ) ? $params['state'] : '',
		);
	}

	/**
	 * Whether a redirect URI is registered for the client.
	 *
	 * @param array  $client       Client with a redirect_uris array.
	 * @param string $redirect_uri Candidate.
	 * @return bool
	 */
	public static function redirect_registered( array $client, $redirect_uri ) {

		foreach ( (array) $client['redirect_uris'] as $registered ) {
			if ( is_string( $registered ) && Util::redirect_uri_matches( $registered, $redirect_uri ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Append query args to a redirect URI (handles existing query strings).
	 *
	 * @param string $redirect_uri Base URI.
	 * @param array  $args         Args; empty values are dropped.
	 * @return string
	 */
	public static function build_redirect( $redirect_uri, array $args ) {

		$pairs = array();

		foreach ( $args as $key => $value ) {
			if ( '' !== (string) $value ) {
				$pairs[] = rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
			}
		}

		if ( empty( $pairs ) ) {
			return $redirect_uri;
		}

		return $redirect_uri . ( false === strpos( $redirect_uri, '?' ) ? '?' : '&' ) . implode( '&', $pairs );
	}

	/**
	 * Read string params from a superglobal, unslashed. Values are validated
	 * and escaped downstream.
	 *
	 * @param array $source $_GET or $_POST.
	 * @return array
	 */
	private static function request_params( array $source ) {

		$params = array();

		foreach ( $source as $key => $value ) {
			if ( is_string( $value ) ) {
				$params[ (string) $key ] = (string) wp_unslash( $value );
			}
		}

		return $params;
	}

	/**
	 * Render the consent screen, then exit.
	 *
	 * @param array $ctx Params: client_id, client_name, redirect_uri, code_challenge, state, scope.
	 * @return void
	 */
	private static function render_consent( array $ctx ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $ctx is read by the required template.

		require PREMIUM_ADDONS_PATH . 'includes/abilities/oauth/templates/consent.php';
		exit;
	}

	/**
	 * Redirect back to the client with an OAuth error, then exit.
	 *
	 * @param string $redirect_uri Validated redirect URI.
	 * @param string $error        OAuth error code.
	 * @param string $state        Opaque state to echo back.
	 * @return void
	 */
	private static function redirect_error( $redirect_uri, $error, $state ) {

		wp_redirect( // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- redirect target validated against the client registration before this is reachable.
			self::build_redirect(
				$redirect_uri,
				array(
					'error' => $error,
					'state' => $state,
				)
			)
		);
		exit;
	}

	/**
	 * Output a minimal HTML error page (used when there is no safe redirect
	 * target), then exit.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	private static function error_page( $message ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $message is read by the required template.

		if ( ! headers_sent() ) {
			status_header( 400 );
			header( 'Content-Type: text/html; charset=utf-8' );
		}

		require PREMIUM_ADDONS_PATH . 'includes/abilities/oauth/templates/error.php';
		exit;
	}

	/**
	 * The absolute URL of the current request (for the login return).
	 *
	 * @return string
	 */
	private static function current_url() {

		$host = isset( $_SERVER['HTTP_HOST'] ) ? (string) wp_unslash( $_SERVER['HTTP_HOST'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passed through esc_url_raw below.
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passed through esc_url_raw below.

		return esc_url_raw( ( is_ssl() ? 'https' : 'http' ) . '://' . $host . $uri );
	}
}
