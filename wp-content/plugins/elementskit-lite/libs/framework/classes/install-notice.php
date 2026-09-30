<?php

namespace ElementsKit_Lite\Libs\Framework\Classes;

defined( 'ABSPATH' ) || exit;

/**
 * Displays and processes the consolidated WPMet plugin install notice.
 *
 * @since 3.9.5
 */
class Install_Notice {

	/**
	 * ElementsKit plugin basename.
	 *
	 * @since 3.9.5
	 * @var string
	 */
	const PLUGIN_FILE = 'elementskit-lite/elementskit-lite.php';

	/**
	 * Register notice, asset, and AJAX hooks.
	 *
	 * @since 3.9.5
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'render' ) );
		add_action( 'wp_ajax_elementskit_dismiss_auto_install_notice', array( $this, 'handle_dismiss' ) );
		add_action( 'wp_ajax_elementskit_confirm_auto_install_notice', array( $this, 'handle_confirm' ) );
	}

	/**
	 * Determine whether the install notice should be displayed.
	 *
	 * @since 3.9.5
	 *
	 * @return bool True when the notice should be displayed.
	 */
	private function should_render(): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( Install_Tracker::is_notice_dismissed( self::PLUGIN_FILE ) ) {
			return false;
		}

		if ( Utils::instance()->get_settings( 'ekit_user_consent_for_banner', 'yes' ) !== 'yes' ) {
			return false;
		}

		// Show when ElementsKit was auto-installed or completed its own onboarding without giving email.
		$registry       = Install_Tracker::get_registry();
		$auto_installed = isset( $registry['elementskit-lite/elementskit-lite.php'] );
		$own_onboarded  = (bool) get_option( 'elements_kit_onboard_status' );
		if ( ! $auto_installed && ! $own_onboarded ) {
			return false;
		}

		// If not auto-installed, suppress when email was already collected during own onboarding.
		if ( ! $auto_installed && Install_Tracker::has_collected_email() ) {
			return false;
		}

		// Only show on the ElementsKit dashboard and Get Help pages.
		$current_page    = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return in_array( $current_page, array( 'elementskit', 'elementskit-lite_get_help' ), true );
	}

	/**
	 * Enqueue the install notice styles and scripts.
	 *
	 * @since 3.9.5
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! $this->should_render() ) {
			return;
		}

		$asset_url = \ElementsKit_Lite::lib_url() . 'framework/assets/';
		$version   = \ElementsKit_Lite::version();

		wp_enqueue_style( 'elementskit-install-notice', $asset_url . 'css/install-notice.css', array(), $version );
		wp_enqueue_script( 'elementskit-install-notice', $asset_url . 'js/install-notice.js', array( 'jquery' ), $version, true );
		wp_localize_script(
			'elementskit-install-notice',
			'elementskitInstallNotice',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'elementskit_auto_install_notice_nonce' ),
				'applying' => __( 'Applying…', 'elementskit-lite' ),
			)
		);
	}

	/**
	 * Render the consolidated plugin install notice.
	 *
	 * @since 3.9.5
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! $this->should_render() ) {
			return;
		}

		?>
		<div class="notice notice-info wpmet-elementskit-auto-install-notice">
			<div class="wpmet-elementskit-notice-content">
				<h3>
					<?php esc_html_e( 'Get more from your new plugins 🚀', 'elementskit-lite' ); ?>
				</h3>
				<p>
					<?php esc_html_e( 'Allow us to use non-sensitive data from your site to improve our products and get personalized performance tips.', 'elementskit-lite' ); ?>
				</p>
			</div>
			<div class="wpmet-elementskit-notice-actions">
				<button class="button wpmet-elementskit-notice-btn-skip" id="wpmet-elementskit-notice-skip">
					<?php esc_html_e( 'Skip for Now', 'elementskit-lite' ); ?>
				</button>
				<button class="button button-primary wpmet-elementskit-notice-btn-confirm" id="wpmet-elementskit-notice-confirm">
					<?php esc_html_e( 'Confirm & Apply', 'elementskit-lite' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle the notice dismissal AJAX request.
	 *
	 * @since 3.9.5
	 *
	 * @return void
	 */
	public function handle_dismiss(): void {
		check_ajax_referer( 'elementskit_auto_install_notice_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		Install_Tracker::dismiss_notice( self::PLUGIN_FILE );
		wp_send_json_success();
	}

	/**
	 * Handle the notice confirmation AJAX request.
	 *
	 * Sends subscription data for ElementsKit before resolving the current
	 * install notice.
	 *
	 * @since 3.9.5
	 *
	 * @return void
	 */
	public function handle_confirm(): void {
		check_ajax_referer( 'elementskit_auto_install_notice_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$email = Install_Tracker::get_collected_email();
		$email = $email ? $email : $this->get_user_email();
		$email = $email ? $email : sanitize_email( get_option( 'admin_email' ) );

		Plugin_Data_Sender::instance()->sendEmailSubscribeData(
			'plugin-subscribe',
			array(
				'email' => $email,
				'slug'  => 'elementskit',
			)
		);

		Install_Tracker::store_collected_email( $email );
		Install_Tracker::dismiss_notice( self::PLUGIN_FILE );
		wp_send_json_success();
	}

	/**
	 * Return the admin email address stored in plugin options, if available.
	 *
	 * @since 3.9.5
	 *
	 * @return string A sanitized email address, or an empty string when not set.
	 */
	private function get_user_email(): string {
		$options = get_option( 'elementskit_options', array() );

		if ( empty( $options['settings']['newsletter_email'] ) ) {
			return '';
		}

		return sanitize_email( $options['settings']['newsletter_email'] );
	}
}
