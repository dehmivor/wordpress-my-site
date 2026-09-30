<?php
/**
 * Process file downloads.
 *
 * @package EDD
 * @since 3.3.3
 */

namespace EDD\Downloads;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Downloads process class
 *
 * @since 3.3.3
 */
class Process {

	/**
	 * Schemes a requested file is served from by URL rather than read from disk.
	 *
	 * @since 3.7.1
	 * @var string[]
	 */
	const DIRECT_URL_SCHEMES = array( 'http', 'https' );

	/**
	 * Validate a download file.
	 *
	 * @since 3.3.3
	 *
	 * @param string $file File path.
	 * @return bool
	 */
	public static function validate( $file ) {
		if ( ! $file ) {
			return false;
		}

		return in_array( validate_file( $file ), self::get_allowed_validations( $file ), true );
	}

	/**
	 * Work out the local path the direct delivery methods read a requested file from.
	 *
	 * On delivery this runs before any header is sent, and a file hosted elsewhere resolves nothing.
	 * The metabox save reaches it too, through local_reference(), to decide whether a value is local.
	 *
	 * edd_get_local_path_from_url() resolves overlapping URL shapes for symlink delivery; the
	 * content_url() and UPLOADS branches change in both.
	 *
	 * @since 3.7.1
	 *
	 * @param string            $requested_file The file being requested, which may be a path or a URL.
	 * @param array|false       $file_details   The requested file, as returned by parse_url().
	 * @param string|false|null $uploads        The UPLOADS constant to read, false for none; null reads the constant.
	 * @return array {
	 *     Both keys are always present.
	 *
	 *     @type string|false $path            The local path. $requested_file unchanged when
	 *                                         nothing translated it, or false when a translation
	 *                                         produced a path which does not resolve.
	 *     @type bool         $reads_from_disk Whether $path is read from disk by this server
	 *                                         rather than being a value it redirects to. True for
	 *                                         an absolute path as well as for a translated one.
	 * }
	 */
	public static function resolve_path( $requested_file, $file_details, $uploads = null ) {
		$reads_from_disk = false;
		$file_path       = $requested_file;

		if ( null === $uploads ) {
			$uploads = defined( 'UPLOADS' ) ? UPLOADS : false;
		}

		if (
			( ! isset( $file_details['scheme'] ) || ! in_array( $file_details['scheme'], self::DIRECT_URL_SCHEMES, true ) ) &&
			isset( $file_details['path'] ) &&
			file_exists( \EDD\Utils\FileSystem::sanitize_file_path( $requested_file ) )
		) {

			/** This is an absolute path */
			$reads_from_disk = true;
			$file_path       = $requested_file;
		} elseif ( false !== $uploads && false !== strpos( $requested_file, $uploads ) && false !== strpos( $requested_file, site_url() ) ) {

			// The translation strips site_url() from the URL, so a URL that does not carry it cannot be translated here.
			$file_path       = str_replace( site_url(), '', $requested_file );
			$file_path       = realpath( ABSPATH . $file_path );
			$reads_from_disk = true;
		} elseif ( strpos( $requested_file, content_url() ) !== false ) {

			/** This is a local file given by URL so we need to figure out the path */
			$file_path       = str_replace( content_url(), WP_CONTENT_DIR, $requested_file );
			$file_path       = realpath( $file_path );
			$reads_from_disk = true;
		} elseif ( strpos( $requested_file, set_url_scheme( content_url(), 'https' ) ) !== false ) {

			/** This is a local file given by an HTTPS URL so we need to figure out the path */
			$file_path       = str_replace( set_url_scheme( content_url(), 'https' ), WP_CONTENT_DIR, $requested_file );
			$file_path       = realpath( $file_path );
			$reads_from_disk = true;
		}

		return array(
			'path'            => $file_path,
			'reads_from_disk' => $reads_from_disk,
		);
	}

	/**
	 * The local value a file reference resolves to, or false when this server does not read it.
	 *
	 * A value with no scheme is a path, taken as given when absolute and resolved against the
	 * WordPress root when relative. It is local when the file is there, or when the path points
	 * into the content or uploads directory. A URL is local when path resolution translates it.
	 *
	 * @since 3.7.1
	 *
	 * @param string $file The path or URL a file row names.
	 * @return string|false The path on this server, the local URL when the file is gone, since its
	 *                      attachment is still looked up, or false when this server does not read
	 *                      the value.
	 */
	public static function local_reference( $file ) {
		$file         = (string) $file;
		$file_details = wp_parse_url( $file );
		$scheme       = empty( $file_details['scheme'] ) ? '' : strtolower( $file_details['scheme'] );

		if ( 'file' === $scheme ) {
			$file   = preg_replace( '#^file://#i', '', $file );
			$scheme = '';
		} elseif ( self::is_drive_path( $file ) ) {
			// wp_parse_url() reads a Windows drive letter as a scheme, but the value is a path.
			$scheme = '';
		}

		if ( '' === $scheme ) {
			return self::path_for_scheme_less_reference( $file );
		}

		if ( ! in_array( $scheme, self::DIRECT_URL_SCHEMES, true ) ) {
			return false;
		}

		// Delivery rewrites a local URL to the scheme it serves over; the store's configured scheme is the one a save can know.
		$file         = set_url_scheme( $file, wp_parse_url( home_url(), PHP_URL_SCHEME ) );
		$file_details = wp_parse_url( $file );
		$resolved     = self::resolve_path( $file, $file_details );

		if ( empty( $resolved['reads_from_disk'] ) ) {
			return false;
		}

		// realpath() gives nothing for a file which is gone, and a URL still maps to an attachment.
		return is_string( $resolved['path'] ) ? $resolved['path'] : self::without_query_string( $file );
	}

