<?php
/**
 * MCP route detection.
 *
 * Knows which other MCP servers on this site already expose Premium Addons
 * abilities (Elementor MCP, Novamira) and whether an AI client uses one of
 * them: a recognised application password or OAuth token for any user, or a
 * recorded handshake on that server's REST route.
 */

namespace PremiumAddons\Includes\Abilities;

use PremiumAddons\Admin\Includes\Admin_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Route_Detector.
 *
 * @since 4.11.107
 */
class Route_Detector {

	/**
	 * Site option holding the credential scan result and recorded handshakes.
	 *
	 * @var string
	 */
	const OPTION = 'pa_mcp_routes';

	/**
	 * Upper bound on users scanned for application passwords.
	 *
	 * @var int
	 */
	const SCAN_USER_LIMIT = 200;

	/**
	 * JSON-RPC methods that prove a client completed a handshake.
	 *
	 * @var string[]
	 */
	const HANDSHAKE_METHODS = array( 'initialize', 'tools/call' );

	/**
	 * Hook the handshake recorder, the credential rescans, and the Elementor
	 * MCP switch.
	 *
	 * Bound before the AI Abilities switch gate, so evidence is already on
	 * record the moment the feature is turned on.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'record_handshake' ), 10, 3 );
		add_action( 'wp_create_application_password', array( __CLASS__, 'scan_credentials' ) );
		add_action( 'wp_delete_application_password', array( __CLASS__, 'scan_credentials' ) );

		// Elementor MCP's Turn On adds the option the first time and updates it
		// after; the new value is the second argument of both hooks.
		add_action( 'add_option_elementor_mcp_enabled', array( __CLASS__, 'follow_elementor_mcp_switch' ), 10, 2 );
		add_action( 'update_option_elementor_mcp_enabled', array( __CLASS__, 'follow_elementor_mcp_switch' ), 10, 2 );
	}

	/**
	 * Turn AI Abilities on when Elementor MCP is turned on. Turning it off
	 * changes nothing: only the user turns AI Abilities off.
	 *
	 * @param mixed $unused  Option name (add) or previous value (update).
	 * @param mixed $enabled New value of the Elementor MCP switch.
	 * @return void
	 */
	public static function follow_elementor_mcp_switch( $unused, $enabled ) {

		if ( ! $enabled || ! empty( Admin_Helper::get_enabled_elements()['premium-ai-abilities'] ) ) {
			return;
		}

		Admin_Helper::update_elements_settings( array( 'premium-ai-abilities' => true ) );
	}

	/**
	 * Known routes, in display order. Premium Addons MCP is not an entry: its
	 * own state lives in Connection_Log.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function catalog() {
		return array(
			'elementor' => array(
				'label'           => __( 'Elementor MCP', 'premium-addons-for-elementor' ),
				'routes'          => array( 'elementor/mcp' ),
				'config_page'     => 'admin.php?page=elementor-mcp',
				'password_prefix' => 'Elementor MCP - ',
			),
			'novamira'  => array(
				'label'           => __( 'Novamira MCP', 'premium-addons-for-elementor' ),
				'routes'          => array( 'mcp/novamira', 'mcp/novamira-oauth' ),
				'config_page'     => 'admin.php?page=novamira-connect',
				'password_prefix' => 'Novamira',
			),
		);
	}

	/**
	 * Whether the server behind a catalog entry can answer at all: the plugin
	 * is active and, for Elementor MCP, its Turn On switch (4.3.0+) is on.
	 *
	 * @param string $key Catalog key.
	 * @return bool
	 */
	public static function is_plugin_active( $key ) {

		switch ( $key ) {
			case 'elementor':
				return class_exists( '\Elementor\Modules\Mcp\Module' )
					&& \Elementor\Modules\Mcp\Module::is_active()
					&& self::is_elementor_mcp_enabled();
			case 'novamira':
				return defined( 'NOVAMIRA_VERSION' );
		}

		return false;
	}

	/**
	 * Elementor 4.3.0 registers its MCP server only while the site option
	 * behind McpSettingsController::is_enabled() is on. Earlier builds that
	 * ship the module without that controller have no switch: always on.
	 *
	 * @return bool
	 */
	private static function is_elementor_mcp_enabled() {

		$controller = '\Elementor\MCP\Composer\Admin\McpSettingsController';

		return ! class_exists( $controller ) || $controller::is_enabled();
	}

	/**
	 * Catalog key an application password belongs to, judged by its name.
	 * Prefixes mirror what each plugin writes when it creates the password.
	 *
	 * @param string $name Application password name.
	 * @return string Catalog key, or '' when the name is not a known route's.
	 */
	public static function password_route( $name ) {

		foreach ( self::catalog() as $key => $entry ) {
			if ( 0 === strpos( $name, $entry['password_prefix'] ) ) {
				return $key;
			}
		}

		return '';
	}

	/**
	 * Known routes with evidence, in catalog order: the plugin is active and a
	 * credential or a handshake is on record.
	 *
	 * @return array<string,array{label:string,config_url:string}>
	 */
	public static function get_routes() {

		$data   = self::read();
		$routes = array();

		foreach ( self::catalog() as $key => $entry ) {

			$state = $data['routes'][ $key ];

			if ( ! self::is_plugin_active( $key ) || ( ! $state['credential'] && 0 === $state['handshake'] ) ) {
				continue;
			}

			$routes[ $key ] = array(
				'label'      => $entry['label'],
				'config_url' => admin_url( $entry['config_page'] ),
			);
		}

		return $routes;
	}

