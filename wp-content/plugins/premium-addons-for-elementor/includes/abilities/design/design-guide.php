<?php
/**
 * Design Guide.
 *
 * Ships the design guide to MCP clients as a prompt, and reminds the build
 * tools of the load-bearing rules inside their own results.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Design;

use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Includes\Abilities\Registry\Ability_Registry;

use WP\MCP\Domain\Prompts\McpPrompt;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Design guide delivery.
 */
class Design_Guide {

	/**
	 * MCP prompt name. Matches the name in the guide's front matter.
	 */
	const PROMPT_NAME = 'pafe-design';

	/**
	 * The part served when the caller asks for none.
	 */
	const DEFAULT_PART = 'workflow';

	/**
	 * Part name => skill file, relative to PREMIUM_ADDONS_PATH.
	 *
	 * The only place a skill file path is written.
	 */
	const PARTS = array(
		'workflow'          => 'includes/abilities/skills/pafe-design/SKILL.md',
		'design-guide'      => 'includes/abilities/skills/pafe-design/references/design-guide.md',
		'premium-templates' => 'includes/abilities/skills/pafe-design/references/premium-templates.md',
		'widget-selection'  => 'includes/abilities/skills/pafe-design/references/widget-selection.md',
		'global-addons'     => 'includes/abilities/skills/pafe-design/references/global-addons.md',
		'page-patterns'     => 'includes/abilities/skills/pafe-design/references/page-patterns.md',
		'troubleshooting'   => 'includes/abilities/skills/pafe-design/references/troubleshooting.md',
	);

	/**
	 * MCP tool names that carry the design note in their result.
	 */
	const BUILD_TOOLS = array(
		'premium-addons-insert-widget',
		'premium-addons-add-container',
		'premium-addons-add-flexbox',
		'premium-addons-update-element-settings',
	);

	/**
	 * Parsed part files, keyed by part. False when the part could not be read.
	 *
	 * @var array<string, array|bool>
	 */
	private static $guides = array();

	/**
	 * Get the design guide as an MCP prompt.
	 *
	 * Null when the loaded MCP Adapter cannot build it. Another plugin may
	 * bundle an older adapter under the same WP\MCP namespace (Rank Math ships
	 * 0.4.1).
	 *
	 * @return McpPrompt|null
	 */
	public static function get_prompt() {

		if ( ! method_exists( McpPrompt::class, 'fromArray' ) ) {
			return null;
		}

		$guide = self::get_guide( self::DEFAULT_PART );

		if ( ! $guide ) {
			return null;
		}

		$prompt = McpPrompt::fromArray(
			array(
				'name'        => self::PROMPT_NAME,
				'title'       => __( 'Premium Addons Design Skill', 'premium-addons-for-elementor' ),
				'description' => $guide['description'],
				'handler'     => function () use ( $guide ) {
					return array( 'text' => $guide['body'] );
				},
				'permission'  => function () {
					return Admin_Helper::check_user_can( 'edit_posts' );
				},
			)
		);

		return is_wp_error( $prompt ) ? null : $prompt;
	}

	/**
	 * Get a part's text without its front matter.
	 *
	 * @param string $part Part name, one of the PARTS keys.
	 * @return string|bool The markdown body, or false when the part is unknown or unreadable.
	 */
	public static function get_body( $part ) {

		$guide = self::get_guide( $part );

		return $guide ? $guide['body'] : false;
	}

	/**
	 * Pre-build hint appended to the build abilities' descriptions.
	 *
	 * The MCP tool exposes the whole description; the dashboard strips it from
	 * the delimiter on. Keep it to a single line.
	 *
	 * @return string
	 */
	public static function build_hint() {
		return Ability_Registry::AGENT_HINT_DELIMITER . __( 'Before your first build or restyle call on this page, call premium-addons/get-design-guide with part: ["workflow", "design-guide"] and follow both for the whole build.', 'premium-addons-for-elementor' );
	}

	/**
	 * Add the design note to build tool results.
	 *
	 * @param mixed  $result    Tool execution result.
	 * @param array  $args      Tool arguments.
	 * @param string $tool_name MCP tool name.
	 * @return mixed
	 */
	public static function filter_tool_result( $result, $args, $tool_name ) {

		if ( ! is_array( $result ) || ! in_array( $tool_name, self::BUILD_TOOLS, true ) ) {
			return $result;
		}

		$result['design_note'] = self::get_note();

		return $result;
	}

	/**
	 * Get the just-in-time design note.
	 *
	 * Kept in lockstep with skills/pafe-design/SKILL.md.
	 *
	 * @return string
	 */
	private static function get_note() {
		return __( 'Design check: reuse the exact color, font and size values from premium-addons/get-global-settings and premium-addons/get-theme-styles — never introduce a new palette, font or off-scale spacing mid-build. Use containers, never sections or columns, and declare the mobile collapse on every multi-column container. Native Elementor only: no injected script, no external CSS or animation libraries. When the section is done, judge the rendered front end, not the stored settings. NEVER skip the full rules — call premium-addons-get-design-guide with part: ["design-guide"].', 'premium-addons-for-elementor' );
	}

	/**
	 * Read and parse a part file once.
	 *
	 * @param string $part Part name, one of the PARTS keys.
	 * @return array|bool
	 */
	private static function get_guide( $part ) {

		if ( array_key_exists( $part, self::$guides ) ) {
			return self::$guides[ $part ];
		}

		self::$guides[ $part ] = false;

		if ( ! isset( self::PARTS[ $part ] ) ) {
			return self::$guides[ $part ];
		}

		$path = PREMIUM_ADDONS_PATH . self::PARTS[ $part ];

		if ( ! is_readable( $path ) ) {
			return self::$guides[ $part ];
		}

		$contents = file_get_contents( $path );

		if ( false === $contents ) {
			return self::$guides[ $part ];
		}

		$body        = trim( $contents );
		$description = '';

		// The front matter is skill metadata, not prompt text: its description
		// becomes the prompt description, the rest becomes the prompt message.
		// Only the workflow part carries it — a part without it is served whole.
		if ( preg_match( '/^---\R(.*?)\R---\R(.*)$/s', $body, $matches ) ) {
			$body = trim( $matches[2] );

			if ( preg_match( '/^description:\s*(.+)$/m', $matches[1], $description_match ) ) {
				$description = trim( $description_match[1] );
			}
		}

		self::$guides[ $part ] = array(
			'description' => $description,
			'body'        => $body,
		);

		return self::$guides[ $part ];
	}
}
