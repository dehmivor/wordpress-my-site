<?php
/**
 * Registration Username Field.
 *
 * @package     EDD\Forms\Register
 * @copyright   Copyright (c) 2025, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.3.9
 */

namespace EDD\Forms\Register;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Forms\Fields\Field;

/**
 * Registration Username Field.
 *
 * @since 3.3.9
 */
class Username extends Field {

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
		return $this->is_block() ? 'edd_user_register' : 'edd_user_login';
	}

	/** Get the field label.
	 *
	 * @since 3.3.9
	 * @return string
	 */
	public function get_label(): string {
		return esc_html__( 'Username or Email', 'easy-digital-downloads' );
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
			$login = new \EDD\HTML\Text(
				array(
					'name'         => 'edd_user_login',
					'id'           => $this->get_id(),
					'class'        => $this->get_field_classes(),
					'required'     => $this->is_required(),
					'include_span' => false,
				)
			);
			$login->output();
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
		return $this->is_block() ? '' : __( 'The username you will use to log into your account.', 'easy-digital-downloads' );
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
		return 'username';
	}

	/**
	 * Renders the field for the shortcode checkout, using legacy wrapper IDs.
	 *
	 * @since 3.7.0
	 */
	private function render_shortcode(): void {
		$required = $this->is_required();
		?>
		<div id="edd-user-login-wrap">
			<?php
			$this->do_label();
			$this->do_description();
			$login = new \EDD\HTML\Text(
				array(
					'name'         => 'edd_user_login',
					'id'           => 'edd_user_login',
					'class'        => $this->get_field_classes(),
					'placeholder'  => esc_html__( 'Username', 'easy-digital-downloads' ),
					'required'     => $required,
					'include_span' => false,
				)
			);
			$login->output();
			?>
		</div>
		<?php
	}
}
