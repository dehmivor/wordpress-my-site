<?php
/**
 * Email tag: refund_link
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
 * Refund link email tag.
 *
 * @since 3.7.1
 */
class RefundLink extends Tag {

	/**
	 * Tag identifier.
	 *
	 * @since 3.7.1
	 * @var string
	 */
	protected $tag = 'refund_link';

	/**
	 * Contexts.
	 *
	 * @since 3.7.1
	 * @var array
	 */
	protected $contexts = array( 'refund' );

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
		return __( 'Refund Link', 'easy-digital-downloads' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'The link to refund record in the EDD admin.', 'easy-digital-downloads' );
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
		if ( 'refund' !== $context || empty( $email_object ) || 'refund' !== $email_object->type ) {
			return '{refund_link}';
		}

		return edd_get_admin_url(
			array(
				'page' => 'edd-payment-history',
				'view' => 'view-refund-details',
				'id'   => absint( $object_id ),
			)
		);
	}
}
