<?php
/**
 * Handles meta related filters/actions for downloads.
 *
 * @since 3.1.0.5
 */
namespace EDD\Admin\Downloads;

use EDD\EventManagement\SubscriberInterface;
use EDD\Downloads\Files;

class Meta implements SubscriberInterface {

	/**
	 * Returns an array of events that this subscriber wants to listen to.
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return array(
			'edd_metabox_save_edd_variable_prices' => 'variable_prices_value',
			'edd_metabox_save_edd_download_files'  => array( 'download_files_value', 10, 2 ),
			'edd_save_download'                    => array( 'bundled_conditions', 10, 2 ),
		);
	}

	/**
	 * Checks the variable prices array to weed out empty prices.
	 *
	 * @since 3.1.0.5
	 * @param array $prices
	 * @return array
	 */
	public function variable_prices_value( $prices ) {
		if ( empty( $prices ) ) {
			return false;
		}
		foreach ( $prices as $id => $price ) {
			if ( empty( $price['amount'] ) && empty( $price['name'] ) ) {
				unset( $prices[ $id ] );
				continue;
			}
		}

		return $prices;
	}


	/**
	 * Checks the download files array to weed out empty file options.
	 *
	 * A row is judged against the values the save will store, not the ones that were posted.
	 *
	 * @since 3.1.0.5
	 * @since 3.7.1 Added the `$post_id` parameter, so a newly referenced attachment or file can be held to the acting user's own files.
	 *
	 * @param array $files   The submitted file rows.
	 * @param int   $post_id The download being saved.
	 * @return array|false
	 */
	public function download_files_value( $files, $post_id = 0 ) {
		if ( empty( $files ) ) {
			return false;
		}

		$stored_references = $this->get_stored_references( $post_id );

		foreach ( $files as $id => $file ) {
			$row = $this->row_to_store( $file, $stored_references );

			if ( null === $row ) {
				unset( $files[ $id ] );
				continue;
			}

			$files[ $id ] = $row;
		}

		return $files;
	}

	/**
	 * Once the download is saved, if it's not a bundle, delete the product conditions meta.
	 *
	 * @since 3.1.0.5
	 * @param int     $post_id
	 * @param WP_Post $post
	 * @return void
	 */
	public function bundled_conditions( $post_id, $post ) {
		if ( ! get_post_meta( $post_id, '_edd_product_type', true ) ) {
			delete_post_meta( $post_id, '_edd_bundled_products_conditions' );
		}
	}

	/**
	 * The file row to store, or null when the row is not stored at all.
	 *
	 * A reference the product already carries was checked when it was added, so only a newly
	 * referenced attachment or file is held to the acting user's own files.
	 *
	 * @since 3.7.1
	 *
	 * @param array $row               The submitted file row.
	 * @param array $stored_references The attachments and files the download already references.
	 * @return array|null
	 */
	private function row_to_store( $row, $stored_references ) {
		if ( empty( $row['name'] ) && empty( $row['file'] ) ) {
			return null;
		}

		if ( ! empty( $row['attachment_id'] ) && ! in_array( (int) $row['attachment_id'], $stored_references['attachment_ids'], true ) && ! Files::can_reference_attachment( $row['attachment_id'] ) ) {
			unset( $row['attachment_id'] );
		}

		$posted_file = isset( $row['file'] ) && is_string( $row['file'] ) ? $row['file'] : '';

		// Judge the value update_post_meta() and the meta sanitizer will store, not the one that was posted.
		$file_to_store = $this->file_value_to_store( $posted_file );

		// A list cannot be a path, and realpath() refuses a null byte outright, so neither has a stored form.
		$unstorable = ( isset( $row['file'] ) && ! is_string( $row['file'] ) ) || false !== strpos( $file_to_store, "\0" );
		if ( $unstorable ) {
			unset( $row['file'] );
			$file_to_store = '';
		}

		if ( '' !== $file_to_store && ! in_array( $file_to_store, $stored_references['files'], true ) && ! Files::can_reference_file( $file_to_store ) ) {
			// Delivery reads the file value, so a row without one is not a download.
			return null;
		}

		// A posted file the store cannot hold, or the sanitizer empties, leaves the row naming nothing to deliver.
		if ( '' === $file_to_store && ( $unstorable || '' !== $posted_file ) && empty( $row['attachment_id'] ) ) {
			return null;
		}

		return $row;
	}

	/**
	 * The file value the metabox save will store for a submitted row.
	 *
	 * Mirrors the unslash in update_metadata() and the trim and strip in
	 * EDD_Register_Meta::sanitize_files().
	 *
	 * @since 3.7.1
	 *
	 * @param string $file The submitted file value.
	 * @return string
	 */
	private function file_value_to_store( $file ) {
		return sanitize_text_field( trim( wp_unslash( $file ) ) );
	}

	/**
	 * The attachments and files a download's stored rows already reference.
	 *
	 * @since 3.7.1
	 *
	 * @param int $post_id The download.
	 * @return array {
	 *     Both keys are always present.
	 *
	 *     @type int[]    $attachment_ids The attachments the stored rows name.
	 *     @type string[] $files          The file values the stored rows name.
	 * }
	 */
	private function get_stored_references( $post_id ) {
		$attachment_ids = array();
		$files          = array();

		foreach ( (array) get_post_meta( $post_id, 'edd_download_files', true ) as $row ) {
			if ( ! empty( $row['attachment_id'] ) ) {
				$attachment_ids[] = (int) $row['attachment_id'];
			}

			if ( ! empty( $row['file'] ) ) {
				$files[] = $row['file'];
			}
		}

		return array(
			'attachment_ids' => $attachment_ids,
			'files'          => $files,
		);
	}
}
