<?php

namespace ElementsKit_Lite\Libs\Framework\Classes;

defined( 'ABSPATH' ) || exit;

/**
 * Tracks plugins installed during a WPMet onboarding flow.
 *
 * @since 3.9.5
 */
class Install_Tracker {

	/**
	 * Option name used to store the shared WPMet onboarding registry.
	 *
	 * @since 3.9.5
	 * @var string
	 */
	const OPTION_KEY = 'wpmet_onboarded_plugins';

	/**
	 * Onboarding status options used by known Wpmet plugins.
	 *
	 * @since 3.9.5
	 * @var array<string, array{option: string, value: mixed, crm_slug?: string}>
	 */
	const ONBOARD_STATUS_MAP = array(
		'elementskit-lite/elementskit-lite.php'           => array( 'option' => 'elements_kit_onboard_status', 'value' => 'onboarded', 'crm_slug' => 'elementskit-lite' ),
		'emailkit/EmailKit.php'                           => array( 'option' => 'emailkit_onboard_status', 'value' => 'onboarded', 'crm_slug' => 'emailkit' ),
		'shopengine/shopengine.php'                       => array( 'option' => 'shopengine_onboard_status', 'value' => 'onboarded', 'crm_slug' => 'shopengine' ),
		'metform/metform.php'                             => array( 'option' => 'met_form_onboard_status', 'value' => 'onboarded', 'crm_slug' => 'metform' ),
		'gutenkit-blocks-addon/gutenkit-blocks-addon.php' => array( 'option' => 'gutenkit_onboard_status', 'value' => 'onboarded', 'crm_slug' => 'gutenkit-blocks-addon' ),
		'getgenie/getgenie.php'                           => array( 'option' => 'getgenie_onboard_status', 'value' => 'onboarded', 'crm_slug' => 'getgenie' ),
		'popup-builder-block/popup-builder-block.php'     => array( 'option' => 'popupkit_onboard_status', 'value' => 'onboarded', 'crm_slug' => 'popupkit' ),
	);

	/**
	 * Return the filterable plugin onboarding map.
	 *
	 * @since 3.9.5
	 *
	 * @return array
	 */
	public static function get_onboard_status_map(): array {
		return (array) apply_filters( 'wpmet_onboard_status_map', self::ONBOARD_STATUS_MAP );
	}

	/**
	 * Record an auto-installed plugin and complete its onboarding.
	 *
	 * @since 3.9.5
	 *
	 * @param string $plugin_file  Plugin basename.
	 * @param string $installed_by Plugin that initiated the installation.
	 * @return void
	 */
	public static function mark( string $plugin_file, string $installed_by = 'elementskit-lite' ): void {
		$registry = self::get_registry();

		if ( ! isset( $registry[ $plugin_file ] ) ) {
			$registry[ $plugin_file ] = array(
				'installed_by' => $installed_by,
				'installed_at' => time(),
			);
			update_option( self::OPTION_KEY, $registry, false );
		}

		$map = self::get_onboard_status_map();

		if ( isset( $map[ $plugin_file ] ) && ! get_option( $map[ $plugin_file ]['option'] ) ) {
			update_option( $map[ $plugin_file ]['option'], $map[ $plugin_file ]['value'], false );
		}
	}

	/**
	 * Check whether a plugin was installed by a Wpmet onboarding flow.
	 *
	 * @since 3.9.5
	 *
	 * @param string $plugin_file Plugin basename.
	 * @return bool
	 */
	public static function was_auto_installed( string $plugin_file ): bool {
		return isset( self::get_registry()[ $plugin_file ] );
	}

	/**
	 * Get the auto-install registry.
	 *
	 * @since 3.9.5
	 *
	 * @return array Shared onboarding registry.
	 */
	public static function get_registry(): array {
		return (array) get_option( self::OPTION_KEY, array() );
	}

	/**
	 * Store the collected onboarding email in the shared registry.
	 *
	 * @since 3.9.5
	 *
	 * @param string $email Email address collected during onboarding.
	 * @return void
	 */
	public static function store_collected_email( string $email ): void {
		if ( ! is_email( $email ) ) {
			return;
		}

		$registry                              = self::get_registry();
		$registry['_shared']['email']           = sanitize_email( $email );
		$registry['_shared']['email_collected'] = true;
		$registry['_shared']['updated_at']      = time();
		self::save_registry( $registry );
	}

	/**
	 * Get the email address stored in the shared onboarding registry.
	 *
	 * @since 3.9.5
	 *
	 * @return string Sanitized email address, or an empty string when unavailable.
	 */
	public static function get_collected_email(): string {
		$registry = self::get_registry();
		$email    = isset( $registry['_shared']['email'] ) ? $registry['_shared']['email'] : '';

		return is_email( $email ) ? sanitize_email( $email ) : '';
	}

	/**
	 * Determine whether an email address has been collected.
	 *
	 * @since 3.9.5
	 *
	 * @return bool True when an email address has been collected.
	 */
	public static function has_collected_email(): bool {
		$registry = self::get_registry();

		return ! empty( $registry['_shared']['email_collected'] );
	}

	/**
	 * Determine whether the install notice was dismissed for a plugin.
	 *
	 * @since 3.9.5
	 *
	 * @param string $plugin_file Plugin basename.
	 * @return bool True when the notice was dismissed.
	 */
	public static function is_notice_dismissed( string $plugin_file ): bool {
		$registry = self::get_registry();

		return ! empty( $registry['_shared']['dismissed'][ $plugin_file ] );
	}

	/**
	 * Mark the install notice as dismissed for a plugin.
	 *
	 * @since 3.9.5
	 *
	 * @param string $plugin_file Plugin basename.
	 * @return void
	 */
	public static function dismiss_notice( string $plugin_file ): void {
		$registry                                                = self::get_registry();
		$registry['_shared']['dismissed'][ $plugin_file ]         = true;
		$registry['_shared']['notice_resolved_at'][ $plugin_file ] = time();
		$registry['_shared']['updated_at']                        = time();
		self::save_registry( $registry );
	}

	/**
	 * Save the shared onboarding registry.
	 *
	 * @since 3.9.5
	 *
	 * @param array $registry Shared onboarding registry.
	 * @return void
	 */
	private static function save_registry( array $registry ): void {
		update_option( self::OPTION_KEY, $registry, false );
	}
}
