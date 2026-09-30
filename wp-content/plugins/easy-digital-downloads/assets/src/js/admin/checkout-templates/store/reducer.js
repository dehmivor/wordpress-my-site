/**
 * Store Reducer
 *
 * @package EDD
 * @since   3.7.0
 */

/**
 * Internal dependencies
 */
import { ACTION_TYPES, FILTER_OPTIONS } from './constants';

/**
 * Get initial state from localized data.
 *
 * @return {Object} Initial state.
 */
const getInitialState = () => {
	const config = globalThis.eddCheckoutTemplates || {};

	return {
		// Modal state.
		isModalOpen: false,

		// Templates data.
		templates: [],
		availableTags: [],
		availableEditors: [],
		selectedTemplate: null,

		// Loading states.
		isLoading: false,
		isImporting: false,
		isRestoring: false,

		// Error state.
		error: null,

		// Import result (success message, edit_url).
		importResult: null,

		// Last import attempt ({ id, editor }), preserved for retry after an import error.
		lastImportAttempt: null,

		// Filters.
		filters: {
			editor: FILTER_OPTIONS.EDITOR.ALL,
			tag: FILTER_OPTIONS.TAG.ALL,
			search: '',
		},

		// Configuration from PHP.
		canImport: config.canImport ?? false,
		licenseStatus: config.licenseStatus ?? 'invalid',
		isLite: config.isLite ?? false,
		editors: config.editors ?? {},
		checkoutPageId: config.checkoutPageId ?? 0,
		checkoutPageUrl: config.checkoutPageUrl ?? '',
		upgradeUrl: config.upgradeUrl ?? '',
	};
};

/**
 * Reducer function.
 *
 * @param {Object} state  Current state.
 * @param {Object} action Action object.
 * @return {Object} New state.
 */
const reducer = ( state = getInitialState(), action ) => {
	switch ( action.type ) {
		// Modal state.
		case ACTION_TYPES.OPEN_MODAL:
			return {
				...state,
				isModalOpen: true,
			};

		case ACTION_TYPES.CLOSE_MODAL:
			return {
				...state,
				isModalOpen: false,
				selectedTemplate: null,
			};

		// Fetch templates.
		case ACTION_TYPES.FETCH_TEMPLATES_START:
			return {
				...state,
				isLoading: true,
				error: null,
			};

		case ACTION_TYPES.FETCH_TEMPLATES_SUCCESS:
			return {
				...state,
				isLoading: false,
				templates: action.templates,
				availableTags: action.availableTags,
				availableEditors: action.availableEditors,
				...( action.canImport !== undefined && { canImport: action.canImport } ),
				...( action.licenseStatus !== undefined && { licenseStatus: action.licenseStatus } ),
			};

		case ACTION_TYPES.FETCH_TEMPLATES_ERROR:
			return {
				...state,
				isLoading: false,
				error: action.error,
			};

		// Template selection.
		case ACTION_TYPES.SELECT_TEMPLATE:
			return {
				...state,
				selectedTemplate: action.template,
				importResult: null,
				error: null,
			};

		case ACTION_TYPES.CLEAR_SELECTION:
			return {
				...state,
				selectedTemplate: null,
			};

		// Import.
		case ACTION_TYPES.IMPORT_START:
			return {
				...state,
				isImporting: true,
				error: null,
				lastImportAttempt: { id: action.templateId, editor: action.editor },
			};

		case ACTION_TYPES.IMPORT_SUCCESS:
			return {
				...state,
				isImporting: false,
				importResult: { ...action.result, resultType: 'import' },
			};

		case ACTION_TYPES.IMPORT_ERROR:
			return {
				...state,
				isImporting: false,
				error: action.error,
			};

		// Restore.
		case ACTION_TYPES.RESTORE_START:
			return {
				...state,
				isRestoring: true,
				error: null,
			};

		case ACTION_TYPES.RESTORE_SUCCESS:
			return {
				...state,
				isRestoring: false,
				importResult: { ...action.result, resultType: 'restore' },
			};

		case ACTION_TYPES.RESTORE_ERROR:
			return {
				...state,
				isRestoring: false,
				importResult: null,
				error: action.error,
			};

		// Error handling.
		case ACTION_TYPES.DISMISS_ERROR:
			return {
				...state,
				error: null,
			};

		// Clear import result.
		case ACTION_TYPES.CLEAR_IMPORT_RESULT:
			return {
				...state,
				importResult: null,
			};

		// Filters.
		case ACTION_TYPES.SET_FILTER:
			return {
				...state,
				filters: {
					...state.filters,
					[ action.filterKey ]: action.filterValue,
				},
			};

		default:
			return state;
	}
};

export default reducer;
