<?php
/**
 * Users Search.
 *
 * @package EDD\Users
 * @copyright Copyright (c) 2026, Sandhills Development, LLC
 * @license https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since 3.6.7
 */

namespace EDD\Users;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\SubscriberInterface;

/**
 * Users Search
 *
 * Handles the AJAX endpoint used by the UserSelect TomSelect/chosen field
 * to search for WordPress users by display name, login, or email.
 *
 * @since 3.6.7
 */
class Search implements SubscriberInterface {

	/**
	 * Get the subscribed events.
	 *
	 * User search is restricted to authenticated admin users only, so no
	 * nopriv counterpart is registered.
	 *
	 * @since 3.6.7
	 * @return array
	 */
	public static function get_subscribed_events(): array {
		return array(
			'wp_ajax_edd_user_search' => 'search',
		);
	}

	/**
	 * Handle the AJAX user search request.
	 *
	 * @since 3.6.7
	 * @return void
	 */
	public function search(): void {
		if ( ! edd_doing_ajax() ) {
			return;
		}

		echo wp_json_encode( $this->get_users() );

		edd_die();
	}

	/**
	 * Get matching users for the search term.
	 *
	 * Returns an empty array when no search term is supplied so that
	 * TomSelect shows its placeholder rather than a "No users found" entry.
	 *
	 * @since 3.6.7
	 * @return array Array of {id, name} objects.
	 */
	private function get_users(): array {
		$user_view_role = apply_filters( 'edd_view_users_role', 'view_shop_reports' );

		if ( ! current_user_can( $user_view_role ) ) {
			return array();
		}

		$search = isset( $_GET['s'] ) ? sanitize_text_field( urldecode( $_GET['s'] ) ) : '';

		if ( empty( $search ) ) {
			return array();
		}

		$args    = array(
			'search' => '*' . $search . '*',
			'number' => 50,
		);
		$exclude = isset( $_GET['exclude'] ) ? sanitize_text_field( urldecode( $_GET['exclude'] ) ) : '';
		if ( ! empty( $exclude ) ) {
			$args['exclude'] = explode( ',', $exclude );
		}

		$users = get_users( $args );

		if ( empty( $users ) ) {
			return array();
		}

		return array_map(
			function ( $user ) {
				return array(
					'id'   => $user->ID,
					'name' => $user->display_name,
				);
			},
			$users
		);
	}
}
