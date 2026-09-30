<?php
/**
 * Download Files trait
 *
 * @package     EDD\Downloads\Traits
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Downloads\Traits;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

trait Files {

	/**
	 * The download files
	 *
	 * @since 2.2
	 * @var array
	 */
	private $files;

	/**
	 * Retrieve the file downloads.
	 *
	 * @since 2.2
	 * @since 3.7.1 The product's whole file list is what is memoized, so a named price
	 *                       option is applied on every call rather than only on the first one.
	 *
	 * @param int|null $variable_price_id The requested price ID, if any.
	 * @return array List of download files
	 */
	public function get_files( $variable_price_id = null ) {

		// Bundled products are not allowed to have files.
		if ( $this->is_bundled_download() ) {
			return array();
		}

		$files = $this->get_all_files();

		if ( ! is_null( $variable_price_id ) && $this->has_variable_prices() ) {
			$files = $this->filter_files_by_condition( $files, $variable_price_id );
		}

		return apply_filters( 'edd_download_files', $files, $this->ID, $variable_price_id );
	}

	/**
	 * Retrieves the files one price option is entitled to download.
	 *
	 * A null price option here means the purchase records none, so a variable-priced product is
	 * entitled only to the files carrying no price option restriction. That is the difference
	 * from get_files(), which returns the whole list when no option is named, and which is what
	 * the download editor, the exporters and the APIs want.
	 *
	 * @since 3.7.1
	 *
	 * @param int|null $price_id The price option, or null when the purchase records none.
	 * @return array List of download files.
	 */
	public function get_files_for_price_id( $price_id ) {

		// Bundled products are not allowed to have files.
		if ( $this->is_bundled_download() ) {
			return array();
		}

		$all_files = $this->get_all_files();
		$files     = $all_files;

		// A product flagged variable priced with no price options has none that could be missing.
		if ( $this->has_variable_prices() && ! empty( $this->get_prices() ) ) {
			$files = $this->filter_files_for_entitlement( $files, $price_id );
		}

		$files = apply_filters( 'edd_download_files', $files, $this->ID, $price_id );

		/**
		 * Filters the files one price option is entitled to download.
		 *
		 * Runs only where a purchase's entitlement is being resolved, unlike `edd_download_files`,
		 * so a store can restore a product's whole list for purchases which record no price
		 * option without also widening the download editor, the exporters or the APIs.
		 *
		 * @since 3.7.1
		 *
		 * @param array    $files       The files this price option may download.
		 * @param int      $download_id The product ID.
		 * @param int|null $price_id    The price option, or null when the purchase records none.
		 * @param array    $all_files   The product's whole file list, before any restriction.
		 */
		return apply_filters( 'edd_download_files_for_price_id', $files, $this->ID, $price_id, $all_files );
	}

	/**
	 * Retrieve the price option that has access to the specified file.
	 *
	 * @since 2.2
	 * @return int|string
	 */
	public function get_file_price_condition( $file_key = 0 ) {
		$files     = $this->get_files();
		$condition = isset( $files[ $file_key ]['condition'] )
			? $files[ $file_key ]['condition']
			: 'all';

		return apply_filters( 'edd_get_file_price_condition', $condition, $this->ID, $files );
	}

	/**
	 * Retrieves the product's whole file list.
	 *
	 * @since 3.7.1
	 * @return array
	 */
	private function get_all_files() {
		if ( ! isset( $this->files ) ) {
			$download_files = get_post_meta( $this->ID, 'edd_download_files', true );
			$this->files    = empty( $download_files ) ? array() : $download_files;
		}

		return $this->files;
	}

	/**
	 * Restricts a file list to the files whose condition names a price option.
	 *
	 * A file carrying no condition at all names no price option, so it is not returned here.
	 * get_files() has read it that way since 2.2.
	 *
	 * @since 3.7.1
	 *
	 * @param array $files    The product's whole file list.
	 * @param int   $price_id The price option named by the caller.
	 * @return array
	 */
	private function filter_files_by_condition( $files, $price_id ) {
		$filtered = array();

		foreach ( $files as $key => $file_info ) {
			if ( isset( $file_info['condition'] ) && $this->file_names_price_option( $file_info, $price_id ) ) {
				$filtered[ $key ] = $file_info;
			}
		}

		return $filtered;
	}

	/**
	 * Restricts a file list to what one price option is entitled to.
	 *
	 * @since 3.7.1
	 *
	 * @param array    $files    The product's whole file list.
	 * @param int|null $price_id The price option, or null when the purchase records none.
	 * @return array
	 */
	private function filter_files_for_entitlement( $files, $price_id ) {
		$filtered = array();

		foreach ( $files as $key => $file_info ) {
			if ( $this->file_is_unrestricted( $file_info ) || $this->file_names_price_option( $file_info, $price_id ) ) {
				$filtered[ $key ] = $file_info;
			}
		}

		return $filtered;
	}

	/**
	 * Whether a file's condition names a given price option.
	 *
	 * @since 3.7.1
	 *
	 * @param array    $file_info A single entry from the product's file list.
	 * @param int|null $price_id  The price option to match against.
	 * @return bool
	 */
	private function file_names_price_option( $file_info, $price_id ) {
		if ( ! isset( $file_info['condition'] ) || is_null( $price_id ) ) {
			return false;
		}

		if ( 'all' === $file_info['condition'] ) {
			return true;
		}

		return (string) $file_info['condition'] === (string) $price_id;
	}

	/**
	 * Whether a file carries no price option restriction at all.
	 *
	 * `all` is the stored value for a file every option may download, and an absent or empty
	 * condition is what get_file_price_condition() already reads as `all`. Price option `0` is a
	 * real restriction, so it is deliberately not caught here.
	 *
	 * @since 3.7.1
	 *
	 * @param array $file_info A single entry from the product's file list.
	 * @return bool
	 */
	private function file_is_unrestricted( $file_info ) {
		if ( ! isset( $file_info['condition'] ) ) {
			return true;
		}

		return in_array( $file_info['condition'], array( 'all', '', false ), true );
	}
}
