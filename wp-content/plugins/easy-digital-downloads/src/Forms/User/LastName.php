<?php
/**
 * User Profile Last Name Field.
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
 * User profile last name field.
 *
 * @since 3.6.7
 */
class LastName extends Field {

	/**
	 * Get the field ID.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get_id(): string {
		return 'edd_last_name';
	}

	/**
	 * Get the field label.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get_label(): string {
		return esc_html__( 'Last Name', 'easy-digital-downloads' );
	}

	/**
	 * Render the input.
	 *
	 * @since 3.6.7
	 */
	public function do_input(): void {
		?>
		<div class="edd-blocks-form__control">
			<?php
			$input = new \EDD\HTML\Text(
				wp_parse_args(
					array(
						'value'        => ! empty( $this->data['user']->last_name ) ? $this->data['user']->last_name : '',
						'autocomplete' => 'family-name',
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
		return 'last-name';
	}
}
