<?php
/**
 * Email tag: fees_total
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
 * Fees total email tag.
 *
 * @since 3.7.1
 */
class FeesTotal extends Tag {

	/**
	 * Tag identifier.
	 *
	 * @since 3.7.1
	 * @var string
	 */
	protected $tag = 'fees_total';

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
		return __( 'Fees Total', 'easy-digital-downloads' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'The total fees on the order, formatted with currency.', 'easy-digital-downloads' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( $object_id, $email_object = null, $context = '' ): string {
		if ( ! $email_object instanceof Order ) {
			$email_object = edd_get_order( $object_id );
		}

		$total = array_reduce(
			$email_object ? $email_object->get_fees() : array(),
			function ( $carry, $fee ) {
				return $carry + $fee->get_amount();
			},
			0
		);

		return edd_currency_filter( edd_format_amount( $total ), $email_object->currency );
	}
}
