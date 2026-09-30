<?php
/**
 * Email tag: order_details_link
 *
 * @package   EDD\Emails\Tags\Definitions
 * @copyright Copyright (c) 2026, Sandhills Development, LLC
 * @license   https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     3.7.1
 */

namespace EDD\Emails\Tags\Definitions;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Order details link email tag.
 *
 * @since 3.7.1
 */
class OrderDetailsLink extends Tag {

	/**
	 * Tag identifier.
	 *
	 * @since 3.7.1
	 * @var string
	 */
	protected $tag = 'order_details_link';

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
		return __( 'Order Details Link', 'easy-digital-downloads' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'The link to the order details page in the EDD admin.', 'easy-digital-downloads' );
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
			return '{order_details_link}';
		}

		if ( ! $email_object ) {
			$email_object = edd_get_order( $object_id );
		}

		if ( ! $email_object ) {
			return '{order_details_link}';
		}

		return edd_get_admin_url(
			array(
				'page' => 'edd-payment-history',
				'view' => 'view-order-details',
				'id'   => absint( $object_id ),
			)
		);
	}
}
