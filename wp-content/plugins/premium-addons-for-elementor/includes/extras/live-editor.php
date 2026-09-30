<?php

namespace PremiumAddons\Includes\Extras;

use PremiumAddons\Includes\Helper_Functions;
use ElementorPro\Plugin as PluginPro;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/*
 * Premium Cross Domain Copy Paste Feature
 */
if ( ! class_exists( 'Live_Editor' ) ) {

	/**
	 * Define Live_Editor class
	 */
	class Live_Editor {

		/**
		 * Class instance
		 *
		 * @var instance
		 */
		private static $instance = null;

		/**
		 * Initialize integration hooks
		 *
		 * @return void
		 */
		public function __construct() {

			add_action( 'elementor/editor/after_enqueue_styles', array( $this, 'load_live_editor_modal' ) );

			add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'after_enqueue_scripts' ) );

			add_action( 'wp_ajax_update_template_title', array( $this, 'update_template_title' ) );
			add_action( 'wp_ajax_handle_live_editor', array( $this, 'handle_live_editor' ) );
			add_action( 'wp_ajax_pa_get_editor_template', array( $this, 'pa_get_editor_template' ) );

			add_action( 'wp_ajax_check_temp_validity', array( $this, 'check_temp_validity' ) );
		}

		/**
		 * Load Live Editor Modal.
		 * Puts live editor popup html into the editor.
		 *
		 * @access public
		 * @since 4.8.10
		 */
		public function load_live_editor_modal() {
			ob_start();
			include_once PREMIUM_ADDONS_PATH . 'includes/extras/live-editor-modal.php';
			$output = ob_get_contents();
			ob_end_clean();
			echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		public function after_enqueue_scripts() {

			wp_enqueue_script(
				'live-editor',
				PREMIUM_ADDONS_URL . 'assets/editor/js/live-editor.js',
				array( 'elementor-editor', 'jquery' ),
				PREMIUM_ADDONS_VERSION,
				true
			);

			$live_editor_data = array(
				'ajaxurl'  => esc_url( admin_url( 'admin-ajax.php' ) ),
				'nonce'    => wp_create_nonce( 'pa-live-editor' ),
				'adminUrl' => esc_url( get_admin_url() ),
			);

			wp_localize_script( 'live-editor', 'liveEditor', $live_editor_data );
		}

		/**
		 * Update Template Title.
		 *
		 * @access public
		 * @since 4.8.10
		 */
		public function update_template_title() {

			check_ajax_referer( 'pa-live-editor', 'security' );

			if ( ! isset( $_POST['title'] ) || ! isset( $_POST['id'] ) ) {
				wp_send_json_error( 'Post has no title.' );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Insufficient user permission' );
			}

			$res = wp_update_post(
				array(
					'ID'         => sanitize_text_field( wp_unslash( $_POST['id'] ) ),
					'post_title' => sanitize_text_field( wp_unslash( $_POST['title'] ) ),
				)
			);

			wp_send_json_success( $res );
		}

		/**
		 * Handle Live Editor Modal.
		 *
		 * @access public
		 * @since 4.8.10
		 */
		public function handle_live_editor() {

			check_ajax_referer( 'pa-live-editor', 'security' );

			if ( ! isset( $_POST['key'] ) ) { // Widget ID ( + Control ID in case of repeater items ).
				wp_send_json_error();
			}

			$post_name  = 'pa-dynamic-temp-' . sanitize_text_field( wp_unslash( $_POST['key'] ) );
			$temp_type  = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : false;
			$meta_input = array(
				'_elementor_edit_mode'     => 'builder',
				'_elementor_template_type' => 'page',
				'_wp_page_template'        => 'elementor_canvas',
			);

			if ( 'loop' === $temp_type ) {
				$meta_input = array(
					'_elementor_edit_mode'     => 'builder',
					'_elementor_template_type' => 'loop-item',
				);
			} elseif ( 'grid' === $temp_type ) {
				$meta_input = array(
					'_elementor_edit_mode'     => 'builder',
					'_elementor_template_type' => 'premium-grid',
				);
			}

			$post_title = '';
			$args       = array(
				'post_type'              => 'elementor_library',
				'name'                   => $post_name,
				'post_status'            => 'publish',
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'posts_per_page'         => 1,
			);

			$post = get_posts( $args );

			if ( empty( $post ) ) { // create a new one.

				$key        = sanitize_text_field( wp_unslash( $_POST['key'] ) );
				$post_title = 'PA Template | #' . substr( md5( $key ), 0, 4 );

				$params = array(
					'post_content' => '',
					'post_type'    => 'elementor_library',
					'post_title'   => $post_title,
					'post_name'    => $post_name,
					'post_status'  => 'publish',
					'meta_input'   => $meta_input,
				);

				$post_id = wp_insert_post( $params );

			} else { // edit post.
				$post_id    = $post[0]->ID;
				$post_title = $post[0]->post_title;
			}

			$edit_url = get_admin_url() . '/post.php?post=' . $post_id . '&action=elementor';

			$result = array(
				'url'   => $edit_url,
				'id'    => $post_id,
				'title' => $post_title,
			);

			wp_send_json_success( $result );
		}

		/**
		 * Get Editor Template.
		 *
		 * Handles AJAX request to retrieve the Elementor editor URL for a given template title.
		 *
		 * @access public
		 * @since 4.8.10
		 *
		 * @return void Outputs JSON response with editor URL, template ID, and title.
		 */
		public function pa_get_editor_template() {

			check_ajax_referer( 'pa-live-editor', 'security' );

			if ( ! isset( $_POST['tempTitle'] ) ) { // template title.
				wp_send_json_error( 'Template title not found', 404 );
			}

			$temp_title = sanitize_text_field( wp_unslash( $_POST['tempTitle'] ) );
			$temp_type  = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : false;

			$decoded_title = html_entity_decode( $temp_title );

			$args = array(
				'post_type'        => 'elementor_library',
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'title'            => $decoded_title,
				'suppress_filters' => true,
			);

			$query = new \WP_Query( $args );

			$post_id = '';

			if ( $query->have_posts() ) {
				$post_id = $query->post->ID;

				$edit_url = get_admin_url() . '/post.php?post=' . $post_id . '&action=elementor';

				$result = array(
					'url'         => $edit_url,
					'id'          => $post_id,
					'title'       => $temp_title,
					'newFunction' => 'hello',
				);

				wp_reset_postdata();

				wp_send_json_success( $result );

			} else {
				wp_send_json_error( 'Template not found.....' . $decoded_title, 404 );
			}
		}

		/**
		 * Check Temp Validity.
		 *
		 * Checks if the template is valid ( has content) or not,
		 * And DELETE the post if it's invalid.
		 *
		 * @access public
		 * @since 4.9.1
		 */
		public function check_temp_validity() {

			check_ajax_referer( 'pa-live-editor', 'security' );

			if ( ! isset( $_POST['templateID'] ) ) {
				wp_send_json_error( 'template ID is not set' );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Insufficient user permission' );
			}

			$temp_id   = isset( $_POST['templateID'] ) ? sanitize_text_field( wp_unslash( $_POST['templateID'] ) ) : '';
			$temp_type = isset( $_POST['tempType'] ) ? sanitize_text_field( wp_unslash( $_POST['tempType'] ) ) : '';

			if ( 'loop' === $temp_type ) {
				/** @var LoopDocument $document */
				$template_content = PluginPro::elementor()->documents->get( $temp_id );

			} else {
				$template_content = Helper_Functions::render_elementor_template( $temp_id, true );

			}

			if ( empty( $template_content ) || ! isset( $template_content ) ) {

				$res = wp_delete_post( $temp_id, true );

				if ( ! is_wp_error( $res ) ) {
					$res = 'Template Deleted.';
				}
			} else {
				$res = 'Template Has Content.';
			}

			wp_send_json_success( $res );
		}

		/**
		 * Returns the instance.
		 *
		 * @since  3.21.1
		 * @return object
		 *
		 * @param array $shortcodes shortcodes.
		 */
		public static function get_instance( $shortcodes = array() ) {

			if ( ! isset( self::$instance ) ) {

				self::$instance = new self( $shortcodes );
			}

			return self::$instance;
		}
	}
}
