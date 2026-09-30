<?php
/**
 * Command Palette navigation commands.
 *
 * Builds the static "jump to" commands for admin sub-views that are not their
 * own menu items (settings tabs, tools tabs, report views, emails tabs) and a
 * few quick actions. These are capability-filtered and localized to the
 * command palette script.
 *
 * @package     EDD\CommandPalette
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\CommandPalette;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Admin\Emails\Screen as EmailsScreen;
use EDD\Admin\Settings\Screen as SettingsScreen;
use EDD\Admin\Tools\Screen as ToolsScreen;

/**
 * Navigation class.
 *
 * @since 3.7.1
 */
class Navigation {

	/**
	 * Get the static navigation commands the current user can access.
	 *
	 * @since 3.7.1
	 *
	 * @return array[] Each command is an array with 'name', 'label', 'url' and 'keywords' keys,
	 *                 plus 'searchLabel' where the command is matched on something other than its label.
	 */
	public function get() {
		return array_values(
			array_merge(
				$this->get_settings_commands(),
				$this->get_tools_commands(),
				$this->get_reports_commands(),
				$this->get_emails_commands(),
				$this->get_quick_actions()
			)
		);
	}

	/**
	 * Settings tab commands.
	 *
	 * @since 3.7.1
	 *
	 * @return array[]
	 */
	private function get_settings_commands() {
		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			return array();
		}

