<?php
/**
 * Shared pagination schema fragments for EDD abilities.
 *
 * @package     EDD\Abilities
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Pagination helpers shared by list abilities.
 *
 * @since 3.7.1
 */
final class Pagination {

	/**
	 * The default number of items returned by list abilities.
	 *
	 * @since 3.7.1
	 * @var int
	 */
	const DEFAULT_LIMIT = 20;

	/**
	 * The maximum number of items returned by list abilities.
	 *
	 * @since 3.7.1
	 * @var int
	 */
	const MAX_LIMIT = 100;

	/**
	 * Gets the input schema properties for paginated list abilities.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	public static function input_properties(): array {
		return array(
			'limit'  => array(
				'type'        => 'integer',
				'minimum'     => 1,
				'maximum'     => self::MAX_LIMIT,
				'default'     => self::DEFAULT_LIMIT,
				'description' => __( 'Maximum number of results to return.', 'easy-digital-downloads' ),
			),
			'offset' => array(
				'type'        => 'integer',
				'minimum'     => 0,
				'default'     => 0,
				'description' => __( 'Number of results to skip, for paging through results.', 'easy-digital-downloads' ),
			),
		);
	}

	/**
	 * Parses the limit and offset from an ability input array.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input The validated ability input.
	 * @return array Array with `limit` and `offset` keys.
	 */
	public static function parse( array $input ): array {
		$limit = isset( $input['limit'] ) ? absint( $input['limit'] ) : self::DEFAULT_LIMIT;

		return array(
			'limit'  => min( max( 1, $limit ), self::MAX_LIMIT ),
			'offset' => isset( $input['offset'] ) ? absint( $input['offset'] ) : 0,
		);
	}

	/**
	 * Gets the output schema for a paginated list of items.
	 *
	 * @since 3.7.1
	 *
	 * @param string $key         The property name holding the list of items.
	 * @param array  $item_schema The JSON Schema describing one item.
	 * @return array
	 */
	public static function output_schema( string $key, array $item_schema ): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				$key       => array(
					'type'  => 'array',
					'items' => $item_schema,
				),
				'total'    => array(
					'type'        => 'integer',
					'description' => __( 'Total number of matching records.', 'easy-digital-downloads' ),
				),
				'limit'    => array(
					'type' => 'integer',
				),
				'offset'   => array(
					'type' => 'integer',
				),
				'has_more' => array(
					'type'        => 'boolean',
					'description' => __( 'Whether more records exist beyond this page.', 'easy-digital-downloads' ),
				),
			),
			'required'   => array( $key, 'total', 'limit', 'offset', 'has_more' ),
		);
	}

	/**
	 * Builds a paginated response envelope.
	 *
	 * @since 3.7.1
	 *
	 * @param string $key    The property name holding the list of items.
	 * @param array  $items  The items for the current page.
	 * @param int    $total  Total number of matching records.
	 * @param int    $limit  The limit used for the query.
	 * @param int    $offset The offset used for the query.
	 * @return array
	 */
	public static function envelope( string $key, array $items, int $total, int $limit, int $offset ): array {
		return array(
			$key       => array_values( $items ),
			'total'    => $total,
			'limit'    => $limit,
			'offset'   => $offset,
			'has_more' => ( $offset + count( $items ) ) < $total,
		);
	}
}
