<?php
/**
 * Class: Module
 * Name: Woocommerce
 * Slug: premium-woocommerce
 * PA WooCommerce Module.
 *
 * @package PA
 */

namespace PremiumAddons\Modules\Woocommerce;

use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Includes\Module_Base;
use PremiumAddons\Modules\Woocommerce\Modules\Products_Module;
use PremiumAddons\Modules\Woocommerce\Modules\CTA_Module;
use PremiumAddons\Modules\Woocommerce\Modules\categories_Module;
use PremiumAddons\Modules\Woocommerce\Modules\Mini_Cart_Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // If this file is called directly, abort.
}

/**
 * Class Module.
 */
class Module extends Module_Base {

	/**
	 * Class object
	 *
	 * @var instance
	 */
	private static $instance = null;

	/**
	 * Module should load or not.
	 *
	 * @since 4.7.0
	 * @access public
	 *
	 * @return bool true|false.
	 */
	public static function is_enable() {
		return true;
	}

	/**
	 * Get Module Name.
	 *
	 * @since 4.7.0
	 * @access public
	 *
	 * @return string Module name.
	 */
	public function get_name() {
		return 'woocommerce';
	}

	/**
	 * Get Widgets.
	 *
	 * @since 4.7.0
	 * @access public
	 *
	 * @return array Widgets.
	 */
	public function get_widgets() {
		return array(
			'Woo_Products',
			'Woo_Categories',
			'Mini_Cart',
			'Woo_CTA',
		);
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();

		// Load individual widget modules.
		$this->load_modules();
	}

	/**
	 * Override Woocommerce default mini cart template.
	 *
	 * @param string $template_name Template name.
	 * @param string $template_path Template path. (default: '').
	 * @param string $default_path  Default path. (default: '').
	 * @see https://woocommerce.github.io/code-reference/files/woocommerce-includes-wc-core-functions.html#source-view.381
	 * @return string
	 */
	public function pa_locate_custom_mini_cart_template( $template, $template_name, $template_path ) {

		if ( 'cart/mini-cart.php' !== $template_name ) {
			return $template;
		}

		$plugin_path = plugin_dir_path( __DIR__ ) . 'woocommerce/templates/wc-templates/';

		if ( file_exists( $plugin_path . 'mini-cart.php' ) ) {
			$template = $plugin_path . 'mini-cart.php';
		}

		return $template;
	}


	/**
	 * Load individual widget modules.
	 *
	 * @since 4.7.0
	 * @access public
	 */
	public function load_modules() {

		$enabled_elements = Admin_Helper::get_enabled_elements();

		if ( isset( $enabled_elements['woo-products'] ) && $enabled_elements['woo-products'] ) {
			Products_Module::get_instance();
		}

		if ( isset( $enabled_elements['woo-cta'] ) && $enabled_elements['woo-cta'] ) {
			CTA_Module::get_instance();
		}

		if ( isset( $enabled_elements['woo-categories'] ) && $enabled_elements['woo-categories'] ) {
			Categories_Module::get_instance();
		}

		$mc_custom_temp_enabled = isset( $enabled_elements['pa_mc_temp'] ) ? $enabled_elements['pa_mc_temp'] : false;

		if ( isset( $enabled_elements['mini-cart'] ) && $enabled_elements['mini-cart'] ) {

			if ( $mc_custom_temp_enabled ) {
				add_filter( 'woocommerce_locate_template', array( $this, 'pa_locate_custom_mini_cart_template' ), 10, 3 );
			}

			Mini_Cart_Module::get_instance();
		}
	}

	/**
	 * Get Current Product Swap Image.
	 *
	 * @since 3.4.0
	 * @access public
	 *
	 * @param string $size image size.
	 */
	public static function get_current_product_swap_image( $size ) {

		global $product;

		$attachment_ids = $product->get_gallery_image_ids();

		if ( $attachment_ids ) {

			$image_size = apply_filters( 'single_product_archive_thumbnail_size', $size );

			echo wp_kses_post( apply_filters( 'pa_woo_product_swap_image', wp_get_attachment_image( reset( $attachment_ids ), $image_size, false, array( 'class' => 'premium-woo-product__on_hover' ) ) ) );
		}
	}

	/**
	 * Get Current Product Images
	 *
	 * Gets current product images
	 *
	 * @since 3.4.0
	 * @access public
	 *
	 * @param string $size image size.
	 */
	public static function get_current_product_linked_images( $size ) {

		global $product;

		$attachment_ids = $product->get_gallery_image_ids();

		if ( $attachment_ids ) {

			$image_size = apply_filters( 'single_product_archive_thumbnail_size', $size );

			foreach ( $attachment_ids as $index => $id ) {
				if ( $index > 2 ) {
					break;
				}

				woocommerce_template_loop_product_link_open();

				echo wp_kses_post( apply_filters( 'pa_woo_product_gallery_image', wp_get_attachment_image( $id, $image_size, false, array( 'class' => 'premium-woo-product__gallery_image' ) ) ) );

				woocommerce_template_loop_product_link_close(); // closes product anchor tag.

			}
		}
	}

	/**
	 * Get Product Short Description
	 *
	 * @since 3.4.0
	 * @access public
	 *
	 * @param integer $length excerpt length.
	 */
	public static function get_product_excerpt( $length ) {

		if ( has_excerpt() ) {

			$excerpt = trim( get_the_excerpt() );

			if ( ! empty( $length ) ) {

				$words = explode( ' ', $excerpt, $length + 1 );

				if ( count( $words ) > $length ) {

					array_pop( $words );

					$words[] = '…';

				}

				$excerpt = implode( ' ', $words );

			}

			echo '<div class="premium-woo-product-desc">';
				echo wp_kses_post( $excerpt );
			echo '</div>';
		}
	}

	/**
	 * Get Current Product Category
	 *
	 * @since 3.4.0
	 * @access public
	 */
	public static function get_current_product_category() {
		if ( apply_filters( 'pa_woo_product_parent_category', true ) ) :
			?>
			<span class="premium-woo-product-category">
				<?php
					global $product;
					$product_categories = function_exists( 'wc_get_product_category_list' ) ? wc_get_product_category_list( get_the_ID(), '&', '', '' ) : $product->get_categories( '&', '', '' );

					$product_categories = wp_strip_all_tags( $product_categories );

				if ( $product_categories ) {
					list( $parent_cat ) = explode( '&', $product_categories );

					echo esc_html( $parent_cat );
				}
				?>
			</span>
			<?php
		endif;
	}

	/**
	 * Get Current Product Images
	 *
	 * Gets current product images
	 *
	 * @since 3.4.0
	 * @access public
	 *
	 * @param string $size image size.
	 */
	public static function get_current_product_gallery_images( $size ) {

		global $product;

		$attachment_ids = $product->get_gallery_image_ids();

		if ( $attachment_ids ) {

			$image_size = apply_filters( 'single_product_archive_thumbnail_size', $size );

			foreach ( $attachment_ids as $index => $id ) {
				if ( $index > 2 ) {
					break;
				}

				echo wp_kses_post( apply_filters( 'pa_woo_product_gallery_image', wp_get_attachment_image( $id, $image_size, false, array( 'class' => 'premium-woo-product__gallery_image' ) ) ) );
			}
		}
	}

	/**
	 * Instance
	 *
	 * @return object self::$instance
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
