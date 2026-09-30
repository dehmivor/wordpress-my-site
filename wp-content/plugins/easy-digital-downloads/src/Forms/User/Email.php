<?php
/**
 * User Profile Email Field.
 *
 * @package     EDD\Forms\User
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.7
 */

namespace EDD\Forms\User;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Forms\Fields\Field;

/**
 * User profile primary email field.
 *
 * Renders a text input when the customer has a single email address,
 * or a select dropdown when they have multiple.
 *
 * @since 3.6.7
 */
class Email extends Field {

	/**
	 * Get the field ID.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get_id(): string {
		return 'edd_email';
	}

	/**
	 * Get the field label.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get_label(): string {
		return esc_html__( 'Primary Email Address', 'easy-digital-downloads' );
	}

	/**
	 * Render the input.
	 *
	 * Renders a select if the customer has multiple email addresses,
	 * otherwise renders a plain email input.
	 *
	 * @since 3.6.7
	 */
	public function do_input(): void {
		$customer = $this->data['customer'] ?? null;
		$user     = $this->data['user'] ?? null;

		if ( $customer instanceof \EDD_Customer && $customer->id > 0 && count( $customer->emails ) > 1 ) {
			$this->do_email_select( $customer );
		} else {
			$this->do_email_input( $customer, $user );
		}
	}

	/**
	 * Get the description.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get_description(): string {
		return '';
	}

	/**
	 * Checks if the field is required.
	 *
	 * @since 3.6.7
	 * @return bool
	 */
	protected function is_required(): bool {
		return true;
	}

	/**
	 * Get the field key.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	protected function get_key(): string {
		return 'email';
	}

	/**
	 * Render a text input for a single email address.
	 *
	 * @since 3.6.7
	 * @param \EDD_Customer|null $customer The customer object.
	 * @param \WP_User|null      $user     The current user.
	 */
	private function do_email_input( ?\EDD_Customer $customer, ?\WP_User $user ): void {
		$value = '';
		if ( $customer instanceof \EDD_Customer && $customer->id > 0 ) {
			$value = $customer->email;
		} elseif ( $user instanceof \WP_User ) {
			$value = $user->user_email;
		}

		?>
		<div class="edd-blocks-form__control">
			<?php
			$input = new \EDD\HTML\Text(
				wp_parse_args(
					array(
						'type'         => 'email',
						'value'        => $value,
						'autocomplete' => 'email',
						'include_span' => false,
					),
					$this->get_defaults()
				)
			);
			$input->output();
			?>
		</div>
		<?php
	}

	/**
	 * Render a select for multiple email addresses.
	 *
	 * @since 3.6.7
	 * @param \EDD_Customer $customer The customer object.
	 */
	private function do_email_select( \EDD_Customer $customer ): void {
		$emails          = array();
		$reversed_emails = array_reverse( $customer->emails, true );
		foreach ( $reversed_emails as $email ) {
			$emails[ $email ] = $email;
		}

		$select_args = array(
			'options'          => $emails,
			'selected'         => $customer->email,
			'show_option_none' => false,
			'show_option_all'  => false,
		);

		?>
		<div class="edd-blocks-form__control">
			<?php
			$select = new \EDD\HTML\Select(
				wp_parse_args(
					$select_args,
					$this->get_defaults()
				)
			);
			$select->output();
			?>
		</div>
		<?php
	}
}
