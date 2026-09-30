<?php
/**
 * Email tag: billing_address
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
 * Billing address email tag.
 *
 * @since 3.7.1
 */
class BillingAddress extends Tag {

	/**
	 * Tag identifier.
	 *
	 * @since 3.7.1
	 * @var string
	 */
	protected $tag = 'billing_address';

	/**
	 * Contexts.
	 *
	 * @since 3.7.1
	 * @var array
	 */
	protected $contexts = array( 'order' );

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Billing Address', 'easy-digital-downloads' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( "The buyer's billing address.", 'easy-digital-downloads' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( $object_id, $email_object = null, $context = '' ): string {
		$billing_addresses = edd_get_order_addresses(
			array(
				'order_id' => $object_id,
				'type'     => 'billing',
			)
		);
		if ( empty( $billing_addresses ) ) {
			return '';
		}

		$address = reset( $billing_addresses )->to_array();

		// Only an HTML email body, and the admin preview of one, renders these values as markup.
		if ( 'none' !== EDD()->emails->get_template() ) {
			$address = array_map( 'esc_html', $address );
		}

		$output = $address['address'] . "\n";
		if ( ! empty( $address['address2'] ) ) {
			$output .= $address['address2'] . "\n";
		}
		$output .= $address['city'] . ' ' . $address['postal_code'] . ' ' . $address['region'] . "\n";
		$output .= $address['country'];

		return $output;
	}
}
