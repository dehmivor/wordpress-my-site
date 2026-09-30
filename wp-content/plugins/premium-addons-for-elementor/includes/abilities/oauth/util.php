<?php
/**
 * OAuth Util.
 *
 * Pure OAuth 2.1 primitives — token generation, hashing, PKCE verification,
 * base64url, redirect-URI matching. No WordPress, no database, so every
 * security-relevant comparison is unit-testable as-is.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Class Util.
 *
 * @since 4.11.90
 */
class Util {

	/**
	 * base64url-encode raw bytes (RFC 4648 §5, no padding).
	 *
	 * @param string $bin Raw bytes.
	 * @return string
	 */
	public static function base64url_encode( $bin ) {
		return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- base64url token encoding (RFC 4648 §5), not obfuscation.
	}

	/**
	 * Generate an opaque token: 32 random bytes → 43-char base64url string.
	 *
	 * @return string
	 */
	public static function generate_token() {
		return self::base64url_encode( random_bytes( 32 ) );
	}

	/**
	 * Generate a public client id (`pa_` + 24 hex chars).
	 *
	 * @return string
	 */
	public static function generate_client_id() {
		return 'pa_' . bin2hex( random_bytes( 12 ) );
	}

	/**
	 * Hash a token/code for at-rest storage. Tokens are high-entropy, so a plain
	 * SHA-256 keeps lookups a single indexed query.
	 *
	 * @param string $token Raw token or code.
	 * @return string 64-char hex digest.
	 */
	public static function hash_token( $token ) {
		return hash( 'sha256', $token );
	}

	/**
	 * Verify a PKCE code_verifier against a stored code_challenge.
	 *
	 * Only S256 is accepted — `plain` cannot be negotiated. The verifier must be
	 * 43-128 chars (RFC 7636 §4.1). Comparison is constant-time.
	 *
	 * @param string $verifier  The client's code_verifier.
	 * @param string $challenge The stored code_challenge.
	 * @param string $method    Challenge method; must be 'S256'.
	 * @return bool
	 */
	public static function verify_pkce( $verifier, $challenge, $method = 'S256' ) {

		if ( 'S256' !== $method ) {
			return false;
		}

		$length = strlen( $verifier );

		if ( '' === $challenge || $length < 43 || $length > 128 ) {
			return false;
		}

		return hash_equals( $challenge, self::base64url_encode( hash( 'sha256', $verifier, true ) ) );
	}

	/**
	 * Whether two redirect URIs match. Exact string match, with the native-app
	 * loopback exception (RFC 8252 §7.3): for http://127.0.0.1 / http://[::1] /
	 * http://localhost the port may differ, since native clients bind an
	 * ephemeral local port.
	 *
	 * @param string $registered A registered redirect URI.
	 * @param string $given      The redirect URI presented in the request.
	 * @return bool
	 */
	public static function redirect_uri_matches( $registered, $given ) {

		if ( hash_equals( $registered, $given ) ) {
			return true;
		}

		$reg = parse_url( $registered ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure helper, no WordPress.
		$req = parse_url( $given ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure helper, no WordPress.

		if ( ! is_array( $reg ) || ! is_array( $req ) ) {
			return false;
		}

		$loopback = array( '127.0.0.1', '::1', 'localhost' );
		$reg_host = isset( $reg['host'] ) ? strtolower( trim( $reg['host'], '[]' ) ) : '';
		$req_host = isset( $req['host'] ) ? strtolower( trim( $req['host'], '[]' ) ) : '';

		return 'http' === ( isset( $reg['scheme'] ) ? $reg['scheme'] : '' )
			&& 'http' === ( isset( $req['scheme'] ) ? $req['scheme'] : '' )
			&& in_array( $reg_host, $loopback, true )
			&& $reg_host === $req_host
			&& ( isset( $reg['path'] ) ? $reg['path'] : '/' ) === ( isset( $req['path'] ) ? $req['path'] : '/' )
			&& ( isset( $reg['query'] ) ? $reg['query'] : '' ) === ( isset( $req['query'] ) ? $req['query'] : '' );
	}

	/**
	 * Whether a redirect URI may be registered: absolute, valid scheme, no
	 * fragment (RFC 6749 §3.1.2), plaintext http only for loopback hosts.
	 * https and custom app schemes (RFC 8252 §7.1) are allowed. URIs are only
	 * ever used in a 302 to the user's browser — never fetched server-side — so
	 * no DNS resolution happens here.
	 *
	 * @param string $uri Candidate URI.
	 * @return bool
	 */
	public static function is_allowed_redirect_uri( $uri ) {

		$parts = parse_url( $uri ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure helper, no WordPress.

		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || isset( $parts['fragment'] ) ) {
			return false;
		}

		$scheme = strtolower( (string) $parts['scheme'] );

		if ( ! preg_match( '/^[a-z][a-z0-9+.\-]*$/', $scheme ) ) {
			return false;
		}

		if ( 'https' === $scheme ) {
			return ! empty( $parts['host'] );
		}

		if ( 'http' === $scheme ) {
			$host = isset( $parts['host'] ) ? strtolower( trim( $parts['host'], '[]' ) ) : '';
			return in_array( $host, array( '127.0.0.1', '::1', 'localhost' ), true );
		}

		// Native applications use custom schemes, but browser-executable schemes
		// would turn a post-consent redirect into script execution on this site.
		return ! in_array( $scheme, array( 'about', 'blob', 'data', 'file', 'javascript', 'vbscript' ), true );
	}
}
