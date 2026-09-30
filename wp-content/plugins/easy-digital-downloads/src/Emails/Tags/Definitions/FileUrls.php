<?php
/**
 * Email tag: file_urls
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
 * File URLs email tag.
 *
 * @since 3.7.1
 */
class FileUrls extends Tag {

	/**
	 * Tag identifier.
	 *
	 * @since 3.7.1
	 * @var string
	 */
	protected $tag = 'file_urls';

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
		return __( 'File URLs', 'easy-digital-downloads' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'A plain-text list of download URLs for each download purchased.', 'easy-digital-downloads' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( $object_id, $email_object = null, $context = '' ): string {
		return edd_email_tag_file_urls( $object_id );
	}
}
