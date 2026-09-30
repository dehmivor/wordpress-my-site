/**
 * EDD Checkout box custom JS element type for Elementor.
 *
 * @package     EDD\Elementor
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

import { EDD_TYPE } from './patterns';
import { hasRequiredInternals } from './container-utils';

/**
 * Register a JS element type for our custom elType that reuses the native
 * Container model and view.
 *
 * Subclasses the native `container` element type and overrides only getType(),
 * so getModel/getView/getEmptyView are inherited and return the real Container
 * model whose isValidChild accepts any widget/element child.
 *
 * @since 3.7.0
 * @return {boolean} True when the type is registered (or already was).
 */
const registerCheckoutBoxType = () => {
	if ( ! hasRequiredInternals() ) {
		return false;
	}

	const manager = globalThis.elementor.elementsManager;

	if ( manager.elementTypes?.[ EDD_TYPE ] ) {
		return true;
	}

	const containerType = manager.getElementTypeClass( 'container' );
	if ( ! containerType ) {
		return false;
	}

	const BaseType = containerType.constructor;

	class EddCheckoutBoxType extends BaseType {
		getType() {
			return EDD_TYPE;
		}
	}

	manager.registerElementType( new EddCheckoutBoxType() );

	return true;
};

export { registerCheckoutBoxType };
