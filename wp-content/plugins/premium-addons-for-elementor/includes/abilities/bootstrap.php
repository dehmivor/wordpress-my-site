<?php
/**
 * AI Abilities Bootstrap.
 *
 * Stands up the bundled MCP server that exposes Premium Addons widgets,
 * templates and site data as MCP tools for AI agents.
 */

namespace PremiumAddons\Includes\Abilities;

use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Includes\Abilities\Registry\Ability_Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Class Bootstrap.
 *
 * @since 4.11.74
 */
class Bootstrap {

	/**
	 * REST namespace the MCP server is registered under.
	 *
	 * @var string
	 */
	const SERVER_NAMESPACE = 'premium-addons';

	/**
	 * REST route the MCP server answers on, within SERVER_NAMESPACE.
	 *
	 * @var string
	 */
	const SERVER_ROUTE = 'mcp';

	/**
	 * Prefix for category labels handed to the WordPress Abilities API.
	 *
	 * @var string
	 */
	const CATEGORY_LABEL_PREFIX = 'PA';

	/**
	 * Class instance.
	 *
	 * @var Bootstrap|null
	 */
	private static $instance = null;

	/**
	 * Ability handler registry.
	 *
	 * @var Ability_Registry
	 */
	private $registry;

	/**
	 * The MCP server's REST route, namespace included. Shared with the OAuth
	 * layer, which advertises this endpoint and scopes its challenge to it.
	 *
	 * @return string
	 */
	public static function server_route() {
		return self::SERVER_NAMESPACE . '/' . self::SERVER_ROUTE;
	}

