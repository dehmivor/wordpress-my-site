<?php
/**
 * Profile Editor Block.
 *
 * @package     EDD\Blocks\Forms
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.7
 */

namespace EDD\Blocks\Forms;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\SubscriberInterface;

/**
 * Profile Editor block registration and rendering.
 *
 * @since 3.6.7
 */
class ProfileEditor implements SubscriberInterface {

	/**
	 * Get the subscribed events.
	 *
	 * @since 3.6.7
	 * @return array
	 */
	public static function get_subscribed_events(): array {
		return array(
			'init' => 'register',
		);
	}

	/**
	 * Register the profile editor block type.
	 *
	 * EDD_BLOCKS_DIR is defined by the blocks plugin at plugins_loaded priority 500,
	 * which always runs before init.
	 *
	 * @since 3.6.7
	 */
	public static function register(): void {
		if ( ! defined( 'EDD_BLOCKS_DIR' ) ) {
			return;
		}

		register_block_type(
			EDD_BLOCKS_DIR . 'build/profile-editor',
			array(
				'render_callback' => array( __CLASS__, 'render' ),
			)
		);
	}

	/**
	 * Render the profile editor block.
	 *
	 * @since 3.6.7
	 * @param array $block_attributes The block attributes.
	 * @return string
	 */
	public static function render( array $block_attributes = array() ): string {
		if ( ! defined( 'EDD_BLOCKS_DIR' ) ) {
			return '';
		}

		if ( ! is_user_logged_in() ) {
			ob_start();
			do_action( 'edd_profile_editor_logged_out' );
			return ob_get_clean();
		}

		$classes  = \EDD\Blocks\Functions\get_block_classes( $block_attributes, array( 'wp-block-edd-profile-editor' ) );
		$user_id  = get_current_user_id();
		$user     = wp_get_current_user();
		$customer = new \EDD_Customer( $user_id, true );
		$address  = edd_get_customer_address( $user_id );
		$data     = array(
			'user'     => $user,
			'customer' => $customer,
			'address'  => $address,
		);

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<?php
			edd_print_errors();
			if ( edd_user_pending_verification() ) {
				edd_get_template_part( 'account', 'pending' );
			} else {
				// $block_attributes is available to the included template via PHP's include scope.
				include EDD_BLOCKS_DIR . 'views/forms/profile-editor.php';
			}
			?>
		</div>
		<?php

		return ob_get_clean();
	}
}
