/* global wsrw_admin_notices */
const WSRWAdminNotices = window.WSRWAdminNotices ||
	( function ( document, window, $ ) {
		const app = {

			init: function () {
				window.WSRWAdminNotices = app;
				app.notice_holder = $( document.getElementById( 'wsrw-notice-area' ) );
				app.document = $( document );
			},

			add_notice: function ( text, type = 'updated' ) {
				const notice = app.get_notice( text, type );
				if ( app.notice_holder.length ) {
					app.notice_holder.append( notice );
				} else {
					// Fallback: append to body if notice holder doesn't exist
					$( 'body' ).prepend( notice );
				}
				app.document.trigger( 'wp-updates-notice-added' );
				notice.find( 'button' ).focus();
			},

			get_notice: function ( text, type ) {
				const notice = $( '<div />' );
				const textel = $( '<p />' );
				textel.html( text );
				notice.addClass( 'fade notice is-dismissible' );
				notice.addClass( type );
				notice.append( textel );

				return notice;
			},

		};

		return app;

	}( document, window, jQuery ) );

WSRWAdminNotices.init();
