<?php require 'personal-info.php'; ?>
<fieldset id="edd_register_fields" class="edd-blocks-form">
	<legend><?php esc_html_e( 'Register For a New Account', 'easy-digital-downloads' ); ?></legend>
	<?php
	\EDD\Forms\Handler::render_fields(
		array(
			'\\EDD\\Forms\\Register\\Username',
			'\\EDD\\Forms\\Register\\Password',
			'\\EDD\\Forms\\Register\\PasswordConfirm',
		),
		array(
			'no_wp_scripts' => true,
			'is_checkout'   => true,
		)
	);
	?>
	<input type="hidden" name="edd-purchase-var" value="needs-to-register"/>
</fieldset>
