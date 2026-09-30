/**
 * Store Actions
 *
 * @package EDD
 * @since   3.7.0
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { apiFetch } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import { ACTION_TYPES } from './constants';

/**
 * Open the templates browser modal.
 *
 * @return {Object} Action object.
 */
export function openModal() {
	return {
		type: ACTION_TYPES.OPEN_MODAL,
	};
}

/**
 * Close the templates browser modal.
 *
 * @return {Object} Action object.
 */
export function closeModal() {
	return {
		type: ACTION_TYPES.CLOSE_MODAL,
	};
}

/**
 * Fetch templates from the remote API.
 *
 * @return {Object} Action object.
 */
export function* fetchTemplates() {
	yield { type: ACTION_TYPES.FETCH_TEMPLATES_START };

	try {
		const response = yield apiFetch( {
			path: '/edd/v3/checkout-templates',
			method: 'GET',
		} );

		// API returns { templates: [], available_tags: [], available_editors: [], can_import: bool, license_status: string }
		const templates = response?.templates || [];
		const availableTags = response?.available_tags || [];
		const availableEditors = response?.available_editors || [];
		const canImport = response?.can_import ?? undefined;
		const licenseStatus = response?.license_status ?? undefined;

		yield {
			type: ACTION_TYPES.FETCH_TEMPLATES_SUCCESS,
			templates,
			availableTags,
			availableEditors,
			canImport,
			licenseStatus,
		};
	} catch ( error ) {
		// Extract rich error data from API response.
		const errorData = error.data || {};
		yield {
			type: ACTION_TYPES.FETCH_TEMPLATES_ERROR,
			error: {
				message: error.message || 'Failed to fetch templates',
				code: error.code || 'unknown_error',
				supportUrl: errorData.support_url || 'https://easydigitaldownloads.com/support/',
				recoverable: errorData.recoverable ?? true,
				retry: errorData.retry ?? true,
				action: errorData.action || null,
				actionUrl: errorData.action_url || null,
				actionLabel: errorData.action_label || null,
				errorType: 'browse',
			},
		};
	}
}

/**
 * Select a template for preview.
 *
 * @param {Object} template Template object.
 * @return {Object} Action object.
 */
export function selectTemplate( template ) {
	return {
		type: ACTION_TYPES.SELECT_TEMPLATE,
		template,
	};
}

/**
 * Clear template selection.
 *
 * @return {Object} Action object.
 */
export function clearSelection() {
	return {
		type: ACTION_TYPES.CLEAR_SELECTION,
	};
}

/**
 * Import a template to the checkout page.
 *
 * @param {string} templateId Template ID to import.
 * @param {string} editor     Editor type (elementor, block).
 * @return {Object} Action object.
 */
export function* importTemplate( templateId, editor ) {
	yield { type: ACTION_TYPES.IMPORT_START, templateId, editor };

	try {
		const result = yield apiFetch( {
			path: `/edd/v3/checkout-templates/${ templateId }/import`,
			method: 'POST',
			data: {
				editor,
			},
		} );

		yield {
			type: ACTION_TYPES.IMPORT_SUCCESS,
			result,
		};
	} catch ( error ) {
		// Extract rich error data from API response.
		const errorData = error.data || {};
		yield {
			type: ACTION_TYPES.IMPORT_ERROR,
			error: {
				message: error.message || 'Failed to import template',
				code: error.code || 'unknown_error',
				supportUrl: errorData.support_url || 'https://easydigitaldownloads.com/support/',
				recoverable: errorData.recoverable ?? false,
				retry: errorData.retry ?? false,
				action: errorData.action || null,
				actionUrl: errorData.action_url || null,
				actionLabel: errorData.action_label || null,
				editor: errorData.editor || null,
				minVersion: errorData.min_version || null,
				errorType: 'import',
			},
		};
	}
}

/**
 * Restore the checkout page from a revision.
 *
 * @param {string} revisionId Revision ID to restore.
 * @return {Object} Action object.
 */
export function* restoreCheckout( revisionId ) {
	yield { type: ACTION_TYPES.RESTORE_START };

	try {
		const result = yield apiFetch( {
			path: '/edd/v3/checkout-templates/restore',
			method: 'POST',
			data: { revision_id: revisionId },
		} );

		yield {
			type: ACTION_TYPES.RESTORE_SUCCESS,
			result,
		};
	} catch ( err ) {
		const errorMessage = err?.message ?? err?.data?.message ?? __( 'Failed to restore checkout page.', 'easy-digital-downloads' );

		yield {
			type: ACTION_TYPES.RESTORE_ERROR,
			error: {
				message: errorMessage,
				code: err?.code ?? 'restore_failed',
				supportUrl: err?.data?.support_url ?? 'https://easydigitaldownloads.com/support/',
				recoverable: false,
				retry: false,
				errorType: 'restore',
			},
		};
	}
}

/**
 * Dismiss the current error.
 *
 * @return {Object} Action object.
 */
export function dismissError() {
	return {
		type: ACTION_TYPES.DISMISS_ERROR,
	};
}

/**
 * Clear the import result notification.
 *
 * @return {Object} Action object.
 */
export function clearImportResult() {
	return {
		type: ACTION_TYPES.CLEAR_IMPORT_RESULT,
	};
}

/**
 * Valid filter keys.
 *
 * @type {Array}
 */
const VALID_FILTER_KEYS = [ 'editor', 'tag', 'search' ];

/**
 * Set a filter value.
 *
 * @param {string} filterKey   Filter key (editor, tag, search).
 * @param {string} filterValue Filter value.
 * @return {Object|null} Action object or null if invalid key.
 */
export function setFilter( filterKey, filterValue ) {
	// Validate filterKey against known keys.
	if ( ! VALID_FILTER_KEYS.includes( filterKey ) ) {
		// eslint-disable-next-line no-console
		console.warn( `Invalid filter key: ${ filterKey }. Valid keys are: ${ VALID_FILTER_KEYS.join( ', ' ) }` );
		return { type: 'NOOP' };
	}

	return {
		type: ACTION_TYPES.SET_FILTER,
		filterKey,
		filterValue,
	};
}
