<?php
/**
 * PayPal Apple Pay domain-association handling.
 *
 * Apple Pay verifies that a merchant owns a domain by GETting
 * `/.well-known/apple-developer-merchantid-domain-association` and matching
 * its contents against the value PayPal registers with Apple on the
 * merchant's behalf. The file's contents are unique per PayPal merchant
 * identity and per environment (sandbox vs live), so we fetch the bytes from
 * EDD Connect at first need, cache them, and serve them at the well-known
 * path — preferring a static copy in the document root for speed, with a
 * `parse_request` PHP fallback for hosts that lock the docroot.
 *
 * Mirrors the EDD Stripe pattern (`includes/gateways/stripe/includes/payment-methods/apple-pay.php`)
 * with one structural difference: Stripe ships the file with the plugin
 * because Stripe is the Apple-Pay-merchant-of-record for everyone, while
 * PayPal acts as a per-merchant identity that varies by store.
 *
 * @package     EDD\Gateways\PayPal\V3\ApplePay
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Gateways\PayPal\V3\ApplePay;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Gateways\PayPal\Gateway;
use EDD\Gateways\PayPal\V3\ConnectAPI;
use EDD\Utils\FileSystem;

/**
 * Apple Pay domain-association handler.
 *
 * @since 3.6.9
 */
class DomainAssociation {

	/**
	 * Well-known path Apple requires for verification.
	 *
	 * @since 3.6.9
	 */
	const WELL_KNOWN_PATH = '.well-known/apple-developer-merchantid-domain-association';

	/**
	 * Option storing the host name the file was last written for.
	 *
	 * @since 3.6.9
	 */
	const HOST_OPTION = 'edd_paypal_applepay_domain';

	/**
	 * Option storing the most recent registration error message, if any.
	 *
	 * @since 3.6.9
	 */
	const ERROR_OPTION = 'edd_paypal_applepay_domain_error';

	/**
	 * Option flag marking the PayPal account as terminally ineligible for
	 * Apple Pay (the Connect API returned `applepay_not_available`).
	 *
	 * Once set, verification stops retrying until the merchant reconnects or
	 * re-verifies — there is nothing the store can do per-request to change a
	 * missing PAYMENT_METHODS subscription, so retrying on every admin page
	 * load just burns Connect API calls and error logs.
	 *
	 * @since 3.7.0
	 */
	const INELIGIBLE_OPTION = 'edd_paypal_applepay_ineligible';

	/**
	 * Option storing the earliest Unix timestamp the next registration retry
	 * is allowed, used to back off after a transient (non-terminal) failure.
	 *
	 * @since 3.7.0
	 */
	const RETRY_OPTION = 'edd_paypal_applepay_next_retry';

	/**
	 * Transient storing the file contents fetched from the Connect service.
	 *
	 * Used as a fallback when the docroot copy isn't readable (PHP serving
	 * path) and to avoid re-fetching from the Connect service on every admin page load.
	 *
	 * @since 3.6.9
	 */
	const CONTENT_TRANSIENT = 'edd_paypal_applepay_domain_association';

	/**
	 * Returns true when the docroot copy of the association file exists
	 * AND the stored host matches the current request host.
	 *
	 * @since 3.6.9
	 *
	 * @return bool
	 */
	public static function is_valid(): bool {
		return self::file_exists_in_docroot() && self::has_matching_host();
	}

	/**
	 * Checks whether the static file exists in the document root.
	 *
	 * @since 3.6.9
	 *
	 * @return bool
	 */
	public static function file_exists_in_docroot(): bool {
		$path = self::get_docroot_file_path();

		return ! empty( $path ) && FileSystem::file_exists( $path );
	}

	/**
	 * Checks whether the stored host matches the current request host.
	 *
	 * @since 3.6.9
	 *
	 * @return bool
	 */
	private static function has_matching_host(): bool {
		$stored  = get_option( self::HOST_OPTION, '' );
		$current = self::get_current_host();

		return ! empty( $stored ) && $stored === $current;
	}

