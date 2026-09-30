<?php

namespace PremiumAddons\Includes\Templates\Types;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No access of directly access
}

if ( ! class_exists( 'Premium_Templates_Types' ) ) {

	/**
	 * Premium Templates Types.
	 *
	 * Templates types responsible for handling templates library tabs
	 *
	 * @since 3.6.0
	 */
	class Premium_Templates_Types {

		/*
		 * Templates Types
		 */
		private $types = null;

		/**
		 * Premium_Templates_Types constructor.
		 *
		 * Get available types for the templates.
		 *
		 * @since 3.6.0
		 * @access public
		 */
		public function __construct() {

			$this->register_types();
		}

		/**
		 * Register default templates types
		 *
		 * @since 3.6.0
		 * @access public
		 *
		 * @return void
		 */
		public function register_types() {

			$base_path = PREMIUM_ADDONS_PATH . 'includes/templates/types/';

			require $base_path . 'base.php';
			require $base_path . 'container.php';
			require $base_path . 'page.php';

			$types = array( new Premium_Structure_Container(), new Premium_Structure_Page() );

			foreach ( $types as $type ) {
				$this->types[ $type->get_id() ] = $type;
			}
		}

		/**
		 * Return types prepared for templates library tabs
		 *
		 * @since 3.6.0
		 * @access public
		 */
		public function get_types_for_popup() {

			$result = array();

			foreach ( $this->types as $id => $structure ) {
				$result[ $id ] = array(
					'title'    => $structure->get_plural_label(),
					'data'     => array(),
					'sources'  => $structure->get_sources(),
					'settings' => $structure->library_settings(),
				);
			}

			return $result;
		}
	}

}
