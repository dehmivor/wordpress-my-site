<?php
/**
 * Apple Pay domain-association lifecycle subscriber.
 *
 * Verifies the Apple Pay domain-association file exists in the document
 * root and matches the current request host on every admin page load (the
 * same cadence EDD Stripe uses). Surfaces a dismissible admin notice when
 * registration fails. Provides a `parse_request` fallback for hosts that
 * lock the document root, serving the cached file content from PHP.
 *
 * @package     EDD\Gateways\PayPal\V3\ApplePay
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Gateways\PayPal\V3\ApplePay;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\SubscriberInterface;
use EDD\Gateways\PayPal\PaymentMethods;
use EDD\Gateways\PayPal\V3\Onboarding;

/**
 * DomainSubscriber class.
 *
 * @since 3.6.9
 */
class DomainSubscriber implements SubscriberInterface {

	/**
	 * Subscribed events.
	 *
	 * @since 3.6.9
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return array(
			'admin_init'                                => 'verify_domain',
			'parse_request'                             => 'maybe_serve_file',
			'admin_notices'                             => 'render_error_notice',
			'wp_ajax_edd_paypal_v3_applepay_reverify'   => 'ajax_reverify',
		);
	}

	/**
	 * AJAX handler — drops PayPal's existing domain registration, clears
	 * local Apple Pay state, and re-installs from scratch so PayPal
	 * genuinely re-calls Apple's `.well-known` domain validation.
	 *
	 * Intended as an admin-only escape hatch from a "PayPal cached the
	 * registration but Apple never validated" state. Never invoked from
	 * customer-facing code paths.
	 *
	 * @since 3.6.9
	 *
	 * @return void
	 */
	public function ajax_reverify() {
		check_ajax_referer( 'edd_paypal_v3_applepay_reverify' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'easy-digital-downloads' ) );
		}

		try {
			DomainAssociation::reverify();
		} catch ( \Throwable $e ) {
			DomainAssociation::record_error( $e->getMessage() );
			wp_send_json_error( $e->getMessage() );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Apple Pay domain re-verified successfully.', 'easy-digital-downloads' ),
			)
		);
	}

	/**
	 * Verifies the domain-association file is in place and registered with
	 * PayPal. Runs on every admin page load (cheap when already valid).
	 *
	 * @since 3.6.9
	 * @since 3.7.0 Skips when the account is terminally ineligible
	 *                       and backs off after transient failures.
	 *
	 * @return void
	 */
	public function verify_domain() {
		if ( ! self::should_verify() ) {
			return;
		}

		if ( DomainAssociation::is_valid() ) {
			return;
		}

		// The account is terminally ineligible (no PAYMENT_METHODS
		// subscription). Stop retrying until the merchant reconnects or
		// re-verifies — both clear this flag.
		if ( get_option( DomainAssociation::INELIGIBLE_OPTION, '' ) ) {
			return;
		}

		// Back off after a transient failure so we don't hammer the Connect API on
		// every admin page load.
		$next_retry = (int) get_option( DomainAssociation::RETRY_OPTION, 0 );
		if ( $next_retry > time() ) {
			return;
		}

		try {
			DomainAssociation::install();
		} catch ( \Throwable $e ) {
			DomainAssociation::record_error( $e->getMessage() );
		}
	}

	/**
	 * Intercepts `/.well-known/apple-developer-merchantid-domain-association`
	 * requests when the static docroot file is missing, streams the cached
	 * content directly from PHP, and exits.
	 *
	 * @since 3.6.9
	 *
	 * @return void
	 */
	public function maybe_serve_file() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path        = parse_url( $request_uri, PHP_URL_PATH );

		if ( '/' . DomainAssociation::WELL_KNOWN_PATH !== $path ) {
			return;
		}

		// If the static file is present in the docroot, let Apache/Nginx serve
		// it without WP running — we shouldn't reach here in that case, but
		// guard anyway.
		if ( DomainAssociation::file_exists_in_docroot() ) {
			return;
		}

		$content = DomainAssociation::get_cached_content();

		if ( '' === $content ) {
			status_header( 404 );
			exit;
		}

		nocache_headers();
		header( 'Content-Type: text/plain; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, nofollow' );

		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — file content is opaque Apple-issued bytes.
		exit;
	}

	/**
	 * Renders an admin notice when the most recent registration attempt
	 * failed, so the store owner can take action.
	 *
	 * @since 3.6.9
	 *
	 * @return void
	 */
	public function render_error_notice() {
		$error = get_option( DomainAssociation::ERROR_OPTION, '' );

		if ( empty( $error ) || ! current_user_can( 'manage_shop_settings' ) ) {
			return;
		}

		// Limit to PayPal settings + main dashboard so the notice isn't
		// shouting on every admin screen.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'dashboard' !== $screen->id && false === strpos( (string) $screen->id, 'edd-settings' ) ) {
			return;
		}

		?>
		<div class="notice edd-notice notice-warning is-dismissible">
			<p>
				<strong><?php esc_html_e( 'PayPal Apple Pay:', 'easy-digital-downloads' ); ?></strong>
				<?php esc_html_e( 'Apple Pay domain registration was unsuccessful, so the Apple Pay button will not render at checkout until this is resolved.', 'easy-digital-downloads' ); ?>
			</p>
			<p>
				<code><?php echo esc_html( $error ); ?></code>
			</p>
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: 1: opening anchor tag, 2: closing anchor tag */
						__( 'You can retry from the %1$sPayPal settings%2$s, or contact your host if the server root is not writable.', 'easy-digital-downloads' ),
						'<a href="' . esc_url( admin_url( 'edit.php?post_type=download&page=edd-settings&tab=gateways&section=paypal_commerce' ) ) . '">',
						'</a>'
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Determines whether to run the verification on this request.
	 *
	 * Mirrors the same skip conditions EDD Stripe uses for its Apple Pay
	 * domain verification, plus a check that the Apple Pay payment method
	 * toggle is enabled in our Payment Methods grid.
	 *
	 * @since 3.6.9
	 *
	 * @return bool
	 */
	private static function should_verify(): bool {
		if ( \EDD\Utils\Request::is_request( array( 'ajax', 'cron' ) ) ) {
			return false;
		}

		// Registering a domain configures the gateway, so it needs the capability that
		// administers the store.
		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			return false;
		}

		// Skip during local/dev environments — Apple cannot verify a
		// non-public host and registration would fail noisily on every load.
		if ( edd_is_dev_environment() ) {
			return false;
		}

		if ( edd_is_test_mode() ) {
			return false;
		}

		if ( ! Onboarding::is_v3_onboarded() ) {
			return false;
		}

		if ( ! PaymentMethods::is_enabled( 'apple_pay', true ) ) {
			return false;
		}

		return true;
	}
}
