/**
 * PanelHeader component.
 *
 * Renders the notification panel header with count, close button, and view toggle.
 *
 * @param {Object}   props
 * @param {number}   props.count          The number of active notifications.
 * @param {boolean}  props.viewDismissed  Whether we are viewing dismissed notifications.
 * @param {Function} props.onClose        Callback when the close button is clicked.
 * @param {Function} props.onToggleView   Callback to toggle between active and dismissed views.
 * @param {Object}   props.headerRef      React ref for the header element.
 * @return {Element} The PanelHeader component.
 */
const PanelHeader = ( { count, viewDismissed, onClose, onToggleView, headerRef } ) => {
	const strings = globalThis.eddNotificationStrings || {};

	const activeTitle = 1 === count
		? ( strings.singleNotification || '(%s) New Notification' ).replace( '%s', count )
		: ( strings.newNotifications || '(%s) New Notifications' ).replace( '%s', count );

	return (
		<div id="edd-notifications-header" tabIndex="-1" ref={ headerRef }>
			<h3 id="edd-notifications-title">
				{ viewDismissed
					? ( strings.dismissedTitle || 'Dismissed Notifications' )
					: activeTitle
				}
			</h3>

			<button
				type="button"
				className="edd-notifications-view-toggle"
				onClick={ onToggleView }
			>
				{ viewDismissed
					? ( strings.viewActive || 'View Active' )
					: ( strings.viewDismissed || 'View Dismissed' )
				}
			</button>

			<button
				type="button"
				className="edd-close"
				onClick={ onClose }
			>
				<span className="dashicons dashicons-no-alt"></span>
				<span className="screen-reader-text">
					{ strings.closePanel || 'Close panel' }
				</span>
			</button>
		</div>
	);
};

export default PanelHeader;
