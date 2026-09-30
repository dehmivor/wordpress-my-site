<?php
/**
 * API Requests Log View Class
 *
 * @package     EDD
 * @subpackage  Admin/Reports
 * @copyright   Copyright (c) 2018, Easy Digital Downloads, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.5
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * EDD_API_Request_Log_Table List Table Class
 *
 * @since 1.5
 * @since 3.0 Updated to use the custom tables and new query classes.
 */
class EDD_API_Request_Log_Table extends EDD_Base_Log_List_Table {

	/**
	 * Log type
	 *
	 * @var string
	 */
	protected $log_type = 'api_requests';

	/**
	 * Retrieve the table columns
	 *
	 * @since 1.5
	 * @return array $columns Array of all the list table columns
	 */
	public function get_columns() {
		return array(
			'ID'      => __( 'Log ID', 'easy-digital-downloads' ),
			'details' => __( 'Request Details', 'easy-digital-downloads' ),
			'version' => __( 'API Version', 'easy-digital-downloads' ),
			'ip'      => __( 'Request IP', 'easy-digital-downloads' ),
			'speed'   => __( 'Request Speed', 'easy-digital-downloads' ),
			'date'    => __( 'Date', 'easy-digital-downloads' ),
		);
	}

	/**
	 * Gets the name of the primary column.
	 *
	 * @since 2.5
	 * @access protected
	 *
	 * @return string Name of the primary column.
	 */
	protected function get_primary_column_name() {
		return 'ID';
	}

	/**
	 * Output Error Message column
	 *
	 * @since 1.5
	 * @since 3.6.9.1 Moved from thickbox to native dialog.
	 * @param array $item Contains all the data of the log.
	 * @return void
	 */
	public function column_details( $item ) {
		$dialog_id       = 'log-details-' . absint( $item['ID'] );
		$dialog_title_id = $dialog_id . '__title';
		?>
		<button type="button" class="button-link edd-logs__view-dialog" data-dialog-id="<?php echo esc_attr( $dialog_id ); ?>"><?php esc_html_e( 'View Request', 'easy-digital-downloads' ); ?></button>
		<dialog id="<?php echo esc_attr( $dialog_id ); ?>" class="edd-modal edd-modal--log" aria-labelledby="<?php echo esc_attr( $dialog_title_id ); ?>">
			<div class="edd-modal__header">
				<h2 id="<?php echo esc_attr( $dialog_title_id ); ?>"><?php esc_html_e( 'API Request', 'easy-digital-downloads' ); ?></h2>
				<button type="button" class="edd-modal__close" aria-label="<?php esc_attr_e( 'Close', 'easy-digital-downloads' ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
					<span class="screen-reader-text"><?php esc_html_e( 'Close', 'easy-digital-downloads' ); ?></span>
				</button>
			</div>
			<div class="edd-modal__content">
				<div class="edd-modal__item--api-request">
					<p><strong><?php esc_html_e( 'API Request:', 'easy-digital-downloads' ); ?></strong></p>
					<div><?php echo esc_html( $item['request'] ); ?></div>
				</div>
				<?php
				if ( ! empty( $item['error'] ) ) {
					?>
					<div class="edd-modal__item--api-request">
						<p><strong><?php esc_html_e( 'Error', 'easy-digital-downloads' ); ?></strong></p>
						<div><?php echo esc_html( $item['error'] ); ?></div>
					</div>
					<?php
				}
				?>
				<div class="edd-modal__item--api-request">
					<p><strong><?php esc_html_e( 'API User:', 'easy-digital-downloads' ); ?></strong></p>
					<div><?php echo esc_html( $item['user_id'] ); ?></div>
				</div>
				<div class="edd-modal__item--api-request">
					<p><strong><?php esc_html_e( 'API Key:', 'easy-digital-downloads' ); ?></strong></p>
					<div><?php echo esc_html( $item['api_key'] ); ?></div>
				</div>
				<div class="edd-modal__item--api-request">
					<p><strong><?php esc_html_e( 'Request Date:', 'easy-digital-downloads' ); ?></strong></p>
					<div><?php echo esc_html( edd_date_i18n( strtotime( $item['date'] ), 'Y-m-d H:i:s' ) . ' ' . edd_get_timezone_abbr() ); ?></div>
				</div>
			</div>
		</dialog>
		<?php
	}

	/**
	 * Gets the log entries for the current view
	 *
	 * @since 1.5
	 *
	 * @return array $logs_data Array of all the Log entries
	 */
	public function get_logs( $log_query = array() ) {
		$logs_data = array();
		$logs      = edd_get_api_request_logs( $log_query );

		if ( $logs ) {
			foreach ( $logs as $log ) {
				/** @var $log EDD\Logs\Api_Request_Log */

				$logs_data[] = array(
					'ID'      => $log->id,
					'version' => $log->version,
					'speed'   => $log->time,
					'ip'      => $log->ip,
					'date'    => $log->date_created,
					'api_key' => $log->api_key,
					'request' => $log->request,
					'error'   => $log->error,
					'user_id' => $log->user_id,
				);
			}
		}

		return $logs_data;
	}

	/**
	 * Get the total number of items
	 *
	 * @since 3.0
	 *
	 * @param array $log_query Arguments for getting logs.
	 *
	 * @return int
	 */
	public function get_total( $log_query = array() ) {
		return edd_count_api_request_logs( $log_query );
	}
}
