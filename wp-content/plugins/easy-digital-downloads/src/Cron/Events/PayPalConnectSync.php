<?php
/**
 * PayPal Connect Sync Daily Event
 *
 * Schedules the daily reconciliation that keeps the EDD Connect API's stored
 * site URL and license in sync with the local store. Staggered across the day
 * to avoid a thundering herd against the Connect API.
 *
 * @package     EDD\Cron\Events
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Cron\Events;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * PayPalConnectSync Event Class
 *
 * @since 3.7.0
 */
class PayPalConnectSync extends Event {

	/**
	 * Hook name.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	protected $hook = 'edd_paypal_v3_sync_connect';

	/**
	 * Schedule.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	protected $schedule = 'daily';

	/**
	 * Constructor.
	 *
	 * Stages a random first-run offset within the next day so existing
	 * connections do not all reconcile at the same moment (à la LogPruning).
	 *
	 * @since 3.7.0
	 */
	public function __construct() {
		$this->first_run = time() + random_int( 0, DAY_IN_SECONDS );

		parent::__construct();
	}
}
