/**
 * Editor utility functions for checkout templates.
 *
 * @package EDD
 * @since   3.7.0
 */

/**
 * Returns the first editor from a template's editors list that is available on this site.
 *
 * @param {Object} template Template data object (must have an `editors` array property).
 * @param {Object} editors  Available editors map from the store (keyed by editor slug).
 * @return {string|undefined} The first available editor slug, or undefined if none found.
 */
export const firstAvailableEditor = ( template, editors ) => {
	const e = template?.editors;
	return Array.isArray( e ) ? e.find( ( ed ) => editors[ ed ]?.available ) : undefined;
};
