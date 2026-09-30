<?php
/**
 * Notifications REST Controller
 *
 * Handles notification REST API requests.
 *
 * @package     EDD\REST\Controllers
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.6
 */

namespace EDD\REST\Controllers;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Models\Notification;

/**
 * Notifications Controller class
 *
 * @since 3.6.6
 */
class Notifications {

	/**
	 * List notifications with optional filtering.
	 *
	 * @since 3.6.6
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function list_notifications( $request ) {
		$args = array(
			'dismissed' => $request->get_param( 'dismissed' ),
		);

		$source = $request->get_param( 'source' );
		if ( ! empty( $source ) ) {
			$args['source'] = $source;
		}

		$type = $request->get_param( 'type' );
		if ( ! empty( $type ) ) {
			$args['type'] = $type;
		}

		$notifications = EDD()->notifications->getNotifications( $args );

		$data = array_map(
			function ( Notification $notification ) {
				$item = $notification->toArray();

				// Sanitize content to prevent XSS when rendered with dangerouslySetInnerHTML.
				if ( ! empty( $item['content'] ) ) {
					$item['content'] = wp_kses_post( $item['content'] );
				}

				// Sanitize title for safe output.
				if ( ! empty( $item['title'] ) ) {
					$item['title'] = wp_kses_post( $item['title'] );
				}

				return $item;
			},
			$notifications
		);

		return new \WP_REST_Response(
			array(
				'notifications' => array_values( $data ),
				'total'         => count( $data ),
			)
		);
	}

	/**
	 * Dismiss a single notification.
	 *
	 * @since 3.6.6
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function dismiss_notification( $request ) {
		$id           = $request->get_param( 'id' );
		$notification = EDD()->notifications->get( $id );

		// Verify the notification exists.
		if ( empty( $notification ) ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'rest_notification_not_found',
					'message' => __( 'Notification not found.', 'easy-digital-downloads' ),
				),
				404
			);
		}

		// Construct a model to check the dismissed state.
		$model = new Notification( (array) $notification );

		// Do not allow dismissing an already-dismissed notification.
		if ( $model->dismissed ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'rest_notification_already_dismissed',
					'message' => __( 'Notification is already dismissed.', 'easy-digital-downloads' ),
				),
				400
			);
		}

		$result = EDD()->notifications->update(
			$id,
			array( 'dismissed' => 1 )
		);

		if ( ! $result ) {
			return new \WP_REST_Response(
				array(
					'code'    => 'rest_notification_dismiss_failed',
					'message' => __( 'Failed to dismiss notification.', 'easy-digital-downloads' ),
				),
				500
			);
		}

		wp_cache_delete( 'edd_active_notification_count', 'edd_notifications' );

		return new \WP_REST_Response( null, 204 );
	}
}
