/* global wp, _ */

/**
 * Internal dependencies.
 */
import { initChosen } from 'utils/chosen.js';

const RegionField = wp.Backbone.View.extend( {
	/**
	 * Bind passed arguments.
	 *
	 * @param {Object} options Extra options passed.
	 */
	initialize: function( options ) {
		_.extend( this, options );
	},

	/**
	 * Create a list of options.
	 */
	render: function() {
		if ( this.global ) {
			return;
		}

		if ( 'nostates' === this.states ) {
			this.setElement( '<input type="text" id="tax_rate_region" />' );
		} else {
			this.$el.find( 'select' ).each( function () {
				this?.tomselect?.destroy();
			} );

			this.$el.html( this.states );
			this.$el.find( 'select' ).each( function() {
				const el = $( this );
				initChosen( el );
			} );
		}
	},
} );

export default RegionField;
