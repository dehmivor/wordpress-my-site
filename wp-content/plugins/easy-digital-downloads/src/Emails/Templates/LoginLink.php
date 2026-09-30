<?php
/**
 * One-Time Login Link Email Template
 *
 * This template is used to send a one-time login link to a user when they request one.
 *
 * @package     EDD\Emails\Templates
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.7
 */

namespace EDD\Emails\Templates;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * One-Time Login Link Email Template Class
 *
 * @since 3.6.7
 */
class LoginLink extends EmailTemplate {

	/**
	 * Whether the email can be previewed.
	 *
	 * @since 3.6.7
	 * @var bool
	 */
	protected $can_preview = true;

	/**
	 * Unique identifier for this template.
	 *
	 * @since 3.6.7
	 * @var string
	 */
	protected $email_id = 'login_link';

	/**
	 * The email recipient.
	 *
	 * @since 3.6.7
	 * @var string
	 */
	protected $recipient = 'user';

	/**
	 * The email context.
	 *
	 * @since 3.6.7
	 * @var string
	 */
	protected $context = 'user';

	/**
	 * The required tag.
	 *
	 * @since 3.6.7
	 * @var string
	 */
	protected $required_tag = 'login_link_url';

	/**
	 * Name of the template.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get_name() {
		return __( 'One-Time Login Link', 'easy-digital-downloads' );
	}

	/**
	 * Description of the email.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get_description() {
		return __( 'This email is sent to a user when they request a login link to sign in without a password during checkout.', 'easy-digital-downloads' );
	}

	/**
	 * Retrieves the default email properties.
	 *
	 * @since 3.6.7
	 * @return array
	 */
	public function defaults(): array {
		return array(
			'subject' => __( 'Your Login Link', 'easy-digital-downloads' ),
			'heading' => __( 'Your Login Link', 'easy-digital-downloads' ),
			'content' => $this->get_body_default(),
			'status'  => \EDD\Users\LoginLink\Utility::enabled() ? 1 : 0,
		);
	}

	/**
	 * Gets the content for the status tooltip, if needed.
	 *
	 * @since 3.6.7
	 * @return array
	 */
	public function get_status_tooltip(): array {
		if ( $this->can_edit( 'status' ) ) {
			return array();
		}

		$content = __( 'This email cannot be disabled when the One-Time Login Link feature is enabled.', 'easy-digital-downloads' );

		if ( ! \EDD\Users\LoginLink\Utility::enabled() ) {
			$content = __( 'This email is only available when the login link feature is enabled in the checkout settings.', 'easy-digital-downloads' );
		}

		return array(
			'content'  => $content,
			'dashicon' => 'dashicons-lock',
		);
	}

	/**
	 * This email cannot be activated if the login link feature is not enabled.
	 *
	 * @since 3.6.7
	 * @return bool
	 */
	public function are_base_requirements_met(): bool {
		return \EDD\Users\LoginLink\Utility::enabled();
	}

	/**
	 * Determines whether the email is enabled.
	 *
	 * The email's enabled state mirrors the login link feature setting directly,
	 * because status is not user-editable for this template.
	 *
	 * @since 3.6.9
	 * @return bool
	 */
	protected function is_enabled(): bool {
		return \EDD\Users\LoginLink\Utility::enabled();
	}

	/**
	 * Gets the required tag parameters for the email editor.
	 *
	 * @since 3.6.7
	 * @return array
	 */
	protected function get_required_tag_parameters() {
		return array(
			'label'       => __( 'One-Time Login Link', 'easy-digital-downloads' ),
			'description' => __( 'The link for the user to log in without a password.', 'easy-digital-downloads' ),
		);
	}

	/**
	 * Retrieves the preview data for this email.
	 *
	 * @since 3.6.7
	 * @return array
	 */
	protected function get_preview_data() {
		return array(
			get_current_user_id(),
			array( 'token' => wp_generate_password( 32, false ) ),
		);
	}

	/**
	 * The email properties that can be edited.
	 *
	 * @since 3.6.7
	 * @return array
	 */
	protected function get_editable_properties(): array {
		return array(
			'content',
			'subject',
			'heading',
		);
	}

	/**
	 * The default email body.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	private function get_body_default() {
		$message = sprintf(
			/* translators: %s: The email tag that will be replaced with the customer's full name. */
			__( 'Hello %s,', 'easy-digital-downloads' ),
			'{fullname}',
		) . "\n\n";
		$message .= sprintf(
			/* translators: %s: The email tag that will be replaced with the Site Name. */
			__( 'A login link was requested for your account on %s.', 'easy-digital-downloads' ),
			'{sitename}',
		) . "\n\n";
		$message .= sprintf(
			/* translators: %s: The email tag that will be replaced with the login link URL. */
			__( 'Click here to log in: %s', 'easy-digital-downloads' ),
			'{login_link_url}',
		) . "\n\n";
		$message .= __( 'If you did not request this link, you can safely ignore this email.', 'easy-digital-downloads' ) . "\n\n";

		return $message;
	}
}
