<?php
/**
 * PA Admin Notices.
 */

namespace PremiumAddons\Admin\Includes;

use PremiumAddons\Includes\Helper_Functions;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Class Admin_Notices
 */
class Admin_Notices {

	/**
	 * Premium Addons Stories
	 *
	 * @var stories
	 */
	private $stories = array();

	/**
	 * Class object
	 *
	 * @var instance
	 */
	private static $instance = null;

	/**
	 * Elementor slug
	 *
	 * @var elementor
	 */
	private static $elementor = 'elementor';

	/**
	 * Notices Keys
	 *
	 * @var notices
	 */
	private static $notices = null;

	/**
	 * Review-notice state, held in one autoloaded option so the check costs no
	 * query. '1' means the user opted out for good; any other numeric value is a
	 * timestamp to stay quiet until; '0' means show it.
	 *
	 * @var string
	 */
	const REVIEW_OPTION = 'pa_review_notice';

	/**
	 * Dashboard news cache. Deliberately not keyed on the plugin version: the
	 * feed is not version-specific, and including it invalidated the cache on
	 * every update, so the first Dashboard load after each one blocked on a
	 * remote request.
	 *
	 * @var string
	 */
	const STORIES_TRANSIENT = 'pa_stories';

	/**
	 * Constructor for the class
	 */
	public function __construct() {

		add_action( 'admin_init', array( $this, 'init' ) );

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		add_action( 'wp_ajax_pa_reset_admin_notice', array( $this, 'reset_admin_notice' ) );

		add_action( 'wp_ajax_pa_dismiss_admin_notice', array( $this, 'dismiss_admin_notice' ) );

		self::$notices = array(
			'pa-review',
			'pa-claude-design-not',
		);

		if ( Helper_Functions::check_hide_notifications() ) {
			return;
		}

		add_action( 'wp_dashboard_setup', array( $this, 'show_story_widget' ), 111 );
	}

	/**
	 * Init
	 *
	 * Init required functions
	 *
	 * The redirection happens on the first admin page after activation ( the plugins page ).
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function init() {

		if ( wp_doing_ajax() ) {
			return;
		}

		$this->handle_review_notice();

		if ( Helper_Functions::check_elementor_version() && get_transient( 'pa_activation_redirect' ) ) {

			delete_transient( 'pa_activation_redirect' );

			$redirect = add_query_arg(
				array(
					'page' => 'pa-setup-wizard', // this mean it should've been added first.
				),
				admin_url( 'admin.php' )
			);

			wp_safe_redirect( $redirect );

			exit;
		}
	}

	/**
	 * Init notices check functions.
	 */
	public function admin_notices() {

		// Skip rendering notices during AJAX requests.
		if ( wp_doing_ajax() ) {
			return;
		}

		$this->required_plugins_check();

		// Make sure "Already did" was not clicked before, and that the notice is
		// not snoozed. Both live in one autoloaded option: an absent option costs
		// a lookup on every admin page view, and a transient costs two.
		$review_state = self::get_notice_state( self::REVIEW_OPTION );

		if ( '1' !== $review_state && (int) $review_state < time() ) {
			$this->show_review_notice();
		}

		if ( Helper_Functions::check_hide_notifications() ) {
			return;
		}

		$this->get_claude_design_notice();
	}

	/**
	 * Handle Review Notice
	 *
	 * Checks if review message is dismissed.
	 *
	 * @access public
	 * @return void
	 */
	public function handle_review_notice() {

		if ( ! isset( $_GET['pa_review'] ) ) {
			return;
		}

		$pa_review = sanitize_text_field( wp_unslash( $_GET['pa_review'] ) );

		if ( 'opt_out' === $pa_review ) {
			check_admin_referer( 'opt_out' );

			update_option( self::REVIEW_OPTION, '1', true );
		}

		wp_safe_redirect( remove_query_arg( 'pa_review' ) );

		exit;
	}

	/**
	 * Required plugin check
	 *
	 * Shows an admin notice when Elementor is missing.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function required_plugins_check() {

		// Early return if Elementor is already active.
		if ( Helper_Functions::check_elementor_version() ) {
			return;
		}

		$elementor_path = sprintf( '%1$s/%1$s.php', self::$elementor );

		$message = '';

		if ( ! Helper_Functions::is_plugin_installed( $elementor_path ) ) {

			if ( ! Admin_Helper::check_user_can( 'install_plugins' ) ) {
				return;
			}

			$install_url = wp_nonce_url( self_admin_url( sprintf( 'update.php?action=install-plugin&plugin=%s', self::$elementor ) ), 'install-plugin_elementor' );

			$message = sprintf( '<p>%s</p>', __( 'Premium Addons for Elementor is not working because you need to Install Elementor plugin.', 'premium-addons-for-elementor' ) );

			$message .= sprintf( '<p><a href="%s" class="button-primary">%s</a></p>', $install_url, __( 'Install Now', 'premium-addons-for-elementor' ) );

		} elseif ( Admin_Helper::check_user_can( 'activate_plugins' ) ) {

			$activation_url = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $elementor_path . '&amp;plugin_status=all&amp;paged=1&amp;s', 'activate-plugin_' . $elementor_path );

			$message = '<p>' . __( 'Premium Addons for Elementor is not working because you need to activate Elementor plugin.', 'premium-addons-for-elementor' ) . '</p>';

			$message .= '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', $activation_url, __( 'Activate Now', 'premium-addons-for-elementor' ) ) . '</p>';
		} else {
			return;
		}

		if ( ! empty( $message ) ) {
			$this->render_admin_notices( $message );
		}
	}

	/**
	 * Get Review Text
	 *
	 * Gets admin review notice HTML.
	 *
	 * @since 2.8.4
	 * @access public
	 *
	 * @param string $review_url plugin page.
	 * @param string $optout_url redirect url.
	 */
	public function get_review_text( $review_url, $optout_url ) {

		$notice = sprintf(
			'<p>' . __( 'Could we take just 2 minutes of your time? We\'d be incredibly grateful if you could give ', 'premium-addons-for-elementor' ) .
			'<b>' . __( 'Premium Addons for Elementor', 'premium-addons-for-elementor' ) . '</b> a 5 Stars Rating on WordPress.org. Your support helps us continue creating even more amazing free features in the future!</p>
            <div>
                <a class="button pa-review-btn button-primary" href="%s" target="_blank"><span>' . __( 'Sure, leave a Review', 'premium-addons-for-elementor' ) . '</span></a>
                <a class="button" href="%2$s"><span>' . __( 'I Already Did', 'premium-addons-for-elementor' ) . '</span></a>
                <a class="button button-secondary pa-notice-reset"><span>' . __( 'Maybe Later', 'premium-addons-for-elementor' ) . '</span></a>
            </div>',
			$review_url,
			$optout_url
		);

		return $notice;
	}

	/**
	 * Checks if review admin notice is dismissed
	 *
	 * @since 2.6.8
	 * @return void
	 */
	public function show_review_notice() {

		$review_url = 'https://wordpress.org/support/plugin/premium-addons-for-elementor/reviews/#new-post';

		$optout_url = wp_nonce_url( add_query_arg( 'pa_review', 'opt_out' ), 'opt_out' );
		?>

		<div class="error pa-notice-wrap pa-review-notice" data-notice="pa-review">
			<div class="pa-img-wrap">
				<img src="<?php echo esc_url( PREMIUM_ADDONS_URL . 'admin/images/pa-logo-symbol.png' ); ?>">
			</div>
			<div class="pa-text-wrap">
				<?php echo wp_kses_post( $this->get_review_text( $review_url, $optout_url ) ); ?>
			</div>
			<div class="pa-notice-close">
				<a href="<?php echo esc_url( $optout_url ); ?>"><span class="dashicons dashicons-dismiss"></span></a>
			</div>
		</div>

		<?php
	}

	/**
	 * Read a notice-state option, seeding it when absent.
	 *
	 * An option that does not exist is not in the autoloaded set, so every read
	 * costs a query. Writing the default once means every later read is served
	 * from the options cache.
	 *
	 * @since 4.11.90
	 * @access private
	 *
	 * @param string $option Option name.
	 * @return string Current state.
	 */
	private static function get_notice_state( $option ) {

		$state = get_option( $option );

		if ( false === $state ) {
			add_option( $option, '0', '', true );
			$state = '0';
		}

		return (string) $state;
	}

	/**
	 * Keep the review notice quiet until $seconds from now.
	 *
	 * @since 4.11.97
	 * @access public
	 *
	 * @param int $seconds Quiet period length.
	 * @return void
	 */
	public static function snooze_review_notice( $seconds ) {

		update_option( self::REVIEW_OPTION, (string) ( time() + $seconds ), true );
	}

