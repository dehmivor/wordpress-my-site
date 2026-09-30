/**
 * Checkout Templates Browser
 *
 * React application for browsing and importing checkout templates.
 *
 * @package EDD
 * @since   3.7.0
 */

/**
 * WordPress dependencies
 */
import { createRoot, StrictMode } from '@wordpress/element';

/**
 * Internal dependencies
 */
import App from './components/App';
import './store';

/**
 * Initialize the Checkout Templates Browser.
 *
 * Mounts the React application to the designated container element.
 */
const initCheckoutTemplates = () => {
	const container = document.getElementById( 'edd-checkout-templates-root' );

	if ( ! container ) {
		return;
	}

	createRoot( container ).render(
		<StrictMode>
			<App />
		</StrictMode>
	);
};

// Initialize when DOM is ready.
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initCheckoutTemplates );
} else {
	initCheckoutTemplates();
}
