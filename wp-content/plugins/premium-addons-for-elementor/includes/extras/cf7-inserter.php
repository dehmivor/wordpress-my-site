<?php

namespace PremiumAddons\Includes\Extras;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/*
 * Premium Cross Domain Copy Paste Feature
 */
if ( ! class_exists( 'CF7_Inserter' ) ) {

	/**
	 * Define CF7_Inserter class
	 */
	class CF7_Inserter {

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

			add_action( 'wp_ajax_insert_cf_form', array( $this, 'insert_cf_form' ) );
		}

		/**
		 * Insert Contact Form 7 Form
		 *
		 * @since 4.10.2
		 * @access public
		 *
		 * @return void
		 */
		public function insert_cf_form() {

			check_ajax_referer( 'pa-editor', 'security' );

			if ( ! isset( $_GET['preset'] ) ) {
				wp_send_json_error();
			}

			$preset = sanitize_text_field( wp_unslash( $_GET['preset'] ) );

			$current_user = wp_get_current_user();

			$props = array(
				'form'                => self::get_cf_form_body( $preset ),
				'mail'                => array(
					'active'             => 1,
					'subject'            => '[_site_title] "[your-subject]"',
					'sender'             => '[_site_title]',
					'recipient'          => '[_site_admin_email]',
					'body'               => 'From: [your-name] [your-email]' . PHP_EOL .
							'Subject: [your-subject]' . PHP_EOL . PHP_EOL .
							'Message Body:' . PHP_EOL . '[your-message]' . PHP_EOL . PHP_EOL .
							'--' . PHP_EOL .
							'This e-mail was sent from a contact form on [_site_title] ([_site_url])',
					'additional_headers' => 'Reply-To: [your-email]',
					'attachments'        => '',
					'use_html'           => '',
					'exclude_blank'      => '',
				),
				'mail_2'              => array(
					'active'             => '',
					'subject'            => '[_site_title] "[your-subject]"',
					'sender'             => '[_site_title]',
					'recipient'          => '[your-email]',
					'body'               => 'Message Body:' . PHP_EOL . '[your-message]' . PHP_EOL . PHP_EOL .
							'--' . PHP_EOL .
							'This e-mail was sent from a contact form on [_site_title] ([_site_url])',
					'additional_headers' => 'Reply-To: [_site_admin_email]',
					'attachments'        => '',
					'use_html'           => '',
					'exclude_blank'      => '',
				),
				'messages'            => array(
					'mail_sent_ok'             => 'Thank you for your message. It has been sent.',
					'mail_sent_ng'             => 'There was an error trying to send your message. Please try again later.',
					'validation_error'         => 'One or more fields have an error. Please check and try again.',
					'spam'                     => 'There was an error trying to send your message. Please try again later.',
					'accept_terms'             => 'You must accept the terms and conditions before sending your message.',
					'invalid_required'         => 'Please fill out this field.',
					'invalid_too_long'         => 'This field has a too long input.',
					'invalid_too_short'        => 'This field has a too short input.',
					'upload_failed'            => 'There was an unknown error uploading the file.',
					'upload_file_type_invalid' => 'You are not allowed to upload files of this type.',
					'upload_file_too_large'    => 'The uploaded file is too large.',
					'upload_failed_php_error'  => 'There was an error uploading the file.',
					'invalid_date'             => 'Please enter a date in YYYY-MM-DD format.',
					'date_too_early'           => 'This field has a too early date.',
					'date_too_late'            => 'This field has a too late date.',
					'invalid_number'           => 'Please enter a number.',
					'number_too_small'         => 'This field has a too small number.',
					'number_too_large'         => 'This field has a too large number.',
					'quiz_answer_not_correct'  => 'The answer to the quiz is incorrect.',
					'invalid_email'            => 'Please enter an email address.',
					'invalid_url'              => 'Please enter a URL.',
					'invalid_tel'              => 'Please enter a telephone number.',
				),
				'additional_settings' => '',
			);

			$post_content = implode( "\n", wpcf7_array_flatten( $props ) );

			$args = array(
				'post_status'  => 'publish',
				'post_type'    => 'wpcf7_contact_form',
				'post_content' => $post_content,
				'post_author'  => $current_user->ID,
				'post_title'   => sprintf(
					__( 'Form | %s', 'premium-addons-for-elementor' ),
					gmdate( 'Y-m-d H:i' )
				),
			);

			$post_id = wp_insert_post( $args );

			foreach ( $props as $prop => $value ) {
				update_post_meta(
					$post_id,
					'_' . $prop,
					wpcf7_normalize_newline_deep( $value )
				);
			}

			$form_id = wpcf7_generate_contact_form_hash( $post_id );

			add_post_meta( $post_id, '_hash', $form_id, true );

			wp_send_json_success( substr( $form_id, 0, 7 ) );
		}

		/**
		 * Get Contact Form Body
		 *
		 * @since 4.10.2
		 * @access public
		 *
		 * @param string $preset form preset.
		 *
		 * @return void
		 */
		public static function get_cf_form_body( $preset ) {

			$forms_array = array(

				'preset1' => '<div class="premium-cf-full"><label class="premium-cf-label">Email</label>
				[email* email-1 class:premium-cf-field placeholder "john@smith.com"]</div>
				[submit "Subscribe"]',

				'preset2' => '<div class="premium-cf-full"><label class="premium-cf-label">Name</label>
				[text* text-1 class:premium-cf-field placeholder "John Smith"]</div>

				<div class="premium-cf-full"><label class="premium-cf-label">Email</label>
				[email* email-1 class:premium-cf-field placeholder "john@smith.com"]</div>

				[submit "Send"]',

				'preset3' => '<div class="premium-cf-full"><label class="premium-cf-label">Name</label>
				[text* text-1 class:premium-cf-field placeholder "John Smith"]</div>

				<div class="premium-cf-full"><label class="premium-cf-label">Email</label>
				[email* email-1 class:premium-cf-field placeholder "john@smith.com"]</div>

				<div class="premium-cf-full"><label class="premium-cf-label">Message</label>
				[textarea* textarea-1 class:premium-cf-field placeholder "Enter your message here..."]</div>

				[submit "Send"]',

				'preset4' => '<div class="premium-cf-half"><label class="premium-cf-label">Name</label>
				[text* text-1 class:premium-cf-field placeholder "John Smith"]</div>

				<div class="premium-cf-half"><label class="premium-cf-label">Email</label>
				[email* email-1 class:premium-cf-field placeholder "john@smith.com"]</div>

				<div class="premium-cf-full"><label class="premium-cf-label">Message</label>
				[textarea* textarea-1 class:premium-cf-field placeholder "Enter your message here..."]</div>

				[submit "Send"]',

				'preset5' => '<div class="premium-cf-half"><label class="premium-cf-label">First Name</label>
				[text* text-1 class:premium-cf-field placeholder "John"]</div>

				<div class="premium-cf-half"><label class="premium-cf-label">Last Name</label>
				[text* text-2 class:premium-cf-field placeholder "Smith"]</div>

				<div class="premium-cf-half"><label class="premium-cf-label">Email</label>
				[email* email-1 class:premium-cf-field placeholder "john@smith.com"]</div>

				<div class="premium-cf-half"><label class="premium-cf-label">Phone</label>
				[tel* tel-1 class:premium-cf-field placeholder "+13137262547"]</div>

				<div class="premium-cf-full"><label class="premium-cf-label">Gender</label>
				[select menu-1 "Male" "Female"]</div>

				<div class="premium-cf-full"><label class="premium-cf-label">Message</label>
				[textarea* textarea-1 class:premium-cf-field placeholder "Enter your message here..."]</div>
				[submit "Send"]',

				'preset6' => '<div class="premium-cf-half"><label class="premium-cf-label">First Name</label>
				[text* text-1 class:premium-cf-field placeholder "John"]</div>

				<div class="premium-cf-half"><label class="premium-cf-label">Last Name</label>
				[text* text-2 class:premium-cf-field placeholder "Smith"]</div>

				<div class="premium-cf-half"><label class="premium-cf-label">Email</label>
				[email* email-1 class:premium-cf-field placeholder "john@smith.com"]</div>

				<div class="premium-cf-half"><label class="premium-cf-label">Phone</label>
				[tel* tel-1 class:premium-cf-field placeholder "+13137262547"]</div>

				<div class="premium-cf-full"><label class="premium-cf-label">Company Size</label>
				[radio radio-1 default:1 "1-10 employees" "11-30 employees" "30-50 employees" "Above 50 employee"]
				</div>

				<div class="premium-cf-full"><label class="premium-cf-label">Message</label>
				[textarea* textarea-1 class:premium-cf-field placeholder "Enter your message here..."]</div>
				[submit "Send"]',

			);

			return $forms_array[ $preset ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
