<?php
/**
 * Session related cron events.
 *
 * @package EDD\Cron\Components
 */

namespace EDD\Cron\Components;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Database\Queries\Session;
use EDD\Cron\Schedulers\Handler;

/**
 * Sessions Class
 *
 * @since 3.3.0
 */
class Sessions extends Component {

	/**
	 * The unique identifier for this component.
	 *
	 * @var string
	 */
	protected static $id = 'sessions';

	/**
	 * Gets the array of subscribed events.
	 */
	public static function get_subscribed_events(): array {
		return array(
			'edd_cleanup_sessions' => 'remove_expired_sessions',
		);
	}

	/**
	 * Deletes all expired sessions from the database.
	 * This uses Berlin instead of directly querying the database
	 * to make use of Berlin's caching support.
	 *
	 * @since 3.3.0
	 * @return void
	 */
	public function remove_expired_sessions() {
		$batch_size       = $this->get_batch_size();
		$query            = new Session();
		$expired_sessions = $query->query(
			array(
				'number'                  => $batch_size,
				'order'                   => 'ASC',
				'orderby'                 => 'session_expiry',
				'session_expiry__compare' => array(
					'relation' => 'AND',
					array(
						'value'   => time(),
						'compare' => '<',
					),
				),
			)
		);

		if ( empty( $expired_sessions ) ) {
			return;
		}

		$deleted = 0;
		foreach ( $expired_sessions as $session ) {
			if ( $query->delete_item( $session->session_id ) ) {
				++$deleted;
			}
		}

		// A full batch was fetched, so more expired rows may remain. Chain another
		// run, but only when at least one row was deleted, so repeated delete
		// failures cannot spin an empty chain.
		if ( count( $expired_sessions ) >= $batch_size && $deleted > 0 ) {
			$this->schedule_continuation();
		}
	}

	/**
	 * Gets the maximum number of expired sessions to delete per cleanup run.
	 *
	 * The same value drives the query limit and the continuation test: a run that
	 * deletes a full batch schedules a follow-up, a short batch ends the chain.
	 *
	 * @since 3.7.1
	 *
	 * @return int The batch size, at least 1.
	 */
	private function get_batch_size(): int {
		/**
		 * Filters the number of expired sessions deleted per cleanup run.
		 *
		 * @since 3.7.1
		 *
		 * @param int $batch_size The maximum number of sessions to delete per run.
		 */
		return max( 1, (int) apply_filters( 'edd/cron/sessions/cleanup_batch_size', 500 ) );
	}

	/**
	 * Enqueues an immediate follow-up cleanup while a backlog remains.
	 *
	 * Uses distinct args ('continuation') so the follow-up never collides with the
	 * recurring edd_cleanup_sessions action (args []). Guards against pile-up by only
	 * enqueuing when no continuation is already pending.
	 *
	 * @since 3.7.1
	 *
	 * @return void
	 */
	private function schedule_continuation() {
		$scheduler = Handler::get_scheduler();
		$hook      = 'edd_cleanup_sessions';
		$args      = array( 'continuation' => 1 );

		// A healthy linear chain has no pending continuation: the current run is
		// in-progress, not pending. If one is already queued, do not add a second.
		if ( $scheduler->has_pending( $hook, $args ) ) {
			return;
		}

		$scheduler->enqueue_async( $hook, $args );
	}
}
