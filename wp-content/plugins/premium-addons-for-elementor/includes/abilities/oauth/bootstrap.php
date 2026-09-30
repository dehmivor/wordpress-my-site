<?php
/**
 * OAuth Bootstrap.
 *
 * Owns the availability gates and wires the OAuth surface — discovery,
 * registration, authorize, token, the root fallback endpoints and the 401
 * challenge — only when the user has opted in. Nothing exists (no tables, no routes, no handlers) until then.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\OAuth;

use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Admin\Includes\MCP_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Class Bootstrap.
 *
 * @since 4.11.90
 */
class Bootstrap {

	/**
	 * Opt-in option. Absent means off.
	 *
	 * @var string
	 */
	const OPTION_ENABLED = 'pa_oauth_enabled';

	/**
	 * REST namespace the OAuth endpoints register under.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'premium-addons/v1';

	/**
	 * The single OAuth scope.
	 *
	 * @var string
	 */
	const SCOPE = 'mcp';

	/**
	 * Daily garbage-collection cron hook.
	 *
	 * Duplicated as a literal in PA_Core::load_abilities(), which binds the gc
	 * callback on every request and must not autoload this class to do it.
	 * Keep both in sync.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'pa_oauth_gc';

	/**
	 * Autoloaded option holding the timestamp registration stays open until.
	 *
	 * @var string
	 */
	const DCR_WINDOW = 'pa_oauth_dcr_window';

	/**
	 * How long registration stays open after an administrator loads the
	 * dashboard, in seconds.
	 *
	 * @var int
	 */
	const DCR_WINDOW_TTL = 1800;

	/**
	 * Class instance.
	 *
	 * @var Bootstrap|null
	 */
	private static $instance = null;

