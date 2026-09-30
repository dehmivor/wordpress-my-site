/* global EDDExtensionManager, ajaxurl */

import { postAdminAjax } from 'utilities/post-admin-ajax';

; ( function ( document, $ ) {
	'use strict';

	$( 'body' ).on( 'click', '.edd-extension-manager__actions .edd-extension-manager__action', function ( e ) {
		e.preventDefault();

		let $btn = $( this ),
			action = $btn.attr( 'data-action' ),
			plugin = $btn.attr( 'data-plugin' ),
			type = $btn.attr( 'data-type' ),
			ajaxAction = '',
			removeClass = '',
			addClass = '',
			isInstaller = $btn.hasClass( 'edd-button__toggle' ),
			product = $btn.attr( 'data-product' );

		if ( $btn.attr( 'disabled' ) ) {
			return;
		}

		switch ( action ) {
			case 'activate':
				ajaxAction = 'edd_activate_extension';
				removeClass = 'edd-plugin__inactive';
				addClass = 'edd-plugin__active';
				if ( !isInstaller ) {
					$btn.text( EDDExtensionManager.activating );
				}
				break;

			case 'install':
				ajaxAction = 'edd_install_extension';
				addClass = 'edd-plugin__active';
				$btn.text( EDDExtensionManager.installing );
				break;

			case 'deactivate':
				ajaxAction = 'edd_deactivate_extension';
				removeClass = 'edd-plugin__active';
				addClass = 'edd-plugin__inactive';
				if ( !isInstaller ) {
					$btn.text( EDDExtensionManager.deactivating );
				}
				break;

			default:
				return;
		}

		if ( $btn.hasClass( 'button-primary' ) ) {
			$btn.removeClass( 'button-primary' ).addClass( 'updating-message' );
		}

		const data = {
			action: ajaxAction,
			nonce: EDDExtensionManager.extension_manager_nonce,
			plugin: plugin,
			type: type,
			pass: $btn.attr( 'data-pass' ),
			id: $btn.attr( 'data-id' ),
			product: product,
		};

		const thisStepBefore = $btn.closest( '.edd-extension-manager__step' );
		const $card = $btn.closest( '.edd-extension-manager__card' );
		const optimisticCard =
			! $( '#edd-admin-about' ).length &&
			! thisStepBefore.length &&
			( action === 'activate' || action === 'deactivate' );

		function revertExtensionCard() {
			if ( optimisticCard ) {
				$card.removeClass( addClass ).addClass( removeClass );
			}
		}

		postAdminAjax( {
			url: ajaxurl,
			data,
			$busy: $btn,
			applyOptimistic() {
				if ( optimisticCard ) {
					$card.removeClass( removeClass ).addClass( addClass );
				}
			},
			revert: revertExtensionCard,
			debug: EDDExtensionManager.debug,
			onResponse( res ) {
				// We handle the 'about page' installer a little differently due to the conditions in at hand, so we'll adjust how we handle the response based on the context.
				if ( $( '#edd-admin-about' ).length ) {
					const actions_container = $btn.closest( '.actions' );
					if ( res.success ) {
						actions_container.addClass('has-response').empty().append( '<div class="status"><span class="status-label active">' + res.data.message + '</span></div>' );
					} else {
						/**
						 * The install class returns an array of error messages, and res.data.message will be undefined.
						 * In that case, we'll use the standard failure messages.
						 */
						let message = res.data.message;
						if ( !message ) {
							message = EDDExtensionManager.plugin_install_failed;
						}
						actions_container.addClass('has-response').empty().append( '<div class="status"><span class="status-label">' + message + '</span></div>' );
					}
				} else {
					const thisStep = $btn.closest( '.edd-extension-manager__step' );
					if ( res.success ) {
						if ( thisStep.length ) {
							const nextStep = thisStep.next();
							if ( nextStep.length ) {
								thisStep.fadeOut();
								nextStep.prepend( '<div class="notice inline-notice notice-success"><p>' + res.data.message + '</p></div>' );
								nextStep.fadeIn();
							}
						} else {
							$btn.closest( '.edd-extension-manager__card' ).removeClass( removeClass ).addClass( addClass );
							if ( res.data.button.length ) {
								$btn.closest( '.edd-extension-manager__control' ).empty().append( res.data.button );
							}
						}
					} else {
						let message = res.data.message;
						/**
						 * The install class returns an array of error messages, and res.data.message will be undefined.
						 * In that case, we'll use the standard failure messages.
						 */
						if ( !message ) {
							if ( 'plugin' !== type ) {
								message = EDDExtensionManager.extension_install_failed;
							} else {
								message = EDDExtensionManager.plugin_install_failed;
							}
						}
						if ( thisStep.length ) {
							thisStep.fadeOut();
							thisStep.after( '<div class="notice inline-notice notice-warning"><p>' + message + '</p></div>' );
						} else {
							const $actions = $btn.closest( '.edd-extension-manager__actions' );
							$actions.find( '.notice' ).remove();
							$actions.append( '<div class="notice inline-notice notice-warning"><p>' + message + '</p></div>' );
						}
					}
				}
			},
		} );
	} );

	let typingTimer,
		searchInput = $( '#edd-extension-manager__bar-search' );

	if ( searchInput ) {
		if ( EDDExtensionManager.filter ) {
			searchInput.val( EDDExtensionManager.filter );
			startSearch();
		}
		searchInput.on( 'keyup', function ( event ) {
			startSearch();
		} );

		searchInput.on( 'search', function ( event ) {
			startSearch();
		} );
	}

	function startSearch () {
		clearTimeout( typingTimer );
		typingTimer = setTimeout( extensionSearch, 342 );
	}

	function extensionSearch () {

		// Locate the search input
		let search_query = searchInput.val().toLowerCase();

		let count = 0;
		if ( ! search_query.length ) {
			let cards = $( '.edd-extension-manager__card' );
			cards.removeClass( 'edd-hidden' );
			count = cards.length;
		} else {
			// Loop through the cards
			$( '.edd-extension-manager__card' ).each( function() {

				// card text
				let innertext = $(this).text().toLowerCase().includes( search_query );
				// card data-filter
				let filter = $(this).data( 'filter' ) && $(this).data( 'filter' ).toLowerCase().includes( search_query );

				if ( ( innertext || filter ) ) {
					count++;
					$(this).removeClass( 'edd-hidden' );
				} else {
					$(this).addClass( 'edd-hidden' );
				}
			});
		}

		$( '.edd-extension-manager__card:not(.edd-hidden)' ).hide().show(0);
		$( '.edd-extension-manager__results' ).remove();
		$( '#edd-extension-manager__bar-search' ).after( '<div role="status" class="edd-extension-manager__results screen-reader-text">' + count + ' ' + EDDExtensionManager.results + '</div>' );
	}
} )( document, jQuery );
