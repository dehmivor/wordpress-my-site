import { useState } from '@wordpress/element';

/**
 * NotificationCard component.
 *
 * Renders a single notification with icon, title, content, buttons, and dismiss.
 *
 * @param {Object}   props
 * @param {Object}   props.notification The notification object.
 * @param {boolean}  props.isDismissed  Whether this notification is in the dismissed view.
 * @param {Function} props.onDismiss    Callback when the dismiss button is clicked.
 * @return {Element} The NotificationCard component.
 */
const NotificationCard = ( { notification, isDismissed, onDismiss } ) => {
	const [ dismissing, setDismissing ] = useState( false );
	const strings = globalThis.eddNotificationStrings || {};

	const handleDismiss = () => {
		setDismissing( true );
		onDismiss( notification.id ).catch( () => {
			setDismissing( false );
		} );
	};

	const className = 'edd-notification' + ( isDismissed ? ' edd-notification--dismissed' : '' );

	return (
		<div className={ className }>
			<div className={ `edd-notification--icon edd-notification--icon-${ notification.type }` }>
				<span className={ `dashicons dashicons-${ notification.icon_name }` }></span>
			</div>

			<div className="edd-notification--body">
				<div className="edd-notification--header">
					<h4 className="edd-notification--title">
						{ notification.title }
					</h4>

					<div className="edd-notification--date">
						{ notification.relative_date }
					</div>
				</div>

				<div
					className="edd-notification--content"
					dangerouslySetInnerHTML={ { __html: notification.content } }
				/>

				<div className="edd-notification--actions">
					{ notification.buttons?.map( ( button ) => (
						<a
							key={ `${ button.type }-${ button.url }` }
							href={ button.url }
							className={ 'primary' === button.type ? 'button button-primary' : 'button button-secondary' }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ button.text }
						</a>
					) ) }

					{ ! isDismissed && (
						<button
							type="button"
							className="edd-notification--dismiss"
							disabled={ dismissing }
							onClick={ handleDismiss }
						>
							{ strings.dismiss || 'Dismiss' }
						</button>
					) }
				</div>
			</div>
		</div>
	);
};

export default NotificationCard;
