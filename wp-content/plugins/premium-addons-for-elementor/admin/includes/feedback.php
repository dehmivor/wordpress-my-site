<?php
/**
 * PA Admin Bar
 */

namespace PremiumAddons\Admin\Includes;

use PremiumAddons\Includes\Helper_Functions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Feedback
 */
class Feedback {

	/**
	 * Class instance
	 *
	 * @var instance
	 */
	private static $instance = null;

	/**
	 * Constructor for the class
	 */
	private function __construct() {

		add_action( 'admin_footer-plugins.php', array( $this, 'create_popup' ) );
		add_action( 'wp_ajax_pa_handle_feedback_action', array( $this, 'send' ) );
	}


	public static function send() {

		check_ajax_referer( 'pa-feedback-nonce', 'nonce' );

		$response = array( 'success' => false );

		if ( ! isset( $_POST['data'] ) || ! is_array( $_POST['data'] ) ) {
			wp_send_json_error( 'Invalid data format' );
		}

		$data = array_map( 'sanitize_text_field', wp_unslash( $_POST['data'] ) );

		$reason      = '';
		$suggestions = null;
		$anonymous   = false;

		if ( isset( $data['feedback'] ) ) {
			$reason      = $data['feedback'];
			$suggestions = isset( $data['suggestions'] ) ? $data['suggestions'] : null;
			$anonymous   = isset( $data['anonymous'] ) ? (bool) $data['anonymous'] : false;
		}

		if ( ! is_string( $reason ) || empty( $reason ) ) {
			return false;
		}

		$wordpress = self::collect_wordpress_data( true );

		$wordpress['deactivated_plugin']['uninstall_reason']  = $reason;
		$wordpress['deactivated_plugin']['uninstall_details'] = '';

		if ( ! empty( $suggestions ) ) {
			$wordpress['deactivated_plugin']['uninstall_details'] .= $suggestions;
		}

		if ( ! $anonymous ) {
			$wordpress['deactivated_plugin']['uninstall_details'] .= ( empty( $wordpress['deactivated_plugin']['uninstall_details'] ) ? '' : PHP_EOL . PHP_EOL ) . 'Domain: ' . self::get_site_domain();

			$wordpress['used_widgets'] = array(
				'widgets' => self::get_usage_count(),
			);
		}

		$body = array(
			'user'      => self::collect_user_data( $anonymous ),
			'wordpress' => $wordpress,
		);

		$api_url = 'https://feedbackpa.leap13.com/wp-json/feedback/v2/add';

		$response = wp_safe_remote_request(
			$api_url,
			array(
				'headers'     => array(
					'Content-Type' => 'application/json',
				),
				'body'        => wp_json_encode( $body ),
				'timeout'     => 20,
				'method'      => 'POST',
				'httpversion' => '1.1',
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( 'REQUEST ERR' );

		}

		if ( ! isset( $response['response'] ) || ! is_array( $response['response'] ) ) {
			wp_send_json_error( 'REQUEST UNKNOWN' );

		}

		if ( ! isset( $response['body'] ) ) {
			wp_send_json_error( 'REQUEST PAYLOAD EMPTY' );

		}

		wp_send_json_success( ( $response['body'] ) );
	}

	/**
	 * Method generates Feedback popup
	 */
	public function create_popup() {

		$plugin_data = get_plugin_data( PREMIUM_ADDONS_FILE );

		?>
			<div class="pa-deactivation-popup hidden" data-type="wrapper" data-slug="<?php echo $plugin_data['TextDomain']; ?>">
				<div class="overlay">
					<div class="close"><i class="dashicons dashicons-no"></i></div>
					<div class="body">
						<section class="title-wrap">
							<div class="pa-img-wrap">
								<img src="<?php echo esc_url( PREMIUM_ADDONS_URL . 'admin/images/pa-logo-symbol.png' ); ?>">
							</div>
							<?php echo esc_html( __( 'Sorry to see you go', 'premium-addons-for-elementor' ) ); ?>
						</section>
						<section class="messages-wrap">
							<p><?php echo esc_html( __( 'Would you quickly give us your reason for doing so?', 'premium-addons-for-elementor' ) ); ?></p>
						</section>
						<section class="options-wrap">
							<label>
								<input type="radio" name="feedback" value="temp">
								<?php echo esc_html( __( 'Temporary deactivation', 'premium-addons-for-elementor' ) ); ?>
							</label>
							<label>
								<input type="radio" name="feedback" value="setup">
								<?php echo esc_html( __( 'Set up is too difficult', 'premium-addons-for-elementor' ) ); ?>
							</label>
							<label>
								<input type="radio" name="feedback" value="e-issues">
								<?php echo esc_html( __( 'Causes issues with Elementor', 'premium-addons-for-elementor' ) ); ?>
							</label>
							<label>
								<input type="radio" name="feedback" value="documentation">
								<?php echo esc_html( __( 'Lack of documentation', 'premium-addons-for-elementor' ) ); ?>
							</label>
							<label>
								<input type="radio" name="feedback" value="features">
								<?php echo esc_html( __( 'Not the features I wanted', 'premium-addons-for-elementor' ) ); ?>
							</label>
							<label>
								<input type="radio" name="feedback" value="better-plugin">
								<?php echo esc_html( __( 'Found a better plugin', 'premium-addons-for-elementor' ) ); ?>
							</label>
							<label>
								<input type="radio" name="feedback" value="incompatibility">
								<?php echo esc_html( __( 'Incompatible with theme or plugin', 'premium-addons-for-elementor' ) ); ?>
							</label>
							<label>
								<input type="radio" name="feedback" value="maintenance">
								<?php echo esc_html( __( 'Other', 'premium-addons-for-elementor' ) ); ?>
							</label>
						</section>
						<section class="messages-wrap hidden" data-feedback>
							<p class="hidden" data-feedback="setup"><?php echo esc_html( __( 'What was the difficult part?', 'premium-addons-for-elementor' ) ); ?></p>
							<p class="hidden" data-feedback="e-issues"><?php echo esc_html( __( 'What was the issue?', 'premium-addons-for-elementor' ) ); ?></p>
							<p class="hidden" data-feedback="documentation"><?php echo esc_html( __( 'What can we describe more?', 'premium-addons-for-elementor' ) ); ?></p>
							<p class="hidden" data-feedback="features"><?php echo esc_html( __( 'How could we improve?', 'premium-addons-for-elementor' ) ); ?></p>
							<p class="hidden" data-feedback="better-plugin"><?php echo esc_html( __( 'Can you mention it?', 'premium-addons-for-elementor' ) ); ?></p>
							<p class="hidden" data-feedback="incompatibility"><?php echo esc_html( __( 'With what plugin or theme is incompatible?', 'premium-addons-for-elementor' ) ); ?></p>
							<p class="hidden" data-feedback="maintenance"><?php echo esc_html( __( 'Please specify:', 'premium-addons-for-elementor' ) ); ?></p>
						</section>
						<section class="options-wrap hidden" data-feedback>
							<label>
								<textarea name="suggestions" rows="2"></textarea>
							</label>
						</section>
						<section class="messages-wrap" data-feedback>

							<p class="options-wrap pa-info-notice">
								<?php
								$link = Helper_Functions::get_campaign_link( 'https://premiumaddons.com/docs/fix-elementor-editor-panel-loading-issues/', 'plugins-page', 'wp-dash', 'deactivate-form' );
								echo __( 'Having speed issues or Elementor editor not loading? Your website PHP limits might be the reason. Here\'s ', 'premium-addons-for-elementor' ) .
										sprintf( '<a target="_blank" href="%s">%s</a>', $link, __( 'how to increase the PHP limits', 'premium-addons-for-elementor' ) );
								?>
							</p>

							<p><?php echo esc_html( __( 'Would you like to share your e-mail and elements usage with us so that we can write you back?', 'premium-addons-for-elementor' ) ); ?></p>
						</section>
						<section class="options-wrap hidden" data-feedback>
							<label>
								<input type="checkbox" name="anonymous" value="1">
							<?php echo esc_html( __( 'No, I\'d like to stay anonymous', 'premium-addons-for-elementor' ) ); ?>
							</label>
						</section>

						<section class="buttons-wrap clearfix">
							<button class="pa-deactivate-btn pa-submit-btn" data-action="deactivation"><?php echo esc_html( __( 'Submit & Deactivate', 'premium-addons-for-elementor' ) ); ?></button>
							<button class="pa-deactivate-btn pa-skip-btn" data-action="skip"><?php echo esc_html( __( 'Skip & Deactivate', 'premium-addons-for-elementor' ) ); ?></button>
						</section>
					</div>

				</div>
			</div>
			<?php
	}

	private static function collect_wordpress_data( $detailed = true ) {

		$current_plugin = get_plugin_data( PREMIUM_ADDONS_FILE );

		// Plugin data
		$data = array(
			'deactivated_plugin' => array(
				'version'    => $current_plugin['Version'],
				'memory'     => 'Memory: ' . size_format( wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) ) ),
				'time'       => 'Time: ' . ini_get( 'max_execution_time' ),
				'install'    => 'Activation: ' . get_option( 'pa_install_time' ),
				'deactivate' => 'Deactivation: ' . gmdate( 'j F, Y', time() ),
			),
		);

