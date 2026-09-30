<?php
/**
 * Email tag: company
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
 * Company/business email tag.
 *
 * @since 3.7.1
 */
class Company extends Tag {

	/**
	 * Tag identifier.
	 *
	 * @since 3.7.1
	 * @var string
	 */
	protected $tag = 'company';

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
		return __( 'Company', 'easy-digital-downloads' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'The company or organization name for your order.', 'easy-digital-downloads' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( $object_id, $email_object = null, $context = '' ): string {
		if ( $context instanceof \EDD\Emails\Types\Email ) {
			$context = $context->get_context();
		}
		if ( 'order' !== $context ) {
			return '';
		}

		return edd_get_order_meta( $object_id, 'company_name', true );
	}
}
