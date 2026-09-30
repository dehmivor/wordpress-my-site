/**
 * Template Grid Component
 *
 * Displays templates in a grid layout with filtering.
 *
 * @package EDD
 * @since   3.7.0
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { Spinner } from '@wordpress/components';
import { useRef, useCallback, useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../store/constants';
import TemplateCard from './TemplateCard';
import TemplateFilters from './TemplateFilters';

/**
 * TemplateGrid component
 *
 * Displays a filterable grid of template cards.
 * Supports keyboard navigation with arrow keys.
 *
 * @return {JSX.Element} The TemplateGrid component.
 */
const TemplateGrid = () => {
	const gridRef = useRef( null );
	const focusedIndexRef = useRef( 0 );

	const { templates, isLoading } = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return {
			templates: store.getFilteredTemplates() || [],
			isLoading: store.isLoading(),
		};
	}, [] );

	/**
	 * Get number of columns in the grid based on current layout.
	 *
	 * @return {number} Number of columns.
	 */
	const getColumnCount = useCallback( () => {
		if ( ! gridRef.current ) {
			return 1;
		}
		const gridStyle = globalThis.getComputedStyle( gridRef.current );
		const columns = gridStyle.getPropertyValue( 'grid-template-columns' );
		return columns.split( ' ' ).length;
	}, [] );

	/**
	 * Get all focusable card elements in the grid.
	 *
	 * @return {HTMLElement[]} Array of focusable elements.
	 */
	const getFocusableCards = useCallback( () => {
		if ( ! gridRef.current ) {
			return [];
		}
		return Array.from( gridRef.current.querySelectorAll( '.edd-checkout-templates__card' ) );
	}, [] );

	/**
	 * Focus a card at a specific index.
	 *
	 * @param {number} index Index of card to focus.
	 */
	const focusCard = useCallback( ( index ) => {
		const cards = getFocusableCards();
		const card = cards[ index ];
		if ( ! card ) {
			return;
		}
		// Prefer a focusable control (enabled button, or an aria-disabled Import
		// button, which has no `disabled` attribute); fall back to the card itself
		// (tabIndex=-1) when the only action is a truly-disabled Import so arrow
		// navigation never stalls on a control-less card.
		const focusable = card.querySelector( 'button:not([disabled]), a' ) || card;
		focusable.focus();
		focusedIndexRef.current = index;
	}, [ getFocusableCards ] );

	/**
	 * Handle keyboard navigation within the grid.
	 *
	 * @param {KeyboardEvent} event Keyboard event.
	 */
	const handleKeyDown = useCallback( ( event ) => {
		const cards = getFocusableCards();
		if ( ! cards.length ) {
			return;
		}

		const currentIndex = focusedIndexRef.current;
		const columnCount = getColumnCount();
		let newIndex = currentIndex;

		switch ( event.key ) {
			case 'ArrowRight':
				newIndex = Math.min( currentIndex + 1, cards.length - 1 );
				break;
			case 'ArrowLeft':
				newIndex = Math.max( currentIndex - 1, 0 );
				break;
			case 'ArrowDown':
				newIndex = Math.min( currentIndex + columnCount, cards.length - 1 );
				break;
			case 'ArrowUp':
				newIndex = Math.max( currentIndex - columnCount, 0 );
				break;
			case 'Home':
				if ( event.ctrlKey ) {
					newIndex = 0;
				}
				break;
			case 'End':
				if ( event.ctrlKey ) {
					newIndex = cards.length - 1;
				}
				break;
			default:
				return;
		}

		if ( newIndex !== currentIndex ) {
			event.preventDefault();
			focusCard( newIndex );
		}
	}, [ getColumnCount, getFocusableCards, focusCard ] );

	/**
	 * Track which card has focus.
	 *
	 * @param {FocusEvent} event Focus event.
	 */
	const handleFocus = useCallback( ( event ) => {
		const cards = getFocusableCards();
		const cardElement = event.target.closest( '.edd-checkout-templates__card' );
		if ( cardElement ) {
			const index = cards.indexOf( cardElement );
			if ( index !== -1 ) {
				focusedIndexRef.current = index;
			}
		}
	}, [ getFocusableCards ] );

	// Reset focus index when templates change.
	useEffect( () => {
		focusedIndexRef.current = 0;
	}, [ templates ] );

	// Loading state.
	if ( isLoading && ! templates.length ) {
		return (
			<div
				className="edd-checkout-templates__loading"
				role="status"
				aria-live="polite"
			>
				<Spinner />
				<p>{ __( 'Loading templates...', 'easy-digital-downloads' ) }</p>
			</div>
		);
	}

	// Empty state after filtering.
	if ( ! templates.length ) {
		return (
			<div className="edd-checkout-templates__grid-wrapper">
				<TemplateFilters />
				<div
					className="edd-checkout-templates__empty"
					role="status"
					aria-live="polite"
				>
					<p>{ __( 'No templates found matching your filters.', 'easy-digital-downloads' ) }</p>
				</div>
			</div>
		);
	}

	return (
		<div className="edd-checkout-templates__grid-wrapper">
			<TemplateFilters />
			<ul
				ref={ gridRef }
				className="edd-checkout-templates__grid"
				role="list"
				aria-label={ __( 'Checkout templates', 'easy-digital-downloads' ) }
				onKeyDown={ handleKeyDown }
				onFocus={ handleFocus }
				tabIndex="-1"
			>
				{ templates.map( ( template, index ) => (
					<TemplateCard
						key={ template.id }
						template={ template }
						index={ index }
						totalCount={ templates.length }
					/>
				) ) }
			</ul>
		</div>
	);
};

export default TemplateGrid;
