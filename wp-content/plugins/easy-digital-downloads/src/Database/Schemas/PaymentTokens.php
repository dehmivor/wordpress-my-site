<?php
/**
 * Payment Tokens Schema Class.
 *
 * @package     EDD\Database\Schemas
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Database\Schemas;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Database\Schema;

/**
 * Payment Tokens Schema Class.
 *
 * @since 3.6.9
 */
class PaymentTokens extends Schema {

	/**
	 * Array of database column objects.
	 *
	 * @since 3.6.9
	 * @var array
	 */
	public $columns = array(

		// id.
		array(
			'name'     => 'id',
			'type'     => 'bigint',
			'length'   => '20',
			'unsigned' => true,
			'extra'    => 'auto_increment',
			'primary'  => true,
			'sortable' => true,
		),

		// customer_id.
		array(
			'name'     => 'customer_id',
			'type'     => 'bigint',
			'length'   => '20',
			'unsigned' => true,
			'default'  => '0',
			'sortable' => true,
		),

		// gateway — the processor slug (e.g. "paypal", "stripe").
		array(
			'name'       => 'gateway',
			'type'       => 'varchar',
			'length'     => '20',
			'default'    => '',
			'searchable' => true,
			'sortable'   => true,
		),

		// token_id.
		array(
			'name'       => 'token_id',
			'type'       => 'varchar',
			'length'     => '255',
			'default'    => '',
			'searchable' => true,
		),

		// gateway_customer_id.
		array(
			'name'       => 'gateway_customer_id',
			'type'       => 'varchar',
			'length'     => '255',
			'default'    => '',
			'searchable' => true,
		),

		// type — the underlying payment instrument (e.g. "card", "paypal").
		// Distinct from the gateway column: gateway identifies the processor,
		// type identifies the instrument the token represents.
		array(
			'name'     => 'type',
			'type'     => 'varchar',
			'length'   => '20',
			'default'  => '',
			'sortable' => true,
		),

		// label.
		array(
			'name'       => 'label',
			'type'       => 'varchar',
			'length'     => '255',
			'default'    => '',
			'searchable' => true,
		),

		// payment_source.
		array(
			'name'    => 'payment_source',
			'type'    => 'longtext',
			'default' => '',
		),

		// mode.
		array(
			'name'     => 'mode',
			'type'     => 'varchar',
			'length'   => '10',
			'default'  => 'live',
			'sortable' => true,
		),

		// status.
		array(
			'name'       => 'status',
			'type'       => 'varchar',
			'length'     => '20',
			'default'    => 'active',
			'sortable'   => true,
			'transition' => true,
		),

		// date_created.
		array(
			'name'       => 'date_created',
			'type'       => 'datetime',
			'default'    => '',
			'created'    => true,
			'date_query' => true,
			'sortable'   => true,
		),

		// date_modified.
		array(
			'name'       => 'date_modified',
			'type'       => 'datetime',
			'default'    => '',
			'modified'   => true,
			'date_query' => true,
			'sortable'   => true,
		),
	);
}
