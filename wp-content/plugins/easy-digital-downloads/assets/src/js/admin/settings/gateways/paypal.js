// PayPal Commerce admin settings: connect/reconnect/disconnect and account status flows.

document.addEventListener( 'DOMContentLoaded', function() {
	// If the main PayPal localized variables aren't defined, we can't do anything more here.
	if ( typeof eddPayPalConnectVars === 'undefined' ) {
		return;
	}

	// Clear errors.
	const errorContainer = document.getElementById( 'edd-paypal-commerce-errors' );
	if ( errorContainer ) {
		while ( errorContainer.firstChild ) {
			errorContainer.removeChild( errorContainer.firstChild );
		}
		errorContainer.classList.remove( 'notice', 'notice-error' );
	}

	if ( ! eddPayPalConnectVars.isConnected ) {
		if ( 'v3' === eddPayPalConnectVars.commerceVersion ) {
			// v3 (3rd party proxy) connect flow.
			eddPayPalV3Connect();
		} else {
			// v2 (1st party) connect flow.
			// If the edd-paypal-commerce-link element is on the page, load the Partner Onboarding script.
			const connectButton = document.getElementById( 'edd-paypal-commerce-link' );
			if ( connectButton ) {

				// Load the Partner Onboarding script.
				const paypalScriptTag = document.createElement( 'script' );
				paypalScriptTag.id  = 'edd-paypal-commerce-onboarding';
				paypalScriptTag.src = 'https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js';
				document.body.appendChild( paypalScriptTag );

				setTimeout(
					() => {
						if ( 'undefined' !== typeof globalThis.PAYPAL?.apps?.Signup ) {
							globalThis.PAYPAL.apps.Signup.render();
						}
					},
					1000
				);
			}

			globalThis.eddPayPalOnboardingCallback = async function eddPayPalOnboardingCallback( authCode, shareId ) {
				const callbackConnectButton = document.getElementById( 'edd-paypal-commerce-link' );
				const callbackErrorContainer = document.getElementById( 'edd-paypal-commerce-errors' );

				const formData = new FormData();
				formData.append( 'action', 'edd_paypal_commerce_get_access_token' );
				formData.append( 'auth_code', authCode );
				formData.append( 'share_id', shareId );
				formData.append( '_ajax_nonce', callbackConnectButton.dataset.nonce );

				try {
					const response = await fetch( globalThis.ajaxurl, {
						method: 'POST',
						body: formData,
					} );

					if ( ! response.ok ) {
						const errorJson = await response.json().catch( () => ( {} ) );
						throw new Error( errorJson.data ?? eddPayPalConnectVars.defaultError );
					}

					callbackConnectButton.classList.add( 'disabled', 'updating-message' );
					callbackConnectButton.disabled = true;
				} catch ( err ) {
					if ( callbackErrorContainer ) {
						callbackErrorContainer.innerHTML = `<p>${ err.message }</p>`;
						callbackErrorContainer.classList.add( 'notice', 'notice-error' );
					}
				}
			};
		}
	} else {
		// If we are connected we can register the rest of the events and functions.
		const reconnectButton = document.getElementById( 'edd-paypal-commerce-reconnect' );
		if ( reconnectButton ) {
			reconnectButton.addEventListener( 'click', async function( e ) {
				e.preventDefault();

				// Clear errors.
				const reconnectErrors = document.getElementById( 'edd-paypal-commerce-errors' );
				if ( reconnectErrors ) {
					reconnectErrors.innerHTML = '';
					reconnectErrors.classList.remove( 'notice', 'notice-error' );
				}

				reconnectButton.classList.add( 'updating-message' );
				reconnectButton.disabled = true;

				const formData = new FormData();
				formData.append( 'action', 'edd_paypal_commerce_reconnect' );
				formData.append( '_ajax_nonce', reconnectButton.dataset.nonce );

				try {
					const response = await fetch( globalThis.ajaxurl, {
						method: 'POST',
						body: formData,
					} );

					if ( ! response.ok ) {
						const errorJson = await response.json().catch( () => ( {} ) );
						throw new Error( errorJson.data ?? eddPayPalConnectVars.defaultError );
					}
				} catch ( err ) {
					console.log( 'Reconnect failure', err.message );
					reconnectButton.classList.remove( 'updating-message' );
					reconnectButton.disabled = false;

					// Set errors.
					if ( reconnectErrors ) {
						reconnectErrors.innerHTML = `<p>${ err.message }</p>`;
						reconnectErrors.classList.add( 'notice', 'notice-error' );
					}
				}
			} );
		}

		/**
		 * Checks the PayPal connection & webhook status.
		 */
		async function eddPayPalGetAccountStatus() {
			const accountInfoEl = document.getElementById( 'edd-paypal-commerce-connect-wrap' );
			if ( ! accountInfoEl ) {
				return;
			}

			const formData = new FormData();
			formData.append( 'action', 'edd_paypal_commerce_get_account_info' );
			formData.append( '_ajax_nonce', accountInfoEl.getAttribute( 'data-nonce' ) );

			try {
				const fetchResponse = await fetch( globalThis.ajaxurl, {
					method: 'POST',
					body: formData,
				} );
				const response = await fetchResponse.json();

				let newHtml = `<p>${ eddPayPalConnectVars.defaultError }</p>`;

				if ( response.success ) {
					newHtml = response.data.account_status;

					if ( response.data.actions && response.data.actions.length ) {
						newHtml += `<p class="edd-paypal-connect-actions">${ response.data.actions.join( ' ' ) }</p>`;
					}

					if ( response.data.disconnect_links && response.data.disconnect_links.length ) {
						const disconnectLinkWrapper = document.getElementById( 'edd-paypal-disconnect' );
						if ( disconnectLinkWrapper ) {
							disconnectLinkWrapper.innerHTML = response.data.disconnect_links.join( ' ' );
						}
					}
				} else if ( response.data && response.data.message ) {
					newHtml = response.data.message;
				}

				accountInfoEl.innerHTML = newHtml;

				// Remove old status messages.
				accountInfoEl.classList.remove( 'notice-success', 'notice-warning', 'notice-error', 'loading' );

				// Add new one.
				const newClass = response.success && response.data.status ? `notice-${ response.data.status }` : 'notice-error';
				accountInfoEl.classList.add( newClass );

				// If we are now connected and verified, we can bind all the action buttons.
				eddPayPalBindActions();
			} catch ( err ) {
				console.log( 'Account status failure', err );
			}
		}
		eddPayPalGetAccountStatus();

		function eddPayPalBindActions() {
			const actionButtons = document.querySelectorAll( '.edd-paypal-connect-action' );
			if ( ! actionButtons.length ) {
				return;
			}

			actionButtons.forEach( function( button ) {
				button.addEventListener( 'click', async function( e ) {
					e.preventDefault();

					const targetButton = e.target;

					// Confirm destructive actions before firing when data-confirm is set.
					if ( targetButton.dataset.confirm && ! globalThis.confirm( targetButton.dataset.confirm ) ) {
						return;
					}

					targetButton.disabled = true;
					targetButton.classList.add( 'updating-message' );

					// Restore the wrap to the same skeleton-loading state we
					// render on initial page load so the user sees the placeholder
					// rows shimmering rather than an empty grey notice box while
					// the proxy refresh is in flight. We can't remove the wrap —
					// eddPayPalGetAccountStatus() targets it by ID and bails
					// silently when it's missing, which was leaving the page
					// stuck in the spinning state after a successful Refresh
					// Merchant Status click.
					const errorWrap = document.getElementById( 'edd-paypal-commerce-connect-wrap' );
					if ( errorWrap ) {
						errorWrap.classList.remove( 'notice-success', 'notice-warning', 'notice-error', 'edd-paypal-actions-error-wrap' );
						errorWrap.classList.add( 'loading' );
						errorWrap.innerHTML = '<ul class="edd-paypal-account-status">'
							+ '<li><span></span></li>'
							+ '<li><span></span></li>'
							+ '<li><span></span></li>'
							+ '<li><span></span></li>'
							+ '</ul>';
					}

					const formData = new FormData();
					formData.append( 'action', targetButton.dataset.action );
					formData.append( '_ajax_nonce', targetButton.dataset.nonce );

					try {
						const response = await fetch( globalThis.ajaxurl, {
							method: 'POST',
							body: formData,
						} );

						if ( ! response.ok ) {
							const errorJson = await response.json().catch( () => ( {} ) );
							throw new Error( errorJson.data ?? eddPayPalConnectVars.defaultError );
						}

						// Refresh account status.
						eddPayPalGetAccountStatus();
					} catch ( err ) {
						console.log( 'Failure', err.message );
						targetButton.disabled = false;
						targetButton.classList.remove( 'updating-message' );

						// Set errors.
						if ( errorWrap ) {
							errorWrap.innerHTML = `<p>${ err.message }</p>`;
							errorWrap.classList.add( 'edd-paypal-actions-error-wrap' );
						}
					}
				} );
			} );
		}
	}
} );

