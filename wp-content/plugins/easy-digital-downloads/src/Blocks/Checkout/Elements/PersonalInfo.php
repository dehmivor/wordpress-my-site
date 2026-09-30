<?php
/**
 * Checkout Block Elements Personal Info
 *
 * @package     EDD\Blocks\Checkout\Elements
 * @copyright   Copyright (c) 2025, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.0
 */

namespace EDD\Blocks\Checkout\Elements;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Personal Info class.
 *
 * @since 3.6.0
 */
class PersonalInfo {

	/**
	 * Wrapper modifier that pairs the first and last name fields on one line.
	 *
	 * The checkout block stylesheet and the Elementor editor-preview fidelity CSS both key their
	 * paired-name geometry off this class, so it is the single source for the string in PHP.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	public const CLASS_NAME_SINGLE_LINE = 'edd-checkout-block__personal-info--name-single-line';

	/**
	 * Shows the login and/or registration form for guest users in checkout.
	 *
	 * @since 3.6.0
	 * @param array $block_attributes The block attributes.
	 * @return void
	 */
	public static function render( $block_attributes ) {
		$customer               = \EDD\Blocks\Checkout\get_customer();
		$customer_info_complete = \EDD\Blocks\Checkout\Attributes::is_customer_info_complete( $block_attributes );
		if ( $customer_info_complete ) {
			include EDD_BLOCKS_DIR . 'views/checkout/purchase-form/logged-in.php';

			$required_fields = array_keys( edd_purchase_form_required_fields() );
			$customer_fields = array(
				'email'      => 'edd_email',
				'first_name' => 'edd_first',
				'last_name'  => 'edd_last',
			);
			foreach ( $customer_fields as $field => $meta_key ) {
				if ( empty( $customer[ $field ] ) && in_array( $meta_key, $required_fields, true ) ) {
					$customer_info_complete = false;
					break;
				}
			}
			if ( $customer_info_complete && ! has_action( 'edd_purchase_form_user_info_fields' ) ) {
				return;
			}
		}
		?>
		<div class="edd-blocks__checkout-user">
			<?php
			$forms = self::get_personal_info_forms( $block_attributes, $customer_info_complete );
			$count = count( $forms );
			if ( ! empty( $forms ) && $count > 1 ) {
				wp_enqueue_script( 'edd-blocks-checkout-forms' );
				$i     = 0;
				$class = 'edd-blocks__checkout-forms';
				if ( $count < 3 ) {
					$class .= ' edd-blocks__checkout-forms--inline';
				}
				echo '<div id="edd-account-forms" class="' . esc_attr( $class ) . '">';
				foreach ( $forms as $id => $form ) {
					printf(
						'<button type="button" class="edd-button-secondary edd-blocks__checkout-%1$s link" data-attr="%1$s"%2$s>%3$s</button>',
						esc_attr( $id ),
						empty( $i ) ? ' disabled' : '',
						esc_html( $form['label'] )
					);
					++$i;
				}
				echo '</div>';
			}
			$form = reset( $forms );
			// Not every caller carries the attribute: the parent checkout block does not declare
			// it, and the Elementor widget passes it down from its own control.
			$customer['name_single_line'] = ! empty( $block_attributes['name_single_line'] );
			echo '<div class="' . esc_attr( self::get_wrapper_class( $block_attributes ) ) . '">';
			if ( $form && ! empty( $form['view'] ) ) {
				if ( is_callable( $form['view'] ) ) {
					echo call_user_func( $form['view'], array( 'current' => true ) );
				} elseif ( \EDD\Utils\FileSystem::file_exists( $form['view'] ) ) {
					include $form['view'];
				}
			} else {
				do_action( 'edd_purchase_form_user_info_fields', $customer );
			}
			?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get the classes for the personal info wrapper.
	 *
	 * The single-line name modifier lives here rather than on the fieldset because the
	 * AJAX form swap re-renders the fieldset without the block attributes, and this
	 * wrapper is outside the swapped markup.
	 *
	 * @since 3.7.0
	 * @param array $block_attributes The block attributes.
	 * @return string
	 */
	private static function get_wrapper_class( $block_attributes ) {
		$classes = array( 'edd-checkout-block__personal-info' );
		if ( ! empty( $block_attributes['name_single_line'] ) ) {
			$classes[] = self::CLASS_NAME_SINGLE_LINE;
		}

		return implode( ' ', $classes );
	}

	/**
	 * Gets the array of personal info forms for the checkout form.
	 *
	 * @since 2.0
	 * @param array $block_attributes       The block attributes.
	 * @param bool  $customer_info_complete Whether the logged in customer information is complete.
	 * @return array
	 */
	public static function get_personal_info_forms( $block_attributes, $customer_info_complete = true ) {
		$forms = array();
		if ( is_user_logged_in() && $customer_info_complete ) {
			return $forms;
		}
		$options = self::get_forms();
		if ( ! edd_no_guest_checkout() || ( ! $customer_info_complete && is_user_logged_in() ) ) {
			$forms['guest'] = $options['guest'];
		}
		if ( ! empty( $block_attributes['show_register_form'] ) && ( ! is_user_logged_in() || \EDD\Blocks\Utility::doing_guest_preview() ) ) {
			$setting = $block_attributes['show_register_form'];
			if ( 'both' === $setting ) {
				$forms['register'] = $options['register'];
				$forms['login']    = $options['login'];
			} elseif ( 'registration' === $setting ) {
				$forms['register'] = $options['register'];
			} elseif ( ! empty( $options[ $setting ] ) ) {
				$forms[ $setting ] = $options[ $setting ];
			}
		}

		// If no forms have been set, add the registration form (guest checkout is disabled).
		if ( empty( $forms ) ) {
			$forms['register'] = $options['register'];
		}

		return $forms;
	}

	/**
	 * Gets the array of forms for the checkout form.
	 *
	 * @since 3.6.0
	 * @return array
	 */
	private static function get_forms() {
		return array(
			'login'    => array(
				'label' => __( 'Log in', 'easy-digital-downloads' ),
				'view'  => EDD_BLOCKS_DIR . 'views/checkout/purchase-form/login.php',
			),
			'register' => array(
				'label' => __( 'Register for a new account', 'easy-digital-downloads' ),
				'view'  => EDD_BLOCKS_DIR . 'views/checkout/purchase-form/register.php',
			),
			'guest'    => array(
				'label' => __( 'Checkout as a guest', 'easy-digital-downloads' ),
				'view'  => EDD_BLOCKS_DIR . 'views/checkout/purchase-form/personal-info.php',
			),
		);
	}
}