	/**
	 * Whether a requested file may be delivered from where it points.
	 *
	 * The requested value is always checked. A local path the caller resolved it to is checked
	 * as well, since that is a different value from the one first checked.
	 *
	 * Runs where `includes/process-download.php` is loaded, which the admin save path is not.
	 *
	 * @since 3.7.1
	 *
	 * @param string      $requested_file The file being requested, a path or a URL.
	 * @param array|false $file_details   The requested file, as returned by parse_url().
	 * @param string|null $resolved_path  The local path the caller resolved the request to, if any.
	 * @return bool
	 */
	public static function is_delivery_allowed( $requested_file, $file_details, $resolved_path = null ) {
		$allowed = edd_local_file_location_is_allowed( $file_details, self::DIRECT_URL_SCHEMES, $requested_file );

		if ( $allowed && is_string( $resolved_path ) && $resolved_path !== $requested_file ) {
			$allowed = edd_local_file_location_is_allowed( parse_url( $resolved_path ), self::DIRECT_URL_SCHEMES, $resolved_path );
		}

		return $allowed;
	}

	/**
	 * Whether a local file sits in a directory the store serves product files from.
	 *
	 * @since 3.7.1
	 *
	 * @param string|false $file File path.
	 * @return bool
	 */
	public static function is_in_allowed_directory( $file ) {
		// realpath() answers with the working directory when given nothing, so guard before it.
		$file = empty( $file ) ? false : realpath( $file );

		return ! empty( $file ) && self::points_into_allowed_directory( $file );
	}

	/**
	 * A file reference with any query string removed.
	 *
	 * @since 3.7.1
	 *
	 * @param string $file The path or URL a file row names.
	 * @return string
	 */
	public static function without_query_string( $file ) {
		return explode( '?', $file )[0];
	}

	/**
	 * Whether a file reference is a Windows path led by a drive letter.
	 *
	 * @since 3.7.1
	 *
	 * @param string $file The path or URL a file row names.
	 * @return bool
	 */
	private static function is_drive_path( $file ) {
		return 1 === preg_match( '#^[A-Za-z]:[\\\\/]#', $file );
	}

	/**
	 * The path a scheme-less file reference names, when this server reads it, or false.
	 *
	 * @since 3.7.1
	 *
	 * @param string $file The path a file row names.
	 * @return string|false
	 */
	private static function path_for_scheme_less_reference( $file ) {
		$path = path_is_absolute( $file ) ? $file : ABSPATH . ltrim( $file, '/' );
		$path = self::collapse_dot_segments( $path );

		if ( file_exists( \EDD\Utils\FileSystem::sanitize_file_path( $path ) ) ) {
			return $path;
		}

		return self::points_into_allowed_directory( $path ) ? $path : false;
	}

	/**
	 * A path with its `.` and `..` segments resolved textually.
	 *
	 * The directory rule holds a path whose file is not on disk yet, so realpath() cannot stand in
	 * for this, and wp_normalize_path() leaves both segments where they are.
	 *
	 * @since 3.7.1
	 *
	 * @param string $path The path to collapse.
	 * @return string
	 */
	private static function collapse_dot_segments( $path ) {
		$segments  = explode( '/', wp_normalize_path( $path ) );
		$root      = array_shift( $segments );
		$collapsed = array();

		foreach ( $segments as $segment ) {
			if ( '.' === $segment ) {
				continue;
			}

			if ( '..' === $segment ) {
				array_pop( $collapsed );
				continue;
			}

			$collapsed[] = $segment;
		}

		array_unshift( $collapsed, $root );

		return implode( '/', $collapsed );
	}

	/**
	 * Whether a path names a location inside a directory the store serves product files from.
	 *
	 * The file itself need not be there, so a path into the uploads tree is held to the same rule
	 * before its file is written as after.
	 *
	 * @since 3.7.1
	 *
	 * @param string $path The path to test.
	 * @return bool
	 */
	private static function points_into_allowed_directory( $path ) {
		$path = trailingslashit( wp_normalize_path( $path ) );

		foreach ( self::get_allowed_directories() as $directory ) {
			if ( 0 === strpos( $path, $directory ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the directories product files may be served from, each with a trailing slash.
	 *
	 * The uploads directory is listed on its own because some hosts place it outside of the
	 * content directory.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	private static function get_allowed_directories() {
		$uploads    = wp_upload_dir( null, false );
		$candidates = array( WP_CONTENT_DIR );

		if ( empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) ) {
			$candidates[] = $uploads['basedir'];
		}

		$directories = array();
		foreach ( $candidates as $candidate ) {
			$directory = wp_normalize_path( realpath( $candidate ) );

			if ( ! empty( $directory ) ) {
				$directories[] = trailingslashit( $directory );
			}
		}

		return array_unique( $directories );
	}

	/**
	 * Get allowed validations.
	 *
	 * @since 3.3.3
	 *
	 * @param string $file File path.
	 * @return array
	 */
	private static function get_allowed_validations( $file ) {
		$allowed_validations = array( 0 );

		if ( edd_is_dev_environment() ) {
			$allowed_validations[] = 2;
		}

		return apply_filters( 'edd_file_allowed_validations', $allowed_validations, $file );
	}
}
