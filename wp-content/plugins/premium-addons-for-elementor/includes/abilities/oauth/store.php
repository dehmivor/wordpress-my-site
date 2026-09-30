<?php
/**
 * OAuth Store.
 *
 * Persistence for the OAuth server: registered clients and issued tokens live
 * in two custom tables, short-lived authorization codes live in transients.
 * Tokens and codes are stored SHA-256-hashed, never in the clear. The tables
 * are created only when the user opts into OAuth — never on activation.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Class Store.
 *
 * @since 4.11.90
 */
class Store {

	/**
	 * Schema version.
	 *
	 * @var int
	 */
	const DB_VERSION = 1;

	/**
	 * Option holding the installed schema version. Autoloaded: is_installed()
	 * reads it on every request through Bootstrap::is_registered(), so it has to
	 * come from the options cache rather than its own query.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'pa_oauth_db_version';

	/**
	 * Authorization-code lifetime in seconds. Wide enough for CLI clients that
	 * print the URL for manual copy-paste.
	 *
	 * @var int
	 */
	const CODE_TTL = 300;

	/**
	 * Transient prefix for authorization codes.
	 *
	 * @var string
	 */
	const CODE_PREFIX = 'pa_oauth_code_';

	/**
	 * Absolute cap on registered clients per site.
	 *
	 * @var int
	 */
	const MAX_CLIENTS = 50;

	/**
	 * Registered-clients table name.
	 *
	 * @return string
	 */
	public static function clients_table() {
		global $wpdb;
		return $wpdb->prefix . 'pa_oauth_clients';
	}

	/**
	 * Issued-tokens table name.
	 *
	 * @return string
	 */
	public static function tokens_table() {
		global $wpdb;
		return $wpdb->prefix . 'pa_oauth_tokens';
	}

	/**
	 * Create/upgrade the OAuth tables when the stored version is behind.
	 * Called from the opt-in AJAX handler only.
	 *
	 * @return bool True when both tables are ready.
	 */
	public static function maybe_install() {

		if ( (int) get_option( self::DB_VERSION_OPTION, 0 ) >= self::DB_VERSION && self::tables_exist() ) {
			return true;
		}

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		global $wpdb;

		$charset = $wpdb->get_charset_collate();
		$clients = self::clients_table();
		$tokens  = self::tokens_table();

		dbDelta(
			"CREATE TABLE {$clients} (
				client_id VARCHAR(64) NOT NULL,
				client_name VARCHAR(191) NOT NULL,
				redirect_uris TEXT NOT NULL,
				created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
				created_at INT NOT NULL,
				PRIMARY KEY  (client_id),
				KEY created_at (created_at)
			) {$charset};"
		);

