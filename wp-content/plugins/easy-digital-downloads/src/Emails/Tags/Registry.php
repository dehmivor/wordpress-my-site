<?php
/**
 * Email tags registry.
 *
 * @package   EDD\Emails\Tags
 * @copyright Copyright (c) 2024, Sandhills Development, LLC
 * @license   https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since     3.3.0
 */

namespace EDD\Emails\Tags;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Tags
 *
 * @since 3.3.0
 * @package EDD\Emails
 */
class Registry {

	/**
	 * Cache of registered tag instances.
	 *
	 * @since 3.7.1
	 * @var array|null
	 */
	private static $registered_tags = null;

	/**
	 * Registers the email tags.
	 *
	 * @since 3.3.0
	 * @since 3.7.1 Class-based tags are passed through `edd_email_tags` so that
	 *                       extensions modifying or removing a core tag continue to work.
	 * @return void
	 */
	public function register() {
		$class_tags = self::get_registered_tags();
		$registered = array();

		foreach ( $this->get_filtered_tags( $class_tags ) as $email_tag ) {
			if ( empty( $email_tag['tag'] ) || in_array( $email_tag['tag'], $registered, true ) ) {
				continue;
			}

			$name         = $email_tag['tag'];
			$registered[] = $name;
			$callback     = $email_tag['function'] ?? ( $email_tag['func'] ?? '' );
			$class_tag    = $class_tags[ $name ] ?? null;

			if ( $class_tag && $this->is_own_callback( $class_tag, $callback ) && $this->is_own_metadata( $class_tag, $email_tag ) ) {
				EDD()->email_tags->register( $class_tag );
				continue;
			}

			if ( $class_tag ) {
				_edd_deprecated_hook(
					'edd_email_tags',
					'3.7.1',
					'edd_registered_email_tags',
					sprintf(
						/* translators: %s: the email tag identifier, for example download_list */
						__( 'The %s tag is now registered as a class and should be replaced by filtering the registered tag classes.', 'easy-digital-downloads' ),
						$name
					)
				);
			}

			edd_add_email_tag(
				$name,
				$email_tag['description'] ?? '',
				$callback,
				$email_tag['label'] ?? '',
				$email_tag['contexts'] ?? null,
				$email_tag['recipients'] ?? null
			);
		}
	}

	/**
	 * Get all registered tag instances.
	 *
	 * @since 3.7.1
	 * @return array<string, Definitions\Tag> Keyed by tag identifier.
	 */
	public static function get_registered_tags(): array {
		if ( null !== self::$registered_tags ) {
			return self::$registered_tags;
		}

		self::$registered_tags = array();
		foreach ( self::get_registered_classes() as $class_name ) {
			$tag = self::get_tag_class( $class_name );
			if ( $tag ) {
				self::$registered_tags[ $tag->get_tag() ] = $tag;
			}
		}

		return self::$registered_tags;
	}

	/**
	 * Clears the cache of registered tag instances.
	 *
	 * @since 3.7.1
	 * @return void
	 */
	public static function reset() {
		self::$registered_tags = null;
	}

