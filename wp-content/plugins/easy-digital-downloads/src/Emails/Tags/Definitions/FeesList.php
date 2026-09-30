<?php
/**
 * Email tag: fees_list
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
 * Fees list email tag.
 *
 * @since 3.7.1
 */
class FeesList extends Tag {

	/**
	 * Tag identifier.
	 *
	 * @since 3.7.1
	 * @var string
	 */
	protected $tag = 'fees_list';

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
		return __( 'Fees List', 'easy-digital-downloads' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'A list of all fees on the order, with amounts.', 'easy-digital-downloads' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( $object_id, $email_object = null, $context = '' ): string {
		if ( ! $email_object instanceof Order ) {
			$email_object = edd_get_order( $object_id );
		}

		$fees = $email_object ? $email_object->get_fees() : array();

		if ( empty( $fees ) ) {
			return '';
		}

		return sprintf(
			'<ul>%s</ul>',
			array_reduce(
				$fees,
				function ( $carry, $fee ) use ( $email_object ) {
					return $carry . sprintf(
						'<li>%s: %s</li>',
						$fee->description ?? __( 'Fee', 'easy-digital-downloads' ),
						edd_currency_filter( edd_format_amount( $fee->get_amount() ), $email_object->currency )
					);
				},
				''
			)
		);
	}
}
