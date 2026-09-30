<?php
/**
 * Transfer Signer.
 *
 * Mints and verifies the HMAC that makes a transfer URL a capability. The secret
 * never leaves the Source: the Source signs, the Source's REST route verifies,
 * and the Destination only passes the signed URL through.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Transfer;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Class Transfer_Signer.
 *
 * @since 4.11.76
 */
class Transfer_Signer {

	/**
	 * Option holding the per-site signing secret.
	 */
	const OPTION = 'pa_transfer_secret';

	/**
	 * Get the site's signing secret, generating it on first use.
	 *
	 * add_option() is a no-op when a concurrent request already created the
	 * option, so re-read afterwards and return whichever value won.
	 *
	 * @return string
	 */
	public static function secret() {

		$secret = get_option( self::OPTION );

		if ( ! empty( $secret ) ) {
			return (string) $secret;
		}

		add_option( self::OPTION, wp_generate_password( 64, true, true ), '', false );

		return (string) get_option( self::OPTION );
	}

	/**
	 * Sign a token/expiry pair.
	 *
	 * @param string $token The transfer token.
	 * @param int    $exp   The expiry timestamp.
	 *
	 * @return string
	 */
	public static function sign( $token, $exp ) {
		return hash_hmac( 'sha256', $token . '|' . $exp, self::secret() );
	}

	/**
	 * Verify a signature against a token/expiry pair.
	 *
	 * @param string $token The transfer token.
	 * @param int    $exp   The expiry timestamp.
	 * @param string $sig   The signature to check.
	 *
	 * @return bool
	 */
	public static function verify( $token, $exp, $sig ) {
		return hash_equals( self::sign( $token, $exp ), (string) $sig );
	}
}
