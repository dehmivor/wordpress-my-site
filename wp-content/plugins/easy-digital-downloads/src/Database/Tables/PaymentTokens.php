<?php
/**
 * Payment Tokens Table.
 *
 * @package     EDD\Database\Tables
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Database\Tables;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Database\Table;

/**
 * Setup the global "edd_payment_tokens" database table.
 *
 * @since 3.6.9
 */
final class PaymentTokens extends Table {

	/**
	 * Table name.
	 *
	 * @since 3.6.9
	 * @var string
	 */
	protected $name = 'payment_tokens';

	/**
	 * Database version.
	 *
	 * @since 3.6.9
	 * @var int
	 */
	protected $version = 202605180;

	/**
	 * Array of upgrade versions and methods.
	 *
	 * @since 3.6.9
	 * @var array
	 */
	protected $upgrades = array();

	/**
	 * Setup the database schema.
	 *
	 * @since 3.6.9
	 * @return void
	 */
	protected function set_schema() {
		$this->schema = "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			customer_id bigint(20) unsigned NOT NULL default '0',
			gateway varchar(20) NOT NULL default '',
			token_id varchar(255) NOT NULL default '',
			gateway_customer_id varchar(255) NOT NULL default '',
			type varchar(20) NOT NULL default '',
			label varchar(255) NOT NULL default '',
			payment_source longtext,
			mode varchar(10) NOT NULL default 'live',
			status varchar(20) NOT NULL default 'active',
			date_created datetime NOT NULL default CURRENT_TIMESTAMP,
			date_modified datetime NOT NULL default CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY customer_id (customer_id),
			KEY gateway (gateway),
			KEY token_id (token_id),
			KEY status (status),
			KEY mode (mode)";
	}
}
