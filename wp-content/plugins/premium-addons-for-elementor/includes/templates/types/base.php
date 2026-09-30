<?php

namespace PremiumAddons\Includes\Templates\Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No access of directly access
}

if ( ! class_exists( 'Premium_Structure_Base' ) ) {

	/**
	 * Define Premium_Structure_Base class
	 */
	abstract class Premium_Structure_Base {

		abstract public function get_id();

		abstract public function get_plural_label();

		abstract public function get_sources();

		/**
		 * Library settings for current structure
		 *
		 * @return array
		 */
		public function library_settings() {

			return array(
				'show_title'    => true,
				'show_keywords' => true,
			);
		}
	}

}
