/**
 * Browser Modal Component
 *
 * Modal wrapper for the checkout templates browser.
 *
 * @package EDD
 * @since   3.7.0
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { Modal, VisuallyHidden } from '@wordpress/components';
import { useEffect, useLayoutEffect, useState, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../store/constants';
import { firstAvailableEditor } from '../utils/editors';
import TemplateGrid from './TemplateGrid';
import ImportResultDialog from './ImportResultDialog';
import ImportDialog from './ImportDialog';
import UpgradePrompt from './UpgradePrompt';

/**
 * BrowserModal component
 *
 * Wraps the template browser in a modal with proper open/close handling.
 * Handles Escape key and overlay click to close.
 * Includes live region for screen reader announcements.
 *
 * @return {JSX.Element|null} The BrowserModal component or null if closed.
 */
const BrowserModal = () => {
	const [ announcement, setAnnouncement ] = useState( '' );
	const previousErrorRef = useRef( null );
	const previousResultRef = useRef( null );
	// Tracks whether the instructional SR announcement has already fired for
	// the current modal session.
	const hasAnnouncedRef = useRef( false );
	const previousFocusRef = useRef( null );

	const {
		isOpen,
		selectedTemplate,
		error,
		errorMessage,
		importResult,
		isRestoring,
		templateCount,
		canImport,
		isImporting,
		editors,
		lastImportAttempt,
	} = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return {
			isOpen: store.isModalOpen(),
			selectedTemplate: store.getSelectedTemplate(),
			error: store.getError(),
			errorMessage: store.getErrorMessage(),
			importResult: store.getImportResult(),
			isRestoring: store.isRestoring(),
			templateCount: store.getFilteredTemplates()?.length || 0,
			canImport: store.canImport(),
			isImporting: store.isImporting(),
			editors: store.getAvailableEditors(),
			lastImportAttempt: store.getLastImportAttempt(),
		};
	}, [] );

	const { closeModal, fetchTemplates, dismissError, clearImportResult, restoreCheckout, importTemplate, clearSelection } = useDispatch( STORE_NAME );

	// Fetch templates when modal opens.
	useEffect( () => {
		if ( isOpen ) {
			fetchTemplates();
		}
	}, [ isOpen, fetchTemplates ] );

	// Reset the announcement gate and stale refs when the modal closes so they
	// fire/restore correctly on next open.
	useEffect( () => {
		if ( ! isOpen ) {
			hasAnnouncedRef.current = false;
			previousFocusRef.current = null;
			previousErrorRef.current = null;
			previousResultRef.current = null;
		}
	}, [ isOpen ] );

	// Announce the instructional message once per modal session, as soon as
	// templates are available.
	useEffect( () => {
		if ( isOpen && templateCount > 0 && ! hasAnnouncedRef.current ) {
			hasAnnouncedRef.current = true;
			setAnnouncement(
				sprintf(
					/* translators: %d: number of templates */
					__( '%d templates available. Use arrow keys to navigate.', 'easy-digital-downloads' ),
					templateCount
				)
			);
		}
	}, [ isOpen, templateCount ] );

	// Announce errors to screen readers.
	useEffect( () => {
		if ( errorMessage && errorMessage !== previousErrorRef.current ) {
			setAnnouncement( errorMessage );
			previousErrorRef.current = errorMessage;
		}
	}, [ errorMessage ] );

	// Announce import results to screen readers.
	useEffect( () => {
		if ( importResult && importResult !== previousResultRef.current ) {
			if ( importResult.success ) {
				setAnnouncement(
					importResult.message || __( 'Template imported successfully!', 'easy-digital-downloads' )
				);
			}
			previousResultRef.current = importResult;
		}
	}, [ importResult ] );

	// Capture focus when a footer panel appears, restore when dismissed.
	// When a panel transition removes the focused element (e.g., ImportDialog unmounts
	// while result loads), focus falls to <body>. Re-focus the modal frame to keep
	// Escape key handling working.
	useLayoutEffect( () => {
		const hasPanel = selectedTemplate || importResult || error;
		if ( hasPanel && ! previousFocusRef.current ) {
			previousFocusRef.current = document.activeElement;
		} else if ( ! hasPanel && previousFocusRef.current ) {
			const target = previousFocusRef.current;
			previousFocusRef.current = null;
			if ( target && document.contains( target ) ) {
				target.focus();
			} else {
				const grid = document.querySelector( '.edd-checkout-templates__grid' );
				grid?.focus();
			}
		}
		if ( hasPanel ) {
			const frame = document.querySelector( '.edd-checkout-templates-modal' );
			if ( frame && ( ! document.activeElement || document.activeElement === document.body ) ) {
				frame.focus();
			}
		}
	}, [ selectedTemplate, importResult, error ] );

	// Clear selectedTemplate when import result or error arrives.
	// Re-focus modal frame after clearing to prevent focus falling to <body>.
	useEffect( () => {
		if ( ( importResult || error ) && selectedTemplate ) {
			clearSelection();
		}
	}, [ importResult, error, selectedTemplate, clearSelection ] );

	// Keep focus inside the modal when the modal opens or panel transitions cause
	// the focused element to unmount.
	useEffect( () => {
		if ( ! isOpen ) {
			return;
		}
		if ( document.activeElement === document.body || ! document.activeElement ) {
			const frame = document.querySelector( '.edd-checkout-templates-modal' );
			if ( frame ) {
				frame.focus();
			}
		}
	}, [ isOpen ] );

	if ( ! isOpen ) {
		return null;
	}

	const title = __( 'Checkout Templates', 'easy-digital-downloads' );

	// Handle modal close requests from WP Modal (Escape, X button, overlay click).
	// WP Modal runs its internal exit animation before calling onRequestClose.
	// Since our store controls isOpen (not the Modal), returning without dispatching
	// closeModal() keeps the modal open — the animation reverses on next render.
	const handleRequestClose = ( event ) => {
		if ( isRestoring || isImporting ) {
			return;
		}

		const isEscape = event?.type === 'keydown';

		// Escape: progressive dismiss.
		if ( isEscape ) {
			if ( importResult || error ) {
				clearImportResult();
				dismissError();
				return;
			}
			if ( selectedTemplate ) {
				clearSelection();
				return;
			}
		}

		// Close: clean up all state and close.
		clearImportResult();
		dismissError();
		clearSelection();
		closeModal();
	};

	return (
		<Modal
			title={ title }
			onRequestClose={ handleRequestClose }
			shouldCloseOnEsc
			className="edd-checkout-templates-modal"
			isFullScreen
			aria-describedby="edd-checkout-templates-description"
		>
			{ /* Screen reader announcements */ }
			<VisuallyHidden>
				<div
					role="status"
					aria-live="polite"
					aria-atomic="true"
				>
					{ announcement }
				</div>
			</VisuallyHidden>

			{ /* Instructions for screen readers */ }
			<VisuallyHidden>
				<p id="edd-checkout-templates-description">
					{ __( 'Browse and import checkout page templates. Use Tab to navigate between filters and templates, and arrow keys to navigate within the template grid.', 'easy-digital-downloads' ) }
				</p>
			</VisuallyHidden>

			<div className="edd-checkout-templates">
				<TemplateGrid />
			</div>

			{ /* Footer panels — mutually exclusive, pinned at bottom */ }
			{ canImport && selectedTemplate && ! importResult && ! error && (
				<ImportDialog
					template={ selectedTemplate }
					onConfirm={ () => {
						importTemplate( selectedTemplate.id, firstAvailableEditor( selectedTemplate, editors ) );
					} }
					onCancel={ () => clearSelection() }
					isImporting={ isImporting }
				/>
			) }
			{ ( importResult || error ) && (
				<ImportResultDialog
					importResult={ importResult }
					error={ error }
					isRestoring={ isRestoring }
					onClose={ () => {
						if ( importResult ) { clearImportResult(); }
						if ( error ) { dismissError(); }
					} }
					onRestore={ ( revisionId ) => restoreCheckout( revisionId ) }
					onRetry={ () => {
						// Retry the failed operation: re-run the import for an import
						// error, otherwise re-fetch the templates list.
						const isImportError = error?.errorType === 'import';
						dismissError();
						if ( isImportError && lastImportAttempt ) {
							importTemplate( lastImportAttempt.id, lastImportAttempt.editor );
						} else {
							fetchTemplates();
						}
					} }
				/>
			) }
			{ ! canImport && (
				<UpgradePrompt
					inline
					reason="license"
				/>
			) }
		</Modal>
	);
};

export default BrowserModal;
