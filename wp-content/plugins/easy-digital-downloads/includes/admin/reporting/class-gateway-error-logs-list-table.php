<?php
/**
 * Gateway Error Log View Class
 *
 * @package     EDD
 * @subpackage  Admin/Reports
 * @copyright   Copyright (c) 2018, Easy Digital Downloads, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * EDD_Gateway_Error_Log_Table Class
 *
 * @since 1.4
 * @since 3.0 Updated to use the custom tables and new query classes.
 */
class EDD_Gateway_Error_Log_Table extends EDD_Base_Log_List_Table {

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
	 * Output Payment ID Column.
	 *
	 * @since 3.3.6
	 * @param array $item Contains all the data of the log.
	 * @return string
	 */
	protected function column_payment_id( $item ) {
		return ! empty( $item['payment_id'] ) ? $item['payment_id'] : '&ndash;';
	}

	/**
	 * Output Error Message Column
	 *
	 * @since 1.4.4
	 * @since 3.6.9.1 Moved from thickbox to native dialog.
	 * @param array $item Contains all the data of the log.
	 * @return void
	 */
	public function column_message( $item ) {
		$dialog_id       = 'log-message-' . absint( $item['ID'] );
		$dialog_title_id = $dialog_id . '__title';

		$log_output = $this->get_log_output( $item );
		?>
		<button type="button" class="button-link edd-logs__view-dialog" data-dialog-id="<?php echo esc_attr( $dialog_id ); ?>"><?php esc_html_e( 'View Log Message', 'easy-digital-downloads' ); ?></button>
		<dialog id="<?php echo esc_attr( $dialog_id ); ?>" class="edd-modal edd-modal--log" aria-labelledby="<?php echo esc_attr( $dialog_title_id ); ?>">
			<div class="edd-modal__header">
				<h2 id="<?php echo esc_attr( $dialog_title_id ); ?>"><?php esc_html_e( 'Log Message', 'easy-digital-downloads' ); ?></h2>
				<button type="button" class="edd-modal__close" aria-label="<?php esc_attr_e( 'Close', 'easy-digital-downloads' ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
					<span class="screen-reader-text"><?php esc_html_e( 'Close', 'easy-digital-downloads' ); ?></span>
				</button>
			</div>
			<div class="edd-modal__content">
				<?php if ( ! empty( $log_output['intro'] ) ) : ?>
					<p><?php echo esc_html( $log_output['intro'] ); ?></p>
				<?php endif; ?>
				<pre class="edd-modal__log"><?php echo esc_html( $log_output['log_data'] ); ?></pre>
			</div>
		</dialog>
		<?php
	}

	/**
	 * Output Error Column.
	 *
	 * @since 3.6.9.1
	 * @param array $item Contains all the data of the log.
	 * @return string
	 */
	public function column_error( $item ) {
		return ! empty( $item['error'] ) ? esc_html( $item['error'] ) : '&ndash;';
	}

	/**
	 * Retrieve the table columns
	 *
	 * @since 1.4
	 * @return array $columns Array of all the list table columns
	 */
	public function get_columns() {
		return array(
			'ID'         => __( 'Log ID', 'easy-digital-downloads' ),
			'payment_id' => __( 'Order Number', 'easy-digital-downloads' ),
			'error'      => __( 'Error', 'easy-digital-downloads' ),
			'message'    => __( 'Error Message', 'easy-digital-downloads' ),
			'gateway'    => __( 'Gateway', 'easy-digital-downloads' ),
			'date'       => __( 'Date', 'easy-digital-downloads' ),
		);
	}

	/**
	 * Gets the log entries for the current view
	 *
	 * @since 1.4
	 * @param array $log_query Query arguments.
	 * @global object $edd_logs  EDD Logs Object.
	 * @return array $logs_data Array of the log data for the current view.
	 */
	public function get_logs( $log_query = array() ) {
		$logs_data         = array();
		$log_query['type'] = 'gateway_error';

		$logs = edd_get_logs( $log_query );

		if ( $logs ) {
			foreach ( $logs as $log ) {
				/** @var $log EDD\Logs\Log */

				$logs_data[] = array(
					'ID'         => $log->id,
					'payment_id' => $log->object_id,
					'error'      => $log->title ? $log->title : __( 'Payment Error', 'easy-digital-downloads' ),
					'gateway'    => edd_get_payment_gateway( $log->object_id ),
					'date'       => $log->date_created,
					'content'    => $log->content,
				);
			}
		}

		return $logs_data;
	}

	/**
	 * Setup the final data for the table.
	 *
	 * @since 1.5
	 */
	public function get_total( $log_query = array() ) {
		$log_query['type'] = 'gateway_error';

		return edd_count_logs( $log_query );
	}

	/**
	 * Gets the log output for the current view
	 *
	 * @since 3.6.9.1
	 * @param array $item Log item.
	 * @return array $log_output Array of the log data for the current view.
	 */
	private function get_log_output( $item ) {
		$log_message = $item['content'];
		$json_pos    = strpos( $log_message, '{"' );
		$intro       = false !== $json_pos ? trim( substr( $log_message, 0, $json_pos ) ) : '';
		$log_data    = false !== $json_pos ? substr( $log_message, $json_pos ) : $log_message;
		$decoded     = json_decode( $log_data, true );
		if ( null !== $decoded ) {
			$log_data = wp_json_encode( $decoded, JSON_PRETTY_PRINT );
		}

		return array(
			'intro'    => $intro,
			'log_data' => $log_data,
		);
	}
}
