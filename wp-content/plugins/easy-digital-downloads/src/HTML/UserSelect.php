<?php
/**
 * User Select HTML Element.
 *
 * @package EDD\HTML
 * @copyright Copyright (c) 2026, Sandhills Development, LLC
 * @license https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since 3.6.7
 */

namespace EDD\HTML;

defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class UserSelect
 *
 * Renders a user-search-powered select element backed by TomSelect (chosen).
 * On initial render only the pre-selected user (or the current logged-in user)
 * is included as an option; all other lookups are performed via the
 * `edd_user_search` AJAX action as the admin types.
 *
 * @since 3.6.7
 * @package EDD\HTML
 */
class UserSelect extends Select {

	/**
	 * Gets the HTML for the user select.
	 *
	 * Populates the initial option list with only the selected user(s) or,
	 * when nothing is selected, the currently logged-in user. This keeps the
	 * initial HTML payload small and delegates all search lookups to AJAX.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get() {
		$this->args['options'] = $this->get_initial_options();

		return parent::get();
	}

	/**
	 * Gets the default arguments for the user select.
	 *
	 * @since 3.6.7
	 * @return array
	 */
	protected function defaults() {
		return wp_parse_args(
			array(
				'name'              => 'user_id',
				'id'                => 'user_id',
				'chosen'            => true,
				'placeholder'       => __( 'Select a User', 'easy-digital-downloads' ),
				'multiple'          => false,
				'selected'          => 0,
				'required'          => false,
				'show_option_all'   => false,
				'show_option_none'  => false,
				'show_option_empty' => __( 'Select a User', 'easy-digital-downloads' ),
				'data'              => array(
					'search-type'        => 'user',
					'search-placeholder' => __( 'Search Users', 'easy-digital-downloads' ),
				),
			),
			parent::defaults()
		);
	}

	/**
	 * Gets the base CSS classes for the user select element.
	 *
	 * @since 3.6.7
	 * @return array
	 */
	protected function get_base_classes(): array {
		return array_merge( parent::get_base_classes(), array( 'edd-user-select' ) );
	}

	/**
	 * Builds the initial options array for the select element.
	 *
	 * Returns only the pre-selected user(s). When no user is selected, falls
	 * back to the current logged-in user so the dropdown is never completely
	 * empty on first render.
	 *
	 * @since 3.6.7
	 * @return array
	 */
	private function get_initial_options(): array {
		$options  = ! empty( $this->args['options'] ) ? $this->args['options'] : array();
		$selected = $this->args['selected'];

		if ( ! is_array( $selected ) ) {
			$selected = array( $selected );
		}

		$selected = array_filter( array_map( 'absint', $selected ) );

		foreach ( $selected as $user_id ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$options[ $user->ID ] = esc_html( $user->display_name );
			}
		}

		if ( empty( $options ) ) {
			$current_user = wp_get_current_user();
			if ( $current_user->exists() ) {
				$options[ $current_user->ID ] = esc_html( $current_user->display_name );
			}
		}

		return $options;
	}
}
