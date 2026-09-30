<?php
/**
 * Legacy EU VAT Rates.
 *
 * @package     EDD\Admin\Extensions\Legacy
 * @copyright   Copyright (c) 2025, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.5.0
 */

namespace EDD\Admin\Extensions\Legacy;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class EUVAT
 *
 * @since 3.5.0
 */
class EUVAT {

	/**
	 * Register the legacy EU VAT rates.
	 *
	 * @since 3.5.0
	 * @since 3.7.0 Initial tax rates are now imported with the latest data.
	 */
	public function register_rates() {
		if ( edd_has_upgrade_completed( 'eu_vat_legacy_rates' ) ) {
			return;
		}

		\EDD\Cron\Events\SingleEvent::add(
			time() + MINUTE_IN_SECONDS,
			'edd_get_vat_rates',
		);

		edd_set_upgrade_complete( 'eu_vat_legacy_rates' );
	}
}
