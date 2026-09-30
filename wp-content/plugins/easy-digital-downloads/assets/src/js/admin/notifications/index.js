import { createRoot } from '@wordpress/element';
import App from './App';

/**
 * Initialize the EDD Notifications React app.
 *
 * Mounts the App component into the #edd-notifications-root element
 * and passes the initial notification count from the data attribute.
 */
document.addEventListener( 'DOMContentLoaded', () => {
	const root = document.getElementById( 'edd-notifications-root' );
	if ( ! root ) {
		return;
	}

	const initialCount = Number.parseInt( root.dataset.count, 10 ) || 0;

	root.classList.remove( 'edd-hidden' );
	createRoot( root ).render( <App initialCount={ initialCount } /> );
} );
