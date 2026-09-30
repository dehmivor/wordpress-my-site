/**
 * Store Selectors
 *
 * @package EDD
 * @since   3.7.0
 */

/**
 * WordPress dependencies
 */
import { createSelector } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { FILTER_OPTIONS } from './constants';

/**
 * Check if modal is open.
 *
 * @param {Object} state Store state.
 * @return {boolean} True if modal is open.
 */
export const isModalOpen = ( state ) => state.isModalOpen;

/**
 * Get available tags from the remote API.
 *
 * @param {Object} state Store state.
 * @return {Array} Array of tag objects with id, name, and count.
 */
export const getAvailableTags = ( state ) => state.availableTags;

/**
 * Get templates filtered by current filters.
 *
 * Memoized selector that only recalculates when templates or filters change.
 *
 * @param {Object} state Store state.
 * @return {Array} Filtered templates.
 */
export const getFilteredTemplates = createSelector(
	( state ) => {
		const { templates, filters } = state;

		// Ensure templates is an array.
		if ( ! Array.isArray( templates ) ) {
			return [];
		}

		return templates.filter( ( template ) => {
			// Filter by editor.
			if ( filters.editor !== FILTER_OPTIONS.EDITOR.ALL ) {
				if ( ! Array.isArray( template.editors ) || ! template.editors.includes( filters.editor ) ) {
					return false;
				}
			}

			// Filter by tag (matched against the tags array).
			if ( filters.tag !== FILTER_OPTIONS.TAG.ALL ) {
				if ( ! Array.isArray( template.tags ) || ! template.tags.includes( filters.tag ) ) {
					return false;
				}
			}

			// Filter by search term.
			if ( filters.search ) {
				const searchLower = filters.search.toLowerCase();
				const nameMatch = template.name?.toLowerCase().includes( searchLower ) || false;
				const descMatch = template.description?.toLowerCase().includes( searchLower ) || false;
				if ( ! nameMatch && ! descMatch ) {
					return false;
				}
			}

			return true;
		} );
	},
	( state ) => [ state.templates, state.filters ]
);

/**
 * Get the currently selected template.
 *
 * @param {Object} state Store state.
 * @return {Object|null} Selected template or null.
 */
export const getSelectedTemplate = ( state ) => state.selectedTemplate;

/**
 * Check if templates are loading.
 *
 * @param {Object} state Store state.
 * @return {boolean} True if loading.
 */
export const isLoading = ( state ) => state.isLoading;

/**
 * Check if an import is in progress.
 *
 * @param {Object} state Store state.
 * @return {boolean} True if importing.
 */
export const isImporting = ( state ) => state.isImporting;

/**
 * Check if a restore is in progress.
 *
 * @param {Object} state Store state.
 * @return {boolean} True if restoring.
 */
export const isRestoring = ( state ) => state.isRestoring;

/**
 * Get current error object.
 *
 * @param {Object} state Store state.
 * @return {Object|null} Error object or null.
 */
export const getError = ( state ) => state.error;

/**
 * Get error message string.
 *
 * @param {Object} state Store state.
 * @return {string|null} Error message or null.
 */
export const getErrorMessage = ( state ) => {
	if ( ! state.error ) {
		return null;
	}
	// Handle both old string format and new object format.
	return typeof state.error === 'string' ? state.error : state.error.message;
};

/**
 * Get current filters.
 *
 * @param {Object} state Store state.
 * @return {Object} Current filters.
 */
export const getFilters = ( state ) => state.filters;

/**
 * Check if user can import templates.
 *
 * @param {Object} state Store state.
 * @return {boolean} True if user can import.
 */
export const canImport = ( state ) => state.canImport;

/**
 * Get the license status.
 *
 * @param {Object} state Store state.
 * @return {string} License status.
 */
export const getLicenseStatus = ( state ) => state.licenseStatus;

/**
 * Whether the site is running EDD Lite (no Pro license).
 *
 * @param {Object} state Store state.
 * @return {boolean} True when the site is Lite.
 */
export const getIsLite = ( state ) => state.isLite;

/**
 * Get available editors on this site.
 *
 * @param {Object} state Store state.
 * @return {Object} Available editors.
 */
export const getAvailableEditors = ( state ) => state.editors;

/**
 * Get editors supported by the templates, from the API response.
 *
 * @param {Object} state Store state.
 * @return {Array} Array of editor objects from the API.
 */
export const getAvailableEditorsFromApi = ( state ) => state.availableEditors;

/**
 * Get the checkout page info.
 *
 * @param {Object} state Store state.
 * @return {Object} Checkout page info with id and url.
 */
export const getCheckoutPage = ( state ) => ( {
	id: state.checkoutPageId,
	url: state.checkoutPageUrl,
	upgradeUrl: state.upgradeUrl,
} );

/**
 * Get the import result (success message, edit_url).
 *
 * @param {Object} state Store state.
 * @return {Object|null} Import result or null.
 */
export const getImportResult = ( state ) => state.importResult;

/**
 * Get the last import attempt ({ id, editor }), preserved for retry after an import error.
 *
 * @param {Object} state Store state.
 * @return {Object|null} Last import attempt or null.
 */
export const getLastImportAttempt = ( state ) => state.lastImportAttempt;