	/**
	 * Get the registered tag classes.
	 *
	 * @since 3.7.1
	 * @return array<string, string> Keyed by tag identifier, values are class names.
	 */
	private static function get_registered_classes(): array {

		/**
		 * Filters the registered email tag classes.
		 *
		 * Extensions can add their own class-based email tags by hooking into this filter.
		 *
		 * @since 3.7.1
		 * @param array<string, string> $tags Keyed by tag identifier, values are fully-qualified class names
		 *                                    that extend \EDD\Emails\Tags\Tag.
		 */
		return apply_filters(
			'edd_registered_email_tags',
			array(
				'download_list'      => Definitions\DownloadList::class,
				'file_urls'          => Definitions\FileUrls::class,
				'name'               => Definitions\Name::class,
				'fullname'           => Definitions\FullName::class,
				'username'           => Definitions\Username::class,
				'user_email'         => Definitions\UserEmail::class,
				'billing_address'    => Definitions\BillingAddress::class,
				'date'               => Definitions\Date::class,
				'subtotal'           => Definitions\Subtotal::class,
				'tax'                => Definitions\Tax::class,
				'fees_total'         => Definitions\FeesTotal::class,
				'fees_list'          => Definitions\FeesList::class,
				'price'              => Definitions\Price::class,
				'payment_id'         => Definitions\PaymentId::class,
				'receipt_id'         => Definitions\ReceiptId::class,
				'payment_method'     => Definitions\PaymentMethod::class,
				'sitename'           => Definitions\SiteName::class,
				'receipt'            => Definitions\Receipt::class,
				'receipt_link'       => Definitions\ReceiptLink::class,
				'discount_codes'     => Definitions\DiscountCodes::class,
				'ip_address'         => Definitions\IpAddress::class,
				'login_link'         => Definitions\LoginLink::class,
				'refund_link'        => Definitions\RefundLink::class,
				'order_details_link' => Definitions\OrderDetailsLink::class,
				'transaction_id'     => Definitions\TransactionId::class,
				'password_link'      => Definitions\PasswordLink::class,
				'refund_amount'      => Definitions\RefundAmount::class,
				'refund_id'          => Definitions\RefundId::class,
				'phone'              => Definitions\Phone::class,
				'company'            => Definitions\Company::class,
			)
		);
	}

	/**
	 * Passes the class-based tags through the legacy `edd_email_tags` filter.
	 *
	 * The filtered array uses the legacy `function` key rather than `func`, because that is the
	 * key extensions have always modified.
	 *
	 * @since 3.7.1
	 * @param array<string, Definitions\Tag> $class_tags The class-based tags.
	 * @return array
	 */
	private function get_filtered_tags( array $class_tags ): array {
		$tags = array();
		foreach ( $class_tags as $tag ) {
			$data             = $tag->to_array();
			$data['function'] = $data['func'];
			unset( $data['func'] );

			$tags[] = $data;
		}

		return (array) apply_filters( 'edd_email_tags', $tags );
	}

	/**
	 * Whether a callback is the tag instance's own render method.
	 *
	 * @since 3.7.1
	 * @param Definitions\Tag $tag      The tag instance.
	 * @param mixed           $callback The callback from the filtered tag array.
	 * @return bool
	 */
	private function is_own_callback( Definitions\Tag $tag, $callback ): bool {
		return array( $tag, 'render' ) === $callback;
	}

	/**
	 * Whether a filtered tag array still carries the tag instance's own metadata.
	 *
	 * A tag whose metadata was changed is registered as a legacy tag instead, so that the
	 * filtered label, description, contexts and recipients are honored.
	 *
	 * @since 3.7.1
	 * @param Definitions\Tag $tag       The tag instance.
	 * @param array           $email_tag The tag array from the filtered set.
	 * @return bool
	 */
	private function is_own_metadata( Definitions\Tag $tag, array $email_tag ): bool {
		return $tag->get_label() === ( $email_tag['label'] ?? '' )
			&& $tag->get_description() === ( $email_tag['description'] ?? '' )
			&& $tag->get_contexts() === ( $email_tag['contexts'] ?? array() )
			&& $tag->get_recipients() === ( $email_tag['recipients'] ?? null );
	}

	/**
	 * Validates and instantiates a tag class.
	 *
	 * @since 3.7.1
	 * @param string $class_name The fully-qualified class name.
	 * @return Definitions\Tag|null
	 */
	private static function get_tag_class( $class_name ) {
		if ( ! is_string( $class_name ) || ! class_exists( $class_name ) ) {
			return null;
		}

		if ( ! is_subclass_of( $class_name, Definitions\Tag::class ) ) {
			return null;
		}

		return new $class_name();
	}
}
