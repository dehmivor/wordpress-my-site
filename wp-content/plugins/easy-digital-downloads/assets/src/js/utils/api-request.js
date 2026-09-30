/* global $, edd_scripts, ajaxurl */

/**
 * Appends a value to a FormData object, recursively flattening objects.
 *
 * @param {FormData} formData The FormData instance to append to.
 * @param {string}   key      The key name.
 * @param {*}        value    The value to append.
 */
function appendToFormData( formData, key, value ) {
	if ( value !== null && typeof value === 'object' && ! ( value instanceof File ) && ! ( value instanceof Blob ) ) {
		Object.keys( value ).forEach( function( subKey ) {
			appendToFormData( formData, key + '[' + subKey + ']', value[ subKey ] );
		} );
	} else {
		formData.append( key, value == null ? '' : value );
	}
}

/**
 * Sends an API request to admin-ajax.php
 *
 * @link https://github.com/WordPress/WordPress/blob/master/wp-includes/js/wp-util.js#L49
 *
 * @param {string} action AJAX action to send to admin-ajax.php
 * @param {Object} data Additional data to send to the action.
 * @return {Promise} jQuery Promise.
 */
export function apiRequest( action, data ) {
	const options = {
		type: 'POST',
		dataType: 'json',
		xhrFields: {
			withCredentials: true,
		},
		url: ( window.edd_scripts && window.edd_scripts.ajaxurl ) || window.ajaxurl,
	};

	if ( data && data.form_data instanceof FormData ) {
		const fd = data.form_data;

		// Remove edd_action to prevent edd_post_actions() from firing on the
		// WordPress `init` hook. With native FormData all form fields become
		// top-level POST parameters; edd_action=purchase would trigger the
		// full core checkout path before admin-ajax.php can route the request.
		fd.delete( 'edd_action' );

		fd.append( 'action', action );

		Object.keys( data ).forEach( function( key ) {
			if ( 'form_data' === key ) {
				return;
			}
			appendToFormData( fd, key, data[ key ] );
		} );

		options.data = fd;
		options.processData = false;
		options.contentType = false;
	} else {
		options.data = {
			action,
			...data,
		};
	}

	const deferred = $.Deferred( function( deferred ) {
		// Use with PHP's wp_send_json_success() and wp_send_json_error()
		deferred.jqXHR = $.ajax( options ).done( function( response ) {
			// Treat a response of 1 or 'success' as successful for backward compatibility with existing handlers.
			if ( response === '1' || response === 1 ) {
				response = { success: true };
			}

			if ( typeof response === 'object' && typeof response.success !== undefined ) {
				deferred[ response.success ? 'resolveWith' : 'rejectWith' ]( this, [ response.data ] );
			} else {
				deferred.rejectWith( this, [ response ] );
			}
		} ).fail( function() {
			deferred.rejectWith( this, arguments );
		} );
	} );

	const promise = deferred.promise();
	promise.abort = function() {
		deferred.jqXHR.abort();
		return this;
	};

	return promise;
}
