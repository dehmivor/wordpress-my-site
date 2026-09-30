<?php
/**
 * Ability handler registry.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Registry;

use PremiumAddons\Admin\Includes\Admin_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Stores ability handlers and registers the enabled set with WordPress.
 */
class Ability_Registry {

	/**
	 * Ability name prefix.
	 */
	const PREFIX = 'premium-addons/';

	/**
	 * Separator between an ability's human description and an agent-only hint
	 * appended to it.
	 */
	const AGENT_HINT_DELIMITER = "\n\n";

	/**
	 * Ability handlers keyed by short name.
	 *
	 * @var array
	 */
	private $handlers = array();

	/**
	 * Disabled full ability names.
	 *
	 * @var array|null
	 */
	private $disabled_names = null;

	/**
	 * Add an ability handler.
	 *
	 * @param object $handler Ability handler.
	 * @return void
	 */
	public function register( $handler ) {
		if (
			! is_object( $handler )
			|| ! method_exists( $handler, 'get_name' )
			|| ! method_exists( $handler, 'get_registration_args' )
			|| ! method_exists( $handler, 'execute' )
		) {
			return;
		}

		$name = $handler->get_name();

		if ( ! is_string( $name ) || '' === $name || isset( $this->handlers[ $name ] ) ) {
			return;
		}

		$this->handlers[ $name ] = $handler;
	}

	/**
	 * Register enabled handlers with the WordPress Abilities API.
	 *
	 * @return void
	 */
	public function register_enabled_abilities() {
		$disabled_names = $this->get_disabled_names();

		foreach ( $this->handlers as $name => $handler ) {
			$full_name = self::PREFIX . $name;

			if ( in_array( $full_name, $disabled_names, true ) ) {
				continue;
			}

			$registration_args                     = $handler->get_registration_args();
			$registration_args['execute_callback'] = array( $handler, 'execute' );

			wp_register_ability( $full_name, $registration_args );
		}
	}

	/**
	 * Get full names of enabled handlers without registering them.
	 *
	 * @return array
	 */
	public function get_enabled_names() {
		$enabled_names  = array();
		$disabled_names = $this->get_disabled_names();

		foreach ( array_keys( $this->handlers ) as $name ) {
			$full_name = self::PREFIX . $name;

			if ( ! in_array( $full_name, $disabled_names, true ) ) {
				$enabled_names[] = $full_name;
			}
		}

		return $enabled_names;
	}

	/**
	 * Get lightweight metadata for every handler.
	 *
	 * @return array
	 */
	public function get_abilities_meta() {
		$ability_metadata = array();

		foreach ( $this->handlers as $name => $handler ) {
			$registration_args = $handler->get_registration_args();
			$annotations       = isset( $registration_args['meta']['annotations'] ) ? $registration_args['meta']['annotations'] : array();
			$description        = isset( $registration_args['description'] ) ? $registration_args['description'] : '';
			$hint_pos           = strpos( $description, self::AGENT_HINT_DELIMITER );

			$ability_metadata[] = array(
				'name'        => $name,
				'full_name'   => self::PREFIX . $name,
				'label'       => isset( $registration_args['label'] ) ? $registration_args['label'] : '',
				'description' => false === $hint_pos ? $description : rtrim( substr( $description, 0, $hint_pos ) ),
				'category'    => isset( $registration_args['category'] ) ? $registration_args['category'] : '',
				'readonly'    => ! empty( $annotations['readonly'] ),
			);
		}

		return $ability_metadata;
	}

	/**
	 * Get all stored short handler names.
	 *
	 * @return array
	 */
	public function get_abilities_names() {
		return array_keys( $this->handlers );
	}

	/**
	 * Read and cache disabled ability names for this registry instance.
	 *
	 * @return array
	 */
	private function get_disabled_names() {
		if ( null === $this->disabled_names ) {
			$settings             = Admin_Helper::get_ai_abilities_settings();
			$this->disabled_names = $settings['disabled_abilities'];
		}

		return $this->disabled_names;
	}
}
