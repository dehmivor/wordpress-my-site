<?php
/**
 * Element Transfer.
 *
 * Self-contained import engine for a cross-domain element copy: collision-safe
 * element-id regeneration plus the Elementor `on_import` media/repeater glue,
 * instrumented to collect warnings instead of silently dropping content.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Helpers;

use Elementor\Controls_Stack;
use PremiumAddons\Includes\Abilities\Helpers as Ability_Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Class Element_Transfer.
 *
 * TODO(cross-domain-copy): consolidate engine — this glue is duplicated in
 * includes/extras/cross-copy-paste.php and includes/templates/sources/base.php.
 * Both delegate here once the copy/paste abilities are verified end to end.
 *
 * @since 4.11.76
 */
class Element_Transfer {

	/**
	 * Regenerate every element id in an imported tree.
	 *
	 * Elementor's own Utils::generate_random_string() carries no collision
	 * guarantee, so seed the generator from the Destination's ids and feed every
	 * id it mints back into that set — a regenerated id can collide neither with
	 * the Destination tree nor with the imported subtree.
	 *
	 * @param array $content      The imported element tree.
	 * @param array $existing_ids Element ids already used in the Destination document.
	 *
	 * @return array The tree with fresh ids.
	 */
	public static function regenerate_ids( $content, $existing_ids = array() ) {

		return \Elementor\Plugin::$instance->db->iterate_data(
			$content,
			function ( $element ) use ( &$existing_ids ) {

				$id             = Ability_Helpers::generate_element_id( $existing_ids );
				$existing_ids[] = $id;
				$element['id']  = $id;

				return $element;
			}
		);
	}

	/**
	 * Run the Elementor import pass over an element tree.
	 *
	 * Walks the tree bottom-up, instantiates each element and lets Elementor's
	 * per-element/per-control `on_import` hooks sideload media and remap whatever
	 * core knows how to remap. An element whose type is not registered is kept
	 * as-is with a warning rather than dropped.
	 *
	 * @param array $content  The element tree.
	 * @param array $warnings Collected warnings, by reference.
	 *
	 * @return array The processed tree.
	 */
	public static function process_import( $content, &$warnings ) {

		return \Elementor\Plugin::$instance->db->iterate_data(
			$content,
			function ( $element_data ) use ( &$warnings ) {

				$element = \Elementor\Plugin::$instance->elements_manager->create_element_instance( $element_data );

				// The widget's plugin is deactivated or the element type is not
				// registered here. Keep the node so reactivating brings the
				// content back — the sibling engines drop it.
				if ( ! $element ) {

					$warnings[] = array(
						'type'   => 'missing_widget',
						'detail' => ! empty( $element_data['widgetType'] ) ? $element_data['widgetType'] : $element_data['elType'],
					);

					return $element_data;
				}

				return self::import_element( $element, $warnings );
			}
		);
	}

	/**
	 * Run the import pass over one element.
	 *
	 * @param Controls_Stack $element  The element instance.
	 * @param array          $warnings Collected warnings, by reference.
	 *
	 * @return array The processed element data.
	 */
	private static function import_element( Controls_Stack $element, &$warnings ) {

		$element_data = $element->get_data();
		$method       = 'on_import';

		if ( method_exists( $element, $method ) ) {
			$element_data = $element->{$method}( $element_data );
		}

		foreach ( $element->get_controls() as $control ) {

			$control_class = \Elementor\Plugin::$instance->controls_manager->get_control( $control['type'] );

			// The control's plugin is deactivated. Skip the control, not the rest
			// of the stack.
			if ( ! $control_class ) {
				continue;
			}

			// A stored query filter points at Source post/term/user ids that mean
			// nothing here, so reset it.
			if ( in_array( $control['name'], self::query_filter_controls(), true ) ) {
				$element_data['settings'][ $control['name'] ] = '';
			}

			if ( ! method_exists( $control_class, $method ) ) {
				continue;
			}

			if ( 'repeater' === $control['type'] ) {
				$element_data['settings'][ $control['name'] ] = self::import_repeater( $element->get_settings( $control['name'] ), $control, $warnings );
			} elseif ( 'media' !== $control['type'] && 'hedia' !== $control['type'] ) {
				$element_data['settings'][ $control['name'] ] = $control_class->{$method}( $element->get_settings( $control['name'] ), $control );
			} elseif ( ! empty( $element_data['settings'][ $control['name'] ]['url'] ) ) {
				$element_data['settings'][ $control['name'] ] = self::import_media( $element->get_settings( $control['name'] ), $warnings );
			}
		}

		return $element_data;
	}

	/**
	 * Sideload one media setting into the Destination media library.
	 *
	 * Downloading is delegated to Elementor's own import-images instance, which
	 * fetches with a safe remote request.
	 *
	 * @param array $settings The media control value.
	 * @param array $warnings Collected warnings, by reference.
	 *
	 * @return array The media control value, original when the sideload failed.
	 */
	private static function import_media( $settings, &$warnings ) {

		if ( empty( $settings['url'] ) || false !== strpos( $settings['url'], 'placeholder' ) ) {
			return $settings;
		}

		// Import_Images::import() returns false when the download or the
		// attachment insert fails.
		$imported = \Elementor\Plugin::$instance->templates_manager->get_import_images_instance()->import( $settings );

		if ( empty( $imported ) ) {

			$warnings[] = array(
				'type'   => 'failed_media',
				'detail' => $settings['url'],
			);

			return $settings;
		}

		return $imported;
	}

	/**
	 * Run the import pass over the rows of a repeater control.
	 *
	 * @param array $settings     The repeater rows.
	 * @param array $control_data The repeater control definition.
	 * @param array $warnings     Collected warnings, by reference.
	 *
	 * @return array The processed rows.
	 */
	private static function import_repeater( $settings, $control_data, &$warnings ) {

		if ( empty( $settings ) || empty( $control_data['fields'] ) ) {
			return $settings;
		}

		$method = 'on_import';

		foreach ( $settings as &$item ) {
			foreach ( $control_data['fields'] as $field ) {

				if ( empty( $field['name'] ) || empty( $item[ $field['name'] ] ) ) {
					continue;
				}

				$control_obj = \Elementor\Plugin::$instance->controls_manager->get_control( $field['type'] );

				if ( ! $control_obj || ! method_exists( $control_obj, $method ) ) {
					continue;
				}

				if ( 'media' !== $field['type'] && 'hedia' !== $field['type'] ) {
					$item[ $field['name'] ] = $control_obj->{$method}( $item[ $field['name'] ], $field );
				} elseif ( ! empty( $item[ $field['name'] ]['url'] ) ) {
					$item[ $field['name'] ] = self::import_media( $item[ $field['name'] ], $warnings );
				}
			}
		}

		return $settings;
	}

	/**
	 * Query-filter control names reset on import.
	 *
	 * @return array
	 */
	private static function query_filter_controls() {

		return array(
			'premium_blog_users',
			'tax_category_post_filter',
			'tax_post_tag_post_filter',
			'premium_blog_posts_exclude',
			'tax_product_cat_product_filter',
			'tax_product_tag_product_filter',
			'custom_posts_filter',
			'featured_post_default',
			'featured_post',
		);
	}
}
