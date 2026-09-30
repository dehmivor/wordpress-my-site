<?php
/**
 * Render email tags.
 *
 * @since 3.3.0
 * @package EDD
 * @subpackage Emails\Tags
 */

namespace EDD\Emails\Tags;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Orders\Order;

/**
 * Class Render
 *
 * @since 3.3.0
 * @deprecated 3.7.1 Each method is now a class in EDD\Emails\Tags\Definitions.
 */
class Render {

	/**
	 * Renders the login link email tag.
	 *
	 * @since 3.3.0
	 * @deprecated 3.7.1 Use EDD\Emails\Tags\Definitions\LoginLink::render_deprecated_tag().
	 * @return string
	 */
	public function login_link() {
		return $this->render_deprecated_tag( Definitions\LoginLink::class, __METHOD__, 0 );
	}

	/**
	 * Email tag callback for {password_link}.
	 * Returns the link for new users; otherwise returns an empty string.
	 *
	 * @since 3.3.0
	 * @deprecated 3.7.1 Use EDD\Emails\Tags\Definitions\PasswordLink::render_deprecated_tag().
	 * @param int    $object_id    The object ID.
	 * @param mixed  $email_object The email object.
	 * @param string $context      The context.
	 * @return string
	 */
	public function password_link( $object_id, $email_object = null, $context = 'order' ) {
		return $this->render_deprecated_tag( Definitions\PasswordLink::class, __METHOD__, $object_id, $email_object, $context );
	}

	/**
	 * Renders the refund link email tag.
	 *
	 * @since 3.3.0
	 * @deprecated 3.7.1 Use EDD\Emails\Tags\Definitions\RefundLink::render_deprecated_tag().
	 * @param int                            $refund_id        Refund ID.
	 * @param Order                          $refund           Refund object.
	 * @param string|\EDD\Emails\Types\Email $context_or_email Context or email object.
	 * @return string
	 */
	public function refund_link( $refund_id, $refund = null, $context_or_email = '' ) {
		return $this->render_deprecated_tag( Definitions\RefundLink::class, __METHOD__, $refund_id, $refund, $context_or_email );
	}

	/**
	 * Renders the order details link email tag.
	 *
	 * @since 3.3.0
	 * @deprecated 3.7.1 Use EDD\Emails\Tags\Definitions\OrderDetailsLink::render_deprecated_tag().
	 * @param int    $order_id Order ID.
	 * @param Order  $order    Order object.
	 * @param string $context  The context.
	 * @return string
	 */
	public function order_details_link( $order_id, $order = null, $context = '' ) {
		return $this->render_deprecated_tag( Definitions\OrderDetailsLink::class, __METHOD__, $order_id, $order, $context );
	}

	/**
	 * Renders the transaction ID email tag.
	 *
	 * @since 3.3.0
	 * @deprecated 3.7.1 Use EDD\Emails\Tags\Definitions\TransactionId::render_deprecated_tag().
	 * @param int    $order_id Order ID.
	 * @param object $order    Order object.
	 * @param string $context  Context.
	 * @return string
	 */
	public function transaction_id( $order_id, $order = null, $context = 'order' ) {
		return $this->render_deprecated_tag( Definitions\TransactionId::class, __METHOD__, $order_id, $order, $context );
	}

	/**
	 * Renders the fees total email tag.
	 *
	 * @since 3.3.0
	 * @deprecated 3.7.1 Use EDD\Emails\Tags\Definitions\FeesTotal::render_deprecated_tag().
	 * @param int    $order_id Order ID.
	 * @param object $order    Order object.
	 * @return string
	 */
	public function fees_total( $order_id, $order = null ) {
		return $this->render_deprecated_tag( Definitions\FeesTotal::class, __METHOD__, $order_id, $order );
	}

	/**
	 * Renders the fees list email tag.
	 *
	 * @since 3.3.0
	 * @deprecated 3.7.1 Use EDD\Emails\Tags\Definitions\FeesList::render_deprecated_tag().
	 * @param int    $order_id Order ID.
	 * @param object $order    Order object.
	 * @return string
	 */
	public function fees_list( $order_id, $order = null ) {
		return $this->render_deprecated_tag( Definitions\FeesList::class, __METHOD__, $order_id, $order );
	}

	/**
	 * Retrieves the total refund amount for a given refund.
	 *
	 * @since 3.3.0
	 * @deprecated 3.7.1 Use EDD\Emails\Tags\Definitions\RefundAmount::render_deprecated_tag().
	 * @param int                            $order_id     The ID of the order.
	 * @param object|null                    $refund_order The refund order object. Default is null.
	 * @param \EDD\Emails\Types\Email|string $context      The context of the refund. Default is null.
	 * @return string
	 */
	public function refund_amount( $order_id, $refund_order = null, $context = null ) {
		return $this->render_deprecated_tag( Definitions\RefundAmount::class, __METHOD__, $order_id, $refund_order, $context );
	}

	/**
	 * Retrieves the refund ID for a given order.
	 *
	 * @since 3.3.0
	 * @deprecated 3.7.1 Use EDD\Emails\Tags\Definitions\RefundId::render_deprecated_tag().
	 * @param int                            $order_id The ID of the order.
	 * @param object|null                    $refund   The refund order object. Default is null.
	 * @param \EDD\Emails\Types\Email|string $context  The context of the refund. Default is null.
	 * @return string
	 */
	public function refund_id( $order_id, $refund = null, $context = null ) {
		return $this->render_deprecated_tag( Definitions\RefundId::class, __METHOD__, $order_id, $refund, $context );
	}

	/**
	 * Renders the phone number email tag.
	 *
	 * @since 3.3.9
	 * @deprecated 3.7.1 Use EDD\Emails\Tags\Definitions\Phone::render_deprecated_tag().
	 * @param int    $object_id    The object ID.
	 * @param mixed  $email_object The email object.
	 * @param string $context      The context.
	 * @return string
	 */
	public function phone( $object_id, $email_object = null, $context = null ) {
		return $this->render_deprecated_tag( Definitions\Phone::class, __METHOD__, $object_id, $email_object, $context );
	}

	/**
	 * Renders the company name email tag.
	 *
	 * @since 3.6.7
	 * @deprecated 3.7.1 Use EDD\Emails\Tags\Definitions\Company::render_deprecated_tag().
	 * @param int    $object_id    The object ID.
	 * @param mixed  $email_object The email object.
	 * @param string $context      The context.
	 * @return string
	 */
	public function company( $object_id, $email_object = null, $context = null ) {
		return $this->render_deprecated_tag( Definitions\Company::class, __METHOD__, $object_id, $email_object, $context );
	}

	/**
	 * Renders a tag definition on behalf of a deprecated method.
	 *
	 * @since 3.7.1
	 * @param string $class_name   The tag definition class name.
	 * @param string $method       The deprecated method being called.
	 * @param int    $object_id    The object ID.
	 * @param mixed  $email_object The email object.
	 * @param mixed  $context      The context or email object.
	 * @return string
	 */
	private function render_deprecated_tag( $class_name, $method, $object_id, $email_object = null, $context = '' ) {
		$tag = new $class_name();

		_edd_deprecated_function( $method, '3.7.1', $class_name . '::render' );

		return $tag->render( $object_id, $email_object, $context );
	}
}
