/**
 * Checkout Templates Data Store
 *
 * Registers the @wordpress/data store for checkout templates.
 *
 * @package EDD
 * @since   3.7.0
 */

/**
 * WordPress dependencies
 */
import { createReduxStore, register } from '@wordpress/data';
import { controls } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import { STORE_NAME } from './constants';
import reducer from './reducer';
import * as selectors from './selectors';
import * as actions from './actions';

/**
 * Store configuration.
 */
const storeConfig = {
	reducer,
	selectors,
	actions,
	controls,
};

/**
 * Create and register the store.
 */
const store = createReduxStore( STORE_NAME, storeConfig );
register( store );

export { STORE_NAME };
export default store;
