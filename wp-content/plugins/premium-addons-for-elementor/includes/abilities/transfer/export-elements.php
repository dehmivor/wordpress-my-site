<?php
/**
 * Export Elements.
 *
 * Packages an element subtree — or a whole page's elements — for a copy to
 * another site and returns a short-lived signed transfer URL.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Transfer;

use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Includes\Abilities\Helpers;
use PremiumAddons\Includes\Helpers\Template_Bundle;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 */
class Export_Elements implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'export-elements';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Export Elements for Copy', 'premium-addons-for-elementor' ),
			'description'         => __( 'Packages elements from a page so they can be copied to another site.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-copy-paste',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'page_id' ),
				'properties'           => array(
					'page_id'     => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the Elementor page, post or template to export from.', 'premium-addons-for-elementor' ),
					),
					'element_ids' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => __( 'Element ids to export, in the order they should land on the destination. Each id is exported with everything nested inside it. Omit or leave empty to export the whole page. Every id must exist — one bad id fails the whole export. Passing a parent and one of its own children duplicates that child on import.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The signed transfer reference. The exported content itself is never returned — it stays on the source site until the destination fetches it.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'transfer_url' => array(
						'type'        => 'string',
						'description' => __( 'Signed, single-use URL to hand to premium-addons/import-elements or premium-addons/check-import-compatibility on the destination site.', 'premium-addons-for-elementor' ),
					),
					'expires_at'   => array(
						'type'        => 'integer',
						'description' => __( 'Unix timestamp the transfer URL stops working at.', 'premium-addons-for-elementor' ),
					),
					'summary'      => array(
						'type'        => 'object',
						'description' => __( 'What was packaged, so the selection can be sanity-checked without downloading the payload.', 'premium-addons-for-elementor' ),
						'properties'  => array(
							'element_count' => array(
								'type'        => 'integer',
								'description' => __( 'Number of top-level elements exported.', 'premium-addons-for-elementor' ),
							),
							'widget_keys'   => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'string' ),
								'description' => __( 'Distinct widget types found in the exported content, including the ones that only appear inside a bundled template.', 'premium-addons-for-elementor' ),
							),
							'templates'     => array(
								'type'        => 'array',
								'description' => __( 'Elementor templates the exported content renders, packaged with it. The destination re-creates the ones it does not already have.', 'premium-addons-for-elementor' ),
								'items'       => array(
									'type'       => 'object',
									'properties' => array(
										'title'         => array(
											'type'        => 'string',
											'description' => __( 'The template title.', 'premium-addons-for-elementor' ),
										),
										'template_type' => array(
											'type'        => 'string',
											'description' => __( 'The Elementor template type (container, section, page, …).', 'premium-addons-for-elementor' ),
										),
										'element_count' => array(
											'type'        => 'integer',
											'description' => __( 'Number of top-level elements in the template.', 'premium-addons-for-elementor' ),
										),
									),
								),
							),
						),
					),
				),
			),
			'permission_callback' => function ( $input = null ) {

				$page_id = ! empty( $input['page_id'] ) ? absint( $input['page_id'] ) : 0;

				if ( ! $page_id || ! get_post( $page_id ) ) {
					return false;
				}

				return Admin_Helper::check_user_can( 'edit_post', $page_id );
			},
			'meta'                => array(
				// Don't want Angie to see this.
				'show_in_rest' => false,
				'mcp'          => array( 'public' => true ),
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			),
		);
	}

	/**
	 * Execute the ability.
	 *
	 * @param array|null $input Ability input.
	 * @return array|\WP_Error
	 */
	public function execute( $input = null ) {

		$error = Helpers::guard_elementor();

		if ( $error ) {
			return $error;
		}

		$page_id  = ! empty( $input['page_id'] ) ? absint( $input['page_id'] ) : 0;
		$resolved = Helpers::resolve_document( $page_id );

		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		list( , $elements ) = $resolved;

		$element_ids = ! empty( $input['element_ids'] ) && is_array( $input['element_ids'] ) ? $input['element_ids'] : array();
		$content     = $elements;
		$scope       = 'page';

		if ( ! empty( $element_ids ) ) {

			$content = array();
			$scope   = 'selection';

			// All-or-nothing: a partial export would copy content the caller never
			// asked for and hide the mistake until it landed on the destination.
			foreach ( $element_ids as $element_id ) {

				$node = Helpers::find_element( $elements, (string) $element_id );

				if ( null === $node ) {
					return new \WP_Error(
						'premium_addons_invalid_element_id',
						sprintf(
							/* translators: 1: element id, 2: post ID. */
							__( 'No element with id %1$s exists on the page with ID %2$d. Nothing was exported.', 'premium-addons-for-elementor' ),
							$element_id,
							$page_id
						)
					);
				}

				$content[] = $node;
			}
		}

		if ( empty( $content ) ) {
			return new \WP_Error(
				'premium_addons_invalid_post_id',
				/* translators: %d: post ID. */
				sprintf( __( 'The page with ID %d has no Elementor elements to export.', 'premium-addons-for-elementor' ), $page_id )
			);
		}

		// PA widgets render their content from templates referenced by title, so the
		// referenced bodies travel with the copy — without them the elements land on
		// the destination pointing at nothing and render empty.
		$template_warnings = array();
		$templates         = Template_Bundle::collect( $content, $template_warnings );

		$widget_keys       = Helpers::collect_widget_types( $content );
		$template_manifest = array();

		foreach ( $templates as $template ) {

			$widget_keys = array_merge( $widget_keys, Helpers::collect_widget_types( $template['content'] ) );

			$template_manifest[] = array(
				'title'         => $template['title'],
				'template_type' => $template['template_type'],
				'element_count' => $template['element_count'],
			);
		}

		$manifest = array(
			'element_count' => count( $content ),
			'widget_keys'   => array_values( array_unique( $widget_keys ) ),
			'templates'     => $template_manifest,
		);

		$token = Transfer_Store::put(
			array(
				'pa_transfer_version' => Helpers::TRANSFER_VERSION,
				'source'              => array(
					'site_url'          => get_site_url(),
					'pafe_version'      => PREMIUM_ADDONS_VERSION,
					'elementor_version' => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '',
					'exported_at'       => time(),
				),
				'scope'               => $scope,
				'manifest'            => $manifest,
				'content'             => $content,
				'templates'           => $templates,
				// Raised on this side, reported on the destination — the export
				// returns a reference, not a result the caller can act on.
				'template_warnings'   => $template_warnings,
			)
		);

		if ( '' === $token ) {
			return new \WP_Error(
				'premium_addons_transfer_data_unavailable',
				__( 'The selected elements could not be packaged for transfer.', 'premium-addons-for-elementor' )
			);
		}

		$expires_at = time() + Transfer_Store::ttl();

		return array(
			'transfer_url' => add_query_arg(
				array(
					'token' => $token,
					'exp'   => $expires_at,
					'sig'   => Transfer_Signer::sign( $token, $expires_at ),
				),
				rest_url( Transfer_Controller::REST_NAMESPACE . Transfer_Controller::REST_ROUTE )
			),
			'expires_at'   => $expires_at,
			'summary'      => $manifest,
		);
	}
}
