<?php
/**
 * ActiveLayer
 *
 * Manages automatic installation/activation for ActiveLayer anti-spam.
 *
 * @package     EDD\Admin\Settings\ProductEducation\ActiveLayer
 * @copyright   Copyright (c) 2026, Easy Digital Downloads
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Admin\Settings\ProductEducation;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\SubscriberInterface;

/**
 * Class ActiveLayer
 *
 * @since 3.7.0
 */
class ActiveLayer extends Setting implements SubscriberInterface {

	/**
	 * Array of configuration data for ActiveLayer.
	 *
	 * @var array
	 */
	protected $config = array(
		'name'         => 'ActiveLayer',
		'plugin'       => 'activelayer-anti-spam-spam-protection-for-forms-comments/activelayer-anti-spam-spam-protection-for-forms-comments.php',
		'wporg_url'    => 'https://wordpress.org/plugins/activelayer-anti-spam-spam-protection-for-forms-comments/',
		'download_url' => 'https://downloads.wordpress.org/plugin/activelayer-anti-spam-spam-protection-for-forms-comments.zip',
		'settings'     => 'admin.php?page=activelayer-settings',
	);

	/**
	 * Returns an array of events that this subscriber wants to listen to.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	public static function get_subscribed_events() {
		return array(
			'edd_settings_misc' => 'register_setting',
			'edd_activelayer'   => 'settings_field',
		);
	}

	/**
	 * Append the ActiveLayer installer to the bottom of the Spam Protection (captcha) section.
	 *
	 * Runs as a callback on the `edd_settings_misc` filter, which the Misc tab applies after
	 * building the captcha section, so appending here lands at the bottom of that section.
	 *
	 * @param array $settings The settings array.
	 * @return array
	 */
	public function register_setting( $settings ): array {
		if ( ! edd_is_admin_page( 'misc' ) ) {
			return $settings;
		}

		$settings['captcha']['activelayer'] = array(
			'id'   => 'activelayer',
			'name' => __( 'Smart Spam Protection', 'easy-digital-downloads' ),
			'desc' => '',
			'type' => 'hook',
		);

		return $settings;
	}

	/**
	 * Gets the description for the settings field.
	 *
	 * @since 3.7.0
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'AI-powered spam protection. No CAPTCHAs, no friction for real visitors, higher conversions. Free tier available, no credit card required.', 'easy-digital-downloads' );
	}

	/**
	 * Whether the ActiveLayer plugin is active.
	 *
	 * @since 3.7.0
	 * @return bool
	 */
	protected function is_active(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( $this->config['plugin'] );
	}
}