	/**
	 * Memoized is_registered() result for this request.
	 *
	 * @var bool|null
	 */
	private static $registered = null;

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
	 * Bootstrap constructor. Runs at plugins_loaded, so it may only add hooks:
	 * the transport gate walks the home_url filter chain, and $wp_rewrite does
	 * not exist yet — any gate that reached rest_url() here would fatal.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register' ), 20 );
	}

	/**
	 * Wire the OAuth surface when every gate passes. Evaluated once here so the
	 * parse_request handlers — which run on every front-end request — never
	 * re-run the gate chain themselves.
	 *
	 * @return void
	 */
	public function register() {

		if ( ! self::is_registered() ) {
			return;
		}

		// Re-arm the cleanup event. Deactivation clears it, and a cron array can
		// also be lost to a migration or a manual wipe — without this an enabled
		// site would keep issuing tokens that nothing ever expires.
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}

		add_action( 'parse_request', array( Metadata::class, 'maybe_serve' ), 0 );
		add_action( 'parse_request', array( Authorize::class, 'maybe_serve' ), 0 );

		// Root /register, /authorize and /token for clients whose discovery was
		// blocked at the host's edge. template_redirect, not parse_request: the
		// catcher must know the request would otherwise 404.
		add_action( 'template_redirect', array( Fallback::class, 'maybe_serve' ), 0 );

		add_action( 'rest_api_init', array( Clients::class, 'register_routes' ) );
		add_action( 'rest_api_init', array( Token::class, 'register_routes' ) );

		add_filter( 'rest_post_dispatch', array( Bearer::class, 'maybe_challenge' ), 10, 3 );
	}

	/**
	 * Open the client-registration window.
	 *
	 * Registration is anonymous by necessity — an MCP client has no WordPress
	 * credentials at the point it needs a client_id — so leaving it open
	 * permanently lets a stranger occupy every slot the client cap allows and
	 * lock legitimate clients out. Every real registration is preceded by an
	 * administrator opening this dashboard to copy the endpoint, so that visit
	 * is the signal used to unlock it.
	 *
	 * No capability check here: the dashboard page is already registered under
	 * manage_options, the same capability the authorize step requires.
	 *
	 * @return void
	 */
	public static function open_registration_window() {

		// A closed OAuth surface has no registration route to protect.
		if ( ! self::is_registered() ) {
			return;
		}

		update_option( self::DCR_WINDOW, time() + self::DCR_WINDOW_TTL, true );
	}

	/**
	 * Whether client registration is currently accepted.
	 *
	 * Define PA_OAUTH_DCR_ALWAYS_OPEN to keep it permanently open, for headless
	 * provisioning where no one can load the dashboard.
	 *
	 * @return bool
	 */
	public static function registration_open() {

		if ( defined( 'PA_OAUTH_DCR_ALWAYS_OPEN' ) && PA_OAUTH_DCR_ALWAYS_OPEN ) {
			return true;
		}

		return (int) get_option( self::DCR_WINDOW, 0 ) > time();
	}

	/**
	 * An OAuth error response. The code is a protocol value the client matches
	 * on (RFC 6749 §5.2, RFC 7591 §3.2.2), so it is never translated.
	 *
	 * @param string $code   OAuth error code.
	 * @param string $desc   Human-readable description.
	 * @param int    $status HTTP status.
	 * @return \WP_REST_Response
	 */
	public static function error_response( $code, $desc, $status = 400 ) {
		return new \WP_REST_Response(
			array(
				'error'             => $code,
				'error_description' => $desc,
			),
			$status
		);
	}

	/**
	 * The site's own path prefix, '' on a root install. Every path this plugin
	 * serves is matched under it, so a subdirectory install answers on the same
	 * URLs it advertises.
	 *
	 * @return string
	 */
	public static function home_path() {
		return rtrim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
	}

	/**
	 * The current request path, no query string and no trailing slash. Shared
	 * by the two parse_request handlers.
	 *
	 * @return string
	 */
	public static function request_path() {

		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- path component only, compared against fixed paths.
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );

		return '/' === $path ? $path : untrailingslashit( $path );
	}

	/**
	 * Why OAuth cannot be offered (gates 1-3), or '' when it can. One source of
	 * truth for the locked card, the opt-in AJAX refusal and is_registered().
	 *
	 * Gate 1 is checked here and not assumed: the opt-in AJAX action lives on
	 * Admin_Helper, which loads even when the abilities switcher is off.
	 *
	 * @return string
	 */
	public static function unavailable_reason() {

		if ( ! function_exists( 'wp_register_ability' ) ) {
			return __( 'OAuth requires the MCP & AI Abilities feature, which needs WordPress 7.0 or later.', 'premium-addons-for-elementor' );
		}

		if ( empty( Admin_Helper::get_enabled_elements()['premium-ai-abilities'] ) ) {
			return __( 'Turn on MCP & AI Abilities to use OAuth.', 'premium-addons-for-elementor' );
		}

		if ( ! MCP_Settings::oauth_transport_allowed() ) {
			return __( 'OAuth requires an HTTPS site. Tokens would travel in clear over HTTP.', 'premium-addons-for-elementor' );
		}

		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			return __( 'OAuth requires pretty permalinks. Choose any structure other than Plain in Settings → Permalinks.', 'premium-addons-for-elementor' );
		}

		return '';
	}

	/**
	 * The lock-plugin error an anonymous visitor would get from the REST
	 * authentication filter chain, or null when anonymous REST is reachable.
	 *
	 * Probed with no user set — the plugins that block anonymous REST exempt
	 * logged-in users. Not a gate: this runs on the opt-in click only.
	 *
	 * @since 4.11.100
	 * @return \WP_Error|null
	 */
	public static function rest_lock_error() {

		$restore = get_current_user_id();

		wp_set_current_user( 0 );
		$probe = apply_filters( 'rest_authentication_errors', null );
		wp_set_current_user( $restore );

		return is_wp_error( $probe ) ? $probe : null;
	}

	/**
	 * Whether the OAuth surface is live: available and opted in (gate 4).
	 * Gates the bearer callback binding, every endpoint and discovery.
	 *
	 * @return bool
	 */
	public static function is_registered() {

		if ( null === self::$registered ) {
			// Opt-in flag first: it is autoloaded, while unavailable_reason() walks
			// the heavily filtered home_url chain. Never-opted-in sites pay nothing.
			self::$registered = (bool) get_option( self::OPTION_ENABLED ) && Store::is_installed() && '' === self::unavailable_reason();
		}

		return self::$registered;
	}

	/**
	 * Best-effort full-page cache purge. Discovery documents are anonymous
	 * front-end GETs, so enabling OAuth must invalidate any cached pre-opt-in
	 * 404.
	 *
	 * @return void
	 */
	public static function flush_page_caches() {

		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache(); // WP Super Cache.
		}

		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all(); // W3 Total Cache.
		}

		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain(); // WP Rocket.
		}

		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache(); // SiteGround Optimizer.
		}

		do_action( 'litespeed_purge_all' ); // LiteSpeed Cache listens on this.
	}
}
