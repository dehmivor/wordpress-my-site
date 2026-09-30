import { __ } from '@wordpress/i18n';
import { Button, Disabled, PanelBody, ToggleControl } from '@wordpress/components';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import './editor.scss';

/**
 * Maps field keys to their sidebar label and block attribute name.
 */
const fieldConfig = {
	country:     { label: __( 'Country', 'easy-digital-downloads' ),        attr: 'show_address_country' },
	line1:       { label: __( 'Line 1', 'easy-digital-downloads' ),         attr: 'show_address_line1' },
	line2:       { label: __( 'Line 2', 'easy-digital-downloads' ),         attr: 'show_address_line2' },
	city:        { label: __( 'City', 'easy-digital-downloads' ),           attr: 'show_address_city' },
	postal_code: { label: __( 'Postal Code', 'easy-digital-downloads' ),    attr: 'show_address_postal_code' },
	state:       { label: __( 'State / Province', 'easy-digital-downloads' ), attr: 'show_address_state' },
	company:     { label: __( 'Company', 'easy-digital-downloads' ),        attr: 'show_company' },
	phone:       { label: __( 'Phone Number', 'easy-digital-downloads' ),   attr: 'show_phone' },
};

/**
 * Renders the static preview markup for a given field key.
 *
 * @param {string} key Field key from fieldConfig.
 * @return {WPElement|null}
 */
const render_field_preview = ( key ) => {
	switch ( key ) {
		case 'country':
			return (
				<div key="country" className="edd-blocks-form__group edd-blocks-form__group-address-country">
					<label htmlFor="edd_address_country">{__( 'Country', 'easy-digital-downloads' )}</label>
					<div className="edd-blocks-form__control">
						<select name="edd_address_country" id="edd_address_country" className="edd-input" readOnly>
							<option>{__( 'Select a Country', 'easy-digital-downloads' )}</option>
						</select>
					</div>
				</div>
			);
		case 'line1':
			return (
				<div key="line1" className="edd-blocks-form__group edd-blocks-form__group-address-line1">
					<label htmlFor="edd_address_line1">{__( 'Line 1', 'easy-digital-downloads' )}</label>
					<div className="edd-blocks-form__control">
						<input name="edd_address_line1" id="edd_address_line1" className="edd-input" type="text" readOnly />
					</div>
				</div>
			);
		case 'line2':
			return (
				<div key="line2" className="edd-blocks-form__group edd-blocks-form__group-address-line2">
					<label htmlFor="edd_address_line2">{__( 'Line 2', 'easy-digital-downloads' )}</label>
					<div className="edd-blocks-form__control">
						<input name="edd_address_line2" id="edd_address_line2" className="edd-input" type="text" readOnly />
					</div>
				</div>
			);
		case 'city':
			return (
				<div key="city" className="edd-blocks-form__group edd-blocks-form__group-address-city">
					<label htmlFor="edd_address_city">{__( 'City', 'easy-digital-downloads' )}</label>
					<div className="edd-blocks-form__control">
						<input name="edd_address_city" id="edd_address_city" className="edd-input" type="text" readOnly />
					</div>
				</div>
			);
		case 'postal_code':
			return (
				<div key="postal_code" className="edd-blocks-form__group edd-blocks-form__group-address-postal-code">
					<label htmlFor="edd_address_zip">{__( 'Postal Code', 'easy-digital-downloads' )}</label>
					<div className="edd-blocks-form__control">
						<input name="edd_address_zip" id="edd_address_zip" className="edd-input" type="text" readOnly />
					</div>
				</div>
			);
		case 'state':
			return (
				<div key="state" className="edd-blocks-form__group edd-blocks-form__group-address-state">
					<label htmlFor="edd_address_state">{__( 'State / Province', 'easy-digital-downloads' )}</label>
					<div className="edd-blocks-form__control">
						<input name="edd_address_state" id="edd_address_state" className="edd-input" type="text" readOnly />
					</div>
				</div>
			);
		case 'company':
			return (
				<div key="company" className="edd-blocks-form__group edd-blocks-form__group-company">
					<label htmlFor="edd_company">{__( 'Company', 'easy-digital-downloads' )}</label>
					<div className="edd-blocks-form__control">
						<input name="edd_company" id="edd_company" className="edd-input" type="text" readOnly />
					</div>
				</div>
			);
		case 'phone':
			return (
				<div key="phone" className="edd-blocks-form__group edd-blocks-form__group-phone">
					<label htmlFor="edd_phone">{__( 'Phone Number', 'easy-digital-downloads' )}</label>
					<div className="edd-blocks-form__control">
						<input name="edd_phone" id="edd_phone" className="edd-input" type="tel" readOnly />
					</div>
				</div>
			);
		default:
			return null;
	}
};

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#edit
 *
 * @return {WPElement} Element to render.
 */
