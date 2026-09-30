<?php
/**
 * Import Elements.
 *
 * Fetches a packaged copy from the source site server-to-server and writes it
 * into a page on this site, re-creating its images in this site's media library.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Transfer;

use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Includes\Abilities\Helpers;
use PremiumAddons\Includes\Helpers\Element_Transfer;
use PremiumAddons\Includes\Helpers\Template_Bundle;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 */
class Import_Elements implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'import-elements';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Import Copied Elements', 'premium-addons-for-elementor' ),
			'description'         => __( 'Adds elements copied from another site into a page on this site.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-copy-paste',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'transfer_url', 'target_page_id' ),
				'properties'           => array(
					'transfer_url'   => array(
						'type'        => 'string',
						'description' => __( 'The transfer URL returned by premium-addons/export-elements on the source site. It is single-use and expires, so import it within the window reported by expires_at.', 'premium-addons-for-elementor' ),
					),
					'target_page_id' => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the Elementor page, post or template on this site to add the copied elements to. Existing content on that page is never removed.', 'premium-addons-for-elementor' ),
					),
					'position'       => array(
						'type'        => 'string',
						'enum'        => array( 'append', 'index' ),
						'default'     => 'append',
						'description' => __( 'Where the copied elements land: append puts them at the end of the page, index puts them at a specific position.', 'premium-addons-for-elementor' ),
					),
					'index'          => array(
						'type'        => 'integer',
						'minimum'     => 0,
						'description' => __( 'Required when position is index. Zero-based position among the page\'s top-level elements; the first copied element lands here and the rest follow in order. An index past the end appends.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'What landed on the target page. The import always completes — anything that could not be reproduced is reported as a warning, never dropped.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'inserted_element_ids' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => __( 'Ids of the newly inserted top-level elements. Every id in the copied content is regenerated, so these differ from the source ids.', 'premium-addons-for-elementor' ),
					),
					'edit_url'             => array(
						'type'        => 'string',
						'description' => __( 'The Elementor editor URL for the target page.', 'premium-addons-for-elementor' ),
					),
					'templates'            => array(
						'type'        => 'array',
						'description' => __( 'The Elementor templates the copied content renders. A template that already existed here was reused as it is; the rest were created.', 'premium-addons-for-elementor' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'title'   => array(
									'type'        => 'string',
									'description' => __( 'The template title.', 'premium-addons-for-elementor' ),
								),
								'post_id' => array(
									'type'        => 'integer',
									'description' => __( 'The ID of the template on this site.', 'premium-addons-for-elementor' ),
								),
								'action'  => array(
									'type'        => 'string',
									'enum'        => array( 'created', 'reused' ),
									'description' => __( 'created: the template was re-created here. reused: a template with the same title already existed and was left untouched.', 'premium-addons-for-elementor' ),
								),
							),
						),
					),
					'warnings'             => array(
						'type'        => 'array',
						'description' => __( 'Problems that did not stop the import.', 'premium-addons-for-elementor' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'type'   => array(
									'type'        => 'string',
									'enum'        => array( 'failed_media', 'missing_widget', 'pro_gated', 'template_failed', 'template_depth_exceeded' ),
									'description' => __( 'failed_media: an image could not be re-created here and the original reference was kept. missing_widget: the widget is not installed or is deactivated here, so the element imported but will not render. pro_gated: the widget needs a Premium Addons Pro license to render. template_failed: an Elementor template could not be re-created here, so whatever referenced it renders empty. template_depth_exceeded: templates nested deeper than the bundling limit were not packaged on the source site.', 'premium-addons-for-elementor' ),
								),
								'detail' => array(
									'type'        => 'string',
									'description' => __( 'The image URL for failed_media, the template title for the template warnings, the widget type key otherwise.', 'premium-addons-for-elementor' ),
								),
							),
						),
					),
				),
			),
			'permission_callback' => function ( $input = null ) {

				$page_id = ! empty( $input['target_page_id'] ) ? absint( $input['target_page_id'] ) : 0;

				if ( ! $page_id || ! get_post( $page_id ) ) {
					return false;
				}

				// Writes content AND sideloads media, so both caps are required.
				return Admin_Helper::check_user_can( 'edit_post', $page_id ) && Admin_Helper::check_user_can( 'upload_files' );
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

		$transfer_url = isset( $input['transfer_url'] ) ? trim( $input['transfer_url'] ) : '';

		if ( '' === $transfer_url ) {
			return new \WP_Error(
				'premium_addons_missing_transfer_url',
				__( 'A transfer URL from premium-addons/export-elements is required.', 'premium-addons-for-elementor' )
			);
		}

		$position = ! empty( $input['position'] ) ? $input['position'] : 'append';
		$index    = null;

		if ( 'index' === $position ) {

			if ( ! isset( $input['index'] ) ) {
				return new \WP_Error(
					'premium_addons_missing_index',
					__( 'An index is required when position is index.', 'premium-addons-for-elementor' )
				);
			}

			$index = (int) $input['index'];

			if ( $index < 0 ) {
				return new \WP_Error(
					'premium_addons_invalid_index',
					__( 'The index must be zero or greater.', 'premium-addons-for-elementor' )
				);
			}
		}

		$target_page_id = ! empty( $input['target_page_id'] ) ? absint( $input['target_page_id'] ) : 0;
		$resolved       = Helpers::resolve_document( $target_page_id );

		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		list( $document, $elements ) = $resolved;

		// Consuming fetch — a transfer URL cannot be replayed once it has landed.
		$payload = Helpers::fetch_transfer_payload( $transfer_url, true );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		// Warnings the source raised while packaging: the export hands back a
		// reference rather than a report, so they surface here.
		$warnings = ! empty( $payload['template_warnings'] ) && is_array( $payload['template_warnings'] )
			? $payload['template_warnings']
			: array();

		// Templates first: the copied elements reference them by title, so a
		// failure has to be visible next to the content that needs it. The content
		// still lands either way.
		$bundle    = ! empty( $payload['templates'] ) && is_array( $payload['templates'] ) ? $payload['templates'] : array();
		$installed = Template_Bundle::install( $bundle, $warnings );

		$content = Element_Transfer::regenerate_ids( $payload['content'], Helpers::collect_element_ids( $elements ) );
		$content = Element_Transfer::process_import( $content, $warnings );
		$content = Template_Bundle::rewrite_template_ids( $content, $installed['id_map'] );

		$inserted_element_ids = array();

		foreach ( $content as $node ) {

			$found    = false;
			$elements = Helpers::insert_element( $elements, '', $index, $node, $found );

			$inserted_element_ids[] = $node['id'];

			// Keep the copied elements in their exported order.
			if ( null !== $index ) {
				++$index;
			}
		}

		$saved = Helpers::save_document( $document, $elements );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$availability = Helpers::scan_widget_availability( Helpers::collect_widget_types( $content ) );

		foreach ( $availability['missing'] as $widget_key ) {
			$warnings[] = array(
				'type'   => 'missing_widget',
				'detail' => $widget_key,
			);
		}

		foreach ( $availability['pro_gated'] as $widget_key ) {
			$warnings[] = array(
				'type'   => 'pro_gated',
				'detail' => $widget_key,
			);
		}

		return array(
			'inserted_element_ids' => $inserted_element_ids,
			'edit_url'             => $document->get_edit_url(),
			'templates'            => $installed['report'],
			'warnings'             => $this->dedupe_warnings( $warnings ),
		);
	}

	/**
	 * Drop repeated warnings.
	 *
	 * A widget missing from three elements, or the same image failing twice, is
	 * one thing to fix — the element-level pass and the post-import scan also
	 * report the same missing widget.
	 *
	 * @param array $warnings The collected warnings.
	 *
	 * @return array
	 */
	private function dedupe_warnings( $warnings ) {

		$seen   = array();
		$unique = array();

		foreach ( $warnings as $warning ) {

			$key = $warning['type'] . '|' . $warning['detail'];

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$unique[]     = $warning;
		}

		return $unique;
	}
}
