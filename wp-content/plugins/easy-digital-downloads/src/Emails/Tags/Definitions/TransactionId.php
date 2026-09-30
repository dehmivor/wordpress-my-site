<?php
/**
 * Email tag: transaction_id
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
 * Transaction ID email tag.
 *
 * @since 3.7.1
 */
class TransactionId extends Tag {

	/**
	 * Tag identifier.
	 *
	 * @since 3.7.1
	 * @var string
	 */
	protected $tag = 'transaction_id';

	/**
	 * Contexts.
	 *
	 * @since 3.7.1
	 * @var array
	 */
	protected $contexts = array( 'order' );

	/**
	 * Recipients.
	 *
	 * @since 3.7.1
	 * @var array
	 */
	protected $recipients = array( 'admin' );

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Transaction ID', 'easy-digital-downloads' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'The merchant transaction ID for this order. This is for admin emails only.', 'easy-digital-downloads' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( $object_id, $email_object = null, $context = '' ): string {
		if ( $context instanceof \EDD\Emails\Types\Email ) {
			if ( 'admin' !== $context->recipient_type ) {
				return '';
			}
			$context = $context->get_context();
		}
		if ( 'order' !== $context ) {
			return '';
		}

		if ( empty( $email_object ) || ! $email_object instanceof Order ) {
			$email_object = edd_get_order( $object_id );
		}

		// phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores
		return (string) apply_filters( 'edd_payment_details_transaction_id-' . $email_object->gateway, $email_object->get_transaction_id(), $email_object->id );
	}
}
