<?php
/**
 * Insert Premium Template.
 *
 * Fetches a section from the remote Premium Templates catalog and inserts it
 * into a page on this site, re-creating its images in the media library.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Templates;

use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Includes\Abilities\Helpers;
use PremiumAddons\Includes\Helpers\Element_Transfer;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 */
class Insert_Premium_Template implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'insert-premium-template';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Insert Premium Template', 'premium-addons-for-elementor' ),
			'description'         => __( 'Inserts a ready-made Premium Templates section into an Elementor page.', 'premium-addons-for-elementor' )
				. "\n\n"
				. __( 'Call premium-addons/list-premium-templates first — it caches the catalog metadata this ability needs. After inserting, customize the section\'s text and images with the element-editing abilities instead of rebuilding it.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-templates',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'template_id', 'post_id' ),
				'properties'           => array(
					'template_id' => array(
						'type'        => 'integer',
						'description' => __( 'The template_id from premium-addons/list-premium-templates.', 'premium-addons-for-elementor' ),
					),
					'post_id'     => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the Elementor page, post or template to insert into. Existing content is never removed.', 'premium-addons-for-elementor' ),
					),
					'position'    => array(
						'type'        => 'integer',
						'minimum'     => 0,
						'description' => __( 'Zero-based position among the page\'s top-level elements. Omit to append at the end.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'What landed on the page. Anything that could not be reproduced is reported as a warning, never dropped.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'inserted_element_ids' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => __( 'Ids of the newly inserted top-level elements, ready for the element-editing abilities.', 'premium-addons-for-elementor' ),
					),
					'post_id'              => array(
						'type'        => 'integer',
						'description' => __( 'The page the section was inserted into.', 'premium-addons-for-elementor' ),
					),
					'edit_url'             => array(
						'type'        => 'string',
						'description' => __( 'The Elementor editor URL for the page.', 'premium-addons-for-elementor' ),
					),
					'templates'            => array(
						'type'        => 'array',
						'description' => __( 'The inner Elementor templates the section renders. One that already existed here was reused as it is; the rest were created.', 'premium-addons-for-elementor' ),
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
									'description' => __( 'created: the template was created here. reused: a template with the same title already existed and was left untouched.', 'premium-addons-for-elementor' ),
								),
							),
						),
					),
					'warnings'             => array(
						'type'        => 'array',
						'description' => __( 'Problems that did not stop the insert.', 'premium-addons-for-elementor' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'type'   => array(
									'type'        => 'string',
									'enum'        => array( 'notice', 'missing_widget', 'failed_media', 'pro_gated', 'template_failed' ),
									'description' => __( 'notice: a catalog requirement note to relay to the user. missing_widget: the widget is not installed or is deactivated here, so the element inserted but will not render. failed_media: an image could not be re-created here and the original reference was kept. pro_gated: the widget needs a Premium Addons Pro license to render. template_failed: an inner template could not be created here, so whatever renders it stays empty.', 'premium-addons-for-elementor' ),
								),
								'detail' => array(
									'type'        => 'string',
									'description' => __( 'The notice text, the image URL for failed_media, the template title for template_failed, the widget type key otherwise.', 'premium-addons-for-elementor' ),
								),
							),
						),
					),
				),
			),
			'permission_callback' => function ( $input = null ) {
				// Writes content AND sideloads media, so both caps are required.
				return Helpers::can_edit_input_post( $input ) && Admin_Helper::check_user_can( 'upload_files' );
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

		if ( ! Admin_Helper::check_premium_templates() ) {
			return new \WP_Error(
				'premium_addons_templates_disabled',
				__( 'Premium Templates is disabled in the Premium Addons dashboard. Enable it under Global Features to use this ability.', 'premium-addons-for-elementor' )
			);
		}

		$template_id = isset( $input['template_id'] ) ? absint( $input['template_id'] ) : 0;

		if ( ! $template_id ) {
			return new \WP_Error(
				'premium_addons_missing_template_id',
				__( 'A template_id from premium-addons/list-premium-templates is required.', 'premium-addons-for-elementor' )
			);
		}

		$post_id  = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
		$resolved = Helpers::resolve_document( $post_id );

		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		list( $document, $elements ) = $resolved;

		$meta = Templates_Api::get_meta( $template_id );

		if ( null === $meta ) {
			return new \WP_Error(
				'premium_addons_template_data_unavailable',
				__( 'No catalog data is cached for this template yet. Call premium-addons/list-premium-templates first, then retry.', 'premium-addons-for-elementor' )
			);
		}

		$warnings = array();
		$blocked  = $this->check_notices( $meta['notice'], $warnings );

		if ( is_wp_error( $blocked ) ) {
			return $blocked;
		}

		$body = Templates_Api::get_template( $template_id );

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		if ( isset( $body['exist'] ) && ! $body['exist'] ) {
			return new \WP_Error(
				'premium_addons_invalid_template_id',
				/* translators: %d: template id. */
				sprintf( __( 'No template %d exists in the Premium Templates catalog.', 'premium-addons-for-elementor' ), $template_id )
			);
		}

		// The server withholds a pro template's content when the site has no
		// valid Pro license.
		if ( ! empty( $body['is_pro'] ) && empty( $body['license'] ) && empty( $body['content'] ) ) {
			return new \WP_Error(
				'premium_addons_missing_pro_license',
				__( 'This template requires Premium Addons Pro.', 'premium-addons-for-elementor' )
			);
		}

		if ( empty( $body['content'] ) || ! is_array( $body['content'] ) ) {
			return new \WP_Error(
				'premium_addons_template_data_unavailable',
				__( 'The catalog returned no content for this template. Try again shortly.', 'premium-addons-for-elementor' )
			);
		}

		// Inner templates first: the section renders them by title, so they have
		// to exist before the page is viewed. A failure is a warning, not a block.
		$installed = $this->install_dependencies( $meta['dependencies'], $warnings );

		$content = Element_Transfer::regenerate_ids( $body['content'], Helpers::collect_element_ids( $elements ) );
		$content = Element_Transfer::process_import( $content, $warnings );

		$position             = isset( $input['position'] ) ? max( 0, (int) $input['position'] ) : null;
		$inserted_element_ids = array();

		foreach ( $content as $node ) {

			$found    = false;
			$elements = Helpers::insert_element( $elements, '', $position, $node, $found );

			$inserted_element_ids[] = $node['id'];

			// Keep the template's elements in their catalog order.
			if ( null !== $position ) {
				++$position;
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
			'post_id'              => $post_id,
			'edit_url'             => $document->get_edit_url(),
			'templates'            => $installed,
			'warnings'             => $this->dedupe_warnings( $warnings ),
		);
	}

	/**
	 * Enforce the catalog notice requirements.
	 *
	 * woocommerce and form name plugins the section's widgets render through,
	 * so a missing one is a hard block; anything else is a warning to relay.
	 *
	 * @param array $notices  Notice strings from the catalog.
	 * @param array $warnings Collected warnings, by reference.
	 *
	 * @return true|\WP_Error
	 */
	private function check_notices( $notices, &$warnings ) {

		foreach ( $notices as $notice ) {

			$requirement = strtolower( trim( (string) $notice ) );

			if ( 'woocommerce' === $requirement && ! class_exists( 'WooCommerce' ) ) {
				return new \WP_Error(
					'premium_addons_missing_plugin',
					__( 'This template needs WooCommerce, which is not active on this site. Activate it and retry.', 'premium-addons-for-elementor' )
				);
			}

			if ( 'form' === $requirement && ! class_exists( 'WPCF7_ContactForm' ) ) {
				return new \WP_Error(
					'premium_addons_missing_plugin',
					__( 'This template needs Contact Form 7, which is not active on this site. Activate it and retry.', 'premium-addons-for-elementor' )
				);
			}

			if ( ! in_array( $requirement, array( 'woocommerce', 'form' ), true ) ) {
				$warnings[] = array(
					'type'   => 'notice',
					'detail' => $requirement,
				);
			}
		}

		return true;
	}

	/**
	 * Create the inner templates the section renders.
	 *
	 * @param array $dependencies { template_id: title } map from the catalog.
	 * @param array $warnings     Collected warnings, by reference.
	 *
	 * @return array Report rows { title, post_id, action }.
	 */
	private function install_dependencies( $dependencies, &$warnings ) {

		$report = array();

		foreach ( $dependencies as $dep_id => $title ) {

			// The listing entity-encodes titles (&#8211; …) but widget settings
			// reference the template by its real title, so decode before matching.
			$title = trim( html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' ) );

			if ( '' === $title ) {

				$warnings[] = array(
					'type'   => 'template_failed',
					'detail' => (string) $dep_id,
				);

				continue;
			}

			$existing_id = $this->find_library_template( $title );

			if ( ! empty( $existing_id ) ) {

				$report[] = array(
					'title'   => $title,
					'post_id' => (int) $existing_id,
					'action'  => 'reused',
				);

				continue;
			}

			$created = $this->create_dependency( absint( $dep_id ), $title, $warnings );

			if ( $created ) {
				$report[] = array(
					'title'   => $title,
					'post_id' => $created,
					'action'  => 'created',
				);
			}
		}

		return $report;
	}

	/**
	 * Find a published library template by exact title.
	 *
	 * Helper_Functions::get_elementor_template_id() caches a miss in the object
	 * cache, which would keep answering "absent" after this ability creates the
	 * template — so look up uncached here.
	 *
	 * @param string $title The template title.
	 *
	 * @return int The library post id, 0 when none exists.
	 */
	private function find_library_template( $title ) {

		$query = new \WP_Query(
			array(
				'post_type'              => 'elementor_library',
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'title'                  => $title,
				'suppress_filters'       => true,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		return $query->have_posts() ? (int) $query->post->ID : 0;
	}

	/**
	 * Fetch one dependency template and save it as a library post.
	 *
	 * @param int    $dep_id   The catalog id of the dependency.
	 * @param string $title    The template title the section references.
	 * @param array  $warnings Collected warnings, by reference.
	 *
	 * @return int The new library post id, 0 on failure.
	 */
	private function create_dependency( $dep_id, $title, &$warnings ) {

		$body = Templates_Api::get_template( $dep_id );

		if ( is_wp_error( $body ) || empty( $body['content'] ) || ! is_array( $body['content'] ) ) {

			$warnings[] = array(
				'type'   => 'template_failed',
				'detail' => $title,
			);

			return 0;
		}

		$content = Element_Transfer::regenerate_ids( $body['content'] );
		$content = Element_Transfer::process_import( $content, $warnings );

		$library_id = Helpers::create_library_template( $title, 'section', $content );

		if ( is_wp_error( $library_id ) ) {

			$warnings[] = array(
				'type'   => 'template_failed',
				'detail' => $title,
			);

			return 0;
		}

		return $library_id;
	}

	/**
	 * Drop repeated warnings.
	 *
	 * The element-level pass and the post-insert scan report the same missing
	 * widget, and one unreachable image can fail across several elements.
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
