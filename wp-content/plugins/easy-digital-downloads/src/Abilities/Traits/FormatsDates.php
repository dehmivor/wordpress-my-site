<?php
/**
 * Shared date handling for EDD abilities.
 *
 * Validates and converts single input dates, and builds the created-date range
 * query a date_start/date_end pair describes.
 *
 * @package     EDD\Abilities\Traits
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Abilities\Traits;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Validates and converts ability input dates, and builds date range queries from them.
 *
 * @since 3.7.1
 */
trait FormatsDates {

	/**
	 * Validates an input date, returning it or the error to hand back.
	 *
	 * A JSON Schema `pattern` matches the shape but not the calendar, so
	 * `2026-02-31` reaches here. EDD's date helpers substitute the current date
	 * for anything they cannot parse and MySQL rejects it in a query, either of
	 * which reports success while doing something else.
	 *
	 * @since 3.7.1
	 *
	 * @param string $field The input field name, for the error message.
	 * @param mixed  $date  The input date value.
	 * @return string|\WP_Error The date as YYYY-MM-DD, or the error.
	 */
	private function validate_date_input( string $field, $date ) {
		$date   = is_string( $date ) ? sanitize_text_field( $date ) : '';
		$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );

		if ( $parsed instanceof \DateTimeImmutable && $parsed->format( 'Y-m-d' ) === $date ) {
			return $date;
		}

		return new \WP_Error(
			'edd_ability_invalid_date',
			sprintf(
				/* translators: %s: the name of the input field holding the invalid date. */
				__( 'The %s value has to be a real calendar date, given as YYYY-MM-DD.', 'easy-digital-downloads' ),
				$field
			),
			array( 'status' => 400 )
		);
	}

	/**
	 * Validates an input date and converts it to a UTC date string for storage.
	 *
	 * Mirrors the admin discount editor: the date is combined with a time in
	 * the store's timezone, then converted to UTC. End dates run to the end of
	 * the day.
	 *
	 * @since 3.7.1
	 *
	 * @param string $field      The input field name, for the error message.
	 * @param mixed  $date       The input date value.
	 * @param bool   $end_of_day Whether to use the end-of-day time (23:59:59).
	 * @return string|\WP_Error The UTC date string, or the error.
	 */
	private function format_date_input( string $field, $date, bool $end_of_day = false ) {
		$date = $this->validate_date_input( $field, $date );
		if ( is_wp_error( $date ) ) {
			return $date;
		}

		$date_string = EDD()->utils->get_date_string(
			$date,
			$end_of_day ? 23 : 0,
			$end_of_day ? 59 : 0,
			$end_of_day ? 59 : 0
		);

		// The date is entered in the store's timezone; convert it to UTC before saving.
		return edd_get_utc_date_string( $date_string );
	}

	/**
	 * Builds a created-date range query from the ability input.
	 *
	 * The dates are read as whole days in the store's timezone and converted to
	 * UTC, because Berlin stamps `date_created` in UTC.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input The validated input.
	 * @return array|\WP_Error The date query, empty when neither date was given, or the error.
	 */
	private function build_date_query( array $input ) {
		$date_query = array();

		if ( ! empty( $input['date_start'] ) ) {
			$date_start = $this->validate_date_input( 'date_start', $input['date_start'] );
			if ( is_wp_error( $date_start ) ) {
				return $date_start;
			}

			$date_query['after'] = edd_get_utc_date_string( $date_start . ' 00:00:00' );
		}

		if ( ! empty( $input['date_end'] ) ) {
			$date_end = $this->validate_date_input( 'date_end', $input['date_end'] );
			if ( is_wp_error( $date_end ) ) {
				return $date_end;
			}

			$date_query['before'] = edd_get_utc_date_string( $date_end . ' 23:59:59' );
		}

		if ( ! empty( $date_query ) ) {
			$date_query['inclusive'] = true;
		}

		return $date_query;
	}
}
