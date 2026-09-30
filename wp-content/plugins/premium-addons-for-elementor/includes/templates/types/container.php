<?php

namespace PremiumAddons\Includes\Templates\Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'Premium_Structure_Container' ) ) {

	/**
	 * Define Premium_Structure_Container class
	 */
	class Premium_Structure_Container extends Premium_Structure_Base {

		public function get_id() {
			return 'premium_container';
		}

		public function get_plural_label() {
			return __( 'Containers', 'premium-addons-for-elementor' );
		}

		public function get_sources() {
			return array( 'premium-api' );
		}

		/**
		 * Library settings for current structure
		 *
		 * @return array
		 */
		public function library_settings() {

			return array(
				'show_title'    => false,
				'show_keywords' => true,
			);
		}
	}

}
