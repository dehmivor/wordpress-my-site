<?php
/**
 * Registration Password Confirm Field.
 *
 * @package     EDD\Forms\Register
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.3.9
 */

namespace EDD\Forms\Register;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Forms\Fields\Field;

/**
 * Registration Password Confirm Field.
 *
 * @since 3.3.9
 */
class PasswordConfirm extends Field {

	/**
	 * Render the field.
	 *
	 * @since 3.3.9
	 * @return void
	 */
	public function render(): void {
		if ( ! $this->is_block() ) {
			$this->render_shortcode();
			return;
		}
		parent::render();
	}

	/**
	 * Get the field ID.
	 *
	 * @since 3.3.9
	 * @return string
	 */
	public function get_id(): string {
		return $this->is_block() ? 'pass2' : 'edd_user_pass_confirm';
	}

	/**
	 * Get the name attribute for the field input.
	 *
	 * The checkout and the registration form are processed by different functions,
	 * which read different keys for the confirmation.
	 *
	 * @since 3.7.1
	 * @return string
	 */
	public function get_name(): string {
		return $this->is_checkout() ? 'edd_user_pass_confirm' : 'edd_user_pass2';
	}

	/** Get the field label.
	 *
	 * @since 3.3.9
	 * @return string
	 */
	public function get_label(): string {
		return esc_html__( 'Confirm Password', 'easy-digital-downloads' );
	}

	/**
	 * Render the input.
	 *
	 * @since 3.3.9
	 */
	public function do_input(): void {
		?>
		<div class="edd-blocks-form__control">
			<?php
			$password_confirm = new \EDD\HTML\Text(
				array(
					'type'         => 'password',
					'name'         => $this->get_name(),
					'id'           => $this->get_id(),
					'class'        => $this->get_field_classes(),
					'include_span' => false,
				)
			);
			$password_confirm->output();
			?>
		</div>
		<?php
	}

	/**
	 * Get the description for the field.
	 *
	 * @since 3.3.9
	 * @return string
	 */
	public function get_description(): string {
		return $this->is_block() ? '' : __( 'Confirm your password.', 'easy-digital-downloads' );
	}

	/**
	 * Checks if the field is required.
	 *
	 * @since 3.3.9
	 * @return bool
	 */
	protected function is_required(): bool {
		if ( $this->is_block() ) {
			return true;
		}
		return edd_no_guest_checkout();
	}

	/**
	 * Get the field key.
	 *
	 * @since 3.3.9
	 * @return string
	 */
	protected function get_key(): string {
		return 'password-confirm';
	}

	/**
	 * Get the form group classes.
	 *
	 * @since 3.3.9
	 * @return array
	 */
	protected function get_form_group_classes(): array {
		$classes   = parent::get_form_group_classes();
		$classes[] = 'user-pass2-wrap';

		return $classes;
	}

	/**
	 * Renders the field for the shortcode checkout, using legacy wrapper IDs and field names.
	 *
	 * @since 3.7.0
	 */
	private function render_shortcode(): void {
		$required = $this->is_required();
		?>
		<div id="edd-user-pass-confirm-wrap" class="edd_register_password">
			<?php
			$this->do_label();
			$this->do_description();
			$password_confirm = new \EDD\HTML\Text(
				array(
					'type'         => 'password',
					'name'         => $this->get_name(),
					'id'           => $this->get_id(),
					'class'        => $this->get_field_classes(),
					'placeholder'  => esc_html__( 'Confirm password', 'easy-digital-downloads' ),
					'required'     => $required,
					'include_span' => false,
				)
			);
			$password_confirm->output();
			?>
		</div>
		<?php
	}

	/**
	 * Checks if the field is being rendered on the checkout.
	 *
	 * Set by both checkout templates, and independent of block vs. shortcode styling.
	 *
	 * @since 3.7.1
	 * @return bool
	 */
	private function is_checkout(): bool {
		return ! empty( $this->data['is_checkout'] );
	}
}
