<?php
/**
 * User Profile New Password Field.
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
 * User profile new password field.
 *
 * Not required — password change is optional on the profile editor form.
 * Includes the WP password strength indicator and show/hide toggle.
 *
 * @since 3.6.7
 */
class Password extends Field {

	/**
	 * Get the field ID.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get_id(): string {
		return 'edd_new_user_pass1';
	}

	/**
	 * Get the field label.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get_label(): string {
		return esc_html__( 'New Password', 'easy-digital-downloads' );
	}

	/**
	 * Override the label to point to the aliased input ID.
	 *
	 * @since 3.6.7
	 */
	protected function do_label(): void {
		?>
		<label for="pass1" class="edd-label">
			<?php echo esc_html( $this->get_label() ); ?>
		</label>
		<?php
	}

	/**
	 * Render the input.
	 *
	 * The input ID is set to "pass1" so WP's user-profile.js picks it up for
	 * strength checking. The name remains "edd_new_user_pass1" for server-side
	 * processing. data-reveal is intentionally omitted — unlike the registration
	 * form, the profile editor password field should start empty.
	 *
	 * @since 3.6.7
	 */
	public function do_input(): void {
		?>
		<div class="edd-blocks-form__control">
			<div class="wp-pwd">
				<?php
				$password = new \EDD\HTML\Text(
					array(
						'type'         => 'password',
						'data'         => array(
							'pw' => wp_generate_password( 16 ),
						),
						'name'         => $this->get_id(),
						'id'           => 'pass1',
						'class'        => $this->get_field_classes(),
						'required'     => false,
						'include_span' => false,
					)
				);
				$password->output();
				?>
				<button type="button" class="button button-secondary wp-hide-pw edd-has-js" data-toggle="0" aria-label="<?php esc_attr_e( 'Hide password', 'easy-digital-downloads' ); ?>">
					<span class="dashicons dashicons-hidden" aria-hidden="true"></span>
				</button>
			</div>
			<div id="pass-strength-result" class="edd-has-js" aria-live="polite"><?php esc_html_e( 'Strength indicator', 'easy-digital-downloads' ); ?></div>
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
		return 'edd-password';
	}

	/**
	 * Get the form group classes.
	 *
	 * @since 3.6.7
	 * @return array
	 */
	protected function get_form_group_classes(): array {
		$classes   = parent::get_form_group_classes();
		$classes[] = 'user-pass1-wrap';

		return $classes;
	}
}
