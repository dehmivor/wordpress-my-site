<?php
/**
 * Company Class.
 *
 * @package     EDD\Forms\Checkout
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.7
 */

namespace EDD\Forms\Checkout;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * The company name field.
 *
 * @since 3.6.7
 */
class Company extends Field {

	/**
	 * Get the field ID.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get_id(): string {
		return 'company';
	}

	/**
	 * Get the field label.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get_label(): string {
		return esc_html__( 'Company', 'easy-digital-downloads' );
	}

	/**
	 * Render the input.
	 *
	 * @since 3.6.7
	 */
	public function do_input(): void {
		$input = new \EDD\HTML\Text(
			wp_parse_args(
				array(
					'placeholder'  => esc_html__( 'Company', 'easy-digital-downloads' ),
					'value'        => $this->data['address']['company'],
					'autocomplete' => 'organization',
					'include_span' => false,
				),
				$this->get_defaults()
			)
		);
		$input->output();
	}

	/**
	 * Get the description.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get_description(): string {
		return __( 'The company or organization name for your order.', 'easy-digital-downloads' );
	}

	/**
	 * Get the field key.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	protected function get_key(): string {
		return 'company';
	}
}
