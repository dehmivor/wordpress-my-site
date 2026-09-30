<?php
namespace ElementsKit_Lite;

defined( 'ABSPATH' ) || exit;

/**
 * ElementsKit_Lite legacy autoloader.
 *
 * Handles dynamically loading legacy (non-PSR-4) classes under the
 * `ElementsKit_Lite` namespace that use underscore-style naming.
 *
 * New code should use PSR-4 namespaces (e.g. `ElementsKit\Lite\Core\...`)
 * and rely on Composer's autoloader (`vendor/autoload.php`) instead.
 *
 * @since 1.0.0
 */
class Autoloader {

	/**
	 * Run autoloader.
	 *
	 * Registers this class's `autoload()` method as an SPL autoloader.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public static function run() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload.
	 *
	 * For a given class name, resolve the corresponding file path and
	 * load it if it exists.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param string $class_name Fully qualified class name.
	 * @return void
	 */
	private static function autoload( $class_name ) {

		// Bail early if the class isn't part of this namespace.
		if ( 0 !== strpos( $class_name, __NAMESPACE__ ) ) {
			return;
		}

		$file_name = strtolower(
			preg_replace(
				array( '/\b' . __NAMESPACE__ . '\\\/', '/([a-z])([A-Z])/', '/_/', '/\\\/' ),
				array( '', '$1-$2', '-', DIRECTORY_SEPARATOR ),
				$class_name
			)
		);

		$file = \ElementsKit_Lite::plugin_dir() . $file_name . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}