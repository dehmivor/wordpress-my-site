<?php
/**
 * User Profile Weak Password Confirmation Field.
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
 * User profile weak password confirmation checkbox.
 *
 * Shown by the WP password strength JS when the chosen password is weak,
 * requiring the user to acknowledge the use of a weak password.
 *
 * @since 3.6.7
 */
class PasswordWeak extends Field {

	/**
	 * Get the field ID.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get_id(): string {
		return 'pw-weak';
	}

	/**
	 * Get the field label.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get_label(): string {
		return esc_html__( 'Confirm use of weak password', 'easy-digital-downloads' );
	}

	/**
	 * Render the field.
	 *
	 * @since 3.6.7
	 * @return void
	 */
	public function render(): void {
		$classes = $this->get_form_group_classes();
		?>
		<div
			<?php if ( ! empty( $classes ) ) : ?>
				class="<?php echo esc_attr( $this->get_css_class_string( $classes ) ); ?>"
			<?php endif; ?>
		>
			<?php $this->do_input(); ?>
		</div>
		<?php
	}

	/**
	 * Render the input.
	 *
	 * @since 3.6.7
	 */
	public function do_input(): void {
		?>
		<div class="edd-blocks-form__control">
			<input type="checkbox" name="pw_weak" id="pw-weak" class="pw-checkbox" />
			<?php $this->do_label(); ?>
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
	 * Get the form group classes.
	 *
	 * @since 3.6.7
	 * @return array
	 */
	protected function get_form_group_classes(): array {
		$classes   = parent::get_form_group_classes();
		$classes[] = 'pw-weak';

		return $classes;
	}
}
