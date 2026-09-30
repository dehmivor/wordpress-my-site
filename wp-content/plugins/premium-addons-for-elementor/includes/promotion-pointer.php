<?php

use PremiumAddons\Includes\Helper_Functions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

add_action(
	'in_admin_header',
	function () {

		if ( Helper_Functions::check_papro_version() || time() > strtotime( '09:59:59pm 30th September, 2026' ) || ( $GLOBALS['pagenow'] !== 'index.php' && get_current_screen()->id !== 'toplevel_page_premium-addons' ) || get_transient( 'pa_sumr26_pointer_dismiss' ) ) {
			return;
		}

		wp_enqueue_script( 'jquery' );
		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );

		$pointer_priority = get_option( '_pa_plugin_pointer_priority' );

		if ( empty( $pointer_priority ) || $pointer_priority > 1 ) {
			update_option( '_pa_plugin_pointer_priority', 1 );
			$pointer_priority = 1;
		}

		if ( absint( $pointer_priority ) === 1 ) {
			?>
			<script>
				jQuery(
					function () {
						jQuery('#toplevel_page_premium-addons').pointer(
							{
								content:
									"<h3 style='font-weight: 600;'>Summer Sale 2026!</h3>" +
									"<p style='margin: 1em 0;'>Unlock the full power of Elementor with 90+ advanced elements and 580+ templates. Build smarter and faster.</p>" +
									"<p><a class='button button-primary' href='<?php echo esc_attr( Helper_Functions::get_campaign_link( 'https://premiumaddons.com/pro/#get-pa-pro', 'pointer', 'wp-dash', 'summer26' ) ); ?>' target='_blank'>Save 30% Now</a></p>",

								position:
									{
										edge: 'left',
										align: 'center'
									},

								pointerClass:
									'wp-pointer',

								close: function () {
									jQuery.post(
										ajaxurl,
										{
											pointer: 'pa',
											action: 'dismiss-wp-pointer',
										}
									);
								},

							}
						).pointer('open');
					}
				);
			</script>
			<?php
		}
	}
);

add_action(
	'admin_init',
	function () {
		$action  = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
		$pointer = isset( $_POST['pointer'] ) ? sanitize_text_field( wp_unslash( $_POST['pointer'] ) ) : '';

		if ( 'dismiss-wp-pointer' === $action && 'pa' === $pointer ) {
			set_transient( 'pa_sumr26_pointer_dismiss', true, DAY_IN_SECONDS * 30 );
			delete_option( '_pa_plugin_pointer_priority' );
		}
	}
);
