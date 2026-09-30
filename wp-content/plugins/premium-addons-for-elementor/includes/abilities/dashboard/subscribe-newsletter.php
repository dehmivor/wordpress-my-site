<?php
/**
 * Subscribe to Newsletter.
 *
 * Subscribes an email address to the Premium Addons newsletter.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Dashboard;

use PremiumAddons\Admin\Includes\Admin_Helper;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 */
class Subscribe_Newsletter implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'subscribe-newsletter';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Subscribe to Premium Addons Newsletter', 'premium-addons-for-elementor' ),
			'description'         => __( 'Subscribes an email address to the Premium Addons newsletter.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-dashboard',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'email' => array(
						'type'        => 'string',
						'format'      => 'email',
						'description' => __( 'The email address to subscribe.', 'premium-addons-for-elementor' ),
					),
				),
				'required'             => array( 'email' ),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The result of the subscribe request.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'success'  => array(
						'type'        => 'boolean',
						'description' => __( 'Whether the request reached the newsletter endpoint successfully (HTTP 200).', 'premium-addons-for-elementor' ),
					),
					'response' => array(
						'type'                 => 'object',
						'description'          => __( 'The decoded response body from the newsletter endpoint.', 'premium-addons-for-elementor' ),
						'additionalProperties' => true,
					),
				),
			),
			'permission_callback' => function () {
				return Admin_Helper::check_user_can( 'manage_options' );
			},
			'meta'                => array(
				// Don't want Angie to see this.
				'show_in_rest' => false,
				'mcp'          => array( 'public' => true ),
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			),
		);
	}

	/**
	 * Execute the ability.
	 *
	 * @param array|null $input Ability input.
	 * @return array|\WP_Error
	 */
	public function execute( $input = null ) {

		$email = isset( $input['email'] ) && is_string( $input['email'] ) ? sanitize_email( $input['email'] ) : '';

		if ( ! is_email( $email ) ) {
			return new \WP_Error(
				'premium_addons_invalid_email',
				__( 'A valid email address is required to subscribe to the newsletter.', 'premium-addons-for-elementor' )
			);
		}

		return Admin_Helper::subscribe_newsletter_request( $email );
	}
}
