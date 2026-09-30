<?php
/**
 * Ability to read EDD email logs.
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
use EDD\Database\Queries\LogEmail;

/**
 * Reads a filtered list of email logs.
 *
 * @since 3.7.1
 */
class Emails extends ReadAbility {

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

		$query = new LogEmail();
		$logs  = $query->query(
			array_merge(
				$query_args,
				array(
					'number' => $pagination['limit'],
					'offset' => $pagination['offset'],
				)
			)
		);

		$count_query = new LogEmail( array_merge( $query_args, array( 'count' => true ) ) );
		$total       = absint( $count_query->found_items );

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
		return 'email-log';
	}

	/**
	 * Gets the ability label.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Email Logs', 'easy-digital-downloads' );
	}

	/**
	 * Gets the ability description.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'List logs of emails the store has sent, filtered by recipient, email ID, related object, or date range.', 'easy-digital-downloads' );
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
					'object_id'   => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Filter by the ID of the object the email relates to (e.g. an order ID).', 'easy-digital-downloads' ),
					),
					'object_type' => array(
						'type'        => 'string',
						'description' => __( 'Filter by the type of object the email relates to (e.g. order).', 'easy-digital-downloads' ),
					),
					'email_id'    => array(
						'type'        => 'string',
						'description' => __( 'Filter by the registered email identifier (e.g. order_receipt).', 'easy-digital-downloads' ),
					),
					'recipient'   => array(
						'type'        => 'string',
						'format'      => 'email',
						'description' => __( 'Filter by the recipient email address.', 'easy-digital-downloads' ),
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
					'email_id'     => array( 'type' => 'string' ),
					'object_id'    => array( 'type' => 'integer' ),
					'object_type'  => array( 'type' => 'string' ),
					'subject'      => array( 'type' => 'string' ),
					'recipient'    => array( 'type' => 'string' ),
					'date_created' => array( 'type' => 'string' ),
				),
				'required'   => array( 'id', 'email_id', 'object_id', 'object_type', 'recipient', 'date_created' ),
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

		if ( ! empty( $input['object_id'] ) ) {
			$args['object_id'] = absint( $input['object_id'] );
		}

		if ( ! empty( $input['object_type'] ) ) {
			$args['object_type'] = sanitize_key( $input['object_type'] );
		}

		if ( ! empty( $input['email_id'] ) ) {
			$args['email_id'] = sanitize_key( $input['email_id'] );
		}

		if ( ! empty( $input['recipient'] ) ) {
			$args['email'] = sanitize_email( $input['recipient'] );
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
	 * Formats an email log for ability output.
	 *
	 * @since 3.7.1
	 *
	 * @param \EDD\Emails\LogEmail $log The email log object.
	 * @return array
	 */
	private static function format_log( $log ): array {
		return array(
			'id'           => (int) $log->id,
			'email_id'     => (string) $log->email_id,
			'object_id'    => (int) $log->object_id,
			'object_type'  => (string) $log->object_type,
			'subject'      => (string) $log->subject,
			'recipient'    => (string) $log->email,
			'date_created' => (string) $log->date_created,
		);
	}
}
