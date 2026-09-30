/* global EDDAdminEmails */

import { postAdminAjax } from 'utilities/post-admin-ajax';

jQuery( document ).ready( function ( $ ) {
	'use strict';

	$( '.edd-email-manager__action' ).on( 'click', function ( e ) {
		e.preventDefault();

		const $btn = $( this ),
			action = $btn.attr( 'data-action' );

		let removeClass = '',
			addClass = '',
			replaceAction = '',
			replaceStatus = '';

		if ( $btn.attr( 'disabled' ) ) {
			return;
		}

		switch ( action ) {
			case 'enable':
				addClass = 'edd-button-toggle--active';
				replaceAction = 'disable';
				replaceStatus = 'inactive';
				break;

			case 'disable':
				removeClass = 'edd-button-toggle--active';
				replaceAction = 'enable';
				replaceStatus = 'active';
				break;

			default:
				return;
		}

		const data = {
			action: 'edd_update_email_status',
			nonce: EDDAdminEmails.nonce,
			email_id: $btn.attr( 'data-id' ),
			status: $btn.attr( 'data-status' ),
			button: action,
		};

		const previous = {
			hadActiveClass: $btn.hasClass( 'edd-button-toggle--active' ),
			action: $btn.attr( 'data-action' ),
			status: $btn.attr( 'data-status' ),
		};

		function revertEmailToggle() {
			$btn.toggleClass( 'edd-button-toggle--active', previous.hadActiveClass );
			$btn.attr( 'data-action', previous.action );
			$btn.attr( 'data-status', previous.status );
		}

		postAdminAjax( {
			url: EDDAdminEmails.ajaxurl,
			data,
			$busy: $btn,
			applyOptimistic() {
				$btn.removeClass( removeClass ).addClass( addClass );
				$btn.attr( 'data-action', replaceAction );
				$btn.attr( 'data-status', replaceStatus );
			},
			revert: revertEmailToggle,
			debug: EDDAdminEmails.debug,
		} );
	} );
} );
