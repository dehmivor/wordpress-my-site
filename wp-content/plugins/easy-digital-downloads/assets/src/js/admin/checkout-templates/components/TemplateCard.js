/**
 * Template Card Component
 *
 * Individual template card in the grid with preview and import actions.
 *
 * @package EDD
 * @since   3.7.0
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../store/constants';
import { firstAvailableEditor } from '../utils/editors';

/**
 * TemplateCard component
 *
 * Displays a template card with thumbnail, metadata, and action buttons.
 *
 * @param {Object} props            Component props.
 * @param {Object} props.template   Template data.
 * @param {number} props.index      Index in the grid (0-based).
 * @param {number} props.totalCount Total number of templates in grid.
 * @return {JSX.Element} The TemplateCard component.
 */
const TemplateCard = ( { template, index = 0, totalCount = 1 } ) => {
	const {
		name,
		description,
		thumbnail,
		editors: templateEditors,
		preview_url: previewUrl,
		id,
	} = template;

	const { canImport, editors } = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return {
			canImport: store.canImport(),
			editors: store.getAvailableEditors(),
		};
	}, [] );

	const { selectTemplate } = useDispatch( STORE_NAME );

	// Find the first editor from the template's editors array that is available on this site.
	const editorAvailable = !! firstAvailableEditor( template, editors );

	/**
	 * Open template preview in new browser tab.
	 *
	 * Opens the R2-hosted static HTML preview in a new tab.
	 * No iframe or modal - direct link.
	 */
	const handlePreview = () => {
		if ( previewUrl ) {
			globalThis.open( previewUrl, '_blank', 'noopener,noreferrer' );
		}
	};

	/**
	 * Handle import button click.
	 * Shows appropriate dialog based on user's license and editor availability.
	 */
	const handleImportClick = () => {
		// License check — Import button should be disabled, but guard defensively.
		if ( ! canImport ) {
			return;
		}

		// Dispatch to store — BrowserModal renders confirmation footer.
		if ( template ) {
			selectTemplate( template );
		}
	};

	const primaryEditor = Array.isArray( templateEditors ) ? templateEditors[ 0 ] : undefined;

	// Labels and the unavailability copy are single-sourced from the server
	// editor registry — never hardcoded here. Fall back to the raw slug only
	// when the map has no entry for it.
	const editorLabel = editors[ primaryEditor ]?.label ?? primaryEditor;
	const unavailableText = editors[ primaryEditor ]?.unavailable_text;

	// Show the reason only when the license allows importing but the required
	// editor is unavailable (the license gate is surfaced separately).
	const showUnavailableReason = canImport && ! editorAvailable && !! unavailableText;

	return (
			<li
				className="edd-checkout-templates__card"
				tabIndex={ -1 }
				aria-labelledby={ `template-title-${ id }` }
				aria-describedby={ description ? `template-desc-${ id }` : undefined }
			>
				<div className="edd-checkout-templates__card-thumbnail">
					{ thumbnail ? (
						<img
							src={ thumbnail }
							alt=""
							loading="lazy"
							aria-hidden="true"
						/>
					) : (
						<div
							className="edd-checkout-templates__card-placeholder"
							aria-hidden="true"
						/>
					) }
				</div>

				<div className="edd-checkout-templates__card-content">
					<h3 className="edd-checkout-templates__card-title" id={ `template-title-${ id }` }>
						{ name }
					</h3>
					{ description && (
						<p
							className="edd-checkout-templates__card-description"
							id={ `template-desc-${ id }` }
						>
							{ description }
						</p>
					) }
					{ showUnavailableReason && (
						<div className="edd-checkout-templates__card-unavailable">
							{ unavailableText }
						</div>
					) }
					<div className="edd-checkout-templates__card-meta">
						<span className="edd-checkout-templates__card-editor">
							<span className="dashicons dashicons-layout" aria-hidden="true"></span>
							{ editorLabel }
							<span className="screen-reader-text"> — { /* translators: required page editor */ __( 'required editor', 'easy-digital-downloads' ) }</span>
						</span>
					</div>
				</div>

				<div className="edd-checkout-templates__card-actions">
					{ previewUrl && (
						<Button
							variant="secondary"
							onClick={ handlePreview }
							aria-label={ sprintf(
								/* translators: 1: template name, 2: current position, 3: total count */
								__( 'Preview %1$s, %2$d of %3$d', 'easy-digital-downloads' ),
								name,
								index + 1,
								totalCount
							) }
						>
							{ __( 'Preview', 'easy-digital-downloads' ) }
						</Button>
					) }

					<Button
						variant="primary"
						onClick={ handleImportClick }
						disabled={ ! editorAvailable || ! canImport }
						accessibleWhenDisabled={ ! editorAvailable }
						title={ showUnavailableReason ? unavailableText : undefined }
						aria-label={ sprintf(
							/* translators: 1: template name, 2: current position, 3: total count */
							__( 'Import %1$s, %2$d of %3$d', 'easy-digital-downloads' ),
							name,
							index + 1,
							totalCount
						) }
					>
						{ __( 'Import', 'easy-digital-downloads' ) }
					</Button>
				</div>
			</li>
	);
};

export default TemplateCard;
