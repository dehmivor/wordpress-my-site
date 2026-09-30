import { initChosen } from 'utils/chosen.js';

jQuery( document ).ready( function ( $ ) {
	$( 'select.edd_countries_filter' ).on( 'change', function () {

		if ( ! $( this ).val() ) {
			return;
		}

		const select = $( this ),
			state_field = $( 'select.edd_regions_filter, input.edd_regions_filter' ),
			data = {
				action: 'edd_get_shop_states',
				country: select.val(),
				nonce: select.data( 'nonce' ),
				field_name: state_field.attr( 'name' ),
				field_id: state_field.attr( 'id' ),
				field_classes: 'edd_regions_filter',
			};

		$.post( ajaxurl, data, function ( response ) {
			// Country selects enhanced with Tom Select include .edd-select-chosen (see EDD\HTML\Select).
			// Replace the whole region field so we can swap between <select> and text input; legacy
			// non-enhanced country dropdowns only update options on an existing region <select>.
			const replaceStateField = select.hasClass( 'edd-select-chosen' );

			if ( replaceStateField ) {
				// Destroy Tom Select on the current field if it is a select.
				if ( state_field.is( 'select' ) ) {
					state_field[0]?.tomselect?.destroy();
				}

				// Re-query after destroy so we have a live reference.
				const current_field = $( 'select.edd_regions_filter, input.edd_regions_filter' );

				if ( ! current_field.length ) {
					return;
				}

				if ( 'nostates' === response ) {
					current_field.replaceWith(
						$( '<input type="text" />' ).attr( {
							name: data.field_name,
							id: data.field_id,
							class: 'edd_regions_filter regular-text',
							placeholder: edd_vars.enter_region,
						} )
					);
				} else {
					current_field.replaceWith( response );
					initChosen( document.querySelector( 'select.edd_regions_filter' ) );
				}

				return;
			}

			$( 'select.edd_regions_filter' ).find( 'option:gt(0)' ).remove();

			if ( 'nostates' !== response ) {
				$( response ).find( 'option:gt(0)' ).appendTo( 'select.edd_regions_filter' );
			}

			const instance = document.querySelector( 'select.edd_regions_filter' )?.tomselect;
			if ( instance ) {
				instance.sync();
				instance.refreshOptions( false );
			}
		} );

		return false;
	} );
} );
