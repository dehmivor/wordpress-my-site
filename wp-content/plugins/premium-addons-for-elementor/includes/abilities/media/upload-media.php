<?php
/**
 * Upload Media.
 *
 * Downloads an image from a URL into the WordPress media library and returns the
 * attachment ID and URL for a build ability to place.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Media;

use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Includes\Abilities\Helpers;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 */
class Upload_Media implements Ability_Handler {

	/**
	 * Attachment meta key holding the URL a file was sideloaded from.
	 */
	const SOURCE_URL_META = '_pa_media_source_url';

	/**
	 * Largest file accepted from a remote URL.
	 */
	const MAX_FILE_SIZE = 5 * MB_IN_BYTES;

	/**
	 * Seconds to wait for the download. Core defaults to 300, far too long for a
	 * tool call an agent is waiting on.
	 */
	const DOWNLOAD_TIMEOUT = 30;

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'upload-media';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Upload Image to Media Library', 'premium-addons-for-elementor' ),
			'description'         => __( 'Adds an image from a URL to the media library. Only run it after the user has approved the upload.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-media',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'source_url', 'consent' ),
				'properties'           => array(
					'source_url' => array(
						'type'        => 'string',
						'description' => __( 'The public http(s) URL of the image to download. Private, loopback and internal addresses are rejected.', 'premium-addons-for-elementor' ),
					),
					'filename'   => array(
						'type'        => 'string',
						'description' => __( 'The filename to store the image under, with its extension. Derived from the source URL when omitted.', 'premium-addons-for-elementor' ),
					),
					'alt'        => array(
						'type'        => 'string',
						'description' => __( 'The alt text to store on the new attachment. Describe what the image shows.', 'premium-addons-for-elementor' ),
					),
					'title'      => array(
						'type'        => 'string',
						'description' => __( 'The attachment title. Taken from the filename when omitted.', 'premium-addons-for-elementor' ),
					),
					'consent'    => array(
						'type'        => 'boolean',
						'description' => __( 'Must be true. Confirms the user explicitly approved adding this file to their media library. Never set it without asking them first.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The attachment the image was stored as.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'id'        => array(
						'type'        => 'integer',
						'description' => __( 'The attachment ID. Pass it with the URL as the media control value in premium-addons/insert-widget or premium-addons/update-element-settings.', 'premium-addons-for-elementor' ),
					),
					'url'       => array(
						'type'        => 'string',
						'description' => __( 'The full size image URL on this site.', 'premium-addons-for-elementor' ),
					),
					'width'     => array(
						'type'        => 'integer',
						'description' => __( 'The full size width in pixels, 0 when the file carries no dimensions.', 'premium-addons-for-elementor' ),
					),
					'height'    => array(
						'type'        => 'integer',
						'description' => __( 'The full size height in pixels, 0 when the file carries no dimensions.', 'premium-addons-for-elementor' ),
					),
					'mime_type' => array(
						'type'        => 'string',
						'description' => __( 'The stored MIME type.', 'premium-addons-for-elementor' ),
					),
					'alt'       => array(
						'type'        => 'string',
						'description' => __( 'The alt text stored on the attachment.', 'premium-addons-for-elementor' ),
					),
					'reused'    => array(
						'type'        => 'boolean',
						'description' => __( 'True when this URL was already in the library, so the existing attachment was returned instead of downloading it again. Any alt text or title passed with the call is still applied.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'permission_callback' => function () {
				return Admin_Helper::check_user_can( 'upload_files' );
			},
			// Idempotent: a repeat call with the same source_url returns the
			// attachment already stored for it rather than adding a second copy.
			'meta'                => array(
				'show_in_rest' => true,
				'mcp'          => array( 'public' => true ),
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
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

		if ( ! isset( $input['consent'] ) ) {
			return new \WP_Error(
				'premium_addons_missing_consent',
				__( 'Adding a file to the media library needs the user\'s explicit approval. Ask them first, then call this again with consent set to true.', 'premium-addons-for-elementor' )
			);
		}

		// Strict compare: the gate is a deliberate confirmation, so a truthy
		// "false" string or a 1 must not pass for it.
		if ( true !== $input['consent'] ) {
			return new \WP_Error(
				'premium_addons_invalid_consent',
				__( 'Consent must be exactly true. Ask the user to approve adding this file to their media library, then call this again.', 'premium-addons-for-elementor' )
			);
		}

		$source_url = isset( $input['source_url'] ) ? trim( $input['source_url'] ) : '';

		if ( '' === $source_url ) {
			return new \WP_Error(
				'premium_addons_missing_source_url',
				__( 'A source URL is required to add an image to the media library.', 'premium-addons-for-elementor' )
			);
		}

		// wp_http_validate_url() rejects anything that is not public http(s) —
		// other schemes, loopback, private ranges and non-standard ports.
		if ( ! wp_http_validate_url( $source_url ) ) {
			return new \WP_Error(
				'premium_addons_invalid_source_url',
				__( 'The source URL must be a public http(s) address. Local, private and internal addresses are not allowed.', 'premium-addons-for-elementor' )
			);
		}

		$existing = $this->find_by_source_url( $source_url );

		if ( $existing ) {

			// The file is already here, but this call may carry better alt text or
			// a better title than the one that first brought it in.
			$this->apply_input_meta( $existing, $input );

			return array_merge( Helpers::format_attachment( $existing ), array( 'reused' => true ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp_file = $this->download( $source_url );

		if ( is_wp_error( $tmp_file ) ) {
			return $tmp_file;
		}

		$filename = ! empty( $input['filename'] ) ? sanitize_file_name( $input['filename'] ) : $this->filename_from_url( $source_url );
		$error    = $this->validate_file( $tmp_file, $filename );

		if ( is_wp_error( $error ) ) {
			wp_delete_file( $tmp_file );
			return $error;
		}

		// No $desc, so the title falls back to the filename; apply_input_meta()
		// is the single writer for a caller-supplied one, new and reused alike.
		$attachment_id = media_handle_sideload(
			array(
				'name'     => $filename,
				'tmp_name' => $tmp_file,
			)
		);

		// A rejected sideload leaves the temp file on disk.
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp_file );
			return $attachment_id;
		}

		$this->apply_input_meta( $attachment_id, $input );

		update_post_meta( $attachment_id, self::SOURCE_URL_META, esc_url_raw( $source_url ) );

		return array_merge( Helpers::format_attachment( $attachment_id ), array( 'reused' => false ) );
	}

	/**
	 * Apply the caller's alt text and title to an attachment.
	 *
	 * @param int   $attachment_id The attachment ID.
	 * @param array $input         The ability input.
	 *
	 * @return void
	 */
	private function apply_input_meta( $attachment_id, $input ) {

		if ( ! empty( $input['alt'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt'] ) );
		}

		if ( ! empty( $input['title'] ) ) {

			$title = sanitize_text_field( $input['title'] );

			// wp_update_post() re-stamps post_modified and fires save_post even when
			// the title is unchanged, so a repeated call would move the library this
			// ability claims idempotence over. update_post_meta() above already
			// no-ops on an unchanged value.
			if ( get_post_field( 'post_title', $attachment_id, 'raw' ) !== $title ) {
				wp_update_post(
					array(
						'ID'         => $attachment_id,
						'post_title' => $title,
					)
				);
			}
		}
	}

	/**
	 * Download a remote file to a temp path, bounded by the size cap.
	 *
	 * download_url() takes no size argument and streams the whole body to disk,
	 * so a huge response would land before filesize() could reject it. The HTTP
	 * layer does accept a byte limit and stops writing past it, but only through
	 * the request args — hence the filter around the one call. The limit is set
	 * one byte over the cap so a truncated body still reads as over the cap and
	 * validate_file() rejects it.
	 *
	 * Helpers::fetch_public_url() adds the second guard, rejecting the addresses
	 * core treats as public on every redirect hop. The downloaded body could never
	 * reach the library anyway — validate_file() only accepts real images — but the
	 * distinct error codes and response timing would still answer "is this internal
	 * host up" for whoever drives the agent.
	 *
	 * @param string $source_url The URL to download.
	 *
	 * @return string|\WP_Error The temp file path, or an error.
	 */
	private function download( $source_url ) {

		$limit_size = function ( $args ) {
			$args['limit_response_size'] = self::MAX_FILE_SIZE + 1;

			return $args;
		};

		add_filter( 'http_request_args', $limit_size );

		$tmp_file = Helpers::fetch_public_url(
			'premium_addons_invalid_source_url',
			function () use ( $source_url ) {
				return download_url( $source_url, self::DOWNLOAD_TIMEOUT );
			}
		);

		remove_filter( 'http_request_args', $limit_size );

		return $tmp_file;
	}

	/**
	 * Find an attachment already sideloaded from this URL.
	 *
	 * An agent that retries would otherwise fill the library with copies of the
	 * same file, so the source URL is recorded on upload and matched here.
	 *
	 * @param string $source_url The source URL.
	 * @return int The attachment ID, or 0 when the URL is new.
	 */
	private function find_by_source_url( $source_url ) {

		$query = new \WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_key'               => self::SOURCE_URL_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'             => esc_url_raw( $source_url ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return ! empty( $query->posts ) ? (int) $query->posts[0] : 0;
	}

	/**
	 * Check a downloaded file is an image this site accepts.
	 *
	 * Reads the type out of the file contents, so a script renamed to .png is
	 * rejected and an image URL carrying no extension at all — the shape most CDNs
	 * serve — still resolves. wp_check_filetype_and_ext() cannot do the second: it
	 * only inspects the bytes once the passed filename already names an image
	 * extension, so an extension-less name comes back as "not an image".
	 *
	 * wp_get_image_mime() reads the file header and only ever returns an image
	 * type, which covers the is-it-an-image question on its own. SVG carries no
	 * such header and so is rejected here, matching core's default.
	 *
	 * @param string $tmp_file The downloaded temp file path.
	 * @param string $filename The filename it will be stored under (by reference,
	 *                         re-extensioned to match the real contents).
	 *
	 * @return \WP_Error|null Error when the file is not an accepted image.
	 */
	private function validate_file( $tmp_file, &$filename ) {

		if ( filesize( $tmp_file ) > self::MAX_FILE_SIZE ) {
			return new \WP_Error(
				'premium_addons_invalid_file_size',
				sprintf(
					/* translators: %s: the maximum file size. */
					__( 'The image is larger than the %s limit. Use a smaller or more compressed file.', 'premium-addons-for-elementor' ),
					size_format( self::MAX_FILE_SIZE )
				)
			);
		}

		$mime_type = wp_get_image_mime( $tmp_file );

		// get_allowed_mime_types() is keyed by an extension pattern (jpg|jpeg|jpe),
		// so the lookup answers "may this site store it" and names it in one step.
		$extensions = $mime_type ? array_search( $mime_type, get_allowed_mime_types(), true ) : false;

		if ( ! $extensions ) {
			return new \WP_Error(
				'premium_addons_invalid_file_type',
				__( 'The downloaded file is not an image type this site accepts. Check that the URL points directly at an image file. SVG files are not supported.', 'premium-addons-for-elementor' )
			);
		}

		// The URL often implies no extension, or the wrong one.
		if ( wp_check_filetype( $filename )['type'] !== $mime_type ) {
			$filename = pathinfo( $filename, PATHINFO_FILENAME ) . '.' . strtok( $extensions, '|' );
		}

		return null;
	}

	/**
	 * Derive a filename from a source URL.
	 *
	 * @param string $source_url The source URL.
	 * @return string
	 */
	private function filename_from_url( $source_url ) {

		$path     = wp_parse_url( $source_url, PHP_URL_PATH );
		$filename = is_string( $path ) ? sanitize_file_name( basename( $path ) ) : '';

		// A URL with no usable path segment still needs a name to sideload under;
		// validate_file() replaces it with the one the contents call for.
		return '' !== $filename ? $filename : 'image';
	}
}
