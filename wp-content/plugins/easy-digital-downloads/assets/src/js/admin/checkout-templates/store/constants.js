/**
 * Store Constants
 *
 * @package EDD
 * @since   3.7.0
 */

/**
 * Store name for checkout templates.
 *
 * @type {string}
 */
export const STORE_NAME = 'edd/checkout-templates';

/**
 * Action types.
 *
 * @type {Object}
 */
export const ACTION_TYPES = {
	// Modal state.
	OPEN_MODAL: 'OPEN_MODAL',
	CLOSE_MODAL: 'CLOSE_MODAL',

	// Template fetching.
	FETCH_TEMPLATES_START: 'FETCH_TEMPLATES_START',
	FETCH_TEMPLATES_SUCCESS: 'FETCH_TEMPLATES_SUCCESS',
	FETCH_TEMPLATES_ERROR: 'FETCH_TEMPLATES_ERROR',

	// Template selection.
	SELECT_TEMPLATE: 'SELECT_TEMPLATE',
	CLEAR_SELECTION: 'CLEAR_SELECTION',

	// Import actions.
	IMPORT_START: 'IMPORT_START',
	IMPORT_SUCCESS: 'IMPORT_SUCCESS',
	IMPORT_ERROR: 'IMPORT_ERROR',

	// Restore actions.
	RESTORE_START: 'RESTORE_START',
	RESTORE_SUCCESS: 'RESTORE_SUCCESS',
	RESTORE_ERROR: 'RESTORE_ERROR',

	// Error handling.
	DISMISS_ERROR: 'DISMISS_ERROR',

	// Clear import result.
	CLEAR_IMPORT_RESULT: 'CLEAR_IMPORT_RESULT',

	// Filter actions.
	SET_FILTER: 'SET_FILTER',
};

/**
 * Template filter options.
 *
 * @type {Object}
 */
export const FILTER_OPTIONS = {
	EDITOR: {
		ALL: 'all',
		ELEMENTOR: 'elementor',
	},
	TAG: {
		ALL: 'all',
	},
};