export default function Edit( { attributes, setAttributes } ) {
	const move_field = ( index, direction ) => {
		const order = [ ...attributes.field_order ];
		const target = index + direction;
		if ( target < 0 || target >= order.length ) {
			return;
		}
		[ order[ index ], order[ target ] ] = [ order[ target ], order[ index ] ];
		setAttributes( { field_order: order } );
	};

	return (
		<div {...useBlockProps()}>
			<InspectorControls>
				<PanelBody title={__( 'Address Fields', 'easy-digital-downloads' )}>
					{attributes.field_order.map( ( key, index ) => {
						const field = fieldConfig[ key ];
						if ( ! field ) {
							return null;
						}
						return (
							<div key={key} className="edd-profile-editor-field-row">
								<div className="edd-profile-editor-field-order">
									<Button
										size="small"
										variant="tertiary"
										disabled={index === 0}
										onClick={() => move_field( index, -1 )}
										aria-label={__( 'Move up', 'easy-digital-downloads' )}
									>↑</Button>
									<Button
										size="small"
										variant="tertiary"
										disabled={index === attributes.field_order.length - 1}
										onClick={() => move_field( index, 1 )}
										aria-label={__( 'Move down', 'easy-digital-downloads' )}
									>↓</Button>
								</div>
								<ToggleControl
									label={field.label}
									checked={attributes[ field.attr ]}
									onChange={( checked ) => setAttributes( { [ field.attr ]: checked } )}
								/>
							</div>
						);
					} )}
				</PanelBody>
			</InspectorControls>
			<p className="description">{__( 'This form is a sample view of your profile editor. Only logged-in users will see it.', 'easy-digital-downloads' )}</p>
			<Disabled>
				<form className="edd-blocks-form edd-blocks-form__profile-editor">
					<fieldset>
						<legend>{__( 'Personal Information', 'easy-digital-downloads' )}</legend>
						<div className="edd-blocks-form__group edd-blocks-form__group-first-name">
							<label htmlFor="edd_first_name">{__( 'First Name', 'easy-digital-downloads' )}</label>
							<div className="edd-blocks-form__control">
								<input name="edd_first_name" id="edd_first_name" className="edd-input" type="text" readOnly />
							</div>
						</div>
						<div className="edd-blocks-form__group edd-blocks-form__group-last-name">
							<label htmlFor="edd_last_name">{__( 'Last Name', 'easy-digital-downloads' )}</label>
							<div className="edd-blocks-form__control">
								<input name="edd_last_name" id="edd_last_name" className="edd-input" type="text" readOnly />
							</div>
						</div>
						<div className="edd-blocks-form__group edd-blocks-form__group-display-name">
							<label htmlFor="edd_display_name">{__( 'Display Name', 'easy-digital-downloads' )}</label>
							<div className="edd-blocks-form__control">
								<select name="edd_display_name" id="edd_display_name" className="edd-input" readOnly>
									<option>{__( 'username', 'easy-digital-downloads' )}</option>
								</select>
							</div>
						</div>
						<div className="edd-blocks-form__group edd-blocks-form__group-email">
							<label htmlFor="edd_email">{__( 'Primary Email Address', 'easy-digital-downloads' )}</label>
							<div className="edd-blocks-form__control">
								<input name="edd_email" id="edd_email" className="edd-input required" type="email" readOnly />
							</div>
						</div>
					</fieldset>
					<fieldset>
						<legend>{__( 'Billing Address', 'easy-digital-downloads' )}</legend>
						{attributes.field_order.map( ( key ) => {
							const field = fieldConfig[ key ];
							if ( ! field || ! attributes[ field.attr ] ) {
								return null;
							}
							return render_field_preview( key );
						} )}
					</fieldset>
					<fieldset>
						<legend>{__( 'Password', 'easy-digital-downloads' )}</legend>
						<div className="edd-blocks-form__group edd-blocks-form__group-edd-password user-pass1-wrap">
							<label htmlFor="edd_new_user_pass1">{__( 'New Password', 'easy-digital-downloads' )}</label>
							<div className="edd-blocks-form__control wp-pwd">
								<input name="edd_new_user_pass1" id="edd_new_user_pass1" className="edd-input" type="password" readOnly />
							</div>
						</div>
						<div className="edd-blocks-form__group edd-blocks-form__group-password-confirm user-pass2-wrap">
							<label htmlFor="edd_new_user_pass2">{__( 'Re-enter Password', 'easy-digital-downloads' )}</label>
							<div className="edd-blocks-form__control">
								<input name="edd_new_user_pass2" id="edd_new_user_pass2" className="edd-input" type="password" readOnly />
							</div>
						</div>
					</fieldset>
					<div className="edd-blocks-form__group edd-blocks-form__group-submit">
						<input
							name="submit"
							type="submit"
							className="edd-submit button"
							value={__( 'Save Changes', 'easy-digital-downloads' )}
							readOnly
						/>
					</div>
				</form>
			</Disabled>
		</div>
	);
}
