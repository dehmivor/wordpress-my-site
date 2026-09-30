<?php
/**
 * Controls Helper
 *
 * Handles element feedback functionality.
 */

namespace PremiumAddons\Includes\Helpers;

use PremiumAddons\Includes\Helper_Functions;
use PremiumAddons\Includes\Helpers\Query_Helper;
use PremiumAddons\Includes\Premium_Template_Tags;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Controls Helper Trait
 *
 * @since 4.11.58
 */
trait AJAX_Helper {

	public function register_ajax_hooks() {

		// Custom Controls AJAX Handlers.
		add_action( 'wp_ajax_premium_update_filter', array( $this, 'get_posts_list' ) );
		add_action( 'wp_ajax_premium_update_tax', array( $this, 'get_related_tax' ) );
		add_action( 'wp_ajax_pa_acf_options', array( $this, 'get_acf_options' ) );

		// Shape Divider AJAX Handlers.
		add_action( 'wp_ajax_pa_get_shape_svgs', array( $this, 'get_shape_svgs' ) );
		add_action( 'wp_ajax_get_shape_divider_svg', array( $this, 'get_shape_divider_svg' ) );

		// Template Content AJAX Handlers.
		add_action( 'wp_ajax_get_elementor_template_content', array( $this, 'get_template_content' ) );
		add_action( 'wp_ajax_nopriv_get_elementor_template_content', array( $this, 'get_template_content' ) );

		// Social Feed AJAX Handlers.
		add_action( 'wp_ajax_get_pinterest_token', array( $this, 'get_pinterest_token' ) );
		add_action( 'wp_ajax_get_pinterest_boards', array( $this, 'get_pinterest_boards' ) );
		add_action( 'wp_ajax_get_tiktok_token', array( $this, 'get_tiktok_token' ) );

		// Send Feedback AJAX Handler.
		add_action( 'wp_ajax_pa_send_element_feedback', array( $this, 'send' ) );

		// Get Posts Query AJAX Handler.
		add_action( 'wp_ajax_pa_get_posts', array( $this, 'get_posts_query' ) );
		add_action( 'wp_ajax_nopriv_pa_get_posts', array( $this, 'get_posts_query' ) );

		// Search Results AJAX Handler.
		add_action( 'wp_ajax_premium_get_search_results', array( $this, 'get_search_results' ) );
		add_action( 'wp_ajax_nopriv_premium_get_search_results', array( $this, 'get_search_results' ) );
	}

	/**
	 * Get posts list
	 *
	 * Get posts list array
	 *
	 * @since 4.2.8
	 * @access public
	 */
	public function get_posts_list() {

		check_ajax_referer( 'pa-blog-widget-nonce', 'nonce' );

		$post_type = isset( $_POST['post_type'] ) ? wp_unslash( $_POST['post_type'] ) : '';

		$post_type = array_map( 'sanitize_text_field', $post_type );

		if ( empty( $post_type ) ) {
			wp_send_json_error( __( 'Empty Post Type.', 'premium-addons-for-elementor' ) );
		}

		$args = array(
			'post_type'              => $post_type,
			'posts_per_page'         => -1,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
		);

		// Exclude the premium-grid and loop-items templates for 'elementor_library' source.
		if ( in_array( 'elementor_library', $post_type, true ) ) {
			$args['meta_query'] = array(
				array(
					'key'     => '_elementor_template_type',
					'value'   => array( 'premium-grid', 'loop-item' ),
					'compare' => 'NOT IN',
				),
			);
		}

		$list = get_posts( $args );

		$options = array();

		if ( ! empty( $list ) && ! is_wp_error( $list ) ) {

			foreach ( $list as $post ) {
				$key             = in_array( 'elementor_library', $post_type, true ) ? $post->post_title : $post->ID;
				$options[ $key ] = $post->post_title;
			}
		}

		wp_send_json_success( wp_json_encode( $options ) );
	}

