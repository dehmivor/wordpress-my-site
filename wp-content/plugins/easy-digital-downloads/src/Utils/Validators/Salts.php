<?php
/**
 * Salts Validator
 *
 * Validates that the install defines complete, unique WordPress security keys
 * (salts), so a feature that derives secrets from them can refuse to operate on
 * a site left on default, empty, or duplicated keys.
 *
 * @package     EDD\Utils\Validators
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

declare(strict_types=1);

namespace EDD\Utils\Validators;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Salts validator class.
 *
 * @since 3.6.9
 */
final class Salts {

	/**
	 * WordPress salt constants a secure install must define.
	 *
	 * @since 3.6.9
	 * @var string[]
	 */
	const REQUIRED_SALTS = array(
		'AUTH_KEY',
		'SECURE_AUTH_KEY',
		'LOGGED_IN_KEY',
		'NONCE_KEY',
		'AUTH_SALT',
		'SECURE_AUTH_SALT',
		'LOGGED_IN_SALT',
		'NONCE_SALT',
	);

	/**
	 * Minimum acceptable length for a configured salt.
	 *
	 * @since 3.6.9
	 * @var int
	 */
	const MIN_SALT_LENGTH = 32;

	/**
	 * Determines whether the install defines complete, unique WordPress salts.
	 *
	 * Every required salt must be defined, a non-empty string, free of the
	 * wp-config sample default, at least MIN_SALT_LENGTH characters, and
	 * distinct from the others.
	 *
	 * @since 3.6.9
	 *
	 * @return bool True when every required salt is defined, non-default, long
	 *              enough, and distinct.
	 */
	public static function are_secure(): bool {
		$placeholder = 'put your unique phrase here';
		$seen        = array();

		foreach ( self::REQUIRED_SALTS as $salt ) {
			if ( ! defined( $salt ) ) {
				return false;
			}

			$value = constant( $salt );
			if ( ! is_string( $value ) ) {
				return false;
			}

			$value = trim( $value );

			// Reject the wp-config sample default explicitly; the length floor catches it
			// today (27 chars), but the guarantee shouldn't depend on that constant.
			if ( '' === $value || $placeholder === $value || strlen( $value ) < self::MIN_SALT_LENGTH ) {
				return false;
			}

			if ( in_array( $value, $seen, true ) ) {
				return false;
			}

			$seen[] = $value;
		}

		return true;
	}
}
