<?php
/**
 * Checkout Templates shared constants.
 *
 * Central home for values shared across the Checkout Template Imports feature:
 * the editor slug, the support URL, and the template tracking meta keys. Lives
 * in Lite so both Lite and Pro classes can reference it.
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
 * Constants class.
 *
 * Shared constants for the Checkout Template Imports feature.
 *
 * @since 3.7.0
 */
final class Constants {

	/**
	 * Editor slug for Block Editor templates.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	const EDITOR_BLOCKS = 'blocks';

	/**
	 * The Elementor editor slug.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	const EDITOR_ELEMENTOR = 'elementor';

	/**
	 * Support URL for error messages.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	const SUPPORT_URL = 'https://easydigitaldownloads.com/support/';

	/**
	 * Marketing URL for upgrading from Lite to Pro.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	const UPGRADE_URL = 'https://easydigitaldownloads.com/lite-upgrade/';

	/**
	 * Account URL for managing licenses and downloads.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	const ACCOUNT_URL = 'https://easydigitaldownloads.com/your-account/';

	/**
	 * Meta key for the imported template ID.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	const META_ID = '_edd_checkout_template_id';

	/**
	 * Meta key for the imported template name.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	const META_NAME = '_edd_checkout_template_name';

	/**
	 * Meta key for the imported template version.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	const META_VERSION = '_edd_checkout_template_version';

	/**
	 * Meta key for the import timestamp.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	const META_IMPORTED = '_edd_checkout_template_imported';

	/**
	 * Meta key for the editor used to import the template.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	const META_EDITOR = '_edd_checkout_template_editor';

	/**
	 * Meta key for the page template in place before an Elementor import.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	const META_PREVIOUS_PAGE_TEMPLATE = '_edd_checkout_template_previous_page_template';

	/**
	 * Attachment meta key recording the remote URL a template asset was sideloaded from.
	 *
	 * Stored on media-library attachments created during import so a re-import of the same
	 * template reuses the existing attachment instead of downloading a duplicate.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	const META_ASSET_SOURCE = '_edd_checkout_template_asset_source';
}