/**
 * Handles the v3 (3rd party proxy) PayPal connect flow.
 *
 * 1. On button click, AJAX to register the store and get a signup link.
 * 2. Load the PayPal Partner Onboarding script and open the minibrowser.
 * 3. On onboarding complete, AJAX to complete onboarding via the proxy.
 */
function eddPayPalV3Connect() {
	const connectButton  = document.getElementById( 'edd-paypal-commerce-v3-connect' );
	const errorContainer = document.getElementById( 'edd-paypal-commerce-errors' );

	if ( ! connectButton ) {
		return;
	}

	// Insert a hidden placeholder onboarding anchor BEFORE preloading partner.js.
	// partner.js auto-initializes on script load by reading `.href` from the
	// first [data-paypal-onboard-button] anchor it finds; without one present,
	// it throws "Cannot read properties of undefined (reading 'href')".
	// The placeholder also lets us preload the script eagerly so its handler is
	// ready by the time the merchant clicks Connect — that's important because
	// the synthetic click on the real onboarding link must fire inside the
	// user-gesture window opened by the Connect click, or the browser will
	// open PayPal in a new tab instead of the minibrowser popup.
	//
	// `href = '#'` is intentional — it forces partner.js to emit
	// `mb_no_partner_found` / `mb_use_old_script` (informational FPTI
	// warnings) and fall through to the "old script" path that re-scans
	// for anchors at click time. Giving the placeholder a real-looking
	// PayPal signup URL makes partner.js bind to the placeholder during
	// pre-init and skip the re-scan, which means it never picks up the
	// real onboarding anchor we create on click — and the synthetic click
	// degrades to native navigation in a new tab instead of opening the
	// minibrowser popup.
	const placeholder = document.createElement( 'a' );
	placeholder.href                          = '#';
	placeholder.style.display                 = 'none';
	placeholder.dataset.paypalOnboardButton   = 'true';
	placeholder.dataset.paypalButton          = 'true';
	placeholder.dataset.paypalOnboardingPlaceholder = 'true';
	document.body.appendChild( placeholder );

	// Preload PayPal's partner.js so its click handler is bound and ready
	// before the synthetic click fires from the connect flow.
	const paypalPartnerScriptReady = loadPayPalPartnerScript();

	connectButton.addEventListener( 'click', async function( e ) {
		e.preventDefault();

		// Clear previous errors.
		if ( errorContainer ) {
			errorContainer.innerHTML = '';
			errorContainer.classList.remove( 'notice', 'notice-error' );
		}

		// Disable the button and let the WordPress core `updating-message` class
		// render the inline spinner inside the button.
		connectButton.disabled = true;
		connectButton.classList.add( 'updating-message' );

		// Step 1: Register store and get signup link from the proxy.
		const formData = new FormData();
		formData.append( 'action', 'edd_paypal_v3_register_store' );
		formData.append( '_ajax_nonce', connectButton.dataset.nonce );

		try {
			const fetchResponse = await fetch( globalThis.ajaxurl, {
				method: 'POST',
				body: formData,
			} );
			const response = await fetchResponse.json();

			if ( ! response.success || ! response.data?.signup_link ) {
				eddPayPalV3ShowError( errorContainer, connectButton, eddPayPalConnectVars.defaultError );
				return;
			}

			const signupLink = `${ response.data.signup_link }&displayMode=minibrowser`;

			// Create the onboarding link element for PayPal's partner.js to bind to.
			const onboardLink = document.createElement( 'a' );
			onboardLink.id                            = 'edd-paypal-commerce-link';
			onboardLink.href                          = signupLink;
			onboardLink.target                        = '_blank';
			onboardLink.dataset.paypalOnboardComplete = 'eddPayPalV3OnboardingCallback';
			onboardLink.dataset.paypalButton          = 'true';
			onboardLink.dataset.paypalOnboardButton   = 'true';
			onboardLink.className                     = 'edd-paypal-connect';
			// Copy innerHTML (not textContent) so the PayPal logo carries over.
			onboardLink.innerHTML                     = connectButton.innerHTML;
			onboardLink.dataset.nonce                 = connectButton.dataset.nonce;

			// Replace the connect button with the onboarding link.
			connectButton.parentNode.insertBefore( onboardLink, connectButton );
			connectButton.style.display = 'none';

			// Wait for partner.js to be ready (it was preloaded eagerly above).
			// The script is usually already loaded by the time this resolves;
			// the await is just a safety net.
			await paypalPartnerScriptReady;

			if ( ! globalThis.PAYPAL?.apps?.Signup ) {
				// partner.js failed to initialize — fall back to native click so
				// the merchant can still complete onboarding in a new tab.
				onboardLink.click();
				return;
			}

			globalThis.PAYPAL.apps.Signup.render();
			onboardLink.click();
		} catch ( err ) {
			let message = eddPayPalConnectVars.defaultError;
			if ( err?.responseJSON?.data ) {
				message = err.responseJSON.data;
			} else if ( err?.message ) {
				message = err.message;
			}
			eddPayPalV3ShowError( errorContainer, connectButton, message );
		}
	} );

	// Step 3: Callback after merchant completes PayPal onboarding.
	// The REST endpoint handles complete_onboarding() and redirects the browser
	// back to the settings page. This callback is a fallback in case partner.js
	// fires it — just reload to pick up the connected state.
	globalThis.eddPayPalV3OnboardingCallback = function( authCode, shareId ) {
		const onboardLink = document.getElementById( 'edd-paypal-commerce-link' );

		if ( onboardLink ) {
			onboardLink.classList.add( 'disabled', 'updating-message' );
			onboardLink.style.pointerEvents = 'none';
		}

		setTimeout( function() {
			globalThis.location.reload();
		}, 1500 );
	};
}

