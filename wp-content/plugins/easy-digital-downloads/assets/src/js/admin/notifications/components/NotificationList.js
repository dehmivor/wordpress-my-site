import NotificationCard from './NotificationCard';

/**
 * NotificationList component.
 *
 * Renders a list of notifications or an appropriate empty/loading state.
 *
 * @param {Object}   props
 * @param {Array}    props.notifications The array of notification objects.
 * @param {boolean}  props.isLoaded      Whether notifications have finished loading.
 * @param {boolean}  props.isDismissed   Whether we are viewing dismissed notifications.
 * @param {Function} props.onDismiss     Callback when a notification is dismissed.
 * @return {Element} The NotificationList component.
 */
const NotificationList = ( { notifications, isLoaded, isDismissed, onDismiss } ) => {
	const strings = globalThis.eddNotificationStrings || {};

	if ( ! isLoaded ) {
		return (
			<div aria-live="polite">
				{ strings.loading || 'Loading notifications...' }
			</div>
		);
	}

	if ( ! notifications.length ) {
		return (
			<div id="edd-notifications-none" aria-live="polite">
				{ isDismissed
					? ( strings.noDismissed || 'You have no dismissed notifications.' )
					: ( strings.noNotifications || 'You have no new notifications.' )
				}
			</div>
		);
	}

	return (
		<div>
			{ notifications.map( ( notification ) => (
				<NotificationCard
					key={ notification.id }
					notification={ notification }
					isDismissed={ isDismissed }
					onDismiss={ onDismiss }
				/>
			) ) }
		</div>
	);
};

export default NotificationList;
