<?php
/**
 * Edit customer dialog.
 *
 * @package EDD\Admin\Views\Customer
 * @copyright Copyright (c) 2026, Easy Digital Downloads, LLC
 * @license http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since 3.6.7
 * @param \EDD_Customer $customer Customer object.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! current_user_can( edd_get_edit_customers_role() ) ) {
	return;
}

// Country.
$selected_country = $address['country'];
$countries        = edd_get_country_list();
$states           = edd_get_shop_states( $selected_country );

// State.
$selected_state = edd_get_shop_state();
$selected_state = isset( $address['region'] )
	? $address['region']
	: $selected_state;

?>
<dialog id="edd-edit-customer-dialog" class="edd-modal edd-modal--edit-customer" aria-labelledby="edd-edit-customer-dialog__title">
	<div class="edd-modal__header">
		<h2 id="edd-edit-customer-dialog__title"><?php esc_html_e( 'Edit Profile', 'easy-digital-downloads' ); ?></h2>
		<button type="button" class="edd-modal__close" aria-label="<?php esc_attr_e( 'Close', 'easy-digital-downloads' ); ?>">
			<span class="dashicons dashicons-no-alt"></span>
			<span class="screen-reader-text"><?php esc_html_e( 'Close', 'easy-digital-downloads' ); ?></span>
		</button>
	</div>
	<form id="edit-customer-info" method="post" action="
		<?php
		echo esc_url(
			edd_get_admin_url(
				array(
					'page' => 'edd-customers',
					'view' => 'overview',
					'id'   => absint( $customer->id ),
				)
			)
		);
		?>
	">
		<div class="edd-modal__content">
			<input type="hidden" data-key="id" name="customerinfo[id]" value="<?php echo esc_attr( $customer->id ); ?>" />
			<input type="hidden" name="edd_action" value="edit-customer" />
			<?php wp_nonce_field( 'edit-customer', '_wpnonce', false, true ); ?>

			<div class="edd-form-group">
				<label class="edd-form-group__label" for="customerinfo-name"><?php esc_html_e( 'Name', 'easy-digital-downloads' ); ?></label>
				<div class="edd-form-group__control">
					<input id="customerinfo-name" type="text" name="customerinfo[name]" value="<?php echo esc_attr( $customer->name ); ?>" placeholder="<?php esc_attr_e( 'Customer Name', 'easy-digital-downloads' ); ?>" class="regular-text" />
				</div>
			</div>

			<div class="edd-form-group">
				<label class="edd-form-group__label" for="customerinfo-email"><?php esc_html_e( 'Email', 'easy-digital-downloads' ); ?></label>
				<div class="edd-form-group__control">
					<input id="customerinfo-email" type="email" name="customerinfo[email]" value="<?php echo esc_attr( $customer->email ); ?>" placeholder="<?php esc_attr_e( 'Customer Email', 'easy-digital-downloads' ); ?>" class="regular-text" />
				</div>
			</div>

			<div class="edd-form-group">
				<label class="edd-form-group__label" for="customerinfo-date-created"><?php esc_html_e( 'Customer Since', 'easy-digital-downloads' ); ?></label>
				<div class="edd-form-group__control">
					<input id="customerinfo-date-created" type="text" name="customerinfo[date_created]" value="<?php echo esc_attr( $customer->date_created ); ?>" placeholder="<?php esc_attr_e( 'Customer Since', 'easy-digital-downloads' ); ?>" class="edd_datepicker" />
				</div>
			</div>

			<div class="edd-form-group">
				<label class="edd-form-group__label" for="customerinfo-status"><?php esc_html_e( 'Status', 'easy-digital-downloads' ); ?></label>
				<div class="edd-form-group__control">
					<select id="customerinfo-status" name="customerinfo[status]">
						<option value="active"<?php selected( $customer->status, 'active' ); ?>><?php esc_html_e( 'Active', 'easy-digital-downloads' ); ?></option>
						<option value="inactive"<?php selected( $customer->status, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'easy-digital-downloads' ); ?></option>
						<option value="disabled"<?php selected( $customer->status, 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'easy-digital-downloads' ); ?></option>
					</select>
				</div>
			</div>

			<div class="edd-form-group">
				<label class="edd-form-group__label" for="user_id"><?php esc_html_e( 'Linked User', 'easy-digital-downloads' ); ?></label>
				<div class="edd-form-group__control">
					<?php echo EDD()->html->ajax_user_search( $user_args ); ?>
				</div>
			</div>

			<div class="edd-form-group">
				<label class="edd-form-group__label" for="customerinfo-company"><?php esc_html_e( 'Company', 'easy-digital-downloads' ); ?></label>
				<div class="edd-form-group__control">
					<input id="customerinfo-company" type="text" name="customerinfo[company_name]" value="<?php echo esc_attr( $company ); ?>" placeholder="<?php esc_attr_e( 'Company', 'easy-digital-downloads' ); ?>" class="regular-text" />
				</div>
			</div>

			<fieldset class="edd-edit-customer-address">
				<legend><?php esc_html_e( 'Address', 'easy-digital-downloads' ); ?></legend>
				<div class="edd-form-group">
					<label class="edd-form-group__label" for="customerinfo-address"><?php esc_html_e( 'Address Line 1', 'easy-digital-downloads' ); ?></label>
					<div class="edd-form-group__control">
						<input id="customerinfo-address" type="text" name="customerinfo[address]" value="<?php echo esc_attr( $address['address'] ); ?>" placeholder="<?php esc_attr_e( 'Address 1', 'easy-digital-downloads' ); ?>" class="regular-text" />
					</div>
				</div>

				<div class="edd-form-group">
					<label class="edd-form-group__label" for="customerinfo-address2"><?php esc_html_e( 'Address Line 2', 'easy-digital-downloads' ); ?></label>
					<div class="edd-form-group__control">
						<input id="customerinfo-address2" type="text" name="customerinfo[address2]" value="<?php echo esc_attr( $address['address2'] ); ?>" placeholder="<?php esc_attr_e( 'Address 2', 'easy-digital-downloads' ); ?>" class="regular-text" />
					</div>
				</div>

				<div class="edd-form-group">
					<label class="edd-form-group__label" for="customerinfo-city"><?php esc_html_e( 'City', 'easy-digital-downloads' ); ?></label>
					<div class="edd-form-group__control">
						<input id="customerinfo-city" type="text" name="customerinfo[city]" value="<?php echo esc_attr( $address['city'] ); ?>" placeholder="<?php esc_attr_e( 'City', 'easy-digital-downloads' ); ?>" class="regular-text" />
					</div>
				</div>

				<div class="edd-form-group">
					<label class="edd-form-group__label" for="customerinfo_country"><?php esc_html_e( 'Country', 'easy-digital-downloads' ); ?></label>
					<div class="edd-form-group__control">
						<?php
						$country_select = new \EDD\HTML\CountrySelect(
							array(
								'name'              => 'customerinfo[country]',
								'id'                => 'customerinfo_country',
								'selected'          => $selected_country,
								'show_option_all'   => false,
								'show_option_none'  => false,
								'show_option_empty' => __( 'Select a Country', 'easy-digital-downloads' ),
							)
						);
						$country_select->output();
						?>
					</div>
				</div>

				<div class="edd-form-group">
					<label class="edd-form-group__label" for="customerinfo_region"><?php esc_html_e( 'State / Province', 'easy-digital-downloads' ); ?></label>
					<div class="edd-form-group__control">
						<?php if ( ! empty( $states ) ) : ?>
							<?php
							$region_select = new \EDD\HTML\RegionSelect(
								array(
									'name'     => 'customerinfo[region]',
									'id'       => 'customerinfo_region',
									'country'  => $selected_country,
									'selected' => $selected_state,
								)
							);
							$region_select->output();
							?>
						<?php else : ?>
							<input type="text" name="customerinfo[region]" id="customerinfo_region" class="edd_regions_filter edd-input regular-text" placeholder="<?php esc_html_e( 'State / Province', 'easy-digital-downloads' ); ?>" value="<?php echo esc_attr( $selected_state ); ?>" />
						<?php endif; ?>
					</div>
				</div>

				<div class="edd-form-group">
					<label class="edd-form-group__label" for="customerinfo-postal-code"><?php esc_html_e( 'Postal Code', 'easy-digital-downloads' ); ?></label>
					<div class="edd-form-group__control">
						<input id="customerinfo-postal-code" type="text" name="customerinfo[postal_code]" value="<?php echo esc_attr( $address['postal_code'] ); ?>" placeholder="<?php esc_attr_e( 'Postal Code', 'easy-digital-downloads' ); ?>" class="regular-text" />
					</div>
				</div>

				<div class="edd-form-group">
					<label class="edd-form-group__label" for="phone"><?php esc_html_e( 'Phone', 'easy-digital-downloads' ); ?></label>
					<div class="edd-form-group__control">
						<?php
						$phone_input = new \EDD\HTML\Phone(
							array(
								'name'        => 'customerinfo[phone]',
								'id'          => 'phone',
								'value'       => $phone,
								'data'        => array(
									'country' => strtolower( $address['country'] ),
								),
								'placeholder' => __( 'Phone', 'easy-digital-downloads' ),
							)
						);
						$phone_input->output();
						?>
					</div>
				</div>
			</fieldset>
		</div>
		<div class="edd-modal__footer">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Update', 'easy-digital-downloads' ); ?></button>
		</div>
	</form>
</dialog>
