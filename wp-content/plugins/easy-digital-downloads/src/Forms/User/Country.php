<?php
/**
 * User Profile Billing Country Field.
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
 * User profile billing country field.
 *
 * @since 3.6.7
 */
class Country extends Field {

	/**
	 * Get the field ID.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get_id(): string {
		return 'edd_address_country';
	}

	/**
	 * Get the field label.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get_label(): string {
		return esc_html__( 'Country', 'easy-digital-downloads' );
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
			$country_select = new \EDD\HTML\CountrySelect(
				wp_parse_args(
					array(
						'selected'          => $this->get_selected_country(),
						'autocomplete'      => 'billing country',
						'show_option_all'   => false,
						'show_option_none'  => false,
						'show_option_empty' => esc_html__( 'Select a Country', 'easy-digital-downloads' ),
						'data'              => array(
							'nonce' => wp_create_nonce( 'edd-country-field-nonce' ),
						),
					),
					$this->get_defaults()
				)
			);
			$country_select->output();
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
		return 'address-country';
	}
}
