<?php
/**
 * Editor Preview Concern for EDD Checkout Inner Widgets
 *
 * Shared trait that enables the EDD block editor preview context for each section
 * widget when rendered inside the Elementor editor. Setting the
 * edd_blocks_is_block_editor GET parameter (verified against the current user's
 * email hash) causes the shared Elements renderers to emit sample/preview content
 * instead of live data — the same mechanism the monolith Checkout widget uses.
 * Because the native Elementor Container has no single parent render wrapping all
 * four section widgets, each widget must
 * activate the context itself; this trait keeps the logic in one place.
 *
 * @package     EDD\Elementor\Widgets\CheckoutInner\Concerns
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Elementor\Widgets\CheckoutInner\Concerns;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Elementor\Utils\Page;

/**
 * EditorPreview trait.
 *
 * Activate the EDD block editor preview context when rendering inside the
 * Elementor editor. Safe to call multiple times — the flag is set only once
 * per request (idempotent).
 *
 * @since 3.7.0
 */
trait EditorPreview {

	/**
	 * Maybe set up the EDD block editor preview context.
	 *
	 * When the current request is an Elementor editor edit-mode render, set the
	 * edd_blocks_is_block_editor GET parameter to the md5 hash of the current
	 * user's email address. This causes \EDD\Blocks\Utility::is_block_editor() to
	 * return true and the shared Elements renderers to emit sample preview content.
	 *
	 * The flag is set only once per request: if it is already present (because
	 * another section widget rendered first), this method returns immediately so
	 * the value is not recalculated unnecessarily.
	 *
	 * It also applies the "Preview as Guest" toggle here, once, before any section
	 * widget's first Attributes::get() call (see checkout_previews_as_guest()).
	 *
	 * This method MUST NOT be called outside of an is_edit_mode() guard — it is
	 * intentionally a no-op on the frontend.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	protected function maybe_setup_editor_preview(): void {
		if ( ! Page::is_edit_mode() ) {
			return;
		}

		// Idempotent: skip if already set by a previous section widget render.
		if ( ! empty( $_GET['edd_blocks_is_block_editor'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- preview-only GET param set/read server-side in editor context.
			return;
		}

		$user = wp_get_current_user();

		$_GET['edd_blocks_is_block_editor'] = md5( $user->user_email ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- editor-only preview flag. NOSONAR md5 mirrors EDD's capability-gated block-editor preview token, verified in Utility::is_block_editor() via hash_equals() + current_user_can(); not a secret-protecting hash.

		// Preview as guest. The toggle lives on the Personal Info section, but the logged_in
		// flag it drives is memoized by Attributes::get() for the whole render pass. In the
		// editor's single-request render another section (e.g. Cart) can call Attributes::get()
		// first and freeze logged_in before Personal Info renders, so the filter is added here —
		// in the setup every section runs before its own first Attributes::get() — rather than in
		// PersonalInfo::render(), where it landed too late whenever another section rendered first.
		if ( $this->checkout_previews_as_guest() ) {
			add_filter( 'edd_blocks_doing_guest_preview', '__return_true' );
		}
	}

	/**
	 * Whether the checkout's Personal Info section is set to preview as a guest.
	 *
	 * The "Preview as Guest" toggle lives on the Personal Info section. When this render IS
	 * that widget, read its live editor setting from $this so a just-changed (still unsaved)
	 * toggle is honored — the same way the monolith Checkout widget reads it. For any other
	 * section that happens to run this setup first, fall back to the saved document data.
	 * An absent setting means the control default (on); an explicit empty value means the
	 * author turned it off. Returns false when the checkout has no Personal Info section.
	 *
	 * @since 3.7.0
	 * @return bool True when the checkout should preview as a guest.
	 */
	private function checkout_previews_as_guest(): bool {
		if ( 'edd-checkout-personal-info' === $this->get_name() ) {
			return ! empty( $this->get_settings( 'preview_as_guest' ) );
		}

		$personal_info = Page::get_widget_data( 'edd-checkout-personal-info' );
		if ( empty( $personal_info ) ) {
			return false;
		}

		return ! empty( $personal_info['settings']['preview_as_guest'] ?? 'yes' );
	}
}
