<?php
/**
 * Checkout Templates REST Controller
 *
 * @package     EDD\REST\Controllers
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\REST\Controllers;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Checkout\Templates\RemoteAPI;
use EDD\Checkout\Templates\License;

/**
 * CheckoutTemplates Controller class
 *
 * Handles REST API request processing for browsing checkout templates.
 *
 * @since 3.7.0
 */
class CheckoutTemplates {

	/**
	 * RemoteAPI instance.
	 *
	 * @since 3.7.0
	 * @var RemoteAPI|null
	 */
	private $remote_api = null;

	/**
	 * Get all templates.
	 *
	 * @since 3.7.0
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with templates list.
	 */
	public function get_templates( \WP_REST_Request $_request ) {
		$data              = $this->get_remote_api()->get_templates();
		$templates         = $data['templates'] ?? array();
		$available_tags    = $this->normalize_tags( $data['available_tags'] ?? array() );
		$available_editors = $data['available_editors'] ?? array();

		/**
		 * Filter the checkout templates list.
		 *
		 * @since 3.7.0
		 * @param array $templates Array of template data.
		 */
		$templates = apply_filters( 'edd_checkout_templates', $templates );

		return new \WP_REST_Response(
			array(
				'templates'         => $templates,
				'available_tags'    => $available_tags,
				'available_editors' => $available_editors,
				'can_import'        => License::can_import(),
				'license_status'    => License::get_status(),
			)
		);
	}

	/**
	 * Get translated labels for known template tags.
	 *
	 * @since 3.7.0
	 * @return array<string, string> Map of tag slug to translated label.
	 */
	private function get_tag_labels(): array {
		return array(
			'modern'       => __( 'Modern', 'easy-digital-downloads' ),
			'minimal'      => __( 'Minimal', 'easy-digital-downloads' ),
			'clean'        => __( 'Clean', 'easy-digital-downloads' ),
			'bold'         => __( 'Bold', 'easy-digital-downloads' ),
			'compact'      => __( 'Compact', 'easy-digital-downloads' ),
			'elegant'      => __( 'Elegant', 'easy-digital-downloads' ),
			'vibrant'      => __( 'Vibrant', 'easy-digital-downloads' ),
			'premium'      => __( 'Premium', 'easy-digital-downloads' ),
			'split-layout' => __( 'Split Layout', 'easy-digital-downloads' ),
			'single-page'  => __( 'Single Page', 'easy-digital-downloads' ),
			'dark-sidebar' => __( 'Dark Sidebar', 'easy-digital-downloads' ),
			'serif'        => __( 'Serif', 'easy-digital-downloads' ),
			'editorial'    => __( 'Editorial', 'easy-digital-downloads' ),
			'gradient'     => __( 'Gradient', 'easy-digital-downloads' ),
			'fintech'      => __( 'Fintech', 'easy-digital-downloads' ),
			'saas'         => __( 'SaaS', 'easy-digital-downloads' ),
		);
	}

	/**
	 * Normalize tag names with translated labels.
	 *
	 * @since 3.7.0
	 * @param array $tags Raw available_tags from the API.
	 * @return array Normalized tags with translated names.
	 */
	private function normalize_tags( array $tags ): array {
		/**
		 * Filter the checkout template tag labels.
		 *
		 * @since 3.7.0
		 * @param array $labels Map of tag slug to translated label.
		 */
		$labels = apply_filters( 'edd_checkout_template_tag_labels', $this->get_tag_labels() );

		foreach ( $tags as &$tag ) {
			if ( empty( $tag['id'] ) || 'all' === $tag['id'] ) {
				if ( 'all' === ( $tag['id'] ?? '' ) ) {
					$tag['name'] = __( 'All Templates', 'easy-digital-downloads' );
				}
				continue;
			}

			$tag['name'] = $labels[ $tag['id'] ] ?? ucwords( str_replace( '-', ' ', $tag['id'] ) );
		}
		unset( $tag );

		return $tags;
	}

	/**
	 * Get the RemoteAPI instance.
	 *
	 * Lazily instantiates the RemoteAPI to avoid creating it multiple times.
	 *
	 * @since 3.7.0
	 * @return RemoteAPI The RemoteAPI instance.
	 */
	private function get_remote_api(): RemoteAPI {
		if ( null === $this->remote_api ) {
			$this->remote_api = new RemoteAPI();
		}
		return $this->remote_api;
	}
}
