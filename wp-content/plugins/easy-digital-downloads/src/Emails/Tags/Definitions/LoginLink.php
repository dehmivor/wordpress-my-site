<?php
/**
 * Email tag: login_link
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
 * Login link email tag.
 *
 * @since 3.7.1
 */
class LoginLink extends Tag {

	/**
	 * Tag identifier.
	 *
	 * @since 3.7.1
	 * @var string
	 */
	protected $tag = 'login_link';

	/**
	 * Contexts.
	 *
	 * @since 3.7.1
	 * @var array
	 */
	protected $contexts = array();

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Login Link', 'easy-digital-downloads' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'The link to log into the site.', 'easy-digital-downloads' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( $object_id, $email_object = null, $context = '' ): string {
		if ( 'text/plain' === EDD()->emails->get_content_type() ) {
			return wp_login_url();
		}

		return sprintf(
			'<a href="%1$s">%2$s</a>',
			wp_login_url(),
			__( 'Login', 'easy-digital-downloads' )
		);
	}
}
