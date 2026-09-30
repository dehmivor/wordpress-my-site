/**
 * Import Result Dialog Component
 *
 * Inline section showing import/restore results with action buttons.
 *
 * @package EDD
 * @since   3.7.0
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState, useRef, useEffect } from '@wordpress/element';
import { Button, Notice } from '@wordpress/components';

/**
 * RestoreButton inner component
 *
 * Renders an inline confirm pattern for the restore action.
 *
 * @param {Object}   props             Component props.
 * @param {number}   props.revisionId  WordPress revision ID to restore.
 * @param {boolean}  props.isRestoring Whether a restore is in progress.
 * @param {Function} props.onRestore   Callback to trigger restore with revision ID.
 * @return {JSX.Element} The RestoreButton component.
 */
const RestoreButton = ( { revisionId, isRestoring: busy, onRestore } ) => {
	const [ showConfirm, setShowConfirm ] = useState( false );

	if ( showConfirm ) {
		return (
			<span className="edd-checkout-templates__result-dialog-confirm">
				{ __( 'Are you sure?', 'easy-digital-downloads' ) }{ ' ' }
				<Button
					variant="primary"
					isDestructive
					isBusy={ busy }
					disabled={ busy }
					onClick={ () => onRestore( revisionId ) }
				>
					{ busy
						? __( 'Restoring...', 'easy-digital-downloads' )
						: __( 'Yes, Restore', 'easy-digital-downloads' )
					}
				</Button>{ ' ' }
				<Button
					variant="secondary"
					disabled={ busy }
					onClick={ () => setShowConfirm( false ) }
				>
					{ __( 'Cancel', 'easy-digital-downloads' ) }
				</Button>
			</span>
		);
	}

	return (
		<Button
			variant="tertiary"
			onClick={ () => setShowConfirm( true ) }
		>
			{ __( 'Restore Original Checkout', 'easy-digital-downloads' ) }
		</Button>
	);
};

/**
 * ImportResultDialog component
 *
 * Shows an inline section with the result of an import or restore operation.
 * On success: provides edit, compare, and restore actions.
 * On error: provides retry, fix issue, and support actions.
 *
 * @param {Object}        props              Component props.
 * @param {Object|null}   props.importResult Import result object from store, or null.
 * @param {Object|null}   props.error        Error object from store, or null.
 * @param {boolean}       props.isRestoring  Whether a restore is currently in progress.
 * @param {Function}      props.onClose      Callback to close the dialog.
 * @param {Function}      props.onRestore    Callback to trigger restore with revision ID.
 * @param {Function}      props.onRetry      Callback to retry after an error.
 * @return {JSX.Element|null} The ImportResultDialog component or null if no result or error.
 */