	/**
	 * Get related taxonomy list
	 *
	 * Get related taxonomy list array
	 *
	 * @since 4.3.1
	 * @access public
	 */
	public function get_related_tax() {

		check_ajax_referer( 'pa-blog-widget-nonce', 'nonce' );

		$post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : '';

		if ( empty( $post_type ) ) {
			wp_send_json_error( __( 'Empty Post Type.', 'premium-addons-for-elementor' ) );
		}

		$taxonomy = Query_Helper::get_taxnomies( $post_type );

		$related_tax = array();

		if ( ! empty( $taxonomy ) ) {

			foreach ( $taxonomy as $index => $tax ) {
				$related_tax[ $index ] = $tax->label;
			}
		}

		wp_send_json_success( wp_json_encode( $related_tax ) );
	}

	/**
	 * Get Acf Options.
	 *
	 * Get options using AJAX.
	 *
	 * @since 4.4.8
	 * @access public
	 */
	public function get_acf_options() {

		check_ajax_referer( 'pa-blog-widget-nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient user permission' );
		}

		$query_options = isset( $_POST['query_options'] ) && is_array( $_POST['query_options'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['query_options'] ) )
			: array();

		$query = new \WP_Query(
			array(
				'post_type'      => 'acf-field',
				'posts_per_page' => -1,
			)
		);

		$results = ACF_Helper::format_acf_query_result( $query->posts, $query_options );

