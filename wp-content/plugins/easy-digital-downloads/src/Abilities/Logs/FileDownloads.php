<?php
/**
 * Ability to read EDD file download logs.
 *
 * @package     EDD\Abilities\Logs
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Abilities\Logs;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Abilities\Pagination;
use EDD\Abilities\ReadAbility;
use EDD\Abilities\Traits\FormatsDates;

/**
 * Reads a filtered list of file download logs.
 *
 * @since 3.7.1
 */
class FileDownloads extends ReadAbility {

	use FormatsDates;

	/**
	 * Reads the store data the ability describes.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input The validated input.
	 * @return array|\WP_Error
	 */
	protected function read_data( array $input ) {
		$pagination = Pagination::parse( $input );
		$query_args = $this->build_query_args( $input );
		if ( is_wp_error( $query_args ) ) {
			return $query_args;
		}

		$logs = edd_get_file_download_logs(
			array_merge(
				$query_args,
				array(
					'number' => $pagination['limit'],
					'offset' => $pagination['offset'],
				)
			)
		);

		$total = edd_count_file_download_logs( $query_args );

		$formatted = array();
		foreach ( $logs as $log ) {
			$formatted[] = self::format_log( $log );
		}

		return Pagination::envelope( 'logs', $formatted, $total, $pagination['limit'], $pagination['offset'] );
	}

	/**
	 * Gets the ability slug.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_slug(): string {
		return 'download-log';
	}

	/**
	 * Gets the ability label.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'File Download Logs', 'easy-digital-downloads' );
	}

	/**
	 * Gets the ability description.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'List file download logs, filtered by product, customer, order, or date range.', 'easy-digital-downloads' );
	}

	/**
	 * Gets the capability required to run the ability.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_capability(): string {
		return 'manage_shop_settings';
	}

	/**
	 * Gets the input schema.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	protected function get_input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array_merge(
				array(
					'product_id'  => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Filter by product (download) ID.', 'easy-digital-downloads' ),
					),
					'customer_id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Filter by customer ID.', 'easy-digital-downloads' ),
					),
					'order_id'    => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Filter by order ID.', 'easy-digital-downloads' ),
					),
					'date_start'  => array(
						'type'        => 'string',
						'format'      => 'date',
						'pattern'     => '^\d{4}-\d{2}-\d{2}$',
						'description' => __( 'Include logs created on or after this date (YYYY-MM-DD, store timezone).', 'easy-digital-downloads' ),
					),
					'date_end'    => array(
						'type'        => 'string',
						'format'      => 'date',
						'pattern'     => '^\d{4}-\d{2}-\d{2}$',
						'description' => __( 'Include logs created on or before this date (YYYY-MM-DD, store timezone).', 'easy-digital-downloads' ),
					),
				),
				Pagination::input_properties()
			),
			'additionalProperties' => false,
			'default'              => array(),
		);
	}

	/**
	 * Gets the output schema.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	protected function get_output_schema(): array {
		return Pagination::output_schema(
			'logs',
			array(
				'type'       => 'object',
				'properties' => array(
					'id'           => array( 'type' => 'integer' ),
					'product_id'   => array( 'type' => 'integer' ),
					'product_name' => array( 'type' => array( 'string', 'null' ) ),
					'file_id'      => array( 'type' => 'integer' ),
					'price_id'     => array( 'type' => 'integer' ),
					'order_id'     => array( 'type' => 'integer' ),
					'customer_id'  => array( 'type' => 'integer' ),
					'ip'           => array( 'type' => 'string' ),
					'date_created' => array( 'type' => 'string' ),
				),
				'required'   => array( 'id', 'product_id', 'order_id', 'customer_id', 'date_created' ),
			)
		);
	}

	/**
	 * Builds the log query arguments from the ability input.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input The validated input.
	 * @return array|\WP_Error The query arguments, or an error naming a bad date input.
	 */
	private function build_query_args( array $input ) {
		$args = array();

		if ( ! empty( $input['product_id'] ) ) {
			$args['product_id'] = absint( $input['product_id'] );
		}

		if ( ! empty( $input['customer_id'] ) ) {
			$args['customer_id'] = absint( $input['customer_id'] );
		}

		if ( ! empty( $input['order_id'] ) ) {
			$args['order_id'] = absint( $input['order_id'] );
		}

		$date_query = $this->build_date_query( $input );
		if ( is_wp_error( $date_query ) ) {
			return $date_query;
		}
		if ( ! empty( $date_query ) ) {
			$args['date_created_query'] = $date_query;
		}

		return $args;
	}

	/**
	 * Formats a file download log for ability output.
	 *
	 * @since 3.7.1
	 *
	 * @param \EDD\Logs\File_Download_Log $log The file download log object.
	 * @return array
	 */
	private static function format_log( $log ): array {
		$product_id   = (int) $log->product_id;
		$product_name = edd_get_download_name( $product_id );

		return array(
			'id'           => (int) $log->id,
			'product_id'   => $product_id,
			'product_name' => $product_name ? (string) $product_name : null,
			'file_id'      => (int) $log->file_id,
			'price_id'     => (int) $log->price_id,
			'order_id'     => (int) $log->order_id,
			'customer_id'  => (int) $log->customer_id,
			'ip'           => (string) $log->ip,
			'date_created' => (string) $log->date_created,
		);
	}
}
