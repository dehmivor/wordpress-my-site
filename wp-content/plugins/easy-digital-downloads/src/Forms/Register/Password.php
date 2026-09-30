<?php
/**
 * Registration Password Field.
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
 * Registration Password Field.
 *
 * @since 3.3.9
 */
class Password extends Field {

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
		return $this->is_block() ? 'pass1' : 'edd_user_pass';
	}

	/** Get the field label.
	 *
	 * @since 3.3.9
	 * @return string
	 */
	public function get_label(): string {
		return esc_html__( 'Password', 'easy-digital-downloads' );
	}

	/**
	 * Render the input.
	 *
	 * @since 3.3.9
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
							'reveal' => 1,
							'pw'     => wp_generate_password( 16 ),
						),
						'name'         => 'edd_user_pass',
						'id'           => $this->get_id(),
						'class'        => $this->get_field_classes(),
						'required'     => $this->is_required(),
						'include_span' => false,
					)
				);
				$password->output();
				// The registration block uses WP scripts, but the registration form on checkout does not.
				if ( empty( $this->data['no_wp_scripts'] ) ) {
					?>
					<button type="button" class="button button-secondary wp-hide-pw edd-has-js" data-toggle="0" aria-label="<?php esc_attr_e( 'Hide password', 'easy-digital-downloads' ); ?>">
						<span class="dashicons dashicons-hidden" aria-hidden="true"></span>
					</button>
				<?php } ?>
			</div>
			<?php if ( empty( $this->data['no_wp_scripts'] ) ) : ?>
				<div id="pass-strength-result" class="edd-has-js" aria-live="polite"><?php esc_html_e( 'Strength indicator', 'easy-digital-downloads' ); ?></div>
			<?php endif; ?>
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
		return $this->is_block() ? '' : __( 'The password used to access your account.', 'easy-digital-downloads' );
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
		return 'edd-password';
	}

	/**
	 * Get the form group classes.
	 *
	 * @since 3.3.9
	 * @return array
	 */
	protected function get_form_group_classes(): array {
		$classes   = parent::get_form_group_classes();
		$classes[] = 'user-pass1-wrap';

		return $classes;
	}

	/**
	 * Renders the field for the shortcode checkout, using legacy wrapper IDs.
	 *
	 * @since 3.7.0
	 */
	private function render_shortcode(): void {
		$required = $this->is_required();
		?>
		<div id="edd-user-pass-wrap">
			<?php
			$this->do_label();
			$this->do_description();
			$password = new \EDD\HTML\Text(
				array(
					'type'         => 'password',
					'name'         => 'edd_user_pass',
					'id'           => 'edd_user_pass',
					'class'        => $this->get_field_classes(),
					'placeholder'  => esc_html__( 'Password', 'easy-digital-downloads' ),
					'required'     => $required,
					'include_span' => false,
				)
			);
			$password->output();
			?>
		</div>
		<?php
	}
}
