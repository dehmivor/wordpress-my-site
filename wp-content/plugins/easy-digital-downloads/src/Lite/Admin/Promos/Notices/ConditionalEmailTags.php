<?php
/**
 * Conditional Email Tags Upgrade Notice
 *
 * @package     EDD\Lite\Admin\Promos\Notices
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.6
 */

namespace EDD\Lite\Admin\Promos\Notices;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Admin\Promos\Notices\Notice;

/**
 * Conditional Email Tags Upgrade Notice class.
 *
 * @since 3.6.6
 */
class ConditionalEmailTags extends Notice {

	/**
	 * Action hook for displaying the notice.
	 */
	const DISPLAY_HOOK = 'admin_print_footer_scripts-download_page_edd-emails';

	/**
	 * Type of promotional notice.
	 */
	const TYPE = 'overlay';

	/**
	 * Capability required to dismiss the notice.
	 */
	const CAPABILITY = 'manage_shop_settings';

	/**
	 * Duration (in seconds) that the notice is dismissed for.
	 * `0` means it's dismissed permanently.
	 *
	 * @since 3.6.6
	 * @return int
	 */
	public static function dismiss_duration() {
		return 1;
	}

	/**
	 * Renders the dismiss button for the notice.
	 * This is intentionally left blank as the dismiss button is rendered in the content.
	 *
	 * @since 3.6.6
	 * @return void
	 */
	public function dismiss_button() {}

	/**
	 * Gets the notice ID.
	 *
	 * @since 3.6.6
	 * @return string
	 */
	public function get_id() {
		return 'conditional-email-tags';
	}

	/**
	 * Displays the notice content.
	 *
	 * @since 3.6.6
	 * @return void
	 */
	protected function _display() {
		if ( ! $this->_should_display() ) {
			return;
		}

		$upgrade_url = edd_link_helper(
			'https://easydigitaldownloads.com/lite-upgrade/',
			array(
				'utm_medium'  => 'settings',
				'utm_content' => 'conditional-email-tags',
			)
		);
		?>
		<div class="edd-promo-notice__image">
			<img src="<?php echo esc_url( EDD_PLUGIN_URL . 'assets/images/promo/email-conditional-tags.png' ); ?>" alt="" />
		</div>

		<div class="edd-promo-notice__content">
			<h2>
				<?php esc_html_e( 'Send the Right Message to the Right Customer', 'easy-digital-downloads' ); ?>
			</h2>
			<p>
				<?php esc_html_e( 'Stop sending the same email to everyone. Pro unlocks conditional email tags so you can show different content based on what customers bought, how much they spent, or whether it\'s their first order—all from one email template.', 'easy-digital-downloads' ); ?>
			</p>
		</div>
		<div class="edd-promo-notice__actions">
			<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary" target="_blank">
				<?php esc_html_e( 'Unlock This', 'easy-digital-downloads' ); ?>
			</a>
			<button class="button button-secondary edd-promo-notice-dismiss">
				<?php esc_html_e( 'Maybe Later', 'easy-digital-downloads' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Determines if the notice should be displayed.
	 *
	 * @since 3.6.6
	 * @return bool
	 */
	protected function _should_display(): bool {
		return ! empty( filter_input( INPUT_GET, 'email', FILTER_SANITIZE_SPECIAL_CHARS ) );
	}
}
