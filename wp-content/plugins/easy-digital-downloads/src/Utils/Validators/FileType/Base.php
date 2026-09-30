<?php
/**
 * File Type Validator (Base).
 *
 * Base class for validating that an uploaded file matches an expected format, by
 * its filename extension and its server-detected contents.
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
 * Base file type validator.
 *
 * @since 3.6.9
 */
abstract class Base {

	/**
	 * Allowed filename extension(s) for this format.
	 *
	 * Returned as an `extension => mime` map suitable for wp_check_filetype().
	 *
	 * @since 3.6.9
	 *
	 * @return array
	 */
	abstract protected function extensions(): array;

	/**
	 * MIME types accepted as this format's content.
	 *
	 * @since 3.6.9
	 *
	 * @return string[]
	 */
	abstract protected function mime_types(): array;

	/**
	 * Validates that a file matches this format.
	 *
	 * Checks the filename extension and the file's contents against the format's
	 * accepted values.
	 *
	 * @since 3.6.9
	 *
	 * @param string $path     Absolute path to the file on disk (e.g. an upload tmp_name).
	 * @param string $filename The filename whose extension is validated.
	 * @return bool True only when the extension and contents are both valid.
	 */
	public function is_valid( string $path, string $filename ): bool {
		if ( empty( $path ) || empty( $filename ) ) {
			return false;
		}

		// Confirm the extension is one we accept.
		$filetype = wp_check_filetype( $filename, $this->extensions() );
		if ( empty( $filetype['ext'] ) ) {
			return false;
		}

		if ( ! FileSystem::file_exists( $path ) ) {
			return false;
		}

		// Confirm the contents, then run any format-specific checks.
		return $this->is_mime_allowed( $path ) && $this->validate_contents( $path );
	}

	/**
	 * Reduces a filename to a single extension.
	 *
	 * Folds any inner extensions into the base, so "shell.php.csv" becomes
	 * "shell-php.csv". Use after is_valid() has confirmed the extension.
	 *
	 * @since 3.6.9.1
	 * @since 3.7.0 Made static; it uses no instance state and callers outside a
	 *                       specific format need it.
	 *
	 * @param string $filename The client-supplied filename.
	 * @return string The sanitized filename.
	 */
	public static function sanitize_filename( string $filename ): string {
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$base      = str_replace( '.', '-', (string) pathinfo( $filename, PATHINFO_FILENAME ) );

		return sanitize_file_name( '' === $extension ? $base : "{$base}.{$extension}" );
	}

	/**
	 * Determines whether the file's contents match an accepted MIME type.
	 *
	 * @since 3.6.9
	 *
	 * @param string $path Absolute path to the file on disk.
	 * @return bool
	 */
	protected function is_mime_allowed( string $path ): bool {
		if ( ! is_callable( 'mime_content_type' ) ) {
			return true;
		}

		return in_array( mime_content_type( $path ), $this->mime_types(), true );
	}

	/**
	 * Optional, format-specific validation hook.
	 *
	 * Overload in a subclass to add structural checks beyond extension and MIME.
	 * Defaults to passing.
	 *
	 * @since 3.6.9
	 *
	 * @param string $path Absolute path to the file on disk.
	 * @return bool
	 */
	protected function validate_contents( string $path ): bool {
		return true;
	}
}
