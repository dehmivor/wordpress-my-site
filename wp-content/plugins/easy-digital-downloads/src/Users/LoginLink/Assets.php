<?php
/**
 * Login Link Assets Class
 *
 * Handles script/style registration, enqueuing, and template output
 * for login link prompts.
 *
 * @package     EDD\Users\LoginLink
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.7
 */

namespace EDD\Users\LoginLink;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\SubscriberInterface;

/**
 * Assets class.
 *
 * Registers front-end scripts and styles for the login link feature
 * and renders the login link prompt in various form contexts.
 *
 * @since 3.6.7
 */
class Assets implements SubscriberInterface {

	/**
	 * Returns an array of events that this subscriber wants to listen to.
	 *
	 * @since 3.6.7
	 * @return array
	 */
	public static function get_subscribed_events() {
		return array(
			'wp_enqueue_scripts'              => 'do_register_assets',
			'edd_checkout_login_fields_after' => 'render_checkout_prompt',
			'edd_purchase_form_after_email'   => array( 'render_email_prompt', 5 ),
		);
	}

	/**
	 * Performs the actual script and style registration.
	 *
	 * Called from the `wp_enqueue_scripts` hook and on-demand from
	 * `enqueue()` when a prompt renders before that hook fires.
	 *
	 * @since 3.6.7
	 * @return void
	 */
	public static function do_register_assets() {
		if ( ! Utility::is_available() ) {
			return;
		}

		if ( ! wp_script_is( 'edd-login-link', 'registered' ) ) {
			wp_register_script(
				'edd-login-link',
				edd_get_assets_url( 'js/frontend/' ) . 'login-link.js',
				array(),
				EDD_VERSION,
				edd_scripts_in_footer()
			);
		}

		// Enqueue on checkout pages so the script is available for AJAX-rendered prompts.
		if ( edd_is_checkout() ) {
			self::enqueue();
		}
	}

	/**
	 * Enqueues login link assets when a prompt is rendered.
	 *
	 * @since 3.6.7
	 * @return void
	 */
	public static function enqueue() {
		if ( ! Utility::is_available() ) {
			return;
		}

		// Don't load the login link UI if the IP has exhausted send requests.
		if ( is_wp_error( Send\Validate::check_ip() ) ) {
			return;
		}

		// Register on demand if wp_enqueue_scripts hasn't fired yet.
		if ( ! wp_script_is( 'edd-login-link', 'registered' ) ) {
			self::do_register_assets();
		}

		wp_enqueue_script( 'edd-login-link' );

		static $localized = false;
		if ( $localized ) {
			return;
		}
		$localized = true;

		wp_localize_script(
			'edd-login-link',
			'edd_login_link_vars',
			array(
				'ajaxurl' => esc_url_raw( edd_get_ajax_url() ),
				'enabled' => Utility::enabled() ? '1' : '0',
				'nonce'   => wp_create_nonce( 'edd-login-link' ),
				'policy'  => Utility::get_policy(),
				'strings' => array(
					'sent'           => __( 'Login link sent! Check your email.', 'easy-digital-downloads' ),
					'error'          => __( 'Something went wrong. Please try again.', 'easy-digital-downloads' ),
					'field_required' => __( 'Please enter your email address or username.', 'easy-digital-downloads' ),
				),
			)
		);
	}

	/**
	 * Renders the login link prompt for the checkout-login context.
	 *
	 * @since 3.6.7
	 * @return void
	 */
	public function render_checkout_prompt() {
		if ( ! Utility::is_available() ) {
			return;
		}

		if ( is_wp_error( Send\Validate::check_ip() ) ) {
			return;
		}

		self::prompt( 'checkout-login' );
	}

	/**
	 * Renders the login link prompt for the checkout-email context.
	 *
	 * @since 3.6.7
	 * @return void
	 */
	public function render_email_prompt() {
		if ( ! Utility::is_available() ) {
			return;
		}

		if ( is_wp_error( Send\Validate::check_ip() ) ) {
			return;
		}

		self::prompt( 'checkout-email' );
	}

	/**
	 * Outputs the login link prompt markup.
	 *
	 * @since 3.6.7
	 * @param string $context Context for the prompt.
	 * @param string $message Prompt message.
	 * @param bool   $hidden  Whether the prompt should be hidden by default.
	 * @return void
	 */
	private static function prompt( $context, $message = '', $hidden = null ) {
		if ( ! Utility::context_enabled( $context ) ) {
			return;
		}

		self::enqueue();

		$context_policy = Utility::get_context_policy( $context );

		if ( '' === $message ) {
			$message = Utility::get_context_message( $context );
		}

		if ( ! is_bool( $hidden ) ) {
			$hidden = ! empty( $context_policy['default_hidden'] );
		}

		?>
		<div class="edd-login-link" data-edd-login-link="<?php echo esc_attr( $context ); ?>"<?php echo $hidden ? ' hidden="hidden"' : ''; ?>>
			<p class="edd-login-link__message"><?php echo esc_html( $message ); ?></p>
			<button type="button" class="edd-submit edd-button-secondary edd-login-link__button" data-edd-login-link-send>
				<?php esc_html_e( 'Email me a login link', 'easy-digital-downloads' ); ?>
			</button>
		</div>
		<?php
	}
}
