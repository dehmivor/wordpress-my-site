<?php
/**
 * Order Addresses Schema Class.
 *
 * @package     EDD\Database\Schemas
 * @copyright   Copyright (c) 2018, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.0
 */

namespace EDD\Database\Schemas;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Database\Schema;

/**
 * Order Addresses Schema Class.
 *
 * @since 3.0
 */
class Order_Addresses extends Schema {

	/**
	 * Array of database column objects
	 *
	 * @since  3.0
	 * @access public
	 * @var array
	 */
	public $columns = array(

		// id
		array(
			'name'     => 'id',
			'type'     => 'bigint',
			'length'   => '20',
			'unsigned' => true,
			'extra'    => 'auto_increment',
			'primary'  => true,
			'sortable' => true,
		),

		// order_id
		array(
			'name'     => 'order_id',
			'type'     => 'bigint',
			'length'   => '20',
			'unsigned' => true,
			'default'  => '0',
		),

		// type.
		array(
			'name'       => 'type',
			'type'       => 'varchar',
			'length'     => '20',
			'default'    => 'billing',
			'sortable'   => true,
			'transition' => true,
		),

		// name
		array(
			'name'       => 'name',
			'type'       => 'mediumtext',
			'searchable' => true,
			'sortable'   => true,
			'validate'   => 'sanitize_text_field',
		),

		// address
		array(
			'name'       => 'address',
			'type'       => 'mediumtext',
			'searchable' => true,
			'sortable'   => true,
			'validate'   => 'sanitize_text_field',
		),

		// address2
		array(
			'name'       => 'address2',
			'type'       => 'mediumtext',
			'searchable' => true,
			'sortable'   => true,
			'validate'   => 'sanitize_text_field',
		),

		// city
		array(
			'name'       => 'city',
			'type'       => 'mediumtext',
			'searchable' => true,
			'sortable'   => true,
			'validate'   => 'sanitize_text_field',
		),

		// region
		array(
			'name'       => 'region',
			'type'       => 'mediumtext',
			'searchable' => true,
			'sortable'   => true,
			'validate'   => 'sanitize_text_field',
		),

		// postal_code
		array(
			'name'       => 'postal_code',
			'type'       => 'varchar',
			'length'     => '32',
			'default'    => '',
			'searchable' => true,
			'sortable'   => true,
			'validate'   => 'sanitize_text_field',
		),

		// country
		array(
			'name'       => 'country',
			'type'       => 'mediumtext',
			'searchable' => true,
			'sortable'   => true,
			'validate'   => 'sanitize_text_field',
		),

		// date_created
		array(
			'name'       => 'date_created',
			'type'       => 'datetime',
			'default'    => '', // Defaults to current time in query class
			'created'    => true,
			'date_query' => true,
			'sortable'   => true,
		),

		// date_modified
		array(
			'name'       => 'date_modified',
			'type'       => 'datetime',
			'default'    => '', // Defaults to current time in query class
			'modified'   => true,
			'date_query' => true,
			'sortable'   => true,
		),

		// uuid
		array(
			'uuid' => true,
		),
	);
}