		if ( Helper_Functions::check_papro_version() ) {
			$data['deactivated_plugin']['papro'] = 'PAPRO: ' . PREMIUM_PRO_ADDONS_VERSION . get_option( 'papro_license_key', false );
		}

		if ( $detailed ) {

			$data['extra'] = array(
				'locale'  => ( get_bloginfo( 'version' ) >= 4.7 ) ? get_user_locale() : get_locale(),
				'themes'  => self::get_themes(),
				'plugins' => self::get_plugins(),
			);

		}

		return $data;
	}

	/**
	 * Get a list of installed plugins
	 */
	private static function get_plugins() {

		if ( ! function_exists( 'get_plugins' ) ) {
			include ABSPATH . '/wp-admin/includes/plugin.php';
		}

		$option = get_option( 'active_plugins', array() );
		$active = array();

		foreach ( $option as $id ) {
			$id = explode( '/', $id );
			$id = ucwords( str_replace( '-', ' ', $id[0] ) );

			$active[] = $id;
		}

		return array(
			// 'installed' => $installed,
			'active' => $active,
		);
	}

	/**
	 * Get current themes
	 *
	 * @return array
	 */
	private static function get_themes() {

		$theme = wp_get_theme();

		return array(
			'active' => array(
				'name' => $theme->get( 'Name' ),
			),
		);
	}

	/**
	 * Collect user data.
	 *
	 * @param bool $anonymous
	 *
	 * @return array
	 */
	private static function collect_user_data( $anonymous ) {
		$user = wp_get_current_user();

		$return = array(
			'email'      => '',
			'first_name' => '',
			'last_name'  => '',
			'domain'     => '',
		);

		if ( $user && ! $anonymous ) {
			$return['email']      = $user->user_email;
			$return['first_name'] = $user->first_name;
			$return['last_name']  = $user->last_name;
			$return['domain']     = self::get_site_domain();
		}

		return $return;
	}

	public static function get_usage_count() {

		$usage_count = array();

		// First, try to get data from Elementor options.
		$all_usage = get_option( 'elementor_controls_usage', array() );

		if ( ! empty( $all_usage ) && is_array( $all_usage ) ) {
			$usage_count = self::get_elements_from_controls_usage( $all_usage );
		}

		return $usage_count;
	}

	/**
	 * Extract Elements Usage From Usage
	 *
	 * @since 4.10.61
	 *
	 * @param array $all_elements All usage data.
	 */
	private static function get_elements_from_controls_usage( $all_elements ) {

		$used_elements = array();

		foreach ( $all_elements as $doc_type => $elements ) {

			if ( ! is_array( $elements ) ) {
				continue;
			}

			// Iterate through element types within each document type
			foreach ( $elements as $element_type => $element_data ) {
				if ( ! is_array( $element_data ) || ! isset( $element_data['count'] ) ) {
					continue;
				}

				$element_name = $element_type;
				$count        = (int) $element_data['count'];

				// Only count our elements.
				if ( strpos( $element_name, 'premium-' ) === 0 ) {
					$used_elements[ $element_name ] = isset( $used_elements[ $element_name ] )
							? $used_elements[ $element_name ] + $count
							: $count;
				}

				// Check for extension usage in controls data
				if ( isset( $element_data['controls'] ) && is_array( $element_data['controls'] ) ) {
					self::get_addons_from_controls_usage( $element_data['controls'], $used_elements );
				}
			}
		}

		return $used_elements;
	}

	/**
	 * Extract Addons Usage From Controls Usage
	 *
	 * @since 4.10.61
	 *
	 * @param array $controls       Controls usage data.
	 * @param array $used_elements  Reference to used elements array.
	 */
	private static function get_addons_from_controls_usage( $controls, &$used_elements ) {

		$map = array(
			'pa_display_conditions_switcher' => 'display-conditions',
			'premium_eq_height_switcher'     => 'equal-height',
			'premium_fe_switcher'            => 'floating-effects',
			'premium_glass_switcher'         => 'glass-effect',
			'premium_global_divider_sw'      => 'shape-divider',
			'premium_tooltip_switcher'       => 'tooltips',
			'premium_wrapper_link_switcher'  => 'wrapper-link',
			'premium_global_badge_switcher'  => 'badge',
			'premium_global_cursor_switcher' => 'custom-cursor',
			'premium_mscroll_switcher'       => 'mscroll',
			'premium_blob_switcher'          => 'blob',
			'premium_gradient_switcher'      => 'animated-gradient',
			'premium_kenburns_switcher'      => 'kenburns',
			'premium_lottie_switcher'        => 'lottie',
			'premium_parallax_switcher'      => 'parallax',
			'premium_particles_switcher'     => 'particles',
		);

		array_walk_recursive(
			$controls,
			function ( $value, $key ) use ( &$used_elements, $map ) {

				if ( ! isset( $map[ $key ] ) ) {
					return;
				}

				$element_name = $map[ $key ];

				$used_elements[ $element_name ] = isset( $used_elements[ $element_name ] )
					? $used_elements[ $element_name ] + $value
					: $value;
			}
		);
	}

	private static function get_site_domain() {
		return function_exists( 'parse_url' ) ? parse_url( get_home_url(), PHP_URL_HOST ) : false;
	}

	/**
	 * Creates and returns an instance of the class
	 *
	 * @since 3.20.9
	 * @access public
	 *
	 * @return object
	 */
	public static function get_instance() {

		if ( ! isset( self::$instance ) ) {

			self::$instance = new self();

		}

		return self::$instance;
	}
}
