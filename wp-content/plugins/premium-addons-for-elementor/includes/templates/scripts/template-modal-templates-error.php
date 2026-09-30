<?php
/**
 * Templates Load Failure View
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="elementor-library-error">
	<div class="elementor-library-error-message">
	<?php
		echo wp_kses_post( __( 'Templates couldn\'t be loaded. The templates server may be temporarily unavailable.', 'premium-addons-for-elementor' ) );
	?>
	</div>
	<div class="elementor-library-error-link">
		<button type="button" class="premium-templates-retry"><?php echo esc_html__( 'Try Again', 'premium-addons-for-elementor' ); ?></button>
	</div>
</div>
