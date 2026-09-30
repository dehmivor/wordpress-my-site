<?php
/**
 * Email tag: password_link
 *
 * @package   EDD\Emails\Tags\Definitions
 * @copyright Copyright (c) 2026, Sandhills Development, LLC
 * @license   https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     3.7.1
 */

namespace EDD\Emails\Tags\Definitions;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Orders\Order;

/**
 * Password reset link email tag.
 *
 * @since 3.7.1
 */
class PasswordLink extends Tag {

	/**
	 * Tag identifier.
	 *
	 * @since 3.7.1
	 * @var string
	 */
	protected $tag = 'password_link';

	/**
	 * Contexts.
	 *
	 * @since 3.7.1
	 * @var array
	 */
	protected $contexts = array( 'order', 'user' );

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Password Reset Link', 'easy-digital-downloads' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( "The link to set the user's password. In an order receipt, this will only be included for the user's first purchase.", 'easy-digital-downloads' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( $object_id, $email_object = null, $context = '' ): string {
		$context = EDD()->email_tags->get_context( $context );
		if ( 'order' === $context && ! $email_object instanceof Order ) {
			$email_object = edd_get_order( $object_id );
		}
		$user = false;
		if ( 'order' === $context ) {
			if ( ! $email_object instanceof Order ) {
				return '';
			}
			$user = $this->get_user_data( $email_object->user_id );
			if ( ! $user ) {
				return '';
			}
			if ( ! $this->is_first_purchase( $email_object->user_id, $email_object->id ) ) {
				return '';
			}
		} elseif ( 'user' === $context ) {
			if ( $email_object instanceof \WP_User ) {
				$user = $email_object;
			} else {
				$user = $this->get_user_data( $object_id );
			}
		}
		if ( ! $user ) {
			return '';
		}
		$password_reset_link = edd_get_password_reset_link( $user );
		if ( ! $password_reset_link ) {
			return '';
		}

		return sprintf(
			'<a href="%1$s">%2$s</a>',
			$password_reset_link,
			__( 'Set your password', 'easy-digital-downloads' )
		);
	}

	/**
	 * Check if it the first purchase for a given user.
	 *
	 * @since 3.7.1
	 * @param int $user_id  The user ID.
	 * @param int $order_id The order ID.
	 * @return bool
	 */
	private function is_first_purchase( $user_id, $order_id ) {
		return empty(
			edd_get_orders(
				array(
					'type'       => 'sale',
					'number'     => 1,
					'status__in' => edd_get_complete_order_statuses(),
					'user_id'    => $user_id,
					'id__not_in' => array( $order_id ),
				)
			)
		);
	}

	/**
	 * Fetch user data.
	 *
	 * @since 3.7.1
	 * @param int $user_id The user ID.
	 * @return \WP_User|false WP_User object on success, false on failure.
	 */
	private function get_user_data( $user_id = 0 ) {
		if ( $user_id ) {
			return get_userdata( $user_id );
		}

		return false;
	}
}