		wp_send_json_success( wp_json_encode( $results ) );
	}

	/**
	 * Get Shape SVGs.
	 *
	 * Get the whole SVG shapes library for the shapes picker.
	 *
	 * @since 4.11.95
	 * @access public
	 */
	public function get_shape_svgs() {

		check_ajax_referer( 'pa-blog-widget-nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'premium-addons-for-elementor' ), 403 );
		}

		wp_send_json_success( array( 'shapes' => Helper_Functions::get_svg_shapes() ) );
	}

	/**
	 * Get Shape Divider SVG
	 *
	 * Get a single shape SVG for the editor preview.
	 *
	 * @since 4.11.32
	 * @access public
	 */
	public function get_shape_divider_svg() {

		check_ajax_referer( 'pa-shape-nonce', 'nonce' );

		if ( ! isset( $_POST['shape'] ) ) {
			wp_send_json_error( 'No shape selected' );
		}

		$shape = sanitize_text_field( wp_unslash( $_POST['shape'] ) );

		$svg_shape = Helper_Functions::get_svg_shapes( $shape );

		if ( empty( $svg_shape ) ) {
			wp_send_json_error( 'Invalid shape' );
		}

		wp_send_json_success( array( 'shape' => $svg_shape ) );
	}

	/**
	 * Get Template Content
	 *
	 * Get Elementor template HTML content.
	 *
	 * @since 3.2.6
	 * @access public
	 */
	public function get_template_content() {

		$template = isset( $_GET['templateID'] ) ? sanitize_text_field( wp_unslash( $_GET['templateID'] ) ) : '';
		$is_ID    = isset( $_GET['is_id'] ) ? filter_var( $_GET['is_id'], FILTER_VALIDATE_BOOLEAN ) : false;

		if ( empty( $template ) ) {
			wp_send_json_error( 'Empty Template ID' );
		}

		// Get the post object to check status and author
		$post = get_post( $template );

		if ( ! $post ) {
			wp_send_json_error( 'Invalid Template ID' );
		}

		// Check if post is published or user has permission to view
		if ( 'publish' !== $post->post_status ) {
			$current_user_id = get_current_user_id();
			$is_author       = ( $current_user_id === (int) $post->post_author );
			$is_admin        = current_user_can( 'manage_options' );

			if ( ! $is_admin && ! $is_author ) {
				wp_send_json_error( 'Permission denied' );
			}
		}

		$template_content = Helper_Functions::render_elementor_template( $template, $is_ID );

		if ( empty( $template_content ) || ! isset( $template_content ) ) {
			wp_send_json_error( 'Empty Content' );
		}

		$data = array(
			'template_content' => $template_content,
		);

		wp_send_json_success( $data );
	}

	/**
	 * Get Pinterest account token for Pinterest Feed widget
	 *
	 * @since 4.10.2
	 * @access public
	 *
	 * @return void
	 */
	public function get_pinterest_token() {

		check_ajax_referer( 'pa-editor', 'security' );

		$api_url = 'https://appfb.premiumaddons.com/wp-json/fbapp/v2/pinterest';

		$response = wp_remote_get(
			$api_url,
			array(
				'timeout'   => 15,
				'sslverify' => true,
			)
		);

		$body = wp_remote_retrieve_body( $response );
		$body = json_decode( $body, true );

		wp_send_json_success( $body );
	}

	/**
	 * Get Pinterest account token for Pinterest Feed widget
	 *
	 * @since 4.10.2
	 * @access public
	 *
	 * @return void
	 */
	public function get_pinterest_boards() {

		check_ajax_referer( 'pa-blog-widget-nonce', 'nonce' );

		if ( ! isset( $_GET['token'] ) ) {
			wp_send_json_error();
		}

		$token = sanitize_text_field( wp_unslash( $_GET['token'] ) );

		$transient_name = 'pa_pinterest_boards_' . substr( $token, 0, 15 );

		$body = get_transient( $transient_name );

		if ( false === $body ) {

			$api_url = 'https://api.pinterest.com/v5/boards?page_size=60';

			$response = wp_remote_get(
				$api_url,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
					),
				)
			);

			$body = wp_remote_retrieve_body( $response );
			$body = json_decode( $body, true );

			set_transient( $transient_name, $body, 30 * MINUTE_IN_SECONDS );

		}

		$boards = array();

		foreach ( $body['items'] as $index => $board ) {
			$boards[ $board['id'] ] = $board['name'];
		}

		wp_send_json_success( wp_json_encode( $boards ) );
	}

	/**
	 * Get Pinterest account token for Pinterest Feed widget
	 *
	 * @since 4.10.2
	 * @access public
	 *
	 * @return void
	 */
	public function get_tiktok_token() {

		check_ajax_referer( 'pa-editor', 'security' );

		$api_url = 'https://appfb.premiumaddons.com/wp-json/fbapp/v2/tiktok';

		$response = wp_remote_get(
			$api_url,
			array(
				'timeout'   => 15,
				'sslverify' => false,
			)
		);

		$body = wp_remote_retrieve_body( $response );
		$body = json_decode( $body, true );

		wp_send_json_success( $body );
	}

	/**
	 * Send Element Feedback
	 *
	 * Send element feedback via AJAX.
	 *
	 * @since 4.11.58
	 * @access public
	 *
	 * @return void
	 */
	public function send() {

		check_ajax_referer( 'pa-editor', 'security' );

		$user_msg = isset( $_POST['user_message'] ) ? sanitize_text_field( wp_unslash( $_POST['user_message'] ) ) : '';

		if ( empty( $user_msg ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Message cannot be empty.', 'premium-addons-for-elementor' ) ) );
		}

		$user = wp_get_current_user();

		$email = $user->user_email;

		if ( empty( $email ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'User email not found.', 'premium-addons-for-elementor' ) ) );
		}

		$element = isset( $_POST['element_name'] ) ? sanitize_text_field( wp_unslash( $_POST['element_name'] ) ) : '';

		$body = array(
			'email'   => $email,
			'element' => $element,
			'message' => $user_msg,
		);

		$api_url = 'https://feedbackpa.leap13.com/wp-json/element-feedback/v2/add';

		$response = wp_safe_remote_request(
			$api_url,
			array(
				'headers'     => array(
					'Content-Type' => 'application/json',
				),
				'body'        => wp_json_encode( $body ),
				'timeout'     => 20,
				'method'      => 'POST',
				'httpversion' => '1.1',
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( 'REQUEST ERR' );

		}

		if ( ! isset( $response['response'] ) || ! is_array( $response['response'] ) ) {
			wp_send_json_error( 'REQUEST UNKNOWN' );

		}

		if ( ! isset( $response['body'] ) ) {
			wp_send_json_error( 'REQUEST PAYLOAD EMPTY' );

		}

		wp_send_json_success( ( $response['body'] ) );
	}

	/**
	 * Get Posts Query
	 *
	 * Get posts query via AJAX.
	 *
	 * @since 4.11.58
	 * @access public
	 *
	 * @return void
	 */
	public function get_posts_query() {
		$myinstance = new Premium_Template_Tags();
		$myinstance->get_posts_query();
	}

	/**
	 * Get Search Results
	 *
	 * Get search results via AJAX.
	 *
	 * @since 4.11.61
	 * @access public
	 *
	 * @return void
	 */
	public function get_search_results() {
		$myinstance = new Premium_Template_Tags();
		$myinstance->get_search_results();
	}
}
