<?php
/**
 * Lite Emails Handler
 *
 * @package     EDD\Lite\Admin\Emails
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.6
 */

namespace EDD\Lite\Admin\Emails;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\SubscriberInterface;

/**
 * Class Handler
 *
 * @since 3.6.6
 */
class Handler implements SubscriberInterface {

	/**
	 * Gets the events to subscribe to.
	 *
	 * @since 3.6.6
	 * @return array
	 */
	public static function get_subscribed_events() {
		return array(
			'edd_email_editor_top' => 'conditional_email_tags',
		);
	}

	/**
	 * Displays the conditional email tags button.
	 *
	 * @since 3.6.6
	 * @return void
	 */
	public function conditional_email_tags() {
		add_action( 'media_buttons', array( $this, 'render_button' ), 12 );
	}

	/**
	 * Renders the conditional email tags button.
	 *
	 * @since 3.6.6
	 * @return void
	 */
	public function render_button() {
		?>
		<button type="button" class="button edd-promo-notice__trigger" data-id="conditional-email-tags">
			<span class="wp-media-buttons-icon dashicons dashicons-randomize"></span>
			<?php esc_html_e( 'Condition', 'easy-digital-downloads' ); ?>
		</button>
		<?php
	}
}
