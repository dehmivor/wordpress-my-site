<?php
/**
 * User Profile Display Name Field.
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
 * User profile display name field.
 *
 * @since 3.6.7
 */
class DisplayName extends Field {

	/**
	 * Get the field ID.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get_id(): string {
		return 'edd_display_name';
	}

	/**
	 * Get the field label.
	 *
	 * @since 3.6.7
	 * @return string
	 */
	public function get_label(): string {
		return esc_html__( 'Display Name', 'easy-digital-downloads' );
	}

	/**
	 * Render the input.
	 *
	 * @since 3.6.7
	 */
	public function do_input(): void {
		$user         = $this->data['user'] ?? null;
		$display_name = $user ? $user->display_name : '';
		$options      = $this->get_display_name_options( $user );
		?>
		<div class="edd-blocks-form__control">
			<select name="<?php echo esc_attr( $this->get_id() ); ?>" id="<?php echo esc_attr( $this->get_id() ); ?>" class="<?php echo esc_attr( $this->get_css_class_string( $this->get_field_classes() ) ); ?>">
				<?php foreach ( $options as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $display_name, $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
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
		return 'display-name';
	}

	/**
	 * Build the list of display name options for the current user.
	 *
	 * @since 3.6.7
	 * @param \WP_User|null $user The current user.
	 * @return array<string, string> Map of option value => label.
	 */
	private function get_display_name_options( ?\WP_User $user ): array {
		if ( ! $user ) {
			return array();
		}

		$options = array();

		if ( ! empty( $user->first_name ) ) {
			$options[ $user->first_name ] = $user->first_name;
		}

		$options[ $user->user_nicename ] = $user->user_nicename;

		if ( ! empty( $user->last_name ) ) {
			$options[ $user->last_name ] = $user->last_name;
		}

		if ( ! empty( $user->first_name ) && ! empty( $user->last_name ) ) {
			$full_name         = $user->first_name . ' ' . $user->last_name;
			$full_name_reverse = $user->last_name . ' ' . $user->first_name;

			$options[ $full_name ]         = $full_name;
			$options[ $full_name_reverse ] = $full_name_reverse;
		}

		if ( ! empty( $user->display_name ) && ! isset( $options[ $user->display_name ] ) ) {
			$options[ $user->display_name ] = $user->display_name;
		}

		return $options;
	}
}