		return $this->get_tab_commands(
			edd_get_settings_tabs(),
			array(
				'page'     => 'edd-settings',
				'prefix'   => 'settings',
				/* translators: %s: settings tab name */
				'pattern'  => __( 'EDD Settings: %s', 'easy-digital-downloads' ),
				'keywords' => __( 'Settings', 'easy-digital-downloads' ),
				'default'  => SettingsScreen::DEFAULT_TAB,
			)
		);
	}

	/**
	 * Emails tab commands.
	 *
	 * @since 3.7.1
	 *
	 * @return array[]
	 */
	private function get_emails_commands() {
		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			return array();
		}

		return $this->get_tab_commands(
			EmailsScreen::get_tabs(),
			array(
				'page'     => 'edd-emails',
				'prefix'   => 'emails',
				/* translators: %s: emails tab name */
				'pattern'  => __( 'EDD Emails: %s', 'easy-digital-downloads' ),
				'keywords' => __( 'Emails', 'easy-digital-downloads' ),
				'default'  => EmailsScreen::DEFAULT_TAB,
			)
		);
	}

	/**
	 * Tools tab commands.
	 *
	 * Kept separate from the shared tab builder: a tools tab may be an array
	 * carrying its own label and off-page URL, such as system info.
	 *
	 * @since 3.7.1
	 *
	 * @return array[]
	 */
	private function get_tools_commands() {
		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			return array();
		}

		$commands = array();
		foreach ( ToolsScreen::get_tabs() as $slug => $tab ) {
			// The default tab is the Tools landing page, already surfaced by core's menu command.
			if ( ToolsScreen::DEFAULT_TAB === (string) $slug ) {
				continue;
			}

			// Some tabs (e.g. system info) define an explicit label and URL.
			if ( is_array( $tab ) ) {
				$label = ! empty( $tab['name'] ) ? $tab['name'] : $slug;
				$url   = ! empty( $tab['url'] ) ? $tab['url'] : edd_get_admin_url(
					array(
						'page' => 'edd-tools',
						'tab'  => $slug,
					)
				);
			} else {
				$label = $tab;
				$url   = edd_get_admin_url(
					array(
						'page' => 'edd-tools',
						'tab'  => $slug,
					)
				);
			}

			$group = __( 'Tools', 'easy-digital-downloads' );

			$commands[] = $this->command(
				'tools-' . $slug,
				/* translators: %s: tools tab name */
				sprintf( __( 'EDD Tools: %s', 'easy-digital-downloads' ), $label ),
				$url,
				$group,
				$this->search_label( $group, $label )
			);
		}

		return $commands;
	}

	/**
	 * Report view commands.
	 *
	 * @since 3.7.1
	 *
	 * @return array[]
	 */
	private function get_reports_commands() {
		if ( ! current_user_can( 'view_shop_reports' ) ) {
			return array();
		}

		// Reports register on the edd_reports_init hook, which is lazy per-screen.
		new \EDD\Reports\Init();

		$reports = \EDD\Reports\get_reports();
		if ( empty( $reports ) ) {
			return array();
		}

		/** This filter is documented in includes/reports/reports-functions.php */
		$default = (string) apply_filters( 'edd_default_report_view', 'overview' );

		$commands = array();
		foreach ( $reports as $slug => $report ) {
			// The default report is the Reports landing page, already surfaced by core's menu command.
			if ( (string) $slug === $default ) {
				continue;
			}

			// The reports registry returns the full attribute array, keyed by report ID.
			$label = is_array( $report ) ? ( $report['label'] ?? $slug ) : $report;
			if ( empty( $label ) || ! is_string( $label ) ) {
				continue;
			}

			$group = __( 'Reports', 'easy-digital-downloads' );

			$commands[] = $this->command(
				'reports-' . $slug,
				/* translators: %s: report view name */
				sprintf( __( 'EDD Reports: %s', 'easy-digital-downloads' ), $label ),
				edd_get_admin_url(
					array(
						'page' => 'edd-reports',
						'view' => $slug,
					)
				),
				$group,
				$this->search_label( $group, $label )
			);
		}

		return $commands;
	}

	/**
	 * Quick action commands.
	 *
	 * @since 3.7.1
	 *
	 * @return array[]
	 */
	private function get_quick_actions() {
		$commands = array();

		if ( current_user_can( 'edit_products' ) ) {
			$commands[] = $this->command(
				'add-download',
				/* translators: %s: Download singular label */
				sprintf( __( 'EDD: Add New %s', 'easy-digital-downloads' ), edd_get_label_singular() ),
				admin_url( 'post-new.php?post_type=download' ),
				__( 'Actions', 'easy-digital-downloads' )
			);
		}

		if ( current_user_can( 'edit_shop_payments' ) ) {
			$commands[] = $this->command(
				'add-order',
				__( 'EDD: Add Order', 'easy-digital-downloads' ),
				edd_get_admin_url(
					array(
						'page' => 'edd-payment-history',
						'view' => 'add-order',
					)
				),
				__( 'Orders', 'easy-digital-downloads' )
			);
		}

		if ( current_user_can( 'manage_shop_discounts' ) ) {
			$commands[] = $this->command(
				'add-discount',
				__( 'EDD: Add New Discount', 'easy-digital-downloads' ),
				edd_get_admin_url(
					array(
						'page' => 'edd-discounts',
						'view' => 'add_discount',
					)
				),
				__( 'Actions', 'easy-digital-downloads' )
			);
		}

		return $commands;
	}

	/**
	 * Build the commands for a page's tab list.
	 *
	 * @since 3.7.1
	 *
	 * @param array $tabs Tab slug/label pairs.
	 * @param array $args {
	 *     Builder arguments.
	 *
	 *     @type string $page     The admin page slug.
	 *     @type string $prefix   Prefix for the command name.
	 *     @type string $pattern  Translated label pattern taking the tab label.
	 *     @type string $keywords Translated term the command is also matched on.
	 *     @type string $default  The tab the page lands on, already covered by core's menu command.
	 * }
	 * @return array[]
	 */
	private function get_tab_commands( array $tabs, array $args ) {
		$commands = array();
		foreach ( $tabs as $slug => $label ) {
			if ( (string) $slug === (string) $args['default'] ) {
				continue;
			}

			$commands[] = $this->command(
				$args['prefix'] . '-' . $slug,
				sprintf( $args['pattern'], $label ),
				edd_get_admin_url(
					array(
						'page' => $args['page'],
						'tab'  => $slug,
					)
				),
				$args['keywords'],
				$this->search_label( $args['keywords'], $label )
			);
		}

		return $commands;
	}

	/**
	 * Build a single command array.
	 *
	 * @since 3.7.1
	 *
	 * @param string $name         Unique command slug (namespaced on output).
	 * @param string $label        The command label shown in the palette.
	 * @param string $url          The URL to navigate to.
	 * @param string $keywords     Term the command is also matched on.
	 * @param string $search_label Optional. String to match on instead of the label.
	 * @return array
	 */
	private function command( $name, $label, $url, $keywords, $search_label = '' ) {
		$command = array(
			'name'     => 'edd/nav/' . $name,
			'label'    => $label,
			'url'      => $url,
			'keywords' => $keywords,
		);

		// An empty value would be matched on, so leave the key out entirely.
		if ( ! empty( $search_label ) ) {
			$command['searchLabel'] = $search_label;
		}

		return $command;
	}

	/**
	 * Build the string the palette matches a sub-view command on.
	 *
	 * The palette scores a match at the start of the string highest, so a label
	 * leading with the brand ranks below every other plugin's rows for a query
	 * like "tools". What is matched leads with the sub-view and carries the
	 * brand last; what is displayed still leads with the brand.
	 *
	 * @since 3.7.1
	 *
	 * @param string $group The group the sub-view belongs to, e.g. Tools.
	 * @param string $label The sub-view name.
	 * @return string
	 */
	private function search_label( $group, $label ) {
		return $group . ': ' . $label . ' EDD';
	}
}
