<?php
/**
 * JSON File Type Validator.
 *
 * @package     EDD\Utils\Validators\FileType
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

declare(strict_types=1);

namespace EDD\Utils\Validators\FileType;

use EDD\Utils\FileSystem;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Validates JSON uploads.
 *
 * @since 3.6.9
 */
final class JSON extends Base {

	/**
	 * Real MIME types accepted as JSON content.
	 *
	 * A JSON file authored in a plain-text editor is commonly detected as
	 * text/plain, so it is accepted alongside the JSON-specific types.
	 *
	 * @since 3.6.9
	 * @var string[]
	 */
	const MIME_TYPES = array(
		'application/json',
		'text/json',
		'text/plain',
	);

	/**
	 * Accepted filename extension for JSON files.
	 *
	 * @since 3.6.9
	 *
	 * @return array
	 */
	protected function extensions(): array {
		return array( 'json' => 'application/json' );
	}

	/**
	 * Accepted real MIME types for JSON content.
	 *
	 * @since 3.6.9
	 *
	 * @return string[]
	 */
	protected function mime_types(): array {
		return self::MIME_TYPES;
	}

	/**
	 * Confirms the uploaded file contains valid JSON.
	 *
	 * @since 3.6.9
	 *
	 * @param string $path Absolute path to the file on disk.
	 * @return bool
	 */
	protected function validate_contents( string $path ): bool {
		$contents = FileSystem::get_contents( $path );
		if ( ! is_string( $contents ) || '' === $contents ) {
			return false;
		}

		json_decode( $contents );

		return JSON_ERROR_NONE === json_last_error();
	}
}
