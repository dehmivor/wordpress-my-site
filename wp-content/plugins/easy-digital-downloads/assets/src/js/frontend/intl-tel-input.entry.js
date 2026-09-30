; ( function ( document, $ ) {
	'use strict';
	// Initialize phone input when page loads.
	const input = document.querySelector( '.edd-input__phone' );
	if ( input ) {
		initIntlTelInput( input );
	}

	// The gateway reload replaces #edd_purchase_form_wrap, so a phone field inside it is a fresh
	// element and must re-init; skip one that survived the swap, since re-wrapping nests a second .iti.
	$( document.body ).on( 'edd_gateway_loaded', function () {
		const input = document.querySelector( '.edd-input__phone' );
		if ( input && ! isInitialized( input ) ) {
			initIntlTelInput( input );
		}
	} );

	/**
	 * Whether intl-tel-input has already wrapped this field.
	 *
	 * @param {HTMLElement} input The phone input.
	 * @return {boolean} True when the field is already initialized.
	 */
	function isInitialized ( input ) {
		return input.classList.contains( 'iti__tel-input' ) || null !== input.closest( '.iti' );
	}

	function initIntlTelInput ( input ) {
		var data = {
			formatOnDisplay: true,
			utilsScript: EDDIntlTelInput.utils,
			nationalMode: true,
		}
		if ( input.dataset.country ) {
			data.initialCountry = input.dataset.country;
		}

		// Initialize the phone input.
		var iti = window.intlTelInput( input, data );

		// If there's an existing value, force format it after utils are loaded
		if ( input.value ) {
			// Check if utils script is loaded and format the number
			var handleUtilsLoaded = function () {
				if ( window.intlTelInputUtils ) {
					// Get the formatted value and set it
					var currentNumber = iti.getNumber();
					if ( currentNumber ) {
						iti.setNumber( currentNumber );
					}
				} else {
					// If utils not loaded yet, wait a bit and try again
					setTimeout( handleUtilsLoaded, 100 );
				}
			};

			// Start checking for utils loaded
			handleUtilsLoaded();
		}

		// Set up country change listener after initialization
		const countrySelect = document.querySelector( 'select.edd_countries_filter' );
		if ( countrySelect ) {
			countrySelect.addEventListener( 'change', function () {
				if ( iti ) {
					iti.setCountry( this.value );
				}
			} );
		}

		return iti;
	}
} )( document, jQuery );

