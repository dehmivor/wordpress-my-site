<?php
/**
 * Templates Loader Error
 */
?>

<div class="elementor-library-error">
	<div class="elementor-library-error-message">
		<span>
			<?php esc_html_e( 'This template requires', 'premium-addons-for-elementor' ); ?>
			<# if( widgetURL ) { #>
				<a class='elementor-library-enable-element' href="{{ widgetURL }}" target='_blank'>{{{ name }}}</a>
			<# } else { #>
				<span>{{{ name }}} feature to enabled. Enable it from WP Dashboard -> Elementor -> Settings -> Features tab.</span>
			<# } #>

			<# if( url ) { #>
			<?php esc_html_e( 'widget to be enabled. Enable it from ', 'premium-addons-for-elementor' ); ?>
			<a class='elementor-library-enable-element' href="{{ url }}" target='_blank'>here</a><?php esc_html_e( ', refresh this page and try to insert the template again.', 'premium-addons-for-elementor' ); ?>
			<# } #>
			<# if( 'Contact Form 7' === name ) { #>
				<?php esc_html_e( ' Also, make sure to install Contact Form 7 plugin from ', 'premium-addons-for-elementor' ); ?>
				<a class='elementor-library-enable-element' href="https://wordpress.org/plugins/contact-form-7/" target='_blank'>here</a>
			<# } #>
		</span>
	</div>
</div>
