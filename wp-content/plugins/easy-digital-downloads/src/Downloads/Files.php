<?php
/**
 * Ownership rules for a download's file list.
 *
 * @package     EDD\Downloads
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Downloads;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Decides whether a file row may name the attachment or file it carries.
 *
 * A write with no current user is programmatic, so there is no row owner to check a reference
 * against, and a reference that names an existing attachment or file is allowed as it stands.
 *
 * @since 3.7.1
 */
class Files {

	/**
	 * Whether the file row being saved may name the given attachment.
	 *
	 * @since 3.7.1
	 *
	 * @param int $attachment_id The attachment named by the file row.
	 * @return bool
	 */
	public static function can_reference_attachment( $attachment_id ) {
		$attachment = get_post( absint( $attachment_id ) );

		if ( empty( $attachment ) || 'attachment' !== $attachment->post_type ) {
			return false;
		}

		if ( ! is_user_logged_in() ) {
			return true;
		}

		if ( get_current_user_id() === (int) $attachment->post_author ) {
			return true;
		}

		// The same capability that admits another author's file admits their attachment.
		return current_user_can( 'edit_post', $attachment->ID ) || current_user_can( 'edit_others_products' );
	}

	/**
	 * Whether the file row being saved may name the given file path or URL.
	 *
	 * A file this server does not read is the store's own decision. A local file is held to the
	 * attachment it resolves to; one that resolves to no attachment needs the capability to edit
	 * others' products.
	 *
	 * @since 3.7.1
	 *
	 * @param string $file The path or URL named by the file row.
	 * @return bool
	 */
	public static function can_reference_file( $file ) {
		$reference = Process::local_reference( $file );

		if ( false === $reference ) {
			return true;
		}

		$attachment_id = self::resolve_attachment_id( $reference );

		if ( ! empty( $attachment_id ) ) {
			return self::can_reference_attachment( $attachment_id );
		}

		if ( ! is_user_logged_in() ) {
			return true;
		}

		return current_user_can( 'edit_others_products' );
	}

	/**
	 * The attachment a local file path or URL belongs to, or 0.
	 *
	 * @since 3.7.1
	 *
	 * @param string $file The path or URL.
	 * @return int
	 */
	private static function resolve_attachment_id( $file ) {
		$attachment_id = self::lookup_attachment_id( $file );

		if ( $attachment_id ) {
			return $attachment_id;
		}

		// An image size variant is served from its own file, which WordPress does not index, so it
		// is looked up through the attachment it was generated from and checked against its sizes.
		$original = self::without_size_suffix( $file );

		if ( $original === $file ) {
			return 0;
		}

		$attachment_id = self::lookup_attachment_id( $original );

		if ( ! $attachment_id ) {
			return 0;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$variants = empty( $metadata['sizes'] ) ? array() : wp_list_pluck( $metadata['sizes'], 'file' );

		return in_array( wp_basename( Process::without_query_string( $file ) ), $variants, true ) ? $attachment_id : 0;
	}

	/**
	 * The attachment WordPress indexes for a file path or URL, or 0.
	 *
	 * @since 3.7.1
	 *
	 * @param string $file The path or URL.
	 * @return int
	 */
	private static function lookup_attachment_id( $file ) {
		$file          = Process::without_query_string( $file );
		$attachment_id = attachment_url_to_postid( $file );

		if ( $attachment_id ) {
			return $attachment_id;
		}

		// attachment_url_to_postid() matches URLs only, so a path under the uploads directory is
		// converted to the URL it is served from and looked up again.
		$upload_dir = wp_get_upload_dir();
		$uploads    = wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) );
		$file       = wp_normalize_path( $file );

		if ( empty( $uploads ) || 0 !== strpos( $file, $uploads ) ) {
			return 0;
		}

		return attachment_url_to_postid( trailingslashit( $upload_dir['baseurl'] ) . substr( $file, strlen( $uploads ) ) );
	}

	/**
	 * A file reference without the `-{width}x{height}` suffix an image size variant carries.
	 *
	 * @since 3.7.1
	 *
	 * @param string $file The path or URL.
	 * @return string
	 */
	private static function without_size_suffix( $file ) {
		return preg_replace( '/-\d+x\d+(?=\.[a-z0-9]+(?:\?.*)?$)/i', '', $file );
	}
}
