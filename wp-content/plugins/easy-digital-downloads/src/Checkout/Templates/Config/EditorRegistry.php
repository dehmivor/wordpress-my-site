<?php
/**
 * Checkout Templates editor registry.
 *
 * Single source of truth for each supported editor's label, minimum required
 * version, and availability. Read by the Lite template browser (the editor
 * list it localizes), the Pro importers (their version floors), and the Pro
 * REST controller (the unavailable-message reason). The minimum-version floor
 * is defined here exactly once. Lives in Lite so both Lite and Pro classes can
 * reference it, and never touches a Pro or Elementor runtime class so it stays
 * safe to load in Lite.
 *
 * @package     EDD\Checkout\Templates\Config
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Checkout\Templates\Config;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * EditorRegistry class.
 *
 * A static descriptor/config map for the supported checkout-template editors,
 * a sibling of Constants. Despite the "Registry" name it is deliberately NOT an
 * \ArrayObject-style registry object: it holds no instance state and is never
 * instantiated. Descriptor arrays and availability are exposed through static
 * accessors so availability can be computed at call time against the current
 * runtime rather than frozen into a class constant.
 *
 * @since 3.7.0
 */
final class EditorRegistry {

	/**
	 * Minimum required Elementor version.
	 *
	 * The single home for the Elementor version floor. Referenced by the Pro
	 * Elementor importer's own constant so bumping it here moves both.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	public const MIN_ELEMENTOR_VERSION = '3.35.0';

	/**
	 * Minimum required WordPress version for block templates.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	public const MIN_WP_VERSION = '6.7';

	/**
	 * Get the descriptor map for the supported editors.
	 *
	 * Each entry carries the editor's translated label, its current
	 * availability, a machine-readable unavailability reason, and the
	 * translated human string for that reason. Availability and the reason are
	 * resolved per editor at call time. The reason text is built here with
	 * __() so the admin UI can render it verbatim without owning a parallel
	 * copy of the strings.
	 *
	 * @since 3.7.0
	 * @return array Descriptor map keyed by editor slug.
	 */
	public static function descriptors(): array {
		return array(
			Constants::EDITOR_BLOCKS    => self::descriptor(
				Constants::EDITOR_BLOCKS,
				__( 'Block Editor', 'easy-digital-downloads' )
			),
			Constants::EDITOR_ELEMENTOR => self::descriptor(
				Constants::EDITOR_ELEMENTOR,
				__( 'Elementor', 'easy-digital-downloads' )
			),
		);
	}

	/**
	 * Build a single editor descriptor.
	 *
	 * @since 3.7.0
	 * @param string $slug  The editor slug.
	 * @param string $label The translated editor label.
	 * @return array Descriptor with label, availability, reason, and unavailable text.
	 */
	private static function descriptor( string $slug, string $label ): array {
		$available = self::is_available( $slug );

		return array(
			'available'        => $available,
			'label'            => $label,
			'reason'           => $available ? '' : self::unavailable_reason( $slug ),
			'unavailable_text' => $available ? '' : self::unavailable_text( $slug ),
		);
	}

	/**
	 * Get the machine-readable reason an editor is unavailable.
	 *
	 * Only called for editors that are not available. Elementor resolves to
	 * 'missing' (the plugin is not loaded) or 'version' (it is below the floor).
	 * The Block Editor has no importer yet, which is reported as 'missing' so
	 * an available editor is the only one with an empty reason.
	 *
	 * @since 3.7.0
	 * @param string $slug The editor slug.
	 * @return string One of 'missing' or 'version'.
	 */
	private static function unavailable_reason( string $slug ): string {
		if ( Constants::EDITOR_ELEMENTOR === $slug && defined( 'ELEMENTOR_VERSION' ) ) {
			return 'version';
		}

		return 'missing';
	}

	/**
	 * Get the translated text explaining why an editor is unavailable.
	 *
	 * Single source for the card's unavailability copy so the admin UI renders
	 * it verbatim. Only called for editors that are not available.
	 *
	 * @since 3.7.0
	 * @param string $slug The editor slug.
	 * @return string The translated unavailability message.
	 */
	private static function unavailable_text( string $slug ): string {
		if ( Constants::EDITOR_ELEMENTOR !== $slug ) {
			return __( 'Block editor templates are currently unavailable.', 'easy-digital-downloads' );
		}

		return sprintf(
			/* translators: %s: minimum required Elementor version */
			__( 'Requires Elementor %s or higher.', 'easy-digital-downloads' ),
			self::MIN_ELEMENTOR_VERSION
		);
	}

	/**
	 * Browse-time message when Elementor's Flexbox Container feature is disabled.
	 *
	 * Elementor is version-available but the composable checkout needs the
	 * Flexbox Container experiment turned on. The runtime check for that state
	 * lives in the trait; this method only owns the copy so the admin UI can
	 * render it verbatim without holding a parallel string.
	 *
	 * @since 3.7.0
	 * @return string The translated container-disabled message.
	 */
	public static function container_unavailable_text(): string {
		return __( 'Requires Elementor\'s Flexbox Container feature — enable it in Elementor > Settings > Features.', 'easy-digital-downloads' );
	}

	/**
	 * Determine whether an editor is available for import on this site.
	 *
	 * Elementor availability is computed against the loaded Elementor version
	 * and the minimum floor. The Block Editor returns a stored literal false:
	 * block import is not yet implemented, so it is advertised as unavailable to
	 * mirror the block importer's stubbed state. No WordPress-version comparison
	 * and no Pro runtime call are involved.
	 *
	 * @since 3.7.0
	 * @param string $slug The editor slug (see Constants::EDITOR_ELEMENTOR, Constants::EDITOR_BLOCKS).
	 * @return bool True when the editor can be used for import.
	 */
	public static function is_available( string $slug ): bool {
		if ( Constants::EDITOR_ELEMENTOR === $slug ) {
			return defined( 'ELEMENTOR_VERSION' )
				&& version_compare( ELEMENTOR_VERSION, self::MIN_ELEMENTOR_VERSION, '>=' );
		}

		return false;
	}
}
