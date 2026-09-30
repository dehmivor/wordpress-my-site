<?php
/**
 * Template Bundle.
 *
 * Carries the Elementor templates a copied element subtree renders through with
 * it: finds every template reference in the tree, packages the referenced
 * template bodies on the Source, and re-creates the missing ones on the
 * Destination so the copy renders there the same as it did here.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Helpers;

use PremiumAddons\Includes\Helper_Functions;
use PremiumAddons\Includes\Abilities\Helpers as Ability_Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Class Template_Bundle.
 *
 * @since 4.11.76
 */
class Template_Bundle {

	/**
	 * How many levels of template-inside-template are followed.
	 */
	const DEFAULT_DEPTH = 3;

	/**
	 * Package every template the given tree references.
	 *
	 * PA template pickers store the template TITLE, which resolves to nothing on
	 * another site, so the body has to travel with the content. References are
	 * found by walking each element's real control stack rather than a hardcoded
	 * widget list — the Tooltips global addon injects a picker into every widget.
	 *
	 * @param array $content  The element tree.
	 * @param array $warnings Collected warnings, by reference.
	 *
	 * @return array List of { ref, source_id, title, template_type, element_count, content }.
	 */
	public static function collect( $content, &$warnings = array() ) {

		$bundle  = array();
		$visited = array();

		self::collect_into( $content, 1, '', $visited, $bundle, $warnings );

		return $bundle;
	}

	/**
	 * Re-create the bundled templates that are missing on this site.
	 *
	 * A template whose title already exists here is reused as it is — the copied
	 * content references it by that same title, so nothing needs rewriting.
	 *
	 * @param array $bundle   The bundled templates from the payload.
	 * @param array $warnings Collected warnings, by reference.
	 *
	 * @return array { report: array, id_map: array } — report rows plus the Source-to-Destination id map.
	 */
	public static function install( $bundle, &$warnings ) {

		$report = array();
		$id_map = array();

		// get_elementor_template_id() caches its lookup per title, so a template
		// created in this same run would still read back as missing. Track what
		// this run created and answer from here first.
		$created = array();

		foreach ( $bundle as $entry ) {

			if ( empty( $entry['title'] ) ) {
				continue;
			}

			$title   = (string) $entry['title'];
			$post_id = isset( $created[ $title ] ) ? $created[ $title ] : (int) Helper_Functions::get_elementor_template_id( $title );
			$action  = 'reused';

			if ( ! $post_id ) {

				$content = Element_Transfer::regenerate_ids( isset( $entry['content'] ) ? $entry['content'] : array() );
				$content = Element_Transfer::process_import( $content, $warnings );

				$post_id = Ability_Helpers::create_library_template(
					$title,
					! empty( $entry['template_type'] ) ? $entry['template_type'] : 'container',
					$content
				);

				if ( is_wp_error( $post_id ) ) {

					$warnings[] = array(
						'type'   => 'template_failed',
						'detail' => $title,
					);

					continue;
				}

				$created[ $title ] = $post_id;
				$action            = 'created';
			}

			// Only an id-based reference needs remapping; a title-based one points
			// at the reused or newly created template by name already.
			if ( ! empty( $entry['source_id'] ) && isset( $entry['ref'] ) && 'id' === $entry['ref'] ) {
				$id_map[ (int) $entry['source_id'] ] = $post_id;
			}

			$report[] = array(
				'title'   => $title,
				'post_id' => $post_id,
				'action'  => $action,
			);
		}

		return array(
			'report' => $report,
			'id_map' => $id_map,
		);
	}

	/**
	 * Point the id-based template references at their Destination posts.
	 *
	 * Elementor Pro's Template widget stores a numeric post id, which means a
	 * different template — or nothing — on this site.
	 *
	 * @param array $content The element tree.
	 * @param array $id_map  Source post id => Destination post id.
	 *
	 * @return array The tree with the ids remapped.
	 */
	public static function rewrite_template_ids( $content, $id_map ) {

		if ( empty( $id_map ) ) {
			return $content;
		}

		return \Elementor\Plugin::$instance->db->iterate_data(
			$content,
			function ( $element_data ) use ( $id_map ) {

				return self::map_template_refs(
					$element_data,
					function ( $kind, $value ) use ( $id_map ) {

						if ( 'id' !== $kind || ! isset( $id_map[ (int) $value ] ) ) {
							return null;
						}

						return $id_map[ (int) $value ];
					}
				);
			}
		);
	}

	/**
	 * Package the templates one tree references, then their own templates.
	 *
	 * @param array  $content  The element tree.
	 * @param int    $depth    Current nesting level, 1 for the copied content.
	 * @param string $label    What the tree is, for the depth warning.
	 * @param array  $visited  Resolved post ids already bundled, by reference.
	 * @param array  $bundle   The bundle being built, by reference.
	 * @param array  $warnings Collected warnings, by reference.
	 *
	 * @return void
	 */
	private static function collect_into( $content, $depth, $label, &$visited, &$bundle, &$warnings ) {

		$refs = self::find_refs( $content );

		if ( empty( $refs ) ) {
			return;
		}

		$max_depth = (int) apply_filters( 'pa_transfer_template_depth', self::DEFAULT_DEPTH );

		if ( $depth > $max_depth ) {

			$warnings[] = array(
				'type'   => 'template_depth_exceeded',
				'detail' => $label,
			);

			return;
		}

		foreach ( $refs as $ref ) {
			foreach ( self::ref_values( $ref['value'] ) as $value ) {

				$post_id = self::resolve_ref( $ref['kind'], $value );

				// A stale title or a deleted template has nothing to bundle.
				if ( ! $post_id || isset( $visited[ $post_id ] ) ) {
					continue;
				}

				$visited[ $post_id ] = true;

				$tree          = self::template_content( $post_id );
				$template_type = get_post_meta( $post_id, '_elementor_template_type', true );

				// The reference is resolved back on the Destination the way the
				// renderer resolves it, so a title reference travels as the exact
				// string the setting holds.
				$title = 'title' === $ref['kind'] ? (string) $value : get_the_title( $post_id );

				$bundle[] = array(
					'ref'           => $ref['kind'],
					'source_id'     => $post_id,
					'title'         => $title,
					'template_type' => $template_type ? $template_type : 'container',
					'element_count' => count( $tree ),
					'content'       => $tree,
				);

				self::collect_into( $tree, $depth + 1, $title, $visited, $bundle, $warnings );
			}
		}
	}

	/**
	 * Find every template reference in a tree.
	 *
	 * @param array $content The element tree.
	 *
	 * @return array List of { kind, value }.
	 */
	private static function find_refs( $content ) {

		$refs = array();

		\Elementor\Plugin::$instance->db->iterate_data(
			$content,
			function ( $element_data ) use ( &$refs ) {

				return self::map_template_refs(
					$element_data,
					function ( $kind, $value ) use ( &$refs ) {

						$refs[] = array(
							'kind'  => $kind,
							'value' => $value,
						);

						return null;
					}
				);
			}
		);

		return $refs;
	}

