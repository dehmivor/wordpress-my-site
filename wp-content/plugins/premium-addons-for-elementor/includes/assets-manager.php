<?php
/**
 * PA Assets Manager.
 */

namespace PremiumAddons\Includes;

use Elementor\Plugin;
use PremiumAddons\Includes\Helper_Functions;
use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Admin\Includes\Admin_Bar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PA Assets Manager Class.
 */
class Assets_Manager {

	/**
	 * Assets Key.
	 */
	const ASSETS_KEY = '_pa_widget_elements';

	/**
	 * Class Instance.
	 *
	 * @var object|null instance.
	 */
	private static $instance = null;

	/**
	 * Post Id.
	 * Option Id.
	 *
	 * @var string|null post_id.
	 */
	protected $post_id = null;

	/**
	 * Enabled Elements.
	 *
	 * @var array|null
	 */
	protected $enabled_elements = null;

	/**
	 * Integrations.
	 *
	 * @var array|null
	 */
	protected $integrations = null;

	/**
	 * Class Constructor.
	 */
	public function __construct( $enabled_elements, $integrations ) {

		$this->enabled_elements = $enabled_elements;
		$this->integrations     = $integrations;

		$this->register_hooks();
	}

	/**
	 * Register Hooks.
	 *
	 * @access protected
	 * @since 4.6.1
	 */
	protected function register_hooks() {

		$is_dynamic_assets_enabled = $this->enabled_elements['premium-assets-generator'];

		if ( $is_dynamic_assets_enabled ) {

			// Register AJAX Hooks for regenerate assets.
			add_action( 'wp_ajax_pa_clear_cached_assets', array( $this, 'pa_clear_cached_assets' ) );

			add_action( 'elementor/editor/after_save', array( $this, 'handle_post_save' ), 10, 2 );

			// Elementor data written outside the editor: WPML/Polylang translations, WP-CLI, importers.
			add_action( 'updated_post_meta', array( $this, 'maybe_invalidate_assets' ), 10, 3 );
			add_action( 'added_post_meta', array( $this, 'maybe_invalidate_assets' ), 10, 3 );

			add_action( 'elementor/theme/register_locations', array( $this, 'get_asset_per_location' ), 20 );
			add_filter( 'elementor/files/file_name', array( $this, 'load_asset_per_file' ) );

			add_action( 'wp_enqueue_scripts', array( $this, 'handle_assets_load' ), 100 );

			// Delete cached options on post delete.
			add_action( 'wp_trash_post', array( $this, 'delete_trashed_post_data' ) );

			add_action( 'elementor/frontend/before_enqueue_styles', array( $this, 'before_enqueue_styles' ) );

			if ( $this->integrations['premium-wp-optimize-exclude'] ) {
				add_filter( 'wp-optimize-minify-default-exclusions', array( $this, 'exclude_pa_assets_from_wp_optimize' ) );
			}

			// Add admin bar tools for dynamic assets clear.
			$row_meta = Helper_Functions::is_hide_row_meta();

			if ( ! is_admin() && ! $row_meta ) {
				Admin_Bar::get_instance();
			}
		}

		add_action( 'elementor/frontend/after_register_styles', array( $this, 'register_frontend_styles' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( $this, 'register_frontend_scripts' ) );
	}

	/**
	 * Handle Assets Load.
	 *
	 * @access public
	 * @since 4.6.1
	 */
	public function handle_assets_load() {

		// Set current post id.
		$this->set_post_id();

		$this->enqueue_elements_handler();

		// This will run only on frontend.
		$this->get_pa_elements_list();

		// Handle generate and enqueue assets for editor.
		if ( $this->is_edit() ) {
			$this->enqueue_asset( null, 'edit' );
		}
	}

	/**
	 * Before Enqueue Styles.
	 *
	 * @access public
	 * @since 4.6.1
	 */
	public function before_enqueue_styles() {

		if ( $this->is_edit() ) {
			return false;
		}

		$this->post_id = get_the_ID();

		// Check for content changes and update assets data.
		$this->get_pa_elements_list( $this->post_id );

		$elements = get_post_meta( $this->post_id, self::ASSETS_KEY, true );

		if ( ! empty( $elements ) ) {
			$this->enqueue_asset( $this->post_id, $elements );
		}
	}

	public function get_asset_per_location( $instance ) {

		if ( is_admin() || ! ( class_exists( 'ElementorPro\Modules\ThemeBuilder\Module' ) ) ) {
			return false;
		}

		$locations = $instance->get_locations();

		foreach ( $locations as $location => $_unused ) {

			$documents_module = \ElementorPro\Modules\ThemeBuilder\Module::instance();

			if ( method_exists( $documents_module, 'get_locations_manager' ) && method_exists( $documents_module->get_locations_manager(), 'get_documents_for_location' ) ) {
				$documents = $documents_module->get_locations_manager()->get_documents_for_location( $location );
			} else {
				$documents = $documents_module->get_conditions_manager()->get_documents_for_location( $location );
			}

			foreach ( $documents as $document ) {
				if ( ! is_object( $document ) ) {
					continue;
				}

				$post_id = $document->get_post()->ID;

				$this->post_id = $post_id;
				$this->get_pa_elements_list( $this->post_id );
				$elements = get_post_meta( $this->post_id, self::ASSETS_KEY, true );

				if ( ! empty( $elements ) ) {
					$this->enqueue_asset( $this->post_id, $elements );
				}
			}
		}
	}

	public function load_asset_per_file( $file_name ) {

		if ( empty( $file_name ) ) {
			return $file_name;
		}

		$post_id = preg_replace( '/[^0-9]/', '', $file_name );

		if ( $post_id < 1 ) {
			return $file_name;
		}

		$this->post_id = $post_id;

		$this->get_pa_elements_list( $this->post_id );
		$elements = get_post_meta( $this->post_id, self::ASSETS_KEY, true );

		if ( ! empty( $elements ) ) {
			$this->enqueue_asset( $this->post_id, $elements );
		}

		return $file_name;
	}

	/**
	 * Enqueue Asset.
	 *
	 * Enqueue dynamic CSS/JS file for the current post.
	 *
	 * @access public
	 * @since 4.6.1
	 *
	 * @param int    $post_id post id.
	 * @param string $location edit|front.
	 */
	public function enqueue_asset( $post_id = null, $location = 'front' ) {

		$dynamic_asset_id = ( $post_id ? '-' . $post_id : '' );

		// If no CSS file found, then generate it.
		if ( ! $this->has_asset_file( $post_id, 'css' ) ) {
			$this->generate_new_asset_file( $post_id, $location, 'css' );
		}

		wp_enqueue_style(
			'pafe' . $dynamic_asset_id,
			Helper_Functions::get_safe_url( PREMIUM_ASSETS_URL . '/' . 'pafe' . $dynamic_asset_id . '.css' ),
			array(),
			get_post_modified_time()
		);

		// If no JS file found, then generate it.
		if ( ! $this->has_asset_file( $post_id, 'js' ) ) {
			$this->generate_new_asset_file( $post_id, $location, 'js' );
		}

		// Check again to prevent case where no JS file generated (independent widgets only).
		if ( $this->has_asset_file( $post_id, 'js' ) ) {
			wp_enqueue_script(
				'pafe' . $dynamic_asset_id,
				Helper_Functions::get_safe_url( PREMIUM_ASSETS_URL . '/' . 'pafe' . $dynamic_asset_id . '.js' ),
				array(),
				get_post_modified_time(),
				true
			);
		}
	}

	/**
	 * Has Asset File.
	 *
	 * Check if the current post ID has an asset file.
	 *
	 * @access public
	 * @since 4.6.1
	 *
	 * @param int    $post_id post id.
	 * @param string $ext js|css.
	 *
	 * @return bool if has asset file.
	 */
	public function has_asset_file( $post_id, $ext = 'css' ) {

		if ( file_exists( Helper_Functions::get_safe_path( PREMIUM_ASSETS_PATH . '/' . 'pafe' . ( $post_id ? '-' . $post_id : '' ) . '.' . $ext ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Handle Post Save.
	 *
	 * @access public
	 * @since 4.6.1
	 */
	public function handle_post_save( $post_id, $data ) {

		$widget_list = $this->extract_pa_elements( $data );
		$this->save_pa_widgets_list( $post_id, $widget_list );
	}

	/**
	 * Maybe Invalidate Assets.
	 *
	 * Drops the cached widgets list and the asset files on writes the editor save
	 * hook never sees - WPML/Polylang, WP-CLI, importers. Rebuilt on the next
	 * front-end request by get_pa_elements_list().
	 *
	 * @access public
	 * @since 4.11.102
	 *
	 * @param int    $meta_id  meta row ID.
	 * @param int    $post_id  post ID.
	 * @param string $meta_key meta key.
	 *
	 * @return void
	 */
	public function maybe_invalidate_assets( $meta_id, $post_id, $meta_key ) {

		if ( '_elementor_data' !== $meta_key ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$post_id = intval( $post_id );

		if ( ! $post_id ) {
			return;
		}

		// Revisions and autosaves hold their own copy of the data, the parent post is untouched.
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Nothing cached yet for this post, get_pa_elements_list() will build it.
		if ( ! $this->has_assets_data( $post_id ) ) {
			return;
		}

		delete_post_meta( $post_id, self::ASSETS_KEY );

		self::delete_assets_files( $post_id );
	}

	/**
	 * Set post unique id.
	 *
	 * @access public
	 * @since 4.6.1
	 *
	 * @return void
	 */
	public function set_post_id() {

		$this->post_id = get_the_ID();
	}

	/**
	 * Extracts PA Elements.
	 *
	 * @access public
	 * @since 4.6.1
	 *
	 * @param array $data  post data.
	 *
	 * @return array
	 */
	public function extract_pa_elements( $data ) {

		if ( empty( $data ) ) {
			return array();
		}

		$pa_names = Admin_Helper::get_pa_elements_names();

		$pa_elems = array();

		Plugin::$instance->db->iterate_data(
			$data,
			function ( $element ) use ( &$pa_elems, $pa_names ) {

				if ( isset( $element['elType'] ) ) {

					if ( 'widget' === $element['elType'] && isset( $element['widgetType'] ) ) {

						$widget_type = ( 'global' === $element['widgetType'] && ! empty( $element['templateID'] ) ) ? $this->get_global_widget_type( $element['templateID'] ) : $element['widgetType'];

						if ( in_array( $widget_type, $pa_names, true ) && ! in_array( $widget_type, $pa_elems, true ) ) {

							$pa_elems[] = $widget_type;

						}
					}
				}
			}
		);

		return $pa_elems;
	}

	/**
	 * Get Global Widget Type.
	 *
	 * @access public
	 * @since 4.6.1
	 * @link https://code.elementor.com/methods/elementor-templatelibrary-manager-get_template_data/
	 * @param int $temp_id  template it.
	 *
	 * @return string|void
	 */
	public function get_global_widget_type( $temp_id ) {

		$temp_data = Plugin::$instance->templates_manager->get_template_data(
			array(
				'source'      => 'local',
				'template_id' => $temp_id,
			)
		);

		if ( is_wp_error( $temp_data ) || ! $temp_data || empty( $temp_data ) ) {
			return;
		}

		if ( ! isset( $temp_data['content'] ) || empty( $temp_data['content'] ) ) {
			return;
		}

		return $temp_data['content'][0]['widgetType'];
	}

	/**
	 * Generate New Asset File.
	 *
	 * Generates a new CSS/JS file for the current post.
	 *
	 * @access public
	 * @since 4.6.1
	 *
	 * @param int    $post_id post id.
	 * @param string $location edit|front.
	 * @param string $ext js|css.
	 */
	public function generate_new_asset_file( $post_id, $location, $ext ) {

		// If no directory found, then create it.
		if ( ! file_exists( PREMIUM_ASSETS_PATH ) ) {
			wp_mkdir_p( PREMIUM_ASSETS_PATH );
		}

		// Generate dynamic asset file content.
		$file_content = $this->get_asset_file_content( $post_id, $location, $ext );

		if ( ! empty( $file_content ) ) {

			$name      = 'pafe' . ( $post_id ? '-' . $post_id : '' ) . '.' . $ext;
			$file_path = Helper_Functions::get_safe_path( PREMIUM_ASSETS_PATH . DIRECTORY_SEPARATOR . $name );

			file_put_contents( $file_path, $file_content );  // phpcs:ignore
		}
	}

	/**
	 * Get Asset File Content.
	 *
	 * Collects pa/papro widgets assets.
	 *
	 * @access public
	 * @since 4.6.1
	 *
	 * @param int    $post_id post id.
	 * @param string $location edit|front.
	 * @param string $ext js|css.
	 *
	 * @return string|array $content
	 */
	public function get_asset_file_content( $post_id, $location, $ext ) {

		$content = '';

		if ( 'edit' === $location ) {
			// For editor, generate assets based on the enabled elements.
			$elements = Helper_Functions::get_enabled_widgets_names();
		} else {
			$elements = get_post_meta( $post_id, self::ASSETS_KEY, true );
		}

		if ( empty( $elements ) ) {
			return '';
		}

		$elements = $this->prepare_pa_elements( $elements, $ext );

		foreach ( $elements as $element ) {

			$path = $this->get_file_path( $element, $ext );

			if ( ! $path ) {
				continue;
			}

			$file_content = $this->get_file_content( Helper_Functions::get_safe_path( $path ) );

			$content .= $file_content;
		}

		return $content;
	}

	/**
	 * Prepare PA Elements.
	 *
	 * @access public
	 * @since 4.6.1
	 *
	 * @param array  $elements  post elements.
	 * @param string $ext  js|css.
	 *
	 * @return array
	 */
	public function prepare_pa_elements( $elements, $ext ) {

		if ( Helper_Functions::check_papro_version() ) {

			$social_revs = array(
				'premium-yelp-reviews',
				'premium-google-reviews',
				'premium-facebook-reviews',
			);

			$if_has_social_reviews = array_intersect( $social_revs, $elements );

			if ( ! empty( $if_has_social_reviews ) ) {
				$elements[] = 'premium-reviews';
			}

			$social_feed = array(
				'premium-twitter-feed',
				'premium-facebook-feed',
			);

			$if_has_social_feed = array_intersect( $social_feed, $elements );

			if ( ! empty( $if_has_social_feed ) ) {
				$elements[] = 'social-common';
			}
		}

		if ( 'css' === $ext ) {
			$common_assets = $this->has_free_elements( $elements ) ? array( 'common' ) : array();
			$common_assets = $this->has_pro_elements( $elements ) ? array_merge( $common_assets, array( 'common-pro' ) ) : $common_assets;

			$elements       = array_merge( $elements, $common_assets );
			$indep_elements = array(
				'premium-world-clock',
				'premium-svg-drawer',
			);

			// Load CSS files for PRO Woo Products skins handled for editor/frontend.
			$if_woo_products = array_intersect( array( 'woo-products', 'premium-woo-products' ), $elements );

			if ( $if_woo_products && Helper_Functions::check_papro_version() ) {
				$elements[] = 'premium-woo-products-pro';
			}
		} else {
			$indep_elements = array(
				'social-common',
				'premium-lottie',
				'premium-vscroll',
				'premium-hscroll',
				'premium-nav-menu',
				'premium-addon-maps',
				'premium-woo-products',
				'premium-woo-products-pro',
				'premium-mini-cart',
				'premium-woo-cta',
				'premium-smart-post-listing',
				'premium-notifications',
				'premium-site-logo',
				'premium-tcloud',
			);

		}

		$elements = array_diff( $elements, $indep_elements );

		return $elements;
	}

	/**
	 * Get File Content.
	 *
	 * @param string $path file path.
	 *
	 * @return string
	 */
	public static function get_file_content( $path ) {

		static $file_cache = array();

		if ( isset( $file_cache[ $path ] ) ) {
			return $file_cache[ $path ];
		}

		if ( ! file_exists( $path ) ) {
			$file_cache[ $path ] = '';
			return '';
		}

		$file_content = file_get_contents( $path );

		$file_cache[ $path ] = $file_content;

		return $file_content;
	}

	/**
	 * Get File Path.
	 * Construct file path.
	 *
	 * @param string $element  pa element name.
	 * @param string $ext      file extension ( js|css).
	 *
	 * @return string file path.
	 */
	public function get_file_path( $element, $ext ) {

		$is_pro = $this->is_pro_widget( $element );

		if ( ! Helper_Functions::check_papro_version() && $is_pro ) {
			return false;
		}

		$element = str_replace( '-addon', '', $element );

		if ( 0 === strpos( $element, 'woo-' ) || 0 === strpos( $element, 'mini-' ) ) {
			$element = 'premium-' . $element;
		}

		$path = $is_pro ? PREMIUM_PRO_ADDONS_PATH : PREMIUM_ADDONS_PATH;

		return $path . 'assets/frontend/min-' . $ext . '/' . $element . '.min.' . $ext;
	}

	/**
	 * Is Pro Widget.
	 * Checks if the widget is pro.
	 *
	 * @access public
	 * @since 4.6.1
	 *
	 * @param string $widget  widget name.
	 *
	 * @return bool
	 */
	public function is_pro_widget( $widget ) {

		$pro_names = array_merge( array( 'common-pro', 'premium-reviews', 'premium-woo-products-pro', 'social-common' ), $this->get_pro_widgets_names() );

		return in_array( $widget, $pro_names, true );
	}

	/**
	 * Has Pro Elements.
	 * Check if the post has pa pro elements.
	 *
	 * @access public
	 * @since 4.6.1
	 *
	 * @param array $post_elements post elements.
	 *
	 * @return boolean
	 */
	public function has_pro_elements( $post_elements ) {

		$pro_elements = $this->get_pro_widgets_names();
		$has_pro      = array_intersect( $post_elements, $pro_elements ) ? true : false;

		return $has_pro;
	}

	/**
	 * Has Free Elements.
	 * Check if the post has pa elements.
	 *
	 * @access public
	 * @since 4.6.1
	 *
	 * @param array $post_elements post elements.
	 *
	 * @return boolean
	 */
	public function has_free_elements( $post_elements ) {

		$free_elements = Admin_Helper::get_free_widgets_names();

		// add some other pro widgets.
		$free_elements = array_merge(
			$free_elements,
			array(
				'premium-smart-post-listing',
				'premium-addon-instagram-feed',
				'premium-notbar',
				'premium-addon-flip-box',
				'premium-addon-icon-box',
				'premium-addon-magic-section',
				'premium-whatsapp-chat',
			)
		);

		$has_free = array_intersect( $post_elements, $free_elements ) ? true : false;

		return $has_free;
	}

	/**
	 * Get Pro Widgets Names.
	 *
	 * @access public
	 * @since 4.6.1
	 *
	 * @return array
	 */
	public function get_pro_widgets_names() {

		static $pro_names = null;

		if ( null !== $pro_names ) {
			return $pro_names;
		}

		$pro_elements = Admin_Helper::get_pro_elements();
		$pro_names    = array();

		foreach ( $pro_elements as $element ) {
			if ( isset( $element['name'] ) ) {
				$pro_names[] = $element['name'];
			}
		}

		return $pro_names;
	}

	/**
	 * Clear Cached Assets.
	 *
	 * Deletes assets options from DB And
	 * deletes assets files from uploads/premium-addons-for-elementor via AJAX
	 * directory.
	 *
	 * @access public
	 * @since 4.9.3
	 */
	public function pa_clear_cached_assets() {

		check_ajax_referer( 'pa-generate-nonce', 'security' );

		$post_id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$this->clear_dynamic_assets_data( $post_id );

		wp_send_json_success( 'Cached Assets Cleared' );
	}

	/**
	 * Clear Dynamic Assets Data.
	 *
	 * Deletes assets options from DB And
	 * deletes assets files from uploads/premium-addons-for-elementor
	 * directory.
	 *
	 * @access public
	 * @since 4.10.51
	 *
	 * @param string $id post ID.
	 */
	public function clear_dynamic_assets_data( $id = '' ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You are not allowed to do this action', 'premium-addons-for-elementor' ) );
		}

		self::clear_dynamic_assets( $id );
	}

	/**
	 * Clear Dynamic Assets.
	 *
	 * Pure dynamic-assets clearer: clears Elementor's file cache, deletes the
	 * generated asset files (site-wide when $id is empty, otherwise for a single
	 * post) and purges the LiteSpeed cache. Shared by clear_dynamic_assets_data()
	 * (AJAX, permission-checked) and the premium-addons/clear-dynamic-assets
	 * ability (permission-gated via its permission_callback) so the two never
	 * drift.
	 *
	 * @since 4.11.74
	 * @access public
	 *
	 * @param string $id post ID. Empty clears the assets site-wide.
	 */
	public static function clear_dynamic_assets( $id = '' ) {

		if ( Helper_Functions::check_elementor_version() ) {
			Plugin::$instance->files_manager->clear_cache();
		}

		if ( ! empty( $id ) ) {
			delete_post_meta( $id, self::ASSETS_KEY );
		}

		// Purge All LS Cache.
		do_action( 'litespeed_purge_all', 'Premium Addons for Elementor' );

		self::delete_assets_files( $id );
	}

	/**
	 * Delete Assets Options.
	 *
	 * @access public
	 * @since 4.9.3
	 */
	public function delete_trashed_post_data( $id = '' ) {

		$this->delete_assets_files( $id );

		delete_post_meta( $id, self::ASSETS_KEY );
	}

	/**
	 * Delete Assets Files.
	 *
	 * @access public
	 * @since 4.6.1
	 *
	 * @param string $id post id.
	 */
	public static function delete_assets_files( $id = '' ) {

		$path = PREMIUM_ASSETS_PATH;

		if ( ! is_dir( $path ) ) {
			return;
		}

		if ( empty( $id ) ) {
			$dir = new \DirectoryIterator( $path );
			foreach ( $dir as $file ) {
				if ( $file->isDot() || ! $file->isFile() ) {
					continue;
				}

				unlink( Helper_Functions::get_safe_path( $file->getPathname() ) );
			}

			return;
		}

		foreach ( array( 'css', 'js' ) as $ext ) {
			$file = Helper_Functions::get_safe_path( $path . '/pafe-' . $id . '.' . $ext );

			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}
	}

	/**
	 * Get PA Elements List.
	 *
	 * @access public
	 * @since 4.6.1
	 *
	 * @return boolean
	 */
	public function get_pa_elements_list() {

		if ( is_object( Plugin::instance()->editor ) && Plugin::instance()->editor->is_edit_mode() ) {
			return false;
		}

		$post_id = $this->post_id;

		if ( $this->has_assets_data( $post_id ) ) {
			return false;
		}

		$document = is_object( Plugin::$instance->documents ) ? Plugin::$instance->documents->get( $post_id ) : array();
		$data     = is_object( $document ) ? $document->get_elements_data() : array();
		$data     = $this->extract_pa_elements( $data );

		$this->save_pa_widgets_list( $post_id, $data );

		return true;
	}

	/**
	 * Save PA Widgets List.
	 *
	 * @access public
	 * @since 4.6.1
	 *
	 * @param int   $post_id  post id.
	 * @param array $list  widgets list.
	 *
	 * @return boolean
	 */
	public function save_pa_widgets_list( $post_id, $list ) {

		if ( \defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		$documents = is_object( Plugin::$instance->documents ) ? Plugin::$instance->documents->get( $post_id ) : array();

		if ( ! in_array( get_post_status( $post_id ), array( 'publish', 'private' ), true ) || ( is_object( $documents ) && ! $documents->is_built_with_elementor() ) ) {
			return false;
		}

		if ( in_array( get_post_meta( $post_id, '_elementor_template_type', true ), array( 'kit' ), true ) ) {
			return false;
		}

		// No new elements added.
		$existing_elements = get_post_meta( $post_id, self::ASSETS_KEY, true );
		if ( $list === $existing_elements ) {
			return false;
		}

		try {

			update_post_meta( $post_id, self::ASSETS_KEY, $list );

			$this->delete_assets_files( $post_id );

			if ( $this->has_assets_data( $post_id ) ) {
				$this->update_assets_files( $post_id, $list );
			}

			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Update Assets Files.
	 *
	 * @access public
	 * @since 4.6.1
	 *
	 * @param int   $post_id  post id.
	 * @param array $elements  elements.
	 */
	public function update_assets_files( $post_id, $elements ) {

		$this->generate_new_asset_file( $post_id, 'front', 'css' );
		$this->generate_new_asset_file( $post_id, 'front', 'js' );
	}

	public function is_edit() {
		return (
			Plugin::instance()->editor->is_edit_mode() ||
			Plugin::instance()->preview->is_preview_mode() ||
			is_preview()
		);
	}

	/**
	 * Has Assets Data.
	 *
	 * @access public
	 * @since 4.10.54
	 *
	 * @param int $post_id post id.
	 *
	 * @return boolean
	 */
	public function has_assets_data( $post_id ) {

		$status = get_post_meta( $post_id, self::ASSETS_KEY, true );

		return ! empty( $status );
	}

	/**
	 * Exclude the generated dynamic assets from WP-Optimize minification.
	 *
	 * WP-Optimize matches each entry as a case-insensitive substring of the asset URL,
	 * so the uploads directory fragment covers every pafe[-{postID}].css/js.
	 *
	 * @since 4.10.73
	 * @access public
	 *
	 * @param array $exclusions WP-Optimize minify exclusions.
	 *
	 * @return array
	 */
	public function exclude_pa_assets_from_wp_optimize( $exclusions ) {

		$exclusions[] = '/premium-addons-elementor/';

		return $exclusions;
	}

	/**
	 * Register Frontend CSS files
	 *
	 * @since 2.9.0
	 * @access public
	 */
	public function register_frontend_styles() {

		$dir    = Helper_Functions::get_styles_dir();
		$suffix = Helper_Functions::get_assets_suffix();

		wp_register_style(
			'pa-common',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/common' . $suffix . '.css',
			array(),
			PREMIUM_ADDONS_VERSION,
			'all'
		);

		wp_register_style(
			'font-awesome-5-all',
			ELEMENTOR_ASSETS_URL . 'lib/font-awesome/css/all.min.css',
			false,
			PREMIUM_ADDONS_VERSION
		);

		wp_register_style(
			'pa-flipster',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/flipster' . $suffix . '.css',
			false,
			PREMIUM_ADDONS_VERSION
		);

		wp_register_style(
			'pa-prettyphoto',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/prettyphoto' . $suffix . '.css',
			array(),
			PREMIUM_ADDONS_VERSION,
			'all'
		);

		wp_register_style(
			'pa-btn',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/button-line' . $suffix . '.css',
			array(),
			PREMIUM_ADDONS_VERSION,
			'all'
		);

		wp_register_style(
			'pa-load-animations',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/load-animations' . $suffix . '.css',
			array(),
			PREMIUM_ADDONS_VERSION,
			'all'
		);

		wp_register_style(
			'pa-share-btn',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/share-button' . $suffix . '.css',
			array(),
			PREMIUM_ADDONS_VERSION,
			'all'
		);

		wp_register_style(
			'pa-image-effects',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/image-effects' . $suffix . '.css',
			array(),
			PREMIUM_ADDONS_VERSION,
			'all'
		);

		wp_register_style(
			'pa-slick',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/slick' . $suffix . '.css',
			array(),
			PREMIUM_ADDONS_VERSION,
			'all'
		);

		wp_register_style(
			'pa-world-clock',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/premium-world-clock' . $suffix . '.css',
			array(),
			PREMIUM_ADDONS_VERSION,
			'all'
		);

		wp_register_style(
			'tooltipster',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/tooltipster.min.css',
			array(),
			PREMIUM_ADDONS_VERSION,
			'all'
		);

		wp_register_style(
			'pa-gTooltips',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/premium-gtooltips' . $suffix . '.css',
			array(),
			PREMIUM_ADDONS_VERSION,
			'all'
		);

		wp_register_style(
			'pa-shape-divider',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/premium-sh-divider' . $suffix . '.css',
			array(),
			PREMIUM_ADDONS_VERSION,
			'all'
		);

		wp_register_style(
			'pa-odometer',
			PREMIUM_ADDONS_URL . 'assets/frontend/min-css/odometer.min.css',
			array(),
			PREMIUM_ADDONS_VERSION,
			'all'
		);

		wp_register_style(
			'pa-glass',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/liquid-glass' . $suffix . '.css',
			array(),
			PREMIUM_ADDONS_VERSION,
			'all'
		);

		if ( ! $this->enabled_elements['premium-assets-generator'] ) {

			// Registered, not enqueued: every widget lists this handle in get_style_depends(), and Elementor
			// enqueues it from the _elementor_page_assets meta before wp_head. Pages carrying no PA widget
			// therefore skip the bundle entirely.
			wp_register_style(
				'premium-addons',
				PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/premium-addons' . $suffix . '.css',
				array(),
				PREMIUM_ADDONS_VERSION,
				'all'
			);

		}
	}

	public function enqueue_elements_handler() {

		wp_enqueue_script(
			'pa-elements-handler',
			PREMIUM_ADDONS_URL . 'assets/frontend/min-js/elements-handler.min.js',
			array(),
			PREMIUM_ADDONS_VERSION,
			true
		);
	}

	/**
	 * Registers required JS files
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function register_frontend_scripts() {

		$maps_settings = $this->integrations;

		$dir    = Helper_Functions::get_scripts_dir();
		$suffix = Helper_Functions::get_assets_suffix();

		wp_localize_script(
			'elementor-frontend',
			'PremiumSettings',
			array(
				'ajaxurl' => esc_url( admin_url( 'admin-ajax.php' ) ),
				'nonce'   => wp_create_nonce( 'pa-blog-widget-nonce' ),

			)
		);

		if ( ! $this->enabled_elements['premium-assets-generator'] ) {
			wp_register_script(
				'premium-addons',
				PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/premium-addons' . $suffix . '.js',
				array( 'jquery' ),
				PREMIUM_ADDONS_VERSION,
				true
			);
		}

		wp_register_script(
			'pa-scrolldir',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/pa-scrolldir' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'prettyPhoto-js',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/prettyPhoto' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'tooltipster-bundle',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/tooltipster' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-vticker',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/vticker' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-typed',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/typed' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'countdown-translator',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/countdown-translator' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-countdown',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/jquery-countdown' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'isotope-js',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/isotope' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-modal',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/modal' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-maps',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/premium-maps' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-vscroll',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/premium-vscroll' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-slimscroll',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/jquery-slimscroll' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-iscroll',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/iscroll' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-tilt',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/universal-tilt' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-tcloud',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/premium-tcloud' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'lottie-js',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/lottie' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-odometer',
			PREMIUM_ADDONS_URL . 'assets/frontend/min-js/odometer.min.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-tweenmax',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/TweenMax' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-draggable',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/Draggable' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-headroom',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/headroom' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION
		);

		wp_register_script(
			'pa-menu',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/premium-nav-menu' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		if ( $maps_settings['premium-map-cluster'] ) {
			wp_register_script(
				'pa-maps-cluster',
				PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/markerclusterer.min.js',
				array(),
				'1.0.1',
				true
			);
		}

		if ( $maps_settings['premium-map-disable-api'] && '1' !== $maps_settings['premium-map-api'] ) {

			$locale = $maps_settings['premium-map-locale'] ?? 'en';

			$api = sprintf( 'https://maps.googleapis.com/maps/api/js?key=%1$s&libraries=marker&callback=initMap&language=%2$s&loading=async', $maps_settings['premium-map-api'], $locale );

			wp_register_script(
				'pa-maps-api',
				$api,
				array(),
				PREMIUM_ADDONS_VERSION,
				true
			);
		}

		wp_register_script(
			'pa-slick',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/slick' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-flipster',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/flipster' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION
		);

		wp_register_script(
			'pa-anime',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/anime' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-feffects',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/premium-float-effects' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-gTooltips',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/premium-gtooltips' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-shape-divider',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/premium-sh-divider' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_localize_script(
			'pa-shape-divider',
			'PaShapeDividerSettings',
			array(
				'ajaxurl' => esc_url( admin_url( 'admin-ajax.php' ) ),
				'nonce'   => wp_create_nonce( 'pa-shape-nonce' ),
			)
		);

		wp_localize_script(
			'pa-gTooltips',
			'PremiumSettings',
			array(
				'ajaxurl' => esc_url( admin_url( 'admin-ajax.php' ) ),
				'nonce'   => wp_create_nonce( 'pa-blog-widget-nonce' ),
			)
		);

		wp_register_script(
			'pa-eq-height',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/premium-eq-height' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-dis-conditions',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/premium-dis-conditions' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-gsap',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/pa-gsap' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-motionpath',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/motionpath' . $suffix . '.js',
			array(
				'jquery',
			),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-scrolltrigger',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/scrollTrigger' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-notifications',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/premium-notifications' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-luxon',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/luxon' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'mousewheel-js',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/jquery-mousewheel' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		wp_register_script(
			'pa-wrapper-link',
			PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/premium-wrap-link' . $suffix . '.js',
			array( 'jquery' ),
			PREMIUM_ADDONS_VERSION,
			true
		);

		// We need to make sure premium-woocommerce.js will not be loaded twice if assets are generated.
		if ( class_exists( 'woocommerce' ) ) {

			wp_register_script(
				'premium-woo-cats',
				PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/premium-woo-categories' . $suffix . '.js',
				array( 'jquery' ),
				PREMIUM_ADDONS_VERSION,
				true
			);

			wp_register_script(
				'premium-mini-cart',
				PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/premium-mini-cart' . $suffix . '.js',
				array( 'jquery' ),
				PREMIUM_ADDONS_VERSION,
				true
			);

			wp_register_script(
				'premium-woo-cart',
				PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/premium-woo-cart' . $suffix . '.js',
				array( 'jquery' ),
				PREMIUM_ADDONS_VERSION,
				true
			);

			wp_register_script(
				'premium-woo-cta',
				PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/premium-woo-cta' . $suffix . '.js',
				array( 'jquery' ),
				PREMIUM_ADDONS_VERSION,
				true
			);

			wp_register_script(
				'premium-woocommerce',
				PREMIUM_ADDONS_URL . 'assets/frontend/' . $dir . '/premium-woo-products' . $suffix . '.js',
				array( 'jquery' ),
				PREMIUM_ADDONS_VERSION,
				true
			);

			wp_localize_script(
				'premium-woo-cta',
				'PAWooCTASettings',
				array(
					'ajaxurl'         => esc_url( admin_url( 'admin-ajax.php' ) ),
					'cta_nonce'       => wp_create_nonce( 'pa-woo-cta-nonce' ),
					'view_cart'       => __( 'View cart', 'woocommerce' ),
					'mini_cart_nonce' => wp_create_nonce( 'pa-mini-cart-nonce' ),
					'qv_nonce'        => wp_create_nonce( 'pa-woo-qv-nonce' ),
				)
			);

			$product_added_to_cart = $this->consume_mini_cart_auto_open_flag();

			wp_localize_script(
				'premium-mini-cart',
				'PAWooMCartSettings',
				array(
					'ajaxurl'            => esc_url( admin_url( 'admin-ajax.php' ) ),
					'cta_nonce'          => wp_create_nonce( 'pa-woo-cta-nonce' ),
					'view_cart'          => __( 'View cart', 'woocommerce' ),
					'mini_cart_nonce'    => wp_create_nonce( 'pa-mini-cart-nonce' ),
					'qv_nonce'           => wp_create_nonce( 'pa-woo-qv-nonce' ),
					'stock_msg'          => __( '*The current stock is only ', 'premium-addons-for-elementor' ),
					'productAddedToCart' => $product_added_to_cart,
				)
			);

			wp_localize_script(
				'premium-woocommerce',
				'PAWooProductsSettings',
				array(
					'ajaxurl'         => esc_url( admin_url( 'admin-ajax.php' ) ),
					'products_nonce'  => wp_create_nonce( 'pa-woo-products-nonce' ),
					'qv_nonce'        => wp_create_nonce( 'pa-woo-qv-nonce' ),
					'cta_nonce'       => wp_create_nonce( 'pa-woo-cta-nonce' ),
					'woo_cart_url'    => get_permalink( wc_get_page_id( 'cart' ) ),
					'view_cart'       => __( 'View cart', 'woocommerce' ),
					'mini_cart_nonce' => wp_create_nonce( 'pa-mini-cart-nonce' ),
				)
			);

		}

		// Localize jQuery with required data for Global Add-ons.
		if ( $this->enabled_elements['premium-floating-effects'] ) {
			wp_localize_script(
				'pa-feffects',
				'PremiumFESettings',
				array(
					'papro_installed' => Helper_Functions::check_papro_version(),
				)
			);
		}

		// Localize jQuery with required data for Global Add-ons.
		if ( $this->enabled_elements['premium-countdown'] ) {

			wp_localize_script(
				'pa-countdown',
				'premiumCountDownStrings',
				array(
					'single' => array(
						__( 'Year', 'premium-addons-for-elementor' ),
						__( 'Month', 'premium-addons-for-elementor' ),
						__( 'Week', 'premium-addons-for-elementor' ),
						__( 'Day', 'premium-addons-for-elementor' ),
						__( 'Hour', 'premium-addons-for-elementor' ),
						__( 'Minute', 'premium-addons-for-elementor' ),
						__( 'Second', 'premium-addons-for-elementor' ),
					),
					'plural' => array(
						__( 'Years', 'premium-addons-for-elementor' ),
						__( 'Months', 'premium-addons-for-elementor' ),
						__( 'Weeks', 'premium-addons-for-elementor' ),
						__( 'Days', 'premium-addons-for-elementor' ),
						__( 'Hours', 'premium-addons-for-elementor' ),
						__( 'Minutes', 'premium-addons-for-elementor' ),
						__( 'Seconds', 'premium-addons-for-elementor' ),
					),
				)
			);
		}
	}

	/**
	 * Reads and clears this visitor's pending mini cart auto open.
	 *
	 * This used to be a transient, which is site wide: one shopper's add to cart opened the mini
	 * cart for every other visitor on the site for the next minute. Visitors with no WooCommerce
	 * session simply never auto open — creating a session here would hand every anonymous page
	 * view a cookie and defeat full page caching.
	 *
	 * Runs on every front end page view, so the flag is consumed whether or not that page renders
	 * a mini cart. Elementor enqueues widget script dependencies at render time, long after this
	 * point, so there is nothing cheap to test here.
	 *
	 * @since 4.11.106
	 * @access private
	 *
	 * @return bool
	 */
	private function consume_mini_cart_auto_open_flag() {

		$session = WC()->session;

		if ( ! $session ) {
			return false;
		}

		$added_at = absint( $session->get( 'pa_product_added_to_cart' ) );

		if ( ! $added_at ) {
			return false;
		}

		$session->set( 'pa_product_added_to_cart', null );

		return ( time() - $added_at ) < MINUTE_IN_SECONDS;
	}

	/**
	 * Creates and returns an instance of the class.
	 *
	 * @since  4.6.1
	 * @access public
	 *
	 * @return object
	 */
	public static function get_instance( $enabled_elements, $integrations ) {

		if ( ! isset( self::$instance ) ) {

			self::$instance = new self( $enabled_elements, $integrations );

		}

		return self::$instance;
	}
}
