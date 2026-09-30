<?php
/**
 * Payment Token Database Object Class.
 *
 * Generic Berlin row for the edd_payment_tokens table. The table is gateway
 * agnostic — any gateway that stores reusable payment tokens (currently
 * PayPal, with Stripe and others possible in the future) can read and write
 * rows through this object.
 *
 * @package     EDD\Database\Rows
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Database\Rows;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Database\Row;

/**
 * Payment Token database row class.
 *
 * @since 3.6.9
 *
 * @property int    $id
 * @property int    $customer_id
 * @property string $gateway
 * @property string $token_id
 * @property string $gateway_customer_id
 * @property string $type
 * @property string $label
 * @property string $payment_source
 * @property string $mode
 * @property string $status
 * @property string $date_created
 * @property string $date_modified
 */
class PaymentToken extends Row {

	/**
	 * Payment Token ID.
	 *
	 * @since 3.6.9
	 * @var int
	 */
	protected $id;

	/**
	 * EDD Customer ID.
	 *
	 * @since 3.6.9
	 * @var int
	 */
	protected $customer_id;

	/**
	 * Gateway identifier (slug), e.g. "paypal" or "stripe".
	 *
	 * @since 3.6.9
	 * @var string
	 */
	protected $gateway;

	/**
	 * Gateway-specific token ID returned by the remote provider.
	 *
	 * @since 3.6.9
	 * @var string
	 */
	protected $token_id;

	/**
	 * Gateway-specific customer ID returned by the remote provider.
	 *
	 * @since 3.6.9
	 * @var string
	 */
	protected $gateway_customer_id;

	/**
	 * Payment instrument type, e.g. "card" or "paypal".
	 *
	 * Distinct from $gateway: $gateway identifies the processor (paypal,
	 * stripe), $type identifies the underlying payment instrument backing
	 * the token (card, paypal account, bank, etc.).
	 *
	 * @since 3.6.9
	 * @var string
	 */
	protected $type;

	/**
	 * Human-readable label.
	 *
	 * @since 3.6.9
	 * @var string
	 */
	protected $label;

	/**
	 * JSON blob of gateway-specific display data.
	 *
	 * @since 3.6.9
	 * @var string
	 */
	protected $payment_source;

	/**
	 * Live or sandbox mode.
	 *
	 * @since 3.6.9
	 * @var string
	 */
	protected $mode;

	/**
	 * Token status (active or deleted).
	 *
	 * @since 3.6.9
	 * @var string
	 */
	protected $status;

	/**
	 * Date created.
	 *
	 * @since 3.6.9
	 * @var string
	 */
	protected $date_created;

	/**
	 * Date modified.
	 *
	 * @since 3.6.9
	 * @var string
	 */
	protected $date_modified;

	/**
	 * Returns the decoded payment_source JSON.
	 *
	 * @since 3.6.9
	 *
	 * @return array|null
	 */
	public function get_payment_source_data() {
		if ( empty( $this->payment_source ) ) {
			return null;
		}

		return json_decode( $this->payment_source, true );
	}
}
