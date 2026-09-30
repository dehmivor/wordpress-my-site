<?php
/**
 * Download File Entitlement
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
 * Which of a product's files one price option may download.
 *
 * The rule is shared by the surfaces which list a purchase's files and by the file download
 * boundary, so a link is only offered where it will also be honored.
 *
 * @since 3.7.1
 */
class Entitlement {

	/**
	 * The product ID.
	 *
	 * @since 3.7.1
	 * @var int
	 */
	private $download_id;

	/**
	 * The price option, or null when the purchase records none.
	 *
	 * @since 3.7.1
	 * @var int|null
	 */
	private $price_id;

	/**
	 * The product this entitlement is for.
	 *
	 * @since 3.7.1
	 * @var \EDD_Download|false|null
	 */
	private $download;

	/**
	 * Constructor.
	 *
	 * @since 3.7.1
	 *
	 * @param int      $download_id The product ID.
	 * @param int|null $price_id    The price option this entitlement covers.
	 */
	public function __construct( $download_id, $price_id = null ) {
		$this->download_id = absint( $download_id );
		$this->price_id    = is_numeric( $price_id ) ? (int) $price_id : null;
	}

	/**
	 * Retrieves the files this price option may download.
	 *
	 * @since 3.7.1
	 * @return array
	 */
	public function get_files() {
		$download = $this->get_download();

		return $download ? $download->get_files_for_price_id( $this->price_id ) : array();
	}

	/**
	 * Whether a file is withheld from this price option.
	 *
	 * Only a file the product actually has can be withheld. A key which is not in the product's
	 * list at all is not this rule's business, so it is left to whichever caller resolves it: a
	 * bundle has no files of its own, and an extension may supply a list through the
	 * `edd_download_files` filter that the product's own meta never held.
	 *
	 * @since 3.7.1
	 *
	 * @param int|string $file_key The file's key in the product's file list.
	 * @return bool
	 */
	public function withholds_file( $file_key ) {
		$download = $this->get_download();

		if ( ! $download || ! array_key_exists( $file_key, $download->get_files() ) ) {
			return false;
		}

		return ! array_key_exists( $file_key, $this->get_files() );
	}

	/**
	 * The product, resolved once.
	 *
	 * @since 3.7.1
	 * @return \EDD_Download|false
	 */
	private function get_download() {
		if ( is_null( $this->download ) ) {
			$this->download = edd_get_download( $this->download_id );
		}

		return $this->download;
	}
}
