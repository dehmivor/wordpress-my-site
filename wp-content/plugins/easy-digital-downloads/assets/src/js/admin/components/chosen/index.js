/**
 * Internal dependencies.
 */
import { initChosen } from 'utils/chosen.js';

document.addEventListener( 'DOMContentLoaded', () => {
	for ( const el of document.querySelectorAll( '.edd-select-chosen' ) ) {
		initChosen( el );
	}
} );

jQuery( document ).ready( function( $ ) {
	// This fixes the Chosen box being 0px wide when the thickbox is opened.
	$( '#post' ).on( 'click', '.edd-thickbox', function() {
		$( '.edd-select-chosen', '#choose-download' ).css( 'width', '100%' );
	} );
} );

