/* global edd_login_link_vars */

if ( ! globalThis.window?.eddLoginLinkInitialized ) {
	if ( 'undefined' !== typeof globalThis.window ) {
		globalThis.window.eddLoginLinkInitialized = true;
	}

	const vars = ( typeof edd_login_link_vars === 'undefined' ) ? {} : edd_login_link_vars;
	const loginLinkEnabled = !! ( vars.enabled && '1' === vars.enabled );
	const loginLinkStrings = vars.strings || {};
	const loginLinkPolicy = vars.policy || {};

	if ( loginLinkEnabled ) {
		applyLoginLinkContextPolicy();

		document.addEventListener( 'edd:checkout-email-check-complete', function ( event ) {
			const emailField = event?.detail?.emailField ?? document.querySelector( '[name="edd_email"]' );
			const code = event?.detail?.code ?? '';

			if ( !emailField?.value?.trim() ) {
				setLoginLinkVisibility( 'checkout-email', false );
				return;
			}

			const shouldShow = shouldShowCheckoutEmailLoginLink( emailField, code );
			if ( shouldShow && emailField ) {
				emailField.setCustomValidity( '' );
			}
			setLoginLinkVisibility( 'checkout-email', shouldShow );
		} );

		document.addEventListener( 'input', function ( event ) {
			if ( event.target?.name !== 'edd_email' ) {
				return;
			}

			if ( !event.target.value?.trim() ) {
				setLoginLinkVisibility( 'checkout-email', false );
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			const button = event.target.closest( '[data-edd-login-link-send]' );
			if ( !button ) {
				return;
			}

			const wrapper = button.closest( '[data-edd-login-link]' );
			if ( !wrapper ) {
				return;
			}

			const context = wrapper.dataset.eddLoginLink;
			const loginField = getLoginLinkField( context );
			if ( !validateLoginLinkField( loginField, context ) ) {
				if ( loginField ) {
					loginField.setCustomValidity( loginLinkStrings.field_required || '' );
					loginField.reportValidity();
					loginField.addEventListener( 'input', function () {
						loginField.setCustomValidity( '' );
					}, { once: true } );
				}
				return;
			}

			if ( loginField ) {
				loginField.setCustomValidity( '' );
			}

			const login = loginField ? loginField.value.trim() : '';

			if ( !login ) {
				return;
			}

			sendLoginLink( login, wrapper, button );
		} );
	}

	/**
	 * Send the one-time login link email.
	 *
	 * @param {string} login
	 * @param {HTMLElement} wrapper
	 * @param {HTMLButtonElement} button
	 */
	async function sendLoginLink ( login, wrapper, button ) {
		button.disabled = true;

		const spinner = document.createElement( 'span' );
		spinner.className = 'edd-loading-ajax edd-loading';
		button.after( spinner );

		const controller = new AbortController();
		const timeoutId  = setTimeout( function () { controller.abort(); }, 15000 );

		const data = new FormData();
		data.append( 'action', 'edd_send_login_link' );
		data.append( 'email', login );
		if ( vars.nonce ) {
			data.append( 'nonce', vars.nonce );
		}

		try {
			const response = await fetch( vars.ajaxurl, {
				method: 'POST',
				body: data,
				signal: controller.signal,
			} );
			const json = await response.json();

			let message = '';
			const success = !! json?.success;
			if ( json?.data?.message ) {
				message = json.data.message;
			} else if ( success ) {
				message = loginLinkStrings.sent || '';
			} else {
				message = loginLinkStrings.error || '';
			}

			updateLoginLinkPrompt( wrapper, message, success );
		} catch {
			updateLoginLinkPrompt( wrapper, loginLinkStrings.error || '', false );
		} finally {
			clearTimeout( timeoutId );
			spinner.remove();
			button.disabled = false;
		}
	}

	/**
	 * Updates the prompt to show only the response message.
	 *
	 * @param {HTMLElement} wrapper
	 * @param {string} message
	 * @param {boolean} success
	 */
	function updateLoginLinkPrompt ( wrapper, message, success ) {
		if ( !wrapper ) {
			return;
		}

		const messageEl = wrapper.querySelector( '.edd-login-link__message' );
		const button = wrapper.querySelector( '[data-edd-login-link-send]' );

		if ( messageEl ) {
			messageEl.textContent = message;
		}

		if ( success && button ) {
			button.remove();
		}
		wrapper.classList.remove( 'edd-alert', 'edd-alert-success', 'edd-alert-error' );
		if ( success ) {
			wrapper.classList.add( 'edd-alert', 'edd-alert-success' );
		} else {
			wrapper.classList.add( 'edd-alert', 'edd-alert-error' );
		}
	}

	/**
	 * Get the login input field for the login link context.
	 *
	 * @param {string} context
	 * @returns {HTMLInputElement|null}
	 */
	function getLoginLinkField ( context ) {
		if ( 'checkout-login' === context ) {
			return document.querySelector( '[name="edd_user_login"]' ) || document.getElementById( 'edd_user_login' );
		}

		return document.querySelector( '[name="edd_email"]' );
	}

	/**
	 * Validate that the login field has a value before sending.
	 *
	 * Does not mutate field.required so the checkout form's own
	 * validation state is left intact.
	 *
	 * @param {HTMLInputElement|null} field
	 * @param {string} context
	 * @returns {boolean}
	 */
	function validateLoginLinkField ( field, context ) { // eslint-disable-line no-unused-vars -- context reserved for future use
		if ( !field ) {
			return false;
		}

		return !! field.value?.trim();
	}

	/**
	 * Show or hide the login link prompt for a given context.
	 *
	 * @param {string} context
	 * @param {boolean} visible
	 */
	function setLoginLinkVisibility ( context, visible ) {
		const wrapper = document.querySelector( '[data-edd-login-link="' + context + '"]' );
		if ( !wrapper ) {
			return;
		}

		wrapper.hidden = !visible;
	}

	/**
	 * Apply centralized visibility policy for each login link context.
	 */
	function applyLoginLinkContextPolicy () {
		document.querySelectorAll( '[data-edd-login-link]' ).forEach( function ( wrapper ) {
			const context = wrapper.dataset.eddLoginLink;
			const contextPolicy = getLoginLinkContextPolicy( context );

			if ( !contextPolicy.enabled ) {
				wrapper.hidden = true;
				return;
			}

			wrapper.hidden = !! contextPolicy.default_hidden;
		} );
	}

	/**
	 * Gets policy information for a login link context.
	 *
	 * @param {string} context
	 * @returns {{enabled: boolean, visibility: string, default_hidden: boolean}}
	 */
	function getLoginLinkContextPolicy ( context ) {
		const defaults = {
			enabled: true,
			visibility: 'existing_account',
			default_hidden: false
		};

		if ( !loginLinkPolicy.contexts?.[ context ] ) {
			return defaults;
		}

		return { ...defaults, ...loginLinkPolicy.contexts[ context ] };
	}

	/**
	 * Determines if checkout-email login link prompt should be visible.
	 *
	 * @param {HTMLInputElement} emailField
	 * @param {string} code
	 * @returns {boolean}
	 */
	function shouldShowCheckoutEmailLoginLink ( emailField, code ) {
		const contextPolicy = getLoginLinkContextPolicy( 'checkout-email' );
		if ( !contextPolicy.enabled ) {
			return false;
		}

		if ( 'entered_email' === contextPolicy.visibility ) {
			return !! emailField?.value?.trim();
		}

		return code === 'email_used' || code === 'edd-customer-email-exists';
	}
}
