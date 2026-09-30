import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { apiRequest } from './api';
import PanelHeader from './components/PanelHeader';
import NotificationList from './components/NotificationList';

/**
 * App component.
 *
 * Main notification panel that manages state, fetching, and user interactions.
 *
 * @param {Object} props
 * @param {number} props.initialCount The initial active notification count from the server.
 * @return {Element} The App component.
 */
const App = ( { initialCount } ) => {
	const [ isPanelOpen, setIsPanelOpen ] = useState( false );
	const [ notifications, setNotifications ] = useState( [] );
	const [ isLoaded, setIsLoaded ] = useState( false );
	const [ activeCount, setActiveCount ] = useState( initialCount );

	// Dismissed view state.
	const [ viewDismissed, setViewDismissed ] = useState( false );
	const [ dismissedNotifications, setDismissedNotifications ] = useState( [] );
	const [ dismissedLoaded, setDismissedLoaded ] = useState( false );

	const headerRef = useRef( null );
	const panelRef = useRef( null );

	/**
	 * Fetch active notifications from the REST API.
	 */
	const fetchNotifications = useCallback( () => {
		apiRequest( '/notifications', 'GET', { dismissed: 0 } )
			.then( ( data ) => {
				setNotifications( data?.notifications || [] );
				setActiveCount( data?.total ?? 0 );
				setIsLoaded( true );

				if ( headerRef.current ) {
					headerRef.current.focus();
				}
			} )
			.catch( ( error ) => {
				// eslint-disable-next-line no-console
				console.error( 'Notification error', error );
				setIsLoaded( true );
			} );
	}, [] );

	/**
	 * Fetch dismissed notifications from the REST API.
	 */
	const fetchDismissedNotifications = useCallback( () => {
		if ( dismissedLoaded ) {
			return;
		}
		apiRequest( '/notifications', 'GET', { dismissed: 1 } )
			.then( ( data ) => {
				setDismissedNotifications( data?.notifications || [] );
				setDismissedLoaded( true );
			} )
			.catch( ( error ) => {
				// eslint-disable-next-line no-console
				console.error( 'Dismissed notification error', error );
				setDismissedLoaded( true );
			} );
	}, [ dismissedLoaded ] );

	/**
	 * Open the notification panel.
	 */
	const openPanel = useCallback( () => {
		setIsPanelOpen( true );

		if ( ! isLoaded ) {
			fetchNotifications();
		} else if ( headerRef.current ) {
			setTimeout( () => {
				headerRef.current.focus();
			} );
		}
	}, [ isLoaded, fetchNotifications ] );

	/**
	 * Close the notification panel.
	 */
	const closePanel = useCallback( () => {
		if ( ! isPanelOpen ) {
			return;
		}

		setIsPanelOpen( false );
		setViewDismissed( false );

		// Native <dialog> restores focus to the element that was focused before showModal().
	}, [ isPanelOpen ] );

	/**
	 * Dismiss a notification.
	 *
	 * @param {number} notificationId The notification ID to dismiss.
	 */
	const dismissNotification = useCallback( ( notificationId ) => {
		return apiRequest( '/notifications/' + notificationId, 'DELETE' )
			.then( () => {
				const withoutDismissed = ( n ) => n.id !== notificationId;
				setNotifications( ( prev ) => prev.filter( withoutDismissed ) );
				setActiveCount( ( prev ) => Math.max( 0, prev - 1 ) );

				// Reset dismissed cache so it refetches when toggled.
				setDismissedLoaded( false );
			} )
			.catch( ( error ) => {
				// eslint-disable-next-line no-console
				console.error( 'Dismiss error', error );
				throw error;
			} );
	}, [] );

	/**
	 * Toggle between active and dismissed views.
	 */
	const toggleView = useCallback( () => {
		setViewDismissed( ( prev ) => {
			const next = ! prev;
			if ( next ) {
				fetchDismissedNotifications();
			}
			return next;
		} );
	}, [ fetchDismissedNotifications ] );

	// Toggle body class for panel visibility.
	useEffect( () => {
		if ( isPanelOpen ) {
			document.body.classList.add( 'edd-notifications-open' );
		} else {
			document.body.classList.remove( 'edd-notifications-open' );
		}
	}, [ isPanelOpen ] );

	// Update the header count badge.
	useEffect( () => {
		const countEl = document.getElementById( 'edd-notification-count' );
		if ( countEl ) {
			countEl.textContent = activeCount;

			const bubble = countEl.closest( '.edd-number' );
			if ( bubble ) {
				if ( activeCount > 0 ) {
					bubble.classList.remove( 'edd-hidden' );
				} else {
					bubble.classList.add( 'edd-hidden' );
				}
			}
		}
	}, [ activeCount ] );

	// Drive the native <dialog> open/close and handle transitions.
	useEffect( () => {
		const dialog = panelRef.current;
		if ( ! dialog ) {
			return;
		}

		if ( isPanelOpen ) {
			if ( ! dialog.open ) {
				dialog.showModal();
			}

			// Double rAF ensures the browser paints the initial (off-screen) state before transitioning.
			requestAnimationFrame( () => {
				requestAnimationFrame( () => {
					dialog.classList.add( 'edd-panel--open' );
				} );
			} );
		} else if ( dialog.open ) {
			dialog.classList.remove( 'edd-panel--open' );

			const onTransitionEnd = () => {
				if ( dialog.open ) {
					dialog.close();
				}
				dialog.removeEventListener( 'transitionend', onTransitionEnd );
			};
			dialog.addEventListener( 'transitionend', onTransitionEnd );

			// Fallback in case the transition event doesn't fire.
			setTimeout( () => {
				if ( dialog.open ) {
					dialog.close();
				}
			}, 350 );
		}
	}, [ isPanelOpen ] );

	// Handle native dialog cancel event (Escape key) and backdrop clicks.
	useEffect( () => {
		const dialog = panelRef.current;
		if ( ! dialog ) {
			return;
		}

		const handleCancel = ( e ) => {
			e.preventDefault();
			closePanel();
		};

		const handleClick = ( e ) => {
			if ( e.target === dialog ) {
				closePanel();
			}
		};

		dialog.addEventListener( 'cancel', handleCancel );
		dialog.addEventListener( 'click', handleClick );

		return () => {
			dialog.removeEventListener( 'cancel', handleCancel );
			dialog.removeEventListener( 'click', handleClick );
		};
	}, [ closePanel ] );

	// Expose openPanel globally for the header button.
	useEffect( () => {
		globalThis.eddNotifications = { openPanel };

		// Show the notification button now that React is ready.
		const notificationButton = document.getElementById( 'edd-notification-button' );
		if ( notificationButton ) {
			notificationButton.classList.remove( 'edd-hidden' );
		}
	}, [ openPanel ] );

	// Check URL params for auto-open.
	useEffect( () => {
		const params = new URLSearchParams( globalThis.location.search );
		if ( params.has( 'notifications' ) && 'true' === params.get( 'notifications' ) ) {
			openPanel();
		}
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	// Show the notification count bubble on mount only if there are active notifications.
	useEffect( () => {
		const notificationCountBubble = document.querySelector( '#edd-notification-button .edd-number' );
		if ( notificationCountBubble && activeCount > 0 ) {
			notificationCountBubble.classList.remove( 'edd-hidden' );
		}
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	const currentNotifications = viewDismissed ? dismissedNotifications : notifications;
	const currentLoaded = viewDismissed ? dismissedLoaded : isLoaded;

	return (
		<dialog
			id="edd-notifications-panel"
			aria-labelledby="edd-notifications-title"
			ref={ panelRef }
		>
			<PanelHeader
				count={ activeCount }
				viewDismissed={ viewDismissed }
				onClose={ closePanel }
				onToggleView={ toggleView }
				headerRef={ headerRef }
			/>

			<div id="edd-notifications-body">
				<NotificationList
					notifications={ currentNotifications }
					isLoaded={ currentLoaded }
					isDismissed={ viewDismissed }
					onDismiss={ dismissNotification }
				/>
			</div>
		</dialog>
	);
};

export default App;
