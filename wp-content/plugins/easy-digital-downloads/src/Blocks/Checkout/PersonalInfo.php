<?php
/**
 * Checkout personal info block.
 *
 * @package     EDD\Blocks\Checkout
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Blocks\Checkout;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\SubscriberInterface;
use EDD\Blocks\Utility;

/**
 * PersonalInfo class.
 */
class PersonalInfo implements SubscriberInterface {

	/**
	 * Get the subscribed events.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	public static function get_subscribed_events(): array {
		return array(
			'init' => 'register',
		);
	}

	/**
	 * Register the personal info block.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	public function register(): void {
		if ( ! defined( 'EDD_BLOCKS_DIR' ) ) {
			return;
		}

		register_block_type(
			EDD_BLOCKS_DIR . 'build/checkout-personal-info',
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Renders the checkout cart component.
	 *
	 * @since 3.7.0
	 * @param array     $block_attributes The block attributes.
	 * @param string    $content          The block inner content.
	 * @param \WP_Block $block            The block object.
	 * @return string PersonalInfo HTML.
	 */
	public function render( $block_attributes = array(), $content = '', $block = null ) {
		$block_attributes = wp_parse_args(
			$block_attributes,
			array(
				'show_register_form' => edd_get_option( 'show_register_form' ),
				'logged_in'          => is_user_logged_in() && ! \EDD\Blocks\Utility::doing_guest_preview( $block ),
			)
		);

		ob_start();
		Utility::do_checkout_form_top( $block_attributes );
		return ob_get_clean();
	}
}
