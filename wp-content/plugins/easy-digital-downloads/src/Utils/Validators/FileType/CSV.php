<?php
/**
 * CSV File Type Validator.
 *
 * @package     EDD\Utils\Validators\FileType
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

declare(strict_types=1);

namespace EDD\Utils\Validators\FileType;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Validates CSV uploads.
 *
 * @since 3.6.9
 */
final class CSV extends Base {

	/**
	 * MIME types accepted as CSV content.
	 *
	 * A CSV authored in a plain-text editor is detected as text/plain, so it is
	 * accepted alongside the CSV-specific types.
	 *
	 * @since 3.6.9
	 * @var string[]
	 */
	const MIME_TYPES = array(
		'text/csv',
		'text/comma-separated-values',
		'text/plain',
		'text/anytext',
		'application/csv',
		'application/excel',
		'application/vnd.ms-excel',
		'application/vnd.msexcel',
	);

	/**
	 * Accepted filename extensions for delimited-text imports.
	 *
	 * Returned as an `extension => mime` map for wp_check_filetype(). Alongside
	 * comma-separated (.csv), the plain-text (.txt) variant is accepted.
	 *
	 * @since 3.6.9
	 * @since 3.6.9.1 Added the txt extension.
	 *
	 * @return array
	 */
	protected function extensions(): array {
		return array(
			'csv' => 'text/csv',
			'txt' => 'text/plain',
		);
	}

	/**
	 * Accepted real MIME types for CSV content.
	 *
	 * @since 3.6.9
	 *
	 * @return string[]
	 */
	protected function mime_types(): array {
		return self::MIME_TYPES;
	}
}
