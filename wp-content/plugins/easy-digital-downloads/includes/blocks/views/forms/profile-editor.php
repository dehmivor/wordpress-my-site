<?php
/**
 * Profile editor form block template.
 *
 * @var array     $data             User, customer, and address data.
 * @var array     $block_attributes Block attributes controlling which fields are shown.
 * @var array     $classes          Block wrapper CSS classes.
 * @package EDD\Blocks\Views\Forms
 */

wp_enqueue_script( 'utils' );
wp_enqueue_script( 'user-profile' );
wp_enqueue_style( 'dashicons' );

$customer = $data['customer'] ?? null;

do_action( 'edd_profile_editor_before' );
?>

<form id="edd-blocks-form__profile-editor" class="edd-blocks-form edd-blocks-form__profile-editor" action="" method="post">

	<?php do_action( 'edd_profile_editor_fields_top' ); ?>

	<fieldset id="edd_profile_personal_fieldset">
		<legend><?php esc_html_e( 'Personal Information', 'easy-digital-downloads' ); ?></legend>

		<?php
		EDD\Forms\Handler::render_fields(
			array(
				'\\EDD\\Forms\\User\\FirstName',
				'\\EDD\\Forms\\User\\LastName',
				'\\EDD\\Forms\\User\\DisplayName',
			),
			$data
		);
		?>

		<?php do_action( 'edd_profile_editor_after_name' ); ?>

		<?php EDD\Forms\Handler::render_field( '\\EDD\\Forms\\User\\Email', $data ); ?>

		<?php do_action( 'edd_profile_editor_email' ); ?>

		<?php
		if ( $customer instanceof \EDD_Customer && $customer->id > 0 && count( $customer->emails ) > 1 ) :
			$emails = $customer->emails;
			?>
			<div class="edd-blocks-form__group edd-blocks-form__group-additional-emails">
				<label><?php esc_html_e( 'Additional Email Addresses', 'easy-digital-downloads' ); ?></label>
				<ul class="edd-profile-emails">
					<?php foreach ( $emails as $email ) : ?>
						<?php
						if ( $email === $customer->email ) {
							continue; }
						?>
						<li class="edd-profile-email">
							<?php echo esc_html( $email ); ?>
							<span class="actions">
								<?php
								$remove_url = wp_nonce_url(
									add_query_arg(
										array(
											'email'      => rawurlencode( $email ),
											'edd_action' => 'profile-remove-email',
											'redirect'   => edd_get_current_page_url(),
										)
									),
									'edd-remove-customer-email'
								);
								?>
								<a href="<?php echo esc_url( $remove_url ); ?>" class="delete"><?php esc_html_e( 'Remove', 'easy-digital-downloads' ); ?></a>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php do_action( 'edd_profile_editor_after_email' ); ?>

	</fieldset>

	<?php do_action( 'edd_profile_editor_after_personal_fields' ); ?>

	<?php
	$address_visible = array(
		'country'     => ! empty( $block_attributes['show_address_country'] ),
		'line1'       => ! empty( $block_attributes['show_address_line1'] ),
		'line2'       => ! empty( $block_attributes['show_address_line2'] ),
		'city'        => ! empty( $block_attributes['show_address_city'] ),
		'postal_code' => ! empty( $block_attributes['show_address_postal_code'] ),
		'state'       => ! empty( $block_attributes['show_address_state'] ),
		'company'     => ! empty( $block_attributes['show_company'] ),
		'phone'       => ! empty( $block_attributes['show_phone'] ),
	);

	if ( ! empty( array_filter( $address_visible ) ) ) :
		$address_field_map   = array(
			'country'     => '\\EDD\\Forms\\User\\Country',
			'line1'       => '\\EDD\\Forms\\User\\Line1',
			'line2'       => '\\EDD\\Forms\\User\\Line2',
			'city'        => '\\EDD\\Forms\\User\\City',
			'postal_code' => '\\EDD\\Forms\\User\\PostalCode',
			'state'       => '\\EDD\\Forms\\User\\State',
			'company'     => '\\EDD\\Forms\\User\\Company',
			'phone'       => '\\EDD\\Forms\\User\\Phone',
		);
		$address_field_order = ! empty( $block_attributes['field_order'] )
			? (array) $block_attributes['field_order']
			: array_keys( $address_field_map );
		?>
		<fieldset id="edd_profile_address_fieldset">
			<legend><?php esc_html_e( 'Billing Address', 'easy-digital-downloads' ); ?></legend>

			<?php
			foreach ( $address_field_order as $field_key ) {
				if ( empty( $address_visible[ $field_key ] ) || ! isset( $address_field_map[ $field_key ] ) ) {
					continue;
				}
				EDD\Forms\Handler::render_field( $address_field_map[ $field_key ], $data );
			}
			do_action( 'edd_profile_editor_address' );
			do_action( 'edd_profile_editor_after_address' );
			?>
		</fieldset>
	<?php endif; ?>

	<?php do_action( 'edd_profile_editor_after_address_fields' ); ?>

	<fieldset id="edd_profile_password_fieldset">
		<legend><?php esc_html_e( 'Password', 'easy-digital-downloads' ); ?></legend>

		<?php
		EDD\Forms\Handler::render_fields(
			array(
				'\\EDD\\Forms\\User\\Password',
				'\\EDD\\Forms\\User\\PasswordWeak',
				'\\EDD\\Forms\\User\\PasswordConfirm',
			),
			$data
		);
		?>

		<p class="description indicator-hint"><?php echo wp_kses_post( wp_get_password_hint() ); ?></p>

		<div class="user-pass1-wrap">
			<button type="button" class="button wp-generate-pw edd-has-js edd-button-secondary"><?php esc_html_e( 'Generate Password', 'easy-digital-downloads' ); ?></button>
		</div>

		<?php do_action( 'edd_profile_editor_password' ); ?>

		<?php do_action( 'edd_profile_editor_after_password' ); ?>

	</fieldset>

	<?php do_action( 'edd_profile_editor_after_password_fields' ); ?>

	<div class="edd-blocks-form__group edd-blocks-form__group-submit">
		<input type="hidden" name="edd_profile_editor_nonce" value="<?php echo esc_attr( wp_create_nonce( 'edd-profile-editor-nonce' ) ); ?>" />
		<input type="hidden" name="edd_action" value="edit_user_profile" />
		<input type="hidden" name="edd_redirect" value="<?php echo esc_url( edd_get_current_page_url() ); ?>" />
		<input class="<?php echo esc_attr( implode( ' ', EDD\Blocks\Functions\get_button_classes() ) ); ?>" name="edd_profile_editor_submit" id="submit" type="submit" value="<?php esc_attr_e( 'Save Changes', 'easy-digital-downloads' ); ?>" />
	</div>

	<?php do_action( 'edd_profile_editor_fields_bottom' ); ?>

</form>

<?php do_action( 'edd_profile_editor_after' ); ?>
