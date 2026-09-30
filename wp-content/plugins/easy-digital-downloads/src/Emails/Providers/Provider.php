<?php
/**
 * Abstract Email Provider class.
 *
 * @package EDD\Emails\Providers
 * @copyright Copyright (c) 2026, Sandhills Development, LLC
 * @license https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since 3.6.6
 */

namespace EDD\Emails\Providers;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Abstract Email Provider class.
 *
 * @since 3.6.6
 */
abstract class Provider implements ProviderInterface {

	/**
	 * Cached provider instances.
	 *
	 * @since 3.6.6
	 * @var array
	 */
	private static $providers = array();

	/**
	 * Get all available email service providers.
	 *
	 * @since 3.6.6
	 * @return array Array of provider instances keyed by ID.
	 */
	public static function get_available_providers(): array {
		if ( empty( self::$providers ) ) {
			self::$providers = array(
				'sendgrid'  => new SendGrid(),
				'mailgun'   => new Mailgun(),
				'ses'       => new SES(),
				'sendlayer' => new SendLayer(),
			);

			/**
			 * Filters the available email service providers.
			 *
			 * @since 3.6.6
			 * @param array $providers Array of provider instances keyed by ID.
			 */
			self::$providers = apply_filters( 'edd_email_providers', self::$providers );
		}

		return self::$providers;
	}

	/**
	 * Get a specific provider by ID.
	 *
	 * @since 3.6.6
	 * @param string $id The provider ID.
	 * @return ProviderInterface|null The provider instance or null if not found.
	 */
	public static function get_provider_by_id( string $id ): ?ProviderInterface {
		$providers = self::get_available_providers();

		return $providers[ $id ] ?? null;
	}

	/**
	 * Get the provider that can handle a given bounce payload.
	 *
	 * @since 3.6.6
	 * @param array $payload The webhook payload.
	 * @return ProviderInterface|null The matching provider or null.
	 */
	public static function get_provider_for_bounce( array $payload ): ?ProviderInterface {
		foreach ( self::get_available_providers() as $provider ) {
			if ( $provider->can_handle_bounce( $payload ) ) {
				return $provider;
			}
		}

		return null;
	}

	/**
	 * Find an email log ID by recipient email address.
	 *
	 * @since 3.6.6
	 * @param string $recipient_email The recipient email address.
	 * @return int|null The email log ID if found, null otherwise.
	 */
	protected function find_email_by_recipient( string $recipient_email ): ?int {
		if ( empty( $recipient_email ) ) {
			return null;
		}

		$query  = new \EDD\Database\Queries\LogEmail();
		$emails = $query->query(
			array(
				'email'   => sanitize_email( $recipient_email ),
				'orderby' => 'date_created',
				'order'   => 'DESC',
				'number'  => 1,
			)
		);

		if ( empty( $emails ) ) {
			return null;
		}

		return (int) $emails[0]->id;
	}

	/**
	 * Reset the static provider cache.
	 *
	 * @since 3.6.6
	 * @return void
	 */
	public static function reset(): void {
		self::$providers = array();
	}
}