	/**
	 * Rescan application passwords and Novamira tokens across users and store
	 * the result. Runs on password create/delete and on every render of the
	 * AI Abilities tab.
	 *
	 * @return void
	 */
	public static function scan_credentials() {

		$found = array_fill_keys( array_keys( self::catalog() ), false );

		foreach ( self::site_passwords() as $password ) {

			$key = self::password_route( (string) $password['name'] );

			if ( '' !== $key ) {
				$found[ $key ] = true;
			}
		}

		if ( ! $found['novamira'] ) {
			$found['novamira'] = self::has_novamira_token();
		}

		$data = self::read();

		foreach ( $found as $key => $credential ) {
			$data['routes'][ $key ]['credential'] = $credential;
		}

		update_option( self::OPTION, $data, false );
	}

	/**
	 * Record a completed MCP handshake on a known route.
	 *
	 * Runs on every REST response, so it exits as early as possible: method
	 * and status, a substring test on the raw body, the route, and only then
	 * a JSON decode. An unauthenticated probe never reaches the write: the
	 * permission callback has already turned $response into a WP_Error.
	 *
	 * @param mixed            $response Result to send, passed through untouched.
	 * @param array            $handler  Route handler.
	 * @param \WP_REST_Request $request  Request.
	 * @return mixed Unmodified $response.
	 */
	public static function record_handshake( $response, $handler, $request ) {

		if ( 'POST' !== $request->get_method() || is_wp_error( $response ) ) {
			return $response;
		}

		if ( $response instanceof \WP_REST_Response && 200 !== $response->get_status() ) {
			return $response;
		}

		$body = $request->get_body();

		if ( '' === $body || false === strpos( $body, '"jsonrpc"' ) ) {
			return $response;
		}

		$key = self::route_key( $request->get_route() );

		if ( '' === $key || ! self::is_handshake( $request->get_json_params() ) ) {
			return $response;
		}

		self::flag_handshake( $key );

		return $response;
	}

	/**
	 * Every application password on the site, across at most
	 * SCAN_USER_LIMIT users. Meta is primed once so the loop costs no
	 * per-user query.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function site_passwords() {

		$user_ids = get_users(
			array(
				'meta_key' => \WP_Application_Passwords::USERMETA_KEY_APPLICATION_PASSWORDS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- the only index of users holding passwords.
				'fields'   => 'ID',
				'number'   => self::SCAN_USER_LIMIT,
			)
		);

		if ( empty( $user_ids ) ) {
			return array();
		}

		update_meta_cache( 'user', $user_ids );

		$passwords = array();

		foreach ( $user_ids as $user_id ) {
			$passwords = array_merge( $passwords, \WP_Application_Passwords::get_user_application_passwords( (int) $user_id ) );
		}

		return $passwords;
	}

	/**
	 * Whether Novamira holds a live OAuth access or refresh token for any
	 * user. Its clients table never counts: rows carry no user and dynamic
	 * client registration lets anyone create one.
	 *
	 * @return bool
	 */
	private static function has_novamira_token() {
		global $wpdb;

		if ( ! self::is_plugin_active( 'novamira' ) ) {
			return false;
		}

		$now = gmdate( 'Y-m-d H:i:s' );

		foreach ( array( 'novamira_oauth_access_tokens', 'novamira_oauth_refresh_tokens' ) as $suffix ) {

			$table = $wpdb->prefix . $suffix;

			if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- third-party table, checked once per scan.
				continue;
			}

			$live = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- third-party table.
				$wpdb->prepare( 'SELECT 1 FROM ' . $table . ' WHERE revoked = 0 AND expires_at > %s LIMIT 1', $now ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is $wpdb->prefix plus a literal.
			);

			if ( $live ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Catalog key a REST route belongs to.
	 *
	 * @param string $route Route as the request saw it.
	 * @return string Catalog key, or '' for any other route.
	 */
	private static function route_key( $route ) {

		$route = trim( strtolower( $route ), '/' );

		foreach ( self::catalog() as $key => $entry ) {
			if ( in_array( $route, $entry['routes'], true ) ) {
				return $key;
			}
		}

		return '';
	}

	/**
	 * Whether a decoded JSON-RPC body, one request or a batch, carries an
	 * initialize or tools/call request.
	 *
	 * @param mixed $json Decoded request body.
	 * @return bool
	 */
	private static function is_handshake( $json ) {

		if ( ! is_array( $json ) ) {
			return false;
		}

		$requests = isset( $json['jsonrpc'] ) ? array( $json ) : $json;

		foreach ( $requests as $item ) {

			if ( ! is_array( $item ) || ! isset( $item['jsonrpc'], $item['method'] ) ) {
				continue;
			}

			if ( '2.0' === $item['jsonrpc'] && in_array( $item['method'], self::HANDSHAKE_METHODS, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Store the first handshake seen on a route. Later ones change nothing.
	 *
	 * @param string $key Catalog key.
	 * @return void
	 */
	private static function flag_handshake( $key ) {

		$data = self::read();

		if ( $data['routes'][ $key ]['handshake'] > 0 ) {
			return;
		}

		$data['routes'][ $key ]['handshake'] = time();

		update_option( self::OPTION, $data, false );
	}

	/**
	 * Stored state, normalised to the full shape so callers never test keys.
	 *
	 * @return array{routes:array<string,array{credential:bool,handshake:int}>}
	 */
	private static function read() {

		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$data   = array(
			'routes' => array(),
		);

		foreach ( array_keys( self::catalog() ) as $key ) {

			$route = isset( $stored['routes'][ $key ] ) && is_array( $stored['routes'][ $key ] ) ? $stored['routes'][ $key ] : array();

			$data['routes'][ $key ] = array(
				'credential' => ! empty( $route['credential'] ),
				'handshake'  => isset( $route['handshake'] ) ? (int) $route['handshake'] : 0,
			);
		}

		return $data;
	}
}
