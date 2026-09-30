<?php
/**
 * Email tag: refund_id
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
 * Refund ID email tag.
 *
 * @since 3.7.1
 */
class RefundId extends Tag {

	/**
	 * Tag identifier.
	 *
	 * @since 3.7.1
	 * @var string
	 */
	protected $tag = 'refund_id';

	/**
	 * Contexts.
	 *
	 * @since 3.7.1
	 * @var array
	 */
	protected $contexts = array( 'refund' );

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Refund ID', 'easy-digital-downloads' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'The unique identifier for this refund.', 'easy-digital-downloads' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( $object_id, $email_object = null, $context = '' ): string {
		if ( $context instanceof \EDD\Emails\Types\Email ) {
			$context = $context->get_context();
		}
		if ( 'refund' !== $context ) {
			return '';
		}
		if ( is_null( $email_object ) ) {
			$email_object = edd_get_order( $object_id );
		}

		if ( $email_object instanceof Order && 'refund' === $email_object->type ) {
			return $email_object->order_number;
		}

		return '{refund_id}';
	}
}