const ImportResultDialog = ( { importResult, error, isRestoring, onClose, onRestore, onRetry } ) => {
	const primaryRef = useRef( null );
	const closeButtonRef = useRef( null );

	// Move focus to the primary action or fallback Close button when result changes.
	useEffect( () => {
		if ( primaryRef.current ) { primaryRef.current.focus(); }
		else { closeButtonRef.current?.focus(); }
	}, [ importResult, error ] );

	if ( ! importResult && ! error ) {
		return null;
	}

	// Success layout.
	if ( importResult?.success === true ) {
		const resultType = importResult.resultType;
		const title = resultType === 'restore'
			? __( 'Checkout Restored Successfully', 'easy-digital-downloads' )
			: __( 'Template Imported Successfully', 'easy-digital-downloads' );

		// When the current Elementor editor session is already open on the imported page,
		// reload it in place instead of opening a new tab. isElementor is localized via
		// wp_localize_script, which serializes the boolean to the string '1'.
		const isEditingCurrentPage = globalThis.eddCheckoutTemplates?.isElementor === '1'
			&& Number( globalThis.elementor?.config?.initial_document?.id ) === Number( importResult.page_id );

		/**
		 * Reload the current Elementor editor page, confirming first if there are unsaved changes.
		 */
		const handleEditReload = () => {
			if ( globalThis.elementor?.saver?.isEditorChanged() ) {
				const confirmed = globalThis.confirm( __( 'You have unsaved changes. Reload anyway?', 'easy-digital-downloads' ) );
				if ( ! confirmed ) {
					return;
				}
			}
			globalThis.location.reload();
		};

		return (
			<section
				className="edd-checkout-templates__result-dialog"
				aria-labelledby="edd-cti-result-heading"
				aria-describedby="result-dialog-message"
			>
				<h2
					id="edd-cti-result-heading"
					className="edd-checkout-templates__result-dialog-title edd-checkout-templates__footer-panel-title"
				>
					{ title }
				</h2>
				<div className="edd-checkout-templates__result-dialog-content">
					<p id="result-dialog-message" className="screen-reader-text">{ importResult.message }</p>

					{ importResult.revision_id && (
						<Notice status="info" isDismissible={ false }>
							{ __( 'Your previous content was saved as a revision.', 'easy-digital-downloads' ) }
						</Notice>
					) }

					{ ( importResult.revisions_enabled === false && resultType === 'import' ) && (
						<Notice status="warning" isDismissible={ false }>
							{ __( 'Note: Revisions are disabled for your checkout page. You will not be able to restore the previous content.', 'easy-digital-downloads' ) }
						</Notice>
					) }
				</div>

				<fieldset className="edd-checkout-templates__result-dialog-actions">
					<legend className="screen-reader-text">
						{ __( 'Result actions', 'easy-digital-downloads' ) }
					</legend>

					{ isEditingCurrentPage ? (
						<Button
							variant="primary"
							onClick={ handleEditReload }
							ref={ primaryRef }
						>
							{ __( 'Edit Page', 'easy-digital-downloads' ) }
						</Button>
					) : (
						<Button
							variant="primary"
							href={ importResult.edit_url }
							target="_blank"
							rel="noopener noreferrer"
							ref={ primaryRef }
						>
							{ __( 'Edit Page', 'easy-digital-downloads' ) }
						</Button>
					) }

					{ importResult.compare_url && (
						<Button
							variant="secondary"
							href={ importResult.compare_url }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ __( 'Compare with Original', 'easy-digital-downloads' ) }
						</Button>
					) }

					{ ( importResult.revision_id && resultType !== 'restore' ) && (
						<RestoreButton
							revisionId={ importResult.revision_id }
							isRestoring={ isRestoring }
							onRestore={ onRestore }
						/>
					) }

					<Button
						variant="secondary"
						onClick={ onClose }
						disabled={ isRestoring }
					>
						{ __( 'Close', 'easy-digital-downloads' ) }
					</Button>
				</fieldset>
			</section>
		);
	}

	// Error layout.
	if ( error ) {
		const errorType = error.errorType;
		const title = errorType === 'restore'
			? __( 'Restore Failed', 'easy-digital-downloads' )
			: errorType === 'browse'
				? __( 'Failed to Load Templates', 'easy-digital-downloads' )
				: __( 'Import Failed', 'easy-digital-downloads' );

		return (
			<section
				className="edd-checkout-templates__result-dialog edd-checkout-templates__result-dialog--error"
				aria-labelledby="edd-cti-result-error-heading"
				aria-describedby="result-dialog-error-message"
			>
				<h2
					id="edd-cti-result-error-heading"
					className="edd-checkout-templates__result-dialog-title edd-checkout-templates__footer-panel-title"
				>
					{ title }
				</h2>
				<div className="edd-checkout-templates__result-dialog-content">
					<Notice status="error" isDismissible={ false }>
						<p id="result-dialog-error-message">{ error.message }</p>
					</Notice>
				</div>

				<fieldset className="edd-checkout-templates__result-dialog-actions">
					<legend className="screen-reader-text">
						{ __( 'Error actions', 'easy-digital-downloads' ) }
					</legend>

					{ ( error.recoverable === true || error.retry === true ) && (
						<Button
							variant="primary"
							onClick={ onRetry }
							ref={ primaryRef }
						>
							{ __( 'Try Again', 'easy-digital-downloads' ) }
						</Button>
					) }

					{ error.actionUrl && (
						<Button
							variant="secondary"
							href={ error.actionUrl }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ error.actionLabel ?? __( 'Fix Issue', 'easy-digital-downloads' ) }
						</Button>
					) }

					<Button
						variant="tertiary"
						href={ error.supportUrl ?? 'https://easydigitaldownloads.com/support/' }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Contact Support', 'easy-digital-downloads' ) }
					</Button>

					<Button
						ref={ closeButtonRef }
						variant="secondary"
						onClick={ onClose }
					>
						{ __( 'Close', 'easy-digital-downloads' ) }
					</Button>
				</fieldset>
			</section>
		);
	}

	return null;
};

export default ImportResultDialog;
