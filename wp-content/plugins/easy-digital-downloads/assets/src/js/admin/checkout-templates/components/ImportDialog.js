/**
 * Import Dialog Component
 *
 * Confirmation dialog for importing a template.
 *
 * @package EDD
 * @since   3.7.0
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { Button, Notice } from '@wordpress/components';
import { useRef, useEffect } from '@wordpress/element';

/**
 * ImportDialog component
 *
 * Shows a confirmation dialog with warning before replacing checkout page content.
 * Auto-focuses the cancel button for safer keyboard navigation.
 *
 * @param {Object}   props            Component props.
 * @param {Object}   props.template   Template to import.
 * @param {Function} props.onConfirm  Callback when user confirms import.
 * @param {Function} props.onCancel   Callback when user cancels.
 * @param {boolean}  props.isImporting Whether import is in progress.
 * @return {JSX.Element|null} The ImportDialog component or null if no template.
 */
const ImportDialog = ( { template, onConfirm, onCancel, isImporting } ) => {
	const cancelButtonRef = useRef( null );

	// Move focus to the cancel button when the dialog opens.
	useEffect( () => { cancelButtonRef.current?.focus(); }, [] );

	if ( ! template ) {
		return null;
	}

	return (
		<section
			className="edd-checkout-templates__import-dialog"
			aria-labelledby="edd-cti-confirm-heading"
			aria-describedby="import-dialog-warning"
		>
			<h2
				id="edd-cti-confirm-heading"
				className="edd-checkout-templates__import-dialog-title edd-checkout-templates__footer-panel-title"
			>
				{ sprintf( __( 'Import %s', 'easy-digital-downloads' ), template?.name ?? '' ) }
			</h2>
			<div className="edd-checkout-templates__import-dialog-content">
				<div id="import-dialog-warning">
					<Notice
						status="warning"
						isDismissible={ false }
					>
						<p>
							<strong>{ __( 'Warning:', 'easy-digital-downloads' ) }</strong>{ ' ' }
							{ __(
								'This will replace your existing checkout page content.',
								'easy-digital-downloads'
							) }
						</p>
					</Notice>
				</div>

				<p className="edd-checkout-templates__import-dialog-revision">
					{ __(
						'Your current checkout page content will be saved as a WordPress revision. You can restore it from the Page editor if needed.',
						'easy-digital-downloads'
					) }
				</p>
			</div>

			<fieldset
				className="edd-checkout-templates__import-dialog-actions"
			>
				<legend className="screen-reader-text">
					{ __( 'Import actions', 'easy-digital-downloads' ) }
				</legend>
				<Button
					ref={ cancelButtonRef }
					variant="secondary"
					onClick={ onCancel }
					disabled={ isImporting }
				>
					{ __( 'Cancel', 'easy-digital-downloads' ) }
				</Button>

				<Button
					variant="primary"
					onClick={ onConfirm }
					isBusy={ isImporting }
					disabled={ isImporting }
					aria-busy={ isImporting }
					aria-label={ isImporting
						? __( 'Importing template, please wait...', 'easy-digital-downloads' )
						: sprintf(
							/* translators: %s: template name */
							__( 'Import %s template', 'easy-digital-downloads' ),
							template.name
						)
					}
				>
					{ isImporting
						? __( 'Importing...', 'easy-digital-downloads' )
						: __( 'Import Template', 'easy-digital-downloads' )
					}
				</Button>
			</fieldset>
		</section>
	);
};

export default ImportDialog;