	/**
	 * Checks if the current user dismissed a per-user notice.
	 *
	 * @since 4.11.106
	 * @access private
	 *
	 * @param string $key notice key.
	 * @return bool
	 */
	private static function is_dismissed_by_user( $key ) {

		$dismissed = get_user_meta( get_current_user_id(), 'pa_dismissed_notices', true );

		return is_array( $dismissed ) && in_array( $key, $dismissed, true );
	}

	/**
	 * Claude Design → Elementor notice, with the sale CTA per license tier.
	 * Admins only, dismissed per user.
	 *
	 * @since 4.11.106
	 * @access private
	 *
	 * @return void
	 */
	private function get_claude_design_notice() {

		$notice_key = 'pa-claude-design-not';

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( self::is_dismissed_by_user( $notice_key ) ) {
			return;
		}

		$tier = Admin_Helper::get_license_tier();

		$doc_link = Helper_Functions::get_campaign_link( 'https://premiumaddons.com/docs/convert-claude-design-to-elementor/', 'claude-design-notification', 'wp-dash', 'claude-design', $tier );

		$claude_link  = Helper_Functions::get_campaign_link( 'https://premiumaddons.com/docs/connect-claude-to-build-wordpress-elementor-pages/', 'claude-design-notification', 'wp-dash', 'connect-ai', $tier );
		$chatgpt_link = Helper_Functions::get_campaign_link( 'https://premiumaddons.com/docs/connect-chatgpt-to-wordpress-elementor-website/', 'claude-design-notification', 'wp-dash', 'connect-ai', $tier );

		$sale_line = '';
		$sale_btn  = '';

		if ( 'free' === $tier ) {

			$sale_link = Helper_Functions::get_campaign_link( 'https://premiumaddons.com/get/papro/#get-pa-pro', 'claude-design-notification', 'wp-dash', 'summer26', 'free' );
			$sale_line = __( 'With Pro, Claude can also build with Elementor Pro and any other widget on your site. Summer sale: 30% off.', 'premium-addons-for-elementor' );
			$sale_btn  = __( 'Get Pro – 30% Off', 'premium-addons-for-elementor' );

		} elseif ( 'pro' === $tier ) {

			$sale_link = Helper_Functions::get_campaign_link( 'https://premiumaddons.com/docs/upgrade-premium-addons-license/', 'claude-design-notification', 'wp-dash', 'summer26', 'pro' );
			$sale_line = __( 'Pay only the difference to go Lifetime, with an extra 30% off.', 'premium-addons-for-elementor' );
			$sale_btn  = __( 'Upgrade to Lifetime', 'premium-addons-for-elementor' );
		}

		$html  = '<div class="pa-notice-row">';
		$html .= '<img class="pa-notice-logo" src="' . esc_url( PREMIUM_ADDONS_URL . 'admin/images/pa-logo-symbol.png' ) . '" alt="" width="40" height="40">';
		$html .= '<div class="pa-notice-text">';
		$html .= '<strong class="pa-notice-title">' . esc_html__( 'Convert Claude Design into Elementor Pages', 'premium-addons-for-elementor' ) . '</strong>';
		$html .= '<span class="pa-notice-desc">' . esc_html__( 'Export your Claude Design as HTML, drop it into a Claude chat, and it gets rebuilt on your site as an editable Elementor page.', 'premium-addons-for-elementor' ) . ' ';
		$html .= sprintf(
			/* translators: 1: ChatGPT guide link opening tag, 2: Claude guide link opening tag, 3: link closing tag. */
			esc_html__( 'Not connected yet? See how to connect %1$sChatGPT%3$s or %2$sClaude%3$s to your site.', 'premium-addons-for-elementor' ),
			'<a href="' . esc_url( $chatgpt_link ) . '" target="_blank" rel="noopener">',
			'<a href="' . esc_url( $claude_link ) . '" target="_blank" rel="noopener">',
			'</a>'
		);
		$html .= '</span>';

		if ( '' !== $sale_line ) {
			$html .= '<span class="pa-notice-sub">' . esc_html( $sale_line ) . '</span>';
		}

		$html .= '</div>';
		$html .= '<div class="pa-notice-actions">';
		$html .= '<a class="button button-primary" href="' . esc_url( $doc_link ) . '" target="_blank" rel="noopener">' . esc_html__( 'See How It Works', 'premium-addons-for-elementor' ) . '</a>';

		if ( '' !== $sale_btn ) {
			$html .= '<a class="button" href="' . esc_url( $sale_link ) . '" target="_blank" rel="noopener">' . esc_html( $sale_btn ) . '</a>';
		}

		$html .= '</div>';
		$html .= '</div>';

		wp_admin_notice(
			$html,
			array(
				'type'               => 'info',
				'dismissible'        => true,
				'paragraph_wrap'     => false,
				'additional_classes' => array( 'pa-notice', 'pa-claude-design-notice' ),
				'attributes'         => array( 'data-notice' => $notice_key ),
			)
		);
	}

