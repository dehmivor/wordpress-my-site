<?php
/**
 * Login Password Field.
 *
 * @package     EDD\Forms\Login
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.3.8
 */

namespace EDD\Forms\Login;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Forms\Fields\Field;

/**
 * Login Password Field.
 *
 * @since 3.3.8
 */
class Password extends Field {

	/**
	 * Render the field.
	 *
	 * @since 3.3.8
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
	 * @since 3.3.8
	 * @return string
	 */
	public function get_id(): string {
		return 'edd_user_pass';
	}

	/** Get the field label.
	 *
	 * @since 3.3.8
	 * @return string
	 */
	public function get_label(): string {
		return esc_html__( 'Password', 'easy-digital-downloads' );
	}

	/**
	 * Render the input.
	 *
	 * @since 3.3.8
	 */
	public function do_input(): void {
		$classes   = $this->get_field_classes();
		$classes[] = 'edd-password';
		?>
		<div class="edd-blocks-form__control">
			<input
				name="edd_user_pass"
				id="edd_user_pass"
				class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
				type="password"
				autocomplete="current-password"
				<?php echo $this->is_required() ? 'required' : ''; ?>
			/>
		</div>
		<?php
	}

	/**
	 * Get the description for the field.
	 *
	 * @since 3.3.8
	 * @return string
	 */
	public function get_description(): string {
		return '';
	}

	/**
	 * Checks if the field is required.
	 *
	 * @since 3.3.8
	 * @return bool
	 */
	protected function is_required(): bool {
		return empty( $this->data['not_required'] );
	}

	/**
	 * Get the field key.
	 *
	 * @since 3.3.8
	 * @return string
	 */
	protected function get_key(): string {
		return 'password';
	}

	/**
	 * Renders the field for the shortcode checkout, using legacy wrapper IDs and classes.
	 *
	 * @since 3.7.0
	 */
	private function render_shortcode(): void {
		$classes   = $this->get_field_classes();
		$classes[] = 'edd-password';
		?>
		<div id="edd-user-pass-wrap" class="edd_login_password">
			<?php $this->do_label(); ?>
			<input
				name="edd_user_pass"
				id="edd_user_pass"
				class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
				type="password"
				placeholder="<?php esc_attr_e( 'Your password', 'easy-digital-downloads' ); ?>"
				autocomplete="current-password"
				<?php echo $this->is_required() ? 'required' : ''; ?>
			/>
		</div>
		<?php
	}
}
