<?php
/**
 * User Profile Billing State Field.
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
use EDD\HTML\Region;

/**
 * User profile billing state/province field.
 *
 * Renders a select dropdown when states are available for the selected country,
 * or a text input otherwise.
 *
 * @since 3.6.7
 */
class State extends Field {

	/**
	 * Get the field ID.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get_id(): string {
		return 'edd_address_state';
	}

	/**
	 * Get the field label.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get_label(): string {
		return esc_html__( 'State / Province', 'easy-digital-downloads' );
	}

	/**
	 * Render the input.
	 *
	 * @since 3.6.7
	 */
	public function do_input(): void {
		$selected_state = $this->get_selected_state();
		?>
		<div class="edd-blocks-form__control">
			<?php
			$input = new Region(
				wp_parse_args(
					array(
						'placeholder'       => esc_html__( 'State / Province', 'easy-digital-downloads' ),
						'value'             => $selected_state,
						'country'           => $this->get_selected_country(),
						'selected'          => $selected_state,
						'chosen'            => false,
						'show_option_empty' => esc_html__( 'Select a State / Province', 'easy-digital-downloads' ),
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
		return 'address-state';
	}

	/**
	 * Get the field classes.
	 *
	 * @since 3.6.7
	 * @return array
	 */
	protected function get_field_classes(): array {
		$classes   = parent::get_field_classes();
		$classes[] = 'card_state';

		return $classes;
	}
}
