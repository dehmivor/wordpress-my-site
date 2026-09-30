<?php
/**
 * Ability handler contract.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Defines the data and execution methods required by an ability handler.
 */
interface Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args();

	/**
	 * Execute the ability.
	 *
	 * @param array|null $input Ability input.
	 * @return array|\WP_Error
	 */
	public function execute( $input = null );
}
