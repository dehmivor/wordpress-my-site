/**
 * Checkout Templates App Component
 *
 * Main application component for the checkout templates browser.
 * Renders a trigger button with modal.
 *
 * @package EDD
 * @since   3.7.0
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { Button } from '@wordpress/components';
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../store/constants';
import BrowserModal from './BrowserModal';

/**
 * App component
 *
 * Renders the checkout templates browser interface.
 * Shows a trigger button that opens the template browser modal.
 *
 * @return {JSX.Element} The App component.
 */
const App = () => {
	const { openModal } = useDispatch( STORE_NAME );

	// Listen for external events to open the modal (e.g., from Elementor editor).
	useEffect( () => {
		const handleOpenModal = () => {
			openModal();
		};

		document.addEventListener( 'edd-open-checkout-templates-modal', handleOpenModal );

		return () => {
			document.removeEventListener( 'edd-open-checkout-templates-modal', handleOpenModal );
		};
	}, [ openModal ] );

	return (
		<>
			<Button
				variant="secondary"
				onClick={ openModal }
				className="edd-checkout-templates__trigger"
			>
				{ __( 'Browse Templates', 'easy-digital-downloads' ) }
			</Button>
			<BrowserModal />
		</>
	);
};

export default App;