	/**
	 * Get class instance.
	 *
	 * @return Bootstrap
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Bootstrap constructor.
	 */
	public function __construct() {

		$this->registry = new Ability_Registry();

		// Check if the AI Abilities feature is enabled.
		$is_enabled = ! empty( Admin_Helper::get_enabled_elements()['premium-ai-abilities'] );

		if ( ! $is_enabled || ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		// Track MCP connections and clear them when an application password is deleted.
		Connection_Log::init();

		// OAuth connect method. Hooks only here; its gates run on init.
		OAuth\Bootstrap::get_instance();

		// Register ability categories and abilities.
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_categories' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );

		// Accept ability input that clients such as Angie send as a JSON string.
		Input_Normalizer::init();

		// Expose the enabled abilities on Elementor MCP as well, so one
		// connection covers both servers.
		Elementor_MCP::init();

		// Remind the build tools of the design rules in their own results, on
		// whichever adapter server runs them (Premium Addons MCP or Elementor MCP).
		add_filter( 'mcp_adapter_tool_call_result', array( Design\Design_Guide::class, 'filter_tool_result' ), 10, 3 );

		// Boot the MCP adapter on the MCP endpoint and WP-CLI only.
		add_action( 'rest_api_init', array( $this, 'maybe_boot_mcp_adapter' ), 5 );

		// Serve copy payloads to Destination sites. Signature authorized, no WordPress auth.
		add_action( 'rest_api_init', array( Transfer\Transfer_Controller::class, 'register_routes' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			add_action( 'init', array( $this, 'boot_mcp_adapter' ), 5 );
		}
	}

	/**
	 * Boot the adapter only when the request is for the MCP endpoint itself.
	 *
	 * rest_api_init fires for every REST request on the site, so booting there
	 * unconditionally makes an editor autosave — or any other plugin's endpoint
	 * — pay for the Jetpack autoloader, the adapter singleton and every ability
	 * handler. The endpoint is a fixed URL that clients are handed verbatim, so
	 * matching it is enough; nothing discovers the server through the /wp-json/
	 * route index.
	 *
	 * The route is read from the query var rather than the request URI because
	 * WordPress populates it for both the pretty and the ?rest_route= form, and
	 * leaves it empty for internal rest_do_request() calls such as the block
	 * editor's preloading — which should not boot the adapter either.
	 *
	 * @return void
	 */
	public function maybe_boot_mcp_adapter() {

		$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? $GLOBALS['wp']->query_vars['rest_route'] : '';

		if ( ! is_string( $route ) ) {
			return;
		}

		if ( 0 !== strpos( trim( $route, '/' ), self::server_route() ) ) {
			return;
		}

		$this->boot_mcp_adapter();
	}

	/**
	 * Boot the MCP adapter and hook server registration.
	 *
	 * @return void
	 */
	public function boot_mcp_adapter() {

		require_once PREMIUM_ADDONS_PATH . 'includes/abilities/vendor/autoload_packages.php';

		if ( ! class_exists( \WP\MCP\Core\McpAdapter::class ) ) {
			return;
		}

		\WP\MCP\Core\McpAdapter::instance();

		add_action( 'mcp_adapter_init', array( $this, 'register_server' ), 20 );
	}

	/**
	 * Get category definitions.
	 *
	 * Rendered without prefix in the dashboard.
	 *
	 * @return array
	 */
	public static function get_categories() {
		return array(
			'pa-discovery'            => array(
				'label'       => __( 'Discovery', 'premium-addons-for-elementor' ),
				'description' => __( 'Abilities that read site and content state without changing anything.', 'premium-addons-for-elementor' ),
			),
			'pa-page-post-management' => array(
				'label'       => __( 'Page/Post Management', 'premium-addons-for-elementor' ),
				'description' => __( 'Abilities that create and manage WordPress pages and posts as Elementor documents.', 'premium-addons-for-elementor' ),
			),
			'pa-build'                => array(
				'label'       => __( 'Build', 'premium-addons-for-elementor' ),
				'description' => __( 'Abilities that create, edit, and remove Elementor elements on a page — containers, atomic flexbox, element settings, and deletion.', 'premium-addons-for-elementor' ),
			),
			'pa-media'                => array(
				'label'       => __( 'Media', 'premium-addons-for-elementor' ),
				'description' => __( 'Abilities that read and add images in the WordPress media library.', 'premium-addons-for-elementor' ),
			),
			'pa-dashboard'            => array(
				'label'       => __( 'Dashboard', 'premium-addons-for-elementor' ),
				'description' => __( 'Abilities for managing the Premium Addons dashboard.', 'premium-addons-for-elementor' ),
			),
			'pa-copy-paste'           => array(
				'label'       => __( 'Cross Domain Copy & Paste', 'premium-addons-for-elementor' ),
				'description' => __( 'Abilities that copy Elementor elements from one site to another without the content passing through the AI client.', 'premium-addons-for-elementor' ),
			),
			'pa-templates'            => array(
				'label'       => __( 'Premium Templates', 'premium-addons-for-elementor' ),
				'description' => __( 'Abilities that browse the Premium Templates catalog and insert its ready-made sections into pages.', 'premium-addons-for-elementor' ),
			),
		);
	}

	/**
	 * Register ability categories.
	 *
	 * @return void
	 */
	public function register_categories() {
		foreach ( self::get_categories() as $slug => $category ) {
			$category['label'] = self::get_agent_category_label( $category['label'] );
			wp_register_ability_category( $slug, $category );
		}
	}

	/**
	 * Get a category label as AI clients see it.
	 *
	 * @param string $label Dashboard label from get_categories().
	 * @return string
	 */
	public static function get_agent_category_label( $label ) {
		return self::CATEGORY_LABEL_PREFIX . ' ' . $label;
	}

	/**
	 * Register enabled abilities.
	 *
	 * @return void
	 */
	public function register_abilities() {
		$this->load_abilities_classes();
		$this->registry->register_enabled_abilities();
	}

	/**
	 * Register the Premium Addons MCP server.
	 *
	 * @param \WP\MCP\Core\McpAdapter $mcp_adapter MCP adapter instance.
	 * @return void
	 */
	public function register_server( $mcp_adapter ) {
		if ( ! did_action( 'init' ) ) {
			return;
		}

		$tools = $this->get_server_tool_names();

		// Return if no enabled abilities.
		if ( empty( $tools ) ) {
			return;
		}

		$design_prompt = Design\Design_Guide::get_prompt();

		$mcp_adapter->create_server(
			'premium-addons',
			self::SERVER_NAMESPACE,
			self::SERVER_ROUTE,
			__( 'Premium Addons MCP Server', 'premium-addons-for-elementor' ),
			__( 'Exposes Premium Addons widgets, templates and site data as MCP tools for AI agents.', 'premium-addons-for-elementor' ),
			'v' . PREMIUM_ADDONS_VERSION,
			array( \WP\MCP\Transport\HttpTransport::class ),
			\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
			\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler::class,
			$tools,
			array(),
			$design_prompt ? array( $design_prompt ) : array(),
			OAuth\Bootstrap::is_registered() ? array( OAuth\Bearer::class, 'permission_callback' ) : null
		);
	}

	/**
	 * Get the ability names to expose as MCP tools.
	 *
	 * The intersection of the Premium Addons abilities registered with
	 * WordPress and the ones enabled in the dashboard. Shared by the Premium
	 * Addons server and the Elementor MCP bridge so both expose one set.
	 *
	 * Must run after init: wp_get_abilities() boots the abilities registry.
	 *
	 * @since 4.11.104
	 * @return string[]
	 */
	public function get_server_tool_names() {
		$this->load_abilities_classes();

		return array_values(
			array_intersect(
				$this->get_registered_ability_names(),
				$this->registry->get_enabled_names()
			)
		);
	}

	/**
	 * Get the cached ability catalog for the settings UI.
	 *
	 * @return array
	 */
	public function get_abilities_catalog() {
		$hash               = md5( implode( ',', $this->ability_classes() ) . '|' . PREMIUM_ADDONS_VERSION );
		$catalog            = get_transient( 'pa_ai_abilities_catalog' );
		$is_current_catalog = is_array( $catalog )
			&& isset( $catalog['hash'], $catalog['items'] )
			&& is_array( $catalog['items'] )
			&& $hash === $catalog['hash'];

		if ( $is_current_catalog ) {
			return $catalog['items'];
		}

		$this->load_abilities_classes();

		$ability_items = $this->registry->get_abilities_meta();

		set_transient(
			'pa_ai_abilities_catalog',
			array(
				'hash'  => $hash,
				'items' => $ability_items,
			),
			WEEK_IN_SECONDS
		);

		return $ability_items;
	}

	/**
	 * Get handler class names.
	 *
	 * @return array
	 */
	private function ability_classes() {
		return array(
			Discovery\Check_Elementor_Element::class,
			Discovery\Detect_Atomic_Support::class,
			Discovery\Get_Addon_Schema::class,
			Discovery\Get_Element_Settings::class,
			Discovery\Get_Global_Settings::class,
			Discovery\Get_Id_By_Title::class,
			Discovery\Get_Page_Structure::class,
			Discovery\Get_Theme_Info::class,
			Discovery\Get_Theme_Styles::class,
			Discovery\Get_Widget_Schema::class,
			Discovery\List_Available_Elements::class,
			Discovery\List_Pa_Addons::class,
			Discovery\List_Pages::class,
			Discovery\List_Templates::class,
			Design\Get_Design_Guide::class,
			Build\Add_Container::class,
			Build\Add_Flexbox::class,
			Build\Insert_Widget::class,
			Build\Remove_Element::class,
			Build\Update_Element_Settings::class,
			Media\List_Media::class,
			Media\Upload_Media::class,
			Dashboard\Clear_Dynamic_Assets::class,
			Dashboard\Disable_Unused_Widgets::class,
			Dashboard\Get_Settings::class,
			Dashboard\Scan_Usage::class,
			Dashboard\Subscribe_Newsletter::class,
			Dashboard\Update_Setting::class,
			PagePostManagement\Change_Post_Status::class,
			PagePostManagement\Create_Elementor_Template::class,
			PagePostManagement\Create_Page::class,
			PagePostManagement\Duplicate_Post::class,
			Transfer\Check_Import_Compatibility::class,
			Transfer\Export_Elements::class,
			Transfer\Import_Elements::class,
			Templates\List_Premium_Templates::class,
			Templates\Insert_Premium_Template::class,
		);
	}

	/**
	 * Populate the handler registry once.
	 *
	 * @return void
	 */
	private function load_abilities_classes() {

		if ( ! empty( $this->registry->get_abilities_names() ) ) {
			return;
		}

		foreach ( $this->ability_classes() as $ability_class ) {
			$this->registry->register( new $ability_class() );
		}

		do_action( 'pa_abilities_register_handlers', $this->registry );
	}

	/**
	 * Get registered Premium Addons ability names from WordPress.
	 *
	 * @return array
	 */
	private function get_registered_ability_names() {
		$names = array();

		foreach ( wp_get_abilities() as $ability ) {
			$name = $ability->get_name();

			if ( 0 === strpos( $name, Ability_Registry::PREFIX ) ) {
				$names[] = $name;
			}
		}

		return $names;
	}
}