	/**
	 * Fetches the association file content from the Connect service and writes it to
	 * the document root, then registers the domain with PayPal so Apple
	 * can verify against it.
	 *
	 * @since 3.6.9
	 *
	 * @throws \RuntimeException When fetch, filesystem write, or registration fails.
	 *
	 * @return void
	 */
	public static function install(): void {
		try {
			$content = self::fetch_content();

			if ( empty( $content ) ) {
				throw new \RuntimeException( __( 'Empty Apple Pay domain-association file returned from proxy.', 'easy-digital-downloads' ) );
			}

			self::cache_content( $content );
			self::write_to_docroot( $content );
			self::register_with_paypal( self::get_current_host() );
		} catch ( \RuntimeException $e ) {
			if ( ! get_option( self::INELIGIBLE_OPTION, '' ) ) {
				update_option( self::RETRY_OPTION, time() + 4 * HOUR_IN_SECONDS, false );
			}
			throw $e;
		}

		update_option( self::HOST_OPTION, self::get_current_host() );
		delete_option( self::ERROR_OPTION );
		delete_option( self::RETRY_OPTION );
	}

	/**
	 * Tears down all local Apple Pay domain-association state.
	 *
	 * Called when the merchant deletes their PayPal connection — the
	 * stored host, any recorded registration error, and the docroot file
	 * all belong to the previous merchant's Apple Pay registration on
	 * PayPal's side. Leaving them behind would either suppress the
	 * re-registration that a fresh connection needs (host-match short-
	 * circuit) or serve a stale .well-known file the new merchant can't
	 * vouch for.
	 *
	 * The PayPal-issued file content is not merchant-specific, so we
	 * intentionally leave the CONTENT_TRANSIENT alone — re-installing
	 * after a fresh connect doesn't need a fresh fetch.
	 *
	 * Also clears the ineligible and retry-backoff guards so a reconnecting
	 * merchant (whose account may now be approved) gets a fresh attempt.
	 *
	 * @since 3.6.9
	 * @since 3.7.0 Clears the ineligible and retry-backoff guards.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		delete_option( self::HOST_OPTION );
		delete_option( self::ERROR_OPTION );
		delete_option( self::INELIGIBLE_OPTION );
		delete_option( self::RETRY_OPTION );

		$path = self::get_docroot_file_path();
		if ( '' !== $path && file_exists( $path ) ) {
			/**
			 * Silenced — the install() write also tolerates missing
			 * permissions, and a leftover file from a previous host is
			 * less harmful than a fatal here. The next install() will
			 * rewrite if/when the merchant reconnects.
			 */
			@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * Forces PayPal to re-validate the domain with Apple.
	 *
	 * PayPal's register-domain endpoint short-circuits with
	 * `DOMAIN_ALREADY_REGISTERED` once a domain has been registered for the
	 * merchant — that's normally what we want, but it also means a failed
	 * Apple-side validation never gets retried. This flow drops the
	 * registration on PayPal, clears local state, and re-installs from
	 * scratch so PayPal genuinely re-calls Apple's validation endpoint.
	 *
	 * Order matters: deregister on PayPal first so a failure leaves local
	 * state untouched (no drift). Only after PayPal confirms removal do we
	 * uninstall locally and then re-install — that way a mid-flight failure
	 * always leaves both sides either fully populated or both empty, never
	 * half-registered.
	 *
	 * Intended to be invoked manually by an admin (e.g. via a settings-page
	 * action), not from any customer-facing code path.
	 *
	 * @since 3.6.9
	 *
	 * @throws \RuntimeException When deregistration or re-install fails.
	 *
	 * @return void
	 */
	public static function reverify(): void {
		// Clear the retry/ineligible guards up front so the admin re-verify
		// action always attempts a fresh registration, even if deregister
		// throws before uninstall() runs.
		delete_option( self::INELIGIBLE_OPTION );
		delete_option( self::RETRY_OPTION );

		// Drop the host that was actually registered, which is what PayPal holds. A store that
		// registered under a different host before the domain was derived from the site address
		// would otherwise leave that registration behind.
		$registered = (string) get_option( self::HOST_OPTION, '' );

		self::deregister_with_paypal( '' !== $registered ? $registered : self::get_current_host() );
		self::uninstall();
		self::install();
	}

