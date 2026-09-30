/**
 * Decodes CSS escape sequences in a string value.
 *
 * Firefox on Windows serializes getComputedStyle font-family values using CSS
 * escape sequences (e.g. "MS Shell Dlg \32 " for "MS Shell Dlg 2") because
 * CSS identifiers cannot start with a digit. Stripe's style API rejects these
 * sequences, so we must decode them before passing the value.
 *
 * @param {string} value Raw CSS value from getComputedStyle.
 * @return {string} Decoded value safe to pass to Stripe.
 */
export function unescapeCSSValue( value ) {
	return value
		.replace( /\\([0-9a-fA-F]{1,6})\s?/g, ( _, hex ) =>
			String.fromCodePoint( Number.parseInt( hex, 16 ) )
		)
		.replace( /\\(.)/g, '$1' );
}

/**
 * Retrieves all following siblings of an element.
 *
 * @param {HTMLElement} el Starting element.
 * @return {Array} siblings List of sibling elements.
 */
export function getNextSiblings( el ) {
	const siblings = [];
	let sibling = el.nextElementSibling;

	while ( sibling ) {
		if ( sibling.nodeType === 1 ) {
			siblings.push( sibling );
		}

		sibling = sibling.nextElementSibling;
	}

	return siblings;
}