		// refresh_of is the cascade column: every rotation and every /revoke
		// deletes by it, and (user_id, expires_at) serves the dashboard's
		// "does this user hold a live token" lookup.
		dbDelta(
			"CREATE TABLE {$tokens} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				token_hash CHAR(64) NOT NULL,
				token_type VARCHAR(10) NOT NULL,
				client_id VARCHAR(64) NOT NULL,
				user_id BIGINT UNSIGNED NOT NULL,
				scopes VARCHAR(191) NOT NULL DEFAULT '',
				expires_at INT NOT NULL,
				refresh_of BIGINT UNSIGNED NULL DEFAULT NULL,
				created_at INT NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY token_hash (token_hash),
				KEY refresh_of (refresh_of),
				KEY user_id_expires (user_id, expires_at),
				KEY client_id (client_id),
				KEY expires_at (expires_at)
			) {$charset};"
		);

		if ( ! self::tables_exist() ) {
			return false;
		}

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, true );

		return true;
	}

	/**
	 * Whether both custom OAuth tables exist after installation.
	 *
	 * @return bool
	 */
	private static function tables_exist() {
		global $wpdb;

		foreach ( array( self::clients_table(), self::tokens_table() ) as $table ) {
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- confirms custom OAuth tables were created.

			if ( $table !== $found ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether the tables were ever installed on this site.
	 *
	 * Reads the autoloaded version flag only. This runs on every request through
	 * Bootstrap::is_registered(), and SHOW TABLES cannot be served from any cache,
	 * so confirming the tables here would put two uncacheable queries on every
	 * page load. verify_tables() does that confirmation where it is affordable.
	 *
	 * @return bool
	 */
	public static function is_installed() {
		return (int) get_option( self::DB_VERSION_OPTION, 0 ) > 0;
	}

	/**
	 * Confirm the tables behind the version flag are really present, clearing the
	 * flag when they are not so is_installed() stops reporting a surface this
	 * site cannot serve. Covers the case the flag alone misses: a staging sync or
	 * partial restore that copies options but not custom tables.
	 *
	 * Called from gc() daily and from the dashboard render — never on the request
	 * path.
	 *
	 * @return bool Whether the tables are present.
	 */
	public static function verify_tables() {

		if ( ! self::is_installed() ) {
			return false;
		}

		if ( self::tables_exist() ) {
			return true;
		}

		delete_option( self::DB_VERSION_OPTION );

		return false;
	}

	/**
	 * Register a new public client (Dynamic Client Registration).
	 *
	 * @param string $name          Human-readable client name.
	 * @param array  $redirect_uris Registered redirect URIs.
	 * @return array|\WP_Error Client data, or an error if registration cannot be stored.
	 */
	public static function create_client( $name, array $redirect_uris ) {
		global $wpdb;

		if ( ! self::acquire_lock( 'pa_oauth_clients' ) ) {
			return new \WP_Error( 'temporarily_unavailable', 'Unable to register a client right now. Please try again.' );
		}

		try {
			if ( self::count_clients() >= self::MAX_CLIENTS ) {
				return new \WP_Error( 'temporarily_unavailable', 'This site has reached its registered-client limit.' );
			}

			$client_id = Util::generate_client_id();
			$name      = mb_substr( $name, 0, 191 );
			$inserted  = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table.
				self::clients_table(),
				array(
					'client_id'     => $client_id,
					'client_name'   => $name,
					'redirect_uris' => wp_json_encode( $redirect_uris ),
					'created_by'    => get_current_user_id(),
					'created_at'    => time(),
				),
				array( '%s', '%s', '%s', '%d', '%d' )
			);

			if ( false === $inserted ) {
				return new \WP_Error( 'server_error', 'Unable to register this client.' );
			}

			return array(
				'client_id'     => $client_id,
				'client_name'   => $name,
				'redirect_uris' => $redirect_uris,
			);
		} finally {
			self::release_lock( 'pa_oauth_clients' );
		}
	}

	/**
	 * Fetch a client by id.
	 *
	 * @param string $client_id Client id.
	 * @return array|null Client data, or null when unknown.
	 */
	public static function get_client( $client_id ) {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
			$wpdb->prepare( 'SELECT * FROM ' . self::clients_table() . ' WHERE client_id = %s', $client_id ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name from trusted helper.
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$uris = json_decode( (string) $row['redirect_uris'], true );

		return array(
			'client_id'     => (string) $row['client_id'],
			'client_name'   => (string) $row['client_name'],
			'redirect_uris' => is_array( $uris ) ? $uris : array(),
		);
	}

	/**
	 * Count registered clients. create_client() locks this check with the insert,
	 * so the registration cap cannot be raced past.
	 *
	 * @return int
	 */
	public static function count_clients() {
		global $wpdb;

		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::clients_table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom table, no user input.
	}

	/**
	 * Delete clients that hold no live token and were registered over 24 h ago.
	 * Anonymous DCR would otherwise grow the table without bound.
	 *
	 * @return void
	 */
	public static function prune_clients() {
		global $wpdb;

		$clients = self::clients_table();
		$tokens  = self::tokens_table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom tables; names from trusted helpers.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE c FROM {$clients} c
				LEFT JOIN {$tokens} t ON t.client_id = c.client_id AND t.expires_at > %d
				WHERE t.id IS NULL AND c.created_at < %d",
				time(),
				time() - DAY_IN_SECONDS
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Store a single-use authorization code and return the raw code.
	 *
	 * @param array $payload Code payload: client_id, user_id, redirect_uri, code_challenge, scopes.
	 * @return string|\WP_Error The raw authorization code, or an error when it cannot be stored.
	 */
	public static function issue_code( array $payload ) {

		$code = Util::generate_token();

		if ( ! set_transient( self::CODE_PREFIX . Util::hash_token( $code ), $payload, self::CODE_TTL ) ) {
			return new \WP_Error( 'server_error', 'Unable to create an authorization code.' );
		}

		return $code;
	}

	/**
	 * Consume (fetch + delete) an authorization code.
	 *
	 * @param string $code Raw authorization code.
	 * @return array|null Payload, or null when unknown/used/expired.
	 */
	public static function consume_code( $code ) {

		$hash = Util::hash_token( $code );
		$lock = 'paoc_' . substr( $hash, 0, 59 );

		if ( ! self::acquire_lock( $lock ) ) {
			return null;
		}

		try {
			$key     = self::CODE_PREFIX . $hash;
			$payload = get_transient( $key );

			if ( ! is_array( $payload ) ) {
				return null;
			}

			if ( ! delete_transient( $key ) ) {
				return null;
			}

			return $payload;
		} finally {
			self::release_lock( $lock );
		}
	}

	/**
	 * Issue and persist a token (stored hashed). Returns the raw token.
	 *
	 * @param string   $type       'access' or 'refresh'.
	 * @param string   $client_id  Client id.
	 * @param int      $user_id    User the token acts as.
	 * @param string   $scopes     Space-separated scopes.
	 * @param int      $ttl        Lifetime in seconds.
	 * @param int|null $refresh_of Refresh row an access token is bound to (rotation cascade).
	 * @return array|\WP_Error Raw token + row id, or an error when persistence fails.
	 */
	public static function issue_token( $type, $client_id, $user_id, $scopes, $ttl, $refresh_of = null ) {
		global $wpdb;

		$token = Util::generate_token();

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table.
			self::tokens_table(),
			array(
				'token_hash' => Util::hash_token( $token ),
				'token_type' => $type,
				'client_id'  => $client_id,
				'user_id'    => $user_id,
				'scopes'     => $scopes,
				'expires_at' => time() + $ttl,
				'refresh_of' => $refresh_of,
				'created_at' => time(),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d' )
		);

		if ( false === $inserted ) {
			return new \WP_Error( 'server_error', 'Unable to issue a token.' );
		}

		return array(
			'token' => $token,
			'id'    => (int) $wpdb->insert_id,
		);
	}

	/**
	 * Look up an unexpired token row by raw token + type.
	 *
	 * @param string $token Raw token.
	 * @param string $type  'access' or 'refresh'.
	 * @return array|null Row, or null.
	 */
	public static function find_token( $token, $type ) {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
			$wpdb->prepare(
				'SELECT * FROM ' . self::tokens_table() . ' WHERE token_hash = %s AND token_type = %s AND expires_at > %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name from trusted helper.
				Util::hash_token( $token ),
				$type,
				time()
			),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Atomically consume an unexpired refresh token and revoke its paired access
	 * token. A token can therefore mint at most one replacement pair.
	 *
	 * @param string $token     Raw refresh token.
	 * @param string $client_id Client id presented at the token endpoint.
	 * @return array|null Consumed row, or null when unknown, expired, mismatched, or busy.
	 */
	public static function consume_refresh_token( $token, $client_id ) {

		$hash = Util::hash_token( $token );
		$lock = 'paor_' . substr( $hash, 0, 59 );

		if ( ! self::acquire_lock( $lock ) ) {
			return null;
		}

		try {
			$row = self::find_token( $token, 'refresh' );

			if ( null === $row || ! hash_equals( (string) $row['client_id'], $client_id ) ) {
				return null;
			}

			if ( ! self::revoke_token( (int) $row['id'] ) ) {
				return null;
			}

			return $row;
		} finally {
			self::release_lock( $lock );
		}
	}

	/**
	 * Delete a token row and any access token bound to it (refresh rotation).
	 *
	 * @param int $id Token row id.
	 * @return bool Whether both delete operations completed.
	 */
	public static function revoke_token( $id ) {
		global $wpdb;

		$token_deleted  = $wpdb->delete( self::tokens_table(), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table.
		$access_deleted = $wpdb->delete( self::tokens_table(), array( 'refresh_of' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table.

		return false !== $token_deleted && false !== $access_deleted;
	}

	/**
	 * Whether a user holds at least one unexpired token.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function user_has_live_token( $user_id ) {
		global $wpdb;

		if ( ! self::is_installed() ) {
			return false;
		}

		return (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
			$wpdb->prepare(
				'SELECT 1 FROM ' . self::tokens_table() . ' WHERE user_id = %d AND expires_at > %d LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name from trusted helper.
				$user_id,
				time()
			)
		);
	}

	/**
	 * Live refresh tokens of one user with their client's name, newest first.
	 * The refresh token is the grant a client holds; its access token hangs
	 * off it through refresh_of and goes with it on revoke.
	 *
	 * @since 4.11.108
	 *
	 * @param int $user_id User ID.
	 * @return array<int,array{id:int,client_name:string,created_at:int,expires_at:int}>
	 */
	public static function user_refresh_tokens( $user_id ) {
		global $wpdb;

		if ( ! self::is_installed() ) {
			return array();
		}

		$tokens  = self::tokens_table();
		$clients = self::clients_table();

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom tables.
			$wpdb->prepare(
				"SELECT t.id, t.created_at, t.expires_at, c.client_name
				FROM {$tokens} t
				LEFT JOIN {$clients} c ON c.client_id = t.client_id
				WHERE t.user_id = %d AND t.token_type = 'refresh' AND t.expires_at > %d
				ORDER BY t.created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from trusted helpers.
				$user_id,
				time()
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static function ( $row ) {
				return array(
					'id'          => (int) $row['id'],
					'client_name' => (string) $row['client_name'],
					'created_at'  => (int) $row['created_at'],
					'expires_at'  => (int) $row['expires_at'],
				);
			},
			$rows
		);
	}

	/**
	 * Fixed-window rate limit per client IP. Returns whether the request is
	 * allowed. The get/set counter is non-atomic, so this is a speed bump, not
	 * a hard cap — the client cap in count_clients() is the atomic backstop.
	 *
	 * @param string $bucket Limit bucket (dcr|token|revoke).
	 * @param int    $limit  Allowed requests per window.
	 * @param int    $window Window length in seconds.
	 * @return bool
	 */
	public static function throttle( $bucket, $limit, $window ) {

		// Raw REMOTE_ADDR on purpose: Helper_Functions::get_user_ip_address()
		// trusts X-Forwarded-For unconditionally, so keying on it would let a
		// client defeat the limit by rotating one header. Behind a real reverse
		// proxy REMOTE_ADDR is the proxy — hosts there can resolve the client
		// IP through this filter instead.
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- hashed immediately, never output or stored raw.
		$ip = (string) apply_filters( 'pa_oauth_client_ip', $ip );

		// Registration keeps its own key namespace: it is the one bucket with an
		// hour-long window, so a shared prefix would let a 60s token/revoke key
		// collide with it if a bucket were ever renamed to "dcr".
		$prefix = 'dcr' === $bucket ? 'pa_oauth_dcr_' : 'pa_oauth_rl_' . $bucket . '_';
		$key    = $prefix . hash( 'sha256', $ip );
		$count  = (int) get_transient( $key );

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, $window );

		return true;
	}

	/**
	 * Acquire a short-lived MySQL advisory lock. These locks make one-time
	 * credential consumption and the client cap safe across concurrent PHP
	 * workers without relying on a particular object-cache backend.
	 *
	 * @param string $name Lock name (64 bytes or fewer).
	 * @return bool
	 */
	private static function acquire_lock( $name ) {
		global $wpdb;

		return 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 0 )', $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- short-lived advisory lock for custom OAuth records.
	}

	/**
	 * Release a MySQL advisory lock held by this request.
	 *
	 * @param string $name Lock name.
	 * @return void
	 */
	private static function release_lock( $name ) {
		global $wpdb;

		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- release the advisory lock acquired above.
	}
	/**
	 * Housekeeping: drop expired tokens and stale clients. Runs daily on the
	 * pa_oauth_gc cron event.
	 *
	 * @return void
	 */
	public static function gc() {
		global $wpdb;

		// Doubles as the daily integrity check: the request path trusts the
		// version flag, so this is where a site whose tables vanished gets that
		// flag cleared.
		if ( ! self::verify_tables() ) {
			return;
		}

		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::tokens_table() . ' WHERE expires_at < %d', time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- custom table.

		self::prune_clients();
	}
}