	/**
	 * Returns the cached association file contents.
	 *
	 * Falls back to fetching from the Connect service when the transient is missing.
	 *
	 * @since 3.6.9
	 *
	 * @return string The file content, or an empty string on failure.
	 */
	public static function get_cached_content(): string {
		$content = get_transient( self::CONTENT_TRANSIENT );

		if ( false !== $content && is_string( $content ) && '' !== $content ) {
			return $content;
		}

		try {
			$content = self::fetch_content();
			self::cache_content( $content );
			return $content;
		} catch ( \Throwable $e ) {
			return '';
		}
	}

	/**
	 * Returns the absolute path Apple expects the file at, under the
	 * server's document root.
	 *
	 * @since 3.6.9
	 *
	 * @return string Path, or empty when document root cannot be determined.
	 */
	private static function get_docroot_file_path(): string {
		$docroot = isset( $_SERVER['DOCUMENT_ROOT'] ) ? untrailingslashit( $_SERVER['DOCUMENT_ROOT'] ) : '';

		if ( empty( $docroot ) ) {
			return '';
		}

		return $docroot . '/' . self::WELL_KNOWN_PATH;
	}

	/**
	 * Records a registration error so it can be surfaced as an admin notice.
	 *
	 * @since 3.6.9
	 *
	 * @param string $message Error message.
	 * @return void
	 */
	public static function record_error( string $message ): void {
		update_option( self::ERROR_OPTION, $message );
	}

	/**
	 * Fetches the file content from the Connect domain-association endpoint.
	 *
	 * @since 3.6.9
	 *
	 * @throws \RuntimeException When the Connect service is unreachable or returns an error.
	 *
	 * @return string File content.
	 */
	private static function fetch_content(): string {
		$mode = Gateway::get_paypal_mode();
		$api  = new ConnectAPI( $mode );

		$response = $api->get( '/v3/paypal/applepay/domain-association?mode=' . rawurlencode( $mode ) );

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( $response->get_error_message() );
		}

		if ( ConnectAPI::is_error( $response ) ) {
			throw new \RuntimeException( ConnectAPI::get_error_message( $response ) );
		}

		// The Connect service returns the raw file content under `file` (or as the body
		// itself depending on the Connect service's response shape). Accept either.
		if ( is_array( $response ) ) {
			if ( ! empty( $response['file'] ) ) {
				return (string) $response['file'];
			}
			if ( ! empty( $response['content'] ) ) {
				return (string) $response['content'];
			}
		}

		return is_string( $response ) ? $response : '';
	}

	/**
	 * Stores the file content as a transient for the PHP-serving fallback.
	 *
	 * @since 3.6.9
	 *
	 * @param string $content File content.
	 * @return void
	 */
	private static function cache_content( string $content ): void {
		/**
		 * 30-day transient. Apple's verification cadence is generally weekly
		 * or less; this is long enough to avoid round-trips on every request
		 * while still letting natural rotations refresh the cache.
		 */
		set_transient( self::CONTENT_TRANSIENT, $content, 30 * DAY_IN_SECONDS );
	}

	/**
	 * Writes the file to `{docroot}/.well-known/apple-developer-merchantid-domain-association`.
	 *
	 * @since 3.6.9
	 *
	 * @param string $content File content.
	 *
	 * @throws \RuntimeException When the directory or file can't be created.
	 *
	 * @return void
	 */
	private static function write_to_docroot( string $content ): void {
		$docroot = isset( $_SERVER['DOCUMENT_ROOT'] ) ? untrailingslashit( $_SERVER['DOCUMENT_ROOT'] ) : '';

		if ( empty( $docroot ) ) {
			throw new \RuntimeException( __( 'Document root could not be determined for Apple Pay domain association.', 'easy-digital-downloads' ) );
		}

		$dir = trailingslashit( $docroot ) . '.well-known';

		if ( ! FileSystem::file_exists( $dir ) && ! FileSystem::mkdir( $dir ) ) {
			throw new \RuntimeException( __( 'Unable to create .well-known directory in the server root.', 'easy-digital-downloads' ) );
		}

		$path    = $dir . '/apple-developer-merchantid-domain-association';
		$written = FileSystem::put_contents( $path, $content );

		if ( false === $written ) {
			throw new \RuntimeException( __( 'Unable to write Apple Pay domain-association file to the server root.', 'easy-digital-downloads' ) );
		}
	}

	/**
	 * Calls the Connect service to register the domain with PayPal.
	 *
	 * @since 3.6.9
	 *
	 * @param string $domain Domain name to register (no protocol).
	 *
	 * @throws \RuntimeException When the Connect call fails.
	 *
	 * @return void
	 */
	private static function register_with_paypal( string $domain ): void {
		if ( '' === $domain ) {
			throw new \RuntimeException( __( 'No domain available for Apple Pay registration.', 'easy-digital-downloads' ) );
		}

		$api      = new ConnectAPI( Gateway::get_paypal_mode() );
		$response = $api->post( '/v3/paypal/applepay/register-domain', array( 'domain' => $domain ) );

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( $response->get_error_message() );
		}

		if ( ConnectAPI::is_error( $response ) ) {
			// A missing PAYMENT_METHODS subscription is terminal — the Connect API
			// returns the same `applepay_not_available` on every retry, so flag
			// the account ineligible and stop attempting until it reconnects.
			if ( 'applepay_not_available' === ConnectAPI::get_error_code( $response ) ) {
				update_option( self::INELIGIBLE_OPTION, '1', false );
			}
			throw new \RuntimeException( ConnectAPI::get_error_message( $response ) );
		}
	}

	/**
	 * Calls the Connect service to deregister the domain from PayPal.
	 *
	 * Treats "not registered" as success — the post-condition is "PayPal
	 * does not have this domain registered", which is already true.
	 *
	 * @since 3.6.9
	 *
	 * @param string $domain Domain name to deregister (no protocol).
	 *
	 * @throws \RuntimeException When the Connect call fails.
	 *
	 * @return void
	 */
	private static function deregister_with_paypal( string $domain ): void {
		if ( '' === $domain ) {
			throw new \RuntimeException( __( 'No domain available for Apple Pay deregistration.', 'easy-digital-downloads' ) );
		}

		$api      = new ConnectAPI( Gateway::get_paypal_mode() );
		$response = $api->delete( '/v3/paypal/applepay/register-domain', array( 'domain' => $domain ) );

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( $response->get_error_message() );
		}

		if ( ConnectAPI::is_error( $response ) ) {
			throw new \RuntimeException( ConnectAPI::get_error_message( $response ) );
		}
	}

	/**
	 * Returns the host Apple Pay is registered for, normalized.
	 *
	 * Taken from the site's configured address rather than the request, since this is the
	 * hostname the gateway registers and a store has one of those however it was reached.
	 *
	 * @since 3.6.9
	 * @since 3.7.1 Derived from home_url() instead of the request host.
	 *
	 * @return string
	 */
	private static function get_current_host(): string {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$host = strtolower( trim( $host ) );

		// Strip any port suffix — Apple checks the hostname only.
		$host = preg_replace( '/:\d+$/', '', $host );

		return is_string( $host ) ? $host : '';
	}
}
