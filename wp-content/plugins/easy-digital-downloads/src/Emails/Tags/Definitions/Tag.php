<?php
/**
 * Abstract class for registered email tags.
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
 * Abstract class for registered email tags.
 *
 * @since 3.7.1
 */
abstract class Tag {

	/**
	 * Tag identifier.
	 *
	 * @since 3.7.1
	 * @var string
	 */
	protected $tag;

	/**
	 * Contexts in which this tag can be used.
	 *
	 * @since 3.7.1
	 * @var array
	 */
	protected $contexts = array( 'order' );

	/**
	 * Recipients for which this tag can be used.
	 * Null means all recipients.
	 *
	 * @since 3.7.1
	 * @var array|null
	 */
	protected $recipients = null;

	/**
	 * Get the tag identifier.
	 *
	 * @since 3.7.1
	 * @return string
	 */
	final public function get_tag(): string {
		return $this->tag;
	}

	/**
	 * Get the human-readable label for this tag.
	 *
	 * @since 3.7.1
	 * @return string
	 */
	abstract public function get_label(): string;

	/**
	 * Get the description for this tag.
	 *
	 * @since 3.7.1
	 * @return string
	 */
	abstract public function get_description(): string;

	/**
	 * Render the tag output.
	 *
	 * @since 3.7.1
	 * @param int                                 $object_id    The object ID.
	 * @param mixed                               $email_object The email object (order, user, etc.).
	 * @param string|\EDD\Emails\Types\Email|null $context      The context or email object.
	 * @return string
	 */
	abstract public function render( $object_id, $email_object = null, $context = '' ): string;

	/**
	 * Get the contexts in which this tag can be used.
	 *
	 * @since 3.7.1
	 * @return array
	 */
	public function get_contexts(): array {
		return $this->contexts;
	}

	/**
	 * Get the recipients for which this tag can be used.
	 *
	 * @since 3.7.1
	 * @return array|null
	 */
	public function get_recipients(): ?array {
		return $this->recipients;
	}

	/**
	 * Converts the tag to the flat array format that Handler::add() expects.
	 *
	 * @since 3.7.1
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'tag'         => $this->get_tag(),
			'label'       => $this->get_label(),
			'description' => $this->get_description(),
			'func'        => array( $this, 'render' ),
			'contexts'    => $this->get_contexts(),
			'recipients'  => $this->get_recipients(),
		);
	}
}
