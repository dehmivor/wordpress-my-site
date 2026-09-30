<?php
/**
 * Email tag: username
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
 * Username email tag.
 *
 * @since 3.7.1
 */
class Username extends Tag {

	/**
	 * Tag identifier.
	 *
	 * @since 3.7.1
	 * @var string
	 */
	protected $tag = 'username';

	/**
	 * Contexts.
	 *
	 * @since 3.7.1
	 * @var array
	 */
	protected $contexts = array( 'user', 'order', 'refund' );

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Username', 'easy-digital-downloads' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( "The buyer's (or user's) user name on the site, if they registered an account.", 'easy-digital-downloads' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( $object_id, $email_object = null, $context = '' ): string {
		return edd_email_tag_username( $object_id, $email_object, $context );
	}
}
