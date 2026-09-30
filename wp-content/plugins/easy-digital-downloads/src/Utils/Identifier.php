<?php
/**
 * Identifier utilities.
 *
 * Generates and persists stable identifiers (UUIDs) used to identify
 * an EDD install across requests, releases, and security key rotation.
 *
 * Privacy note: this UUID is used solely to maintain a stable identity
 * for the EDD install when communicating with EDD-authored integrations
 * that need a per-install correlation key. It is never sent to analytics,
 * telemetry, or any third-party tracking service.
 *
 * @package     EDD\Utils
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Utils;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Utils\Validators\Salts;

/**
 * Identifier class.
 *
 * @since 3.6.9
 */
class Identifier {

	/**
	 * Option key for the persisted per-install UUID.
	 *
	 * @since 3.6.9
	 * @var string
	 */
	const SITE_UUID_OPTION = 'edd_site_uuid';

	/**
	 * Gets a persistent UUID for this EDD install.
	 *
	 * Generates the identifier on first use and persists it so it stays stable
	 * for the life of the install.
	 *
	 * @since 3.6.9
	 *
	 * @return string The persisted UUID for this install.
	 */
	public static function get_site_uuid(): string {
		$uuid = get_option( self::SITE_UUID_OPTION, '' );

		if ( ! empty( $uuid ) ) {
			return $uuid;
		}

		if ( ! Salts::are_secure() ) {
			return '';
		}

		$uuid = self::generate_site_uuid();

		update_option( self::SITE_UUID_OPTION, $uuid, false );

		return $uuid;
	}

	/**
	 * Generates a UUID-formatted identifier for this install.
	 *
	 * @since 3.6.9
	 *
	 * @return string A UUID-formatted string.
	 */
	private static function generate_site_uuid(): string {
		global $wpdb;

		$raw = md5( AUTH_KEY . SECURE_AUTH_KEY . $wpdb->prefix );

		return sprintf(
			'%s-%s-%s-%s-%s',
			substr( $raw, 0, 8 ),
			substr( $raw, 8, 4 ),
			substr( $raw, 12, 4 ),
			substr( $raw, 16, 4 ),
			substr( $raw, 20, 12 )
		);
	}
}
