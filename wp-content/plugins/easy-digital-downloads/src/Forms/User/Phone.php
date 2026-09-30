<?php
/**
 * User Profile Phone Field.
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
 * User profile phone number field.
 *
 * @since 3.6.7
 */
class Phone extends Field {

	/**
	 * Get the field ID.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get_id(): string {
		return 'edd_phone';
	}

	/**
	 * Get the field label.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get_label(): string {
		return esc_html__( 'Phone Number', 'easy-digital-downloads' );
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
			$input = new \EDD\HTML\Phone(
				wp_parse_args(
					array(
						'placeholder' => __( '(001) 555-0123', 'easy-digital-downloads' ),
						'value'       => $this->data['address']['phone'] ?? '',
						'data'        => array(
							'country' => strtolower( $this->get_selected_country() ),
						),
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
		return 'address-phone';
	}
}
