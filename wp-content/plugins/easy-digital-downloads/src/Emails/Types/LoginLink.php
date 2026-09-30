<?php
/**
 * The One-Time Login Link Email.
 *
 * @package     EDD\Emails\Types
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.7
 */

namespace EDD\Emails\Types;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * The One-Time Login Link email class.
 *
 * This is sent to users when they request a login link to sign in without a password.
 *
 * @since 3.6.7
 */
class LoginLink extends Email {

	/**
	 * The email ID.
	 *
	 * @var string
	 * @since 3.6.7
	 */
	protected $id = 'login_link';

	/**
	 * The email context.
	 *
	 * @var string
	 * @since 3.6.7
	 */
	protected $context = 'user';

	/**
	 * The email recipient type.
	 *
	 * @var string
	 * @since 3.6.7
	 */
	protected $recipient_type = 'user';

	/**
	 * The user ID.
	 *
	 * @var int
	 * @since 3.6.7
	 */
	protected $user_id;

	/**
	 * The user data.
	 *
	 * @var \WP_User
	 * @since 3.6.7
	 */
	protected $user_data;

	/**
	 * The login link token.
	 *
	 * @var string
	 * @since 3.6.7
	 */
	protected $token;

	/**
	 * The class constructor.
	 *
	 * @since 3.6.7
	 * @param int   $user_id    The user ID.
	 * @param array $token_data Token data containing 'token' and 'expires_at'.
	 */
	public function __construct( $user_id, $token_data = array() ) {
		$this->user_id   = $user_id;
		$this->user_data = get_userdata( $user_id );
		$this->token     = ! empty( $token_data['token'] ) ? $token_data['token'] : '';
	}

	/**
	 * Set the email message.
	 *
	 * @since 3.6.7
	 * @return void
	 */
	protected function set_message() {
		parent::set_message();
		$this->message = $this->parse_tag( $this->message );
		$this->message = $this->process_tags( $this->message, $this->user_id, $this->user_data );
	}

	/**
	 * Set the email to address.
	 *
	 * @since 3.6.7
	 * @return void
	 */
	protected function set_to_email() {
		$this->send_to = $this->user_data->user_email;
	}

	/**
	 * Parses the {login_link_url} tag.
	 *
	 * @since 3.6.7
	 * @param string $content The content to parse.
	 * @return string
	 */
	private function parse_tag( string $content ): string {
		$url = \EDD\Users\LoginLink\Token::generate_url( $this->user_id, $this->token );
		if ( ! $url ) {
			return $content;
		}

		return str_replace( '{login_link_url}', esc_url_raw( $url ), $content );
	}
}
