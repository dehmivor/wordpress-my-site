<?php
/**
 * Payment Token Query Class.
 *
 * @package     EDD\Database\Queries
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Database\Queries;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Database\Query;

/**
 * Class used for querying payment tokens.
 *
 * @since 3.6.9
 *
 * @see \EDD\Database\Queries\PaymentToken::__construct() for accepted arguments.
 */
class PaymentToken extends Query {

	/**
	 * Name of the database table to query.
	 *
	 * @since 3.6.9
	 * @var string
	 */
	protected $table_name = 'payment_tokens';

	/**
	 * String used to alias the database table in MySQL statement.
	 *
	 * @since 3.6.9
	 * @var string
	 */
	protected $table_alias = 'pt';

	/**
	 * Name of class used to setup the database schema.
	 *
	 * @since 3.6.9
	 * @var string
	 */
	protected $table_schema = '\\EDD\\Database\\Schemas\\PaymentTokens';

	/**
	 * Name for a single item.
	 *
	 * @since 3.6.9
	 * @var string
	 */
	protected $item_name = 'payment_token';

	/**
	 * Plural version for a group of items.
	 *
	 * @since 3.6.9
	 * @var string
	 */
	protected $item_name_plural = 'payment_tokens';

	/**
	 * Callback function for turning IDs into objects.
	 *
	 * @since 3.6.9
	 * @var string
	 */
	protected $item_shape = '\\EDD\\Database\\Rows\\PaymentToken';

	/**
	 * Group to cache queries and queried items in.
	 *
	 * @since 3.6.9
	 * @var string
	 */
	protected $cache_group = 'payment_tokens';
}