	/**
	 * Renders an admin notice error message
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param string $message notice text.
	 * @param string $class notice class.
	 * @param string $handle notice handle.
	 *
	 * @return void
	 */
	private function render_admin_notices( $message, $class = '', $handle = '' ) {
		?>
			<div class="error pa-new-feature-notice <?php echo esc_attr( $class ); ?>" data-notice="<?php echo esc_attr( $handle ); ?>">
				<?php echo wp_kses_post( $message ); ?>
			</div>
		<?php
	}



	/**
	 * Register admin scripts
	 *
	 * @since 3.2.8
	 * @access public
	 */
	public function admin_enqueue_scripts() {

		// Skip loading scripts during AJAX or REST requests.
		if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		wp_enqueue_script(
			'pa-dashboard',
			PREMIUM_ADDONS_URL . 'admin/assets/js/pa-dashboard.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_localize_script(
			'pa-dashboard',
			'PaNoticeSettings',
			array(
				'ajaxurl'        => esc_url( admin_url( 'admin-ajax.php' ) ),
				'nonce'          => wp_create_nonce( 'pa-notice-nonce' ),
				'feedback_nonce' => wp_create_nonce( 'pa-feedback-nonce' ),
			)
		);
	}

	/**
	 * Set transient for admin notice
	 *
	 * @since 3.2.8
	 * @access public
	 *
	 * @return void
	 */
	public function reset_admin_notice() {

		check_ajax_referer( 'pa-notice-nonce', 'nonce' );

		if ( ! Admin_Helper::check_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$key = isset( $_POST['notice'] ) ? sanitize_text_field( wp_unslash( $_POST['notice'] ) ) : '';

		if ( ! empty( $key ) && in_array( $key, self::$notices, true ) ) {

			self::snooze_review_notice( WEEK_IN_SECONDS );

			wp_send_json_success();

		} else {

			wp_send_json_error();

		}
	}

	/**
	 * Dismiss admin notice
	 *
	 * @since 3.11.7
	 * @access public
	 *
	 * @return void
	 */
	public function dismiss_admin_notice() {

		check_ajax_referer( 'pa-notice-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$key = isset( $_POST['notice'] ) ? sanitize_text_field( wp_unslash( $_POST['notice'] ) ) : '';

		if ( ! empty( $key ) && in_array( $key, self::$notices, true ) ) {

			if ( 'pa-claude-design-not' === $key ) {

				// Per user: other admins keep seeing it.
				$uid       = get_current_user_id();
				$dismissed = get_user_meta( $uid, 'pa_dismissed_notices', true );
				$dismissed = is_array( $dismissed ) ? $dismissed : array();

				if ( ! in_array( $key, $dismissed, true ) ) {
					$dismissed[] = $key;
					update_user_meta( $uid, 'pa_dismissed_notices', $dismissed );
				}

				wp_send_json_success();
			}

			// Make sure new features notices will not appear again.
			if ( false !== strpos( $key, 'not' ) ) {
				update_option( $key, '1', true );
			} else {
				// Was set_transient( 'pa-review', ... ), a key nothing ever read —
				// the review notice reappeared immediately after dismissal.
				self::snooze_review_notice( 20 * DAY_IN_SECONDS );
			}

			wp_send_json_success();

		} else {

			wp_send_json_error();

		}
	}

	/**
	 * Get PA Stories
	 *
	 * Gets a list of the latest three blog posts
	 *
	 * @since 4.10.64
	 *
	 * @access public
	 */
	public function get_pa_stories() {

		$stories = get_transient( self::STORIES_TRANSIENT );

		if ( ! $stories ) {

			$api_url = 'https://premiumaddons.com/wp-json/stories/v2/get';

			$response = wp_remote_get(
				$api_url,
				array(
					'timeout'   => 3,
					'sslverify' => true,
				)
			);

			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				set_transient( self::STORIES_TRANSIENT, true, DAY_IN_SECONDS );
				return false;
			}

			$body    = wp_remote_retrieve_body( $response );
			$stories = json_decode( $body, true );

			set_transient( self::STORIES_TRANSIENT, $stories, WEEK_IN_SECONDS );

		}

		$this->stories = $stories;

		return $stories;
	}

	public function show_story_widget() {

		$stories = $this->get_pa_stories();

		if ( ! is_array( $stories ) || empty( $stories ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'pa-stories',
			__( 'Premium Addons News', 'premium-addons-for-elementor' ),
			array( $this, 'show' ),
			null,
			null,
			'column3',
			'core'
		);
	}


	public function show() {

		$stories = $this->stories;

		$time = time();

		$papro_path = 'premium-addons-pro/premium-addons-pro-for-elementor.php';

		$highlight = false;

		if ( 'pro' === Admin_Helper::get_license_tier() ) {

			$highlight = true;
			array_unshift(
				$stories['posts'],
				array(
					'title' => 'Upgrade to Premium Addons Pro Lifetime, Pay the Difference & Save 30% Today!',
					'link'  => Helper_Functions::get_campaign_link( 'https://premiumaddons.com/docs/upgrade-premium-addons-license/', 'wp-dash', 'summer26-dash-widget', 'summer26' ),
				)
			);
		}

		?>
			<style>
				.pa-banners-grid {
					margin-bottom: 10px;
				}

				.pa-stories-banner {
					position: relative;
				}

				.pa-stories-banner a {
					position: absolute;
					inset: 0;
				}

				.pa-story-img-container img {
					width: 100%;
					display: block;
				}

				.pa-news-post {
					margin-bottom: 5px;
				}

				.pa-news-post a {
					font-weight: 500;
					color: #0073aa;
					text-decoration: none;
					padding-bottom: 5px;
					display: inline-block;
				}

				.pa-dashboard-widget-block {
					width: 100%;
				}

				.pa-footer-bar {
					border-top: 1px solid #eee;
					padding-top: 1rem;
					display: flex;
					justify-content: space-between;
				}

				.pa-dashboard-widget-block a {
					text-decoration: none;
					font-size: 13px;
					color: #007cba;
				}

				.pa-dashboard-widget-block .dashicons {
					vertical-align: middle;
					font-size: 17px;
				}
			</style>


			<div class="pa-banners-grid">

				<?php foreach ( $stories['banners'] as $index => $banner ) : ?>

					<?php if ( isset( $banner['end'] ) && $time < $banner['end'] ) : ?>

						<div class="pa-stories-banner">
							<div class="pa-story-img-container">
								<img src="<?php echo esc_url( $banner['image'] ); ?>" alt="<?php echo esc_attr( $banner['description'] ); ?>">
							</div>
							<a href="<?php echo esc_url( Helper_Functions::get_campaign_link( $banner['link'], 'wp-dash', 'dash-widget', 'summer26' ) ); ?>" target="_blank" title="<?php echo esc_attr( $banner['description'] ); ?>"></a>
						</div>

					<?php endif; ?>

				<?php endforeach; ?>

			</div>


			<div class="pa-posts-grid">

				<?php foreach ( $stories['posts'] as $index => $post ) : ?>

					<div class="pa-news-post">
						<a style="<?php echo 0 === $index && $highlight ? 'color: #93003f' : ''; ?>" target="_blank" href="<?php echo esc_url( $post['link'] ); ?>">
							<?php echo wp_kses_post( $post['title'] ); ?>
						</a>
					</div>

				<?php endforeach; ?>

			</div>

			<div class="pa-dashboard-widget-block">
				<div class="pa-footer-bar">
					<a href="https://wordpress.org/support/plugin/premium-addons-for-elementor/" target="_blank" style="color: #27ae60">
						Need Help?
						<span aria-hidden="true" class="dashicons dashicons-external"></span>
					</a>
					<a href="https://www.youtube.com/leap13" target="_blank" style="color: #e1002d">
						YouTube Channel
						<span aria-hidden="true" class="dashicons dashicons-youtube"></span>
					</a>
					<a href="https://www.facebook.com/groups/PremiumAddons" target="_blank" style="color: #1877F2;">
						Facebook Community
						<span aria-hidden="true" class="dashicons dashicons-facebook-alt"></span>
					</a>
				</div>
			</div>

		<?php
	}

	/**
	 * Creates and returns an instance of the class
	 *
	 * @since 2.8.4
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