/**
 * Loads PayPal's partner onboarding script once and resolves when it is ready.
 *
 * Reuses the existing <script> tag when called multiple times so we never
 * download partner.js more than once per page. Resolves whether the script
 * loads successfully or errors — callers should check
 * `globalThis.PAYPAL?.apps?.Signup` before relying on the SDK.
 *
 * @return {Promise<void>}
 */
function loadPayPalPartnerScript() {
	const SCRIPT_ID  = 'edd-paypal-commerce-onboarding';
	const SCRIPT_SRC = 'https://www.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js';

	const existing = document.getElementById( SCRIPT_ID );
	if ( existing ) {
		if ( globalThis.PAYPAL?.apps?.Signup ) {
			return Promise.resolve();
		}
		return new Promise( ( resolve ) => {
			existing.addEventListener( 'load', () => resolve(), { once: true } );
			existing.addEventListener( 'error', () => resolve(), { once: true } );
		} );
	}

	return new Promise( ( resolve ) => {
		const script = document.createElement( 'script' );
		script.id  = SCRIPT_ID;
		script.src = SCRIPT_SRC;
		script.addEventListener( 'load', () => resolve(), { once: true } );
		script.addEventListener( 'error', () => resolve(), { once: true } );
		document.body.appendChild( script );
	} );
}

/**
 * Shows an error message for the v3 connect flow.
 *
 * @param {HTMLElement|null} errorContainer The error container element.
 * @param {HTMLElement|null} button         The button to re-enable.
 * @param {string}           message        The error message.
 */
function eddPayPalV3ShowError( errorContainer, button, message ) {
	if ( errorContainer ) {
		errorContainer.innerHTML = `<p>${ message }</p>`;
		errorContainer.classList.add( 'notice', 'notice-error' );
	}

	if ( button ) {
		button.disabled = false;
		button.classList.remove( 'disabled', 'updating-message' );
		button.style.display       = '';
		button.style.pointerEvents = '';
	}
}
