<?php
/**
 * User Profile Confirm New Password Field.
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
 * User profile confirm new password field.
 *
 * Not required — password change is optional on the profile editor form.
 *
 * @since 3.6.7
 */
class PasswordConfirm extends Field {

	/**
	 * Get the field ID.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get_id(): string {
		return 'edd_new_user_pass2';
	}

	/**
	 * Get the field label.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get_label(): string {
		return esc_html__( 'Re-enter Password', 'easy-digital-downloads' );
	}

	/**
	 * Override the label to point to the aliased input ID.
	 *
	 * @since 3.6.7
	 */
	protected function do_label(): void {
		?>
		<label for="pass2" class="edd-label">
			<?php echo esc_html( $this->get_label() ); ?>
		</label>
		<?php
	}

	/**
	 * Render the input.
	 *
	 * The input ID is set to "pass2" so WP's user-profile.js can sync its value
	 * to the password field on form submit. The name remains "edd_new_user_pass2"
	 * for server-side processing.
	 *
	 * @since 3.6.7
	 */
	public function do_input(): void {
		?>
		<div class="edd-blocks-form__control">
			<?php
			$password_confirm = new \EDD\HTML\Text(
				wp_parse_args(
					array(
						'type'         => 'password',
						'id'           => 'pass2',
						'class'        => $this->get_field_classes(),
						'include_span' => false,
					),
					$this->get_defaults()
				)
			);
			$password_confirm->output();
			?>
		</div>
		<?php
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
	 * Get the field key.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	protected function get_key(): string {
		return 'password-confirm';
	}

	/**
	 * Get the form group classes.
	 *
	 * @since 3.6.7
	 * @return array
	 */
	protected function get_form_group_classes(): array {
		$classes   = parent::get_form_group_classes();
		$classes[] = 'user-pass2-wrap';

		return $classes;
	}
}
