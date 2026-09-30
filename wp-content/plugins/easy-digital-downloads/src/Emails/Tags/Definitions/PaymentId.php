<?php
/**
 * Email tag: payment_id
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
 * Payment ID email tag.
 *
 * @since 3.7.1
 */
class PaymentId extends Tag {

	/**
	 * Tag identifier.
	 *
	 * @since 3.7.1
	 * @var string
	 */
	protected $tag = 'payment_id';

	/**
	 * Contexts.
	 *
	 * @since 3.7.1
	 * @var array
	 */
	protected $contexts = array( 'order', 'refund' );

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Payment ID', 'easy-digital-downloads' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'The unique identifier for this purchase.', 'easy-digital-downloads' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( $object_id, $email_object = null, $context = '' ): string {
		return (string) edd_email_tag_payment_id( $object_id, $email_object );
	}
}