	/**
	 * Run a callback over one element's template-reference settings.
	 *
	 * The single traversal shared by the Source-side collect and the
	 * Destination-side id rewrite, so the two can never disagree on what counts
	 * as a reference. The callback returns a replacement value, or null to leave
	 * the setting alone.
	 *
	 * @param array    $element_data The element data.
	 * @param callable $callback     function( string $kind, mixed $value ): mixed|null.
	 *
	 * @return array The element data.
	 */
	private static function map_template_refs( $element_data, $callback ) {

		$element = \Elementor\Plugin::$instance->elements_manager->create_element_instance( $element_data );

		// The widget is deactivated or is not registered here, so its control
		// stack — and with it any picker — cannot be read.
		if ( ! $element || empty( $element_data['settings'] ) ) {
			return $element_data;
		}

		foreach ( $element->get_controls() as $name => $control ) {

			if ( 'repeater' === $control['type'] ) {

				if ( empty( $control['fields'] ) || empty( $element_data['settings'][ $name ] ) || ! is_array( $element_data['settings'][ $name ] ) ) {
					continue;
				}

				foreach ( $element_data['settings'][ $name ] as $index => $row ) {
					foreach ( $control['fields'] as $field ) {

						$field_name = isset( $field['name'] ) ? $field['name'] : '';
						$kind       = self::ref_kind( $field );

						if ( '' === $kind || empty( $row[ $field_name ] ) ) {
							continue;
						}

						$replacement = call_user_func( $callback, $kind, $row[ $field_name ] );

						if ( null !== $replacement ) {
							$element_data['settings'][ $name ][ $index ][ $field_name ] = $replacement;
						}
					}
				}

				continue;
			}

			$kind = self::ref_kind( $control );

			if ( '' === $kind || empty( $element_data['settings'][ $name ] ) ) {
				continue;
			}

			$replacement = call_user_func( $callback, $kind, $element_data['settings'][ $name ] );

			if ( null !== $replacement ) {
				$element_data['settings'][ $name ] = $replacement;
			}
		}

		return $element_data;
	}

	/**
	 * Whether a control holds a template reference, and in which shape.
	 *
	 * PA pickers declare `source => elementor_library` and their companion live
	 * "Create Template" controls carry live_temp_content in the name; Elementor
	 * Pro's picker declares the library_template autocomplete object. Nothing
	 * else is a reference — premium-vscroll's `template_id`, for one, is a CSS id.
	 *
	 * @param array $control A control or repeater-field definition.
	 *
	 * @return string 'title', 'id', or '' when the control holds no reference.
	 */
	private static function ref_kind( $control ) {

		if ( isset( $control['source'] ) && 'elementor_library' === $control['source'] ) {
			return 'title';
		}

		if ( isset( $control['name'] ) && false !== strpos( $control['name'], 'live_temp_content' ) ) {
			return 'title';
		}

		if ( isset( $control['autocomplete']['object'] ) && 'library_template' === $control['autocomplete']['object'] ) {
			return 'id';
		}

		return '';
	}

	/**
	 * Normalize a reference setting to a flat list of references.
	 *
	 * A `multiple` picker — the legacy premium_carousel_slider_content — stores an
	 * array of titles where the rest store one.
	 *
	 * @param mixed $value The setting value.
	 *
	 * @return array
	 */
	private static function ref_values( $value ) {

		if ( ! is_array( $value ) ) {
			return array( $value );
		}

		return array_filter( $value, 'is_scalar' );
	}

	/**
	 * Resolve a reference to a template post id on this site.
	 *
	 * @param string $kind  'title' or 'id'.
	 * @param mixed  $value The reference value.
	 *
	 * @return int The post id, 0 when it resolves to nothing.
	 */
	private static function resolve_ref( $kind, $value ) {

		if ( 'id' === $kind ) {
			return 'elementor_library' === get_post_type( (int) $value ) ? (int) $value : 0;
		}

		$title   = trim( (string) $value );
		$post_id = (int) Helper_Functions::get_elementor_template_id( $title );

		// Same fallback render_elementor_template() makes: a title stored with
		// HTML entities where the post holds the decoded characters.
		if ( ! $post_id ) {
			$post_id = (int) Helper_Functions::get_elementor_template_id( html_entity_decode( $title ) );
		}

		return $post_id;
	}

	/**
	 * Read a template's element tree.
	 *
	 * @param int $post_id The template post id.
	 *
	 * @return array The element tree, empty when the template holds none.
	 */
	private static function template_content( $post_id ) {

		$raw  = get_post_meta( $post_id, '_elementor_data', true );
		$tree = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : $raw;

		return is_array( $tree ) ? $tree : array();
	}
}
