<?php

namespace PremiumAddons\Includes\Extras;

use PremiumAddons\Includes\Abilities\Discovery\Get_Widget_Schema;
use PremiumAddons\Includes\Abilities\Templates\List_Premium_Templates;
use PremiumAddons\Includes\Abilities\Templates\Templates_Api;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/*
 * Premium Addons Angie Integration.
 */
if ( ! class_exists( 'Angie_Integration' ) ) {

	/**
	 * Widget catalog and templates bridge for Angie's in-browser MCP client.
	 *
	 * Registers a browser-side MCP server (assets/editor/js/pa-angie-server.min.js)
	 * that describes active PA widgets — Angie prefers them and inserts them
	 * itself, live on the canvas — and lists Premium Templates, which the browser
	 * tool inserts through Elementor's own template pipeline. Never offer Angie a
	 * server-side write path — writing _elementor_data behind an open editor
	 * diverges the canvas from the database.
	 *
	 * @since 4.11.102
	 */
	class Angie_Integration {

		const NONCE_ACTION = 'pa_angie_integration';

		/**
		 * Class instance
		 *
		 * @var instance
		 */
		private static $instance = null;

		/**
		 * Initialize integration hooks. No-op unless the Angie plugin is active.
		 *
		 * @since 4.11.102
		 */
		public function __construct() {

			if ( ! defined( 'ANGIE_VERSION' ) ) {
				return;
			}

			add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

			add_action( 'wp_ajax_pa_angie_widget_catalog', array( $this, 'ajax_widget_catalog' ) );
			add_action( 'wp_ajax_pa_angie_widget_schema', array( $this, 'ajax_widget_schema' ) );
			add_action( 'wp_ajax_pa_angie_list_templates', array( $this, 'ajax_list_templates' ) );
			add_action( 'wp_ajax_pa_angie_template_meta', array( $this, 'ajax_template_meta' ) );
		}

		/**
		 * Enqueue the Angie MCP server bundle.
		 *
		 * @since 4.11.102
		 */
		public function enqueue_scripts() {

			if ( wp_script_is( 'pa-angie-server', 'enqueued' ) || ! $this->current_user_can_access() ) {
				return;
			}

			wp_enqueue_script(
				'pa-angie-server',
				PREMIUM_ADDONS_URL . 'assets/editor/js/pa-angie-server.min.js',
				array(),
				PREMIUM_ADDONS_VERSION,
				true
			);

			wp_localize_script(
				'pa-angie-server',
				'premiumAddonsAngie',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
					'version' => PREMIUM_ADDONS_VERSION,
				)
			);
		}

		/**
		 * List active PA widgets: name, title, keywords, categories.
		 *
		 * @since 4.11.102
		 */
		public function ajax_widget_catalog() {

			$this->verify_request();

			$widgets = array();

			foreach ( \Elementor\Plugin::$instance->widgets_manager->get_widget_types() as $widget ) {

				if ( ! $this->is_pa_widget( $widget ) ) {
					continue;
				}

				$widgets[] = array(
					'name'       => $this->sanitize_agent_text( $widget->get_name() ),
					'title'      => $this->sanitize_agent_text( $widget->get_title() ),
					'keywords'   => array_map( array( $this, 'sanitize_agent_text' ), (array) $widget->get_keywords() ),
					'categories' => array_map( array( $this, 'sanitize_agent_text' ), (array) $widget->get_categories() ),
				);
			}

			wp_send_json_success( array( 'widgets' => $widgets ) );
		}

		/**
		 * Control schema for one PA widget, delegated to the discovery ability.
		 *
		 * @since 4.11.102
		 */
		public function ajax_widget_schema() {

			$this->verify_request();

			$widget_name = isset( $_POST['widget'] ) ? sanitize_text_field( wp_unslash( $_POST['widget'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$tab         = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'content'; // phpcs:ignore WordPress.Security.NonceVerification.Missing

			$widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $widget_name );

			if ( ! $widget || ! $this->is_pa_widget( $widget ) ) {
				wp_send_json_error( __( 'Widget not found.', 'premium-addons-for-elementor' ), 404 );
			}

			$schema = ( new Get_Widget_Schema() )->execute(
				array(
					'element' => $widget_name,
					'tab'     => $tab,
				)
			);

			if ( is_wp_error( $schema ) ) {
				$data   = $schema->get_error_data();
				$status = isset( $data['status'] ) ? (int) $data['status'] : 400;
				wp_send_json_error( $schema->get_error_message(), $status );
			}

			wp_send_json_success( $schema );
		}

		/**
		 * List Premium Templates, delegated to the templates ability.
		 *
		 * @since 4.11.102
		 */
		public function ajax_list_templates() {

			$this->verify_request();

			$input = array();

			foreach ( array( 'category', 'keyword' ) as $list_param ) {
				if ( ! empty( $_POST[ $list_param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$decoded = json_decode( sanitize_text_field( wp_unslash( $_POST[ $list_param ] ) ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

					if ( is_array( $decoded ) ) {
						$input[ $list_param ] = array_map( 'sanitize_key', $decoded );
					}
				}
			}

			foreach ( array( 'page', 'per_page' ) as $int_param ) {
				if ( isset( $_POST[ $int_param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$input[ $int_param ] = absint( $_POST[ $int_param ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
				}
			}

			if ( isset( $_POST['pro'] ) && '' !== $_POST['pro'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$input['pro'] = '1' === $_POST['pro']; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}

			$result = ( new List_Premium_Templates() )->execute( $input );

			if ( is_wp_error( $result ) ) {
				$data   = $result->get_error_data();
				$status = isset( $data['status'] ) ? (int) $data['status'] : 400;
				wp_send_json_error( $result->get_error_message(), $status );
			}

			wp_send_json_success( $result );
		}

		/**
		 * Cached catalog meta for one template — the dependencies map the browser
		 * insert flow feeds to premium_inner_template.
		 *
		 * @since 4.11.102
		 */
		public function ajax_template_meta() {

			$this->verify_request();

			$template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

			if ( ! $template_id ) {
				wp_send_json_error( __( 'A template_id is required.', 'premium-addons-for-elementor' ), 400 );
			}

			$meta = Templates_Api::get_meta( $template_id );

			if ( null === $meta ) {
				wp_send_json_error( __( 'No catalog data is cached for this template yet. Call list-premium-templates first, then retry.', 'premium-addons-for-elementor' ), 404 );
			}

			wp_send_json_success( $meta );
		}

		/**
		 * Whether the current user gets the Angie server at all.
		 *
		 * Author+ floor (edit_posts alone is the Contributor floor, below the real
		 * editor boundary) plus use_angie, so the bundle only loads where Angie's
		 * own app does.
		 *
		 * @since 4.11.102
		 *
		 * @return bool
		 */
		public function current_user_can_access() {

			$can = current_user_can( 'edit_posts' ) && current_user_can( 'edit_published_posts' ) && current_user_can( 'use_angie' );

			// Inverted name upstream: TRUE means the user is NOT role-excluded from editing.
			if ( $can && class_exists( '\Elementor\User' ) && ! \Elementor\User::is_current_user_in_editing_black_list() ) {
				$can = false;
			}

			return (bool) apply_filters( 'premium_addons/angie/current_user_can_access', $can );
		}

		/**
		 * Shared request guard for the AJAX endpoints.
		 *
		 * @since 4.11.102
		 */
		private function verify_request() {

			$nonce = isset( $_POST['nonce'] ) ? sanitize_key( wp_unslash( $_POST['nonce'] ) ) : '';

			if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
				wp_send_json_error( __( 'Invalid nonce.', 'premium-addons-for-elementor' ), 403 );
			}

			if ( ! $this->current_user_can_access() ) {
				wp_send_json_error( __( 'Insufficient permissions.', 'premium-addons-for-elementor' ), 403 );
			}

			if ( ! did_action( 'elementor/loaded' ) ) {
				wp_send_json_error( __( 'Elementor is not loaded.', 'premium-addons-for-elementor' ), 400 );
			}
		}

		/**
		 * Whether a widget belongs to Premium Addons.
		 *
		 * Membership is the self-declared 'premium-elements' category, which
		 * covers PA and PA Pro widgets with no path coupling. The filter lets
		 * site owners adjust what the AI agent gets described.
		 *
		 * @since 4.11.102
		 *
		 * @param object $widget widget type instance.
		 *
		 * @return bool
		 */
		private function is_pa_widget( $widget ) {

			$is_pa = $widget instanceof \Elementor\Widget_Base
				&& in_array( 'premium-elements', (array) $widget->get_categories(), true );

			// Whatever this returns true for is described to a write-capable AI agent — only add widgets you own.
			return (bool) apply_filters( 'premium_addons/angie/is_pa_widget', $is_pa, $widget );
		}

		/**
		 * Sanitize a string before it reaches the agent's context.
		 *
		 * The control-byte class stops a value faking a new line of instructions
		 * in the tool result; byte class only — multibyte-safe without /u.
		 *
		 * @since 4.11.102
		 *
		 * @param mixed $value raw value.
		 *
		 * @return string
		 */
		private function sanitize_agent_text( $value ) {

			$value = wp_strip_all_tags( (string) $value );
			$value = preg_replace( '/[\x00-\x1F\x7F]+/', ' ', $value );
			$value = trim( preg_replace( '/\s+/', ' ', $value ) );

			return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 200 ) : substr( $value, 0, 200 );
		}

		/**
		 * Creates and returns an instance of the class.
		 *
		 * @since 4.11.102
		 *
		 * @return object
		 */
		public static function get_instance() {

			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}
	}
}
