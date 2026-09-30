<?php
/**
 * Shared helpers for the AI abilities.
 *
 * The single helper bag for every ability group: the Elementor-active guard,
 * Elementor document resolution (with raw _elementor_data fallback), the shared
 * per-post edit permission check, the media-group attachment payload, and the
 * build-group tree/atomic utilities (element-id generation, tree insert/modify,
 * document save, and Elementor control-value formatters). Resolved through the
 * plugin autoloader.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities;

use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Includes\Helper_Functions;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Class Helpers.
 *
 * The single helper bag for every ability group — the group-neutral guards, the
 * media-group attachment payload, plus the build-group tree/atomic utilities.
 *
 * @since 4.11.75
 */
class Helpers {

	/**
	 * Cross-domain copy payload format version.
	 */
	const TRANSFER_VERSION = 1;

	/**
	 * Guard: Elementor active.
	 *
	 * @return \WP_Error|null Error when Elementor is not active, null otherwise.
	 */
	public static function guard_elementor() {

		if ( ! Helper_Functions::check_elementor_version() ) {
			return new \WP_Error(
				'premium_addons_not_initialized',
				__( 'Elementor is not active.', 'premium-addons-for-elementor' )
			);
		}

		return null;
	}

	/**
	 * Whether an Elementor core or Premium Addons widget.
	 *
	 * @param object $type_object Elementor widget type object.
	 * @return bool
	 */
	public static function is_core_or_pa_widget( $type_object ) {

		if ( ! is_object( $type_object ) ) {
			return false;
		}

		$class = get_class( $type_object );

		// For our widgets.
		if ( 0 === strpos( $class, 'PremiumAddons\\' ) ) {
			return true;
		}

		// For widgets with no Elementor\ namespace.
		if ( 0 !== strpos( $class, 'Elementor\\' ) ) {
			return false;
		}

		// For third-party plugins with Elementor\ namespace.
		static $cache = array();

		if ( ! isset( $cache[ $class ] ) ) {
			$file            = wp_normalize_path( ( new \ReflectionClass( $type_object ) )->getFileName() );
			$core_path       = defined( 'ELEMENTOR_PATH' ) ? wp_normalize_path( ELEMENTOR_PATH ) : '';
			$cache[ $class ] = '' !== $core_path && 0 === strpos( $file, $core_path );
		}

		return $cache[ $class ];
	}

	/**
	 * Guard: reject a third-party widget when Premium Addons Pro is inactive.
	 *
	 * @param object $type_object Elementor widget type object.
	 * @param string $name        Widget type name.
	 * @return \WP_Error|null Error when the widget is locked, null otherwise.
	 */
	public static function guard_widget_source( $type_object, $name ) {

		if ( self::is_core_or_pa_widget( $type_object ) ) {
			return null;
		}

		$has_pro   = Helper_Functions::check_papro_version();
		$settings  = Admin_Helper::get_ai_abilities_settings();
		$switch_on = ! empty( $settings['third_party_widgets'] );

		// If the user has Pro and the switch is on, allow third-party widgets.
		if ( $has_pro && $switch_on ) {
			return null;
		}

		if ( $has_pro ) {
			return new \WP_Error(
				'premium_addons_widget_source_disabled',
				sprintf(
					/* translators: %s: widget type name. */
					__( 'The widget type %s is a third-party widget. Building with third-party widgets is turned off in Premium Addons → AI Abilities → Build. Enable it there to allow this.', 'premium-addons-for-elementor' ),
					$name
				)
			);
		}

		$pro_link = Helper_Functions::get_campaign_link( 'https://premiumaddons.com/pro/#get-pa-pro', 'ai-abilities', 'mcp', 'get-pro' );

		return new \WP_Error(
			'premium_addons_widget_source_locked',
			sprintf(
				/* translators: 1: widget type name, 2: Premium Addons Pro URL. */
				__( 'The widget type %1$s is a third-party widget. Premium Addons free supports Elementor and Premium Addons widgets. Upgrade to Premium Addons Pro to build with third-party widgets: %2$s', 'premium-addons-for-elementor' ),
				$name,
				$pro_link
			)
		);
	}

	/**
	 * Resolve an Elementor document and its element tree for editing.
	 *
	 * Validates the post exists and is an Elementor document, then returns the
	 * document plus its current elements array (raw _elementor_data fallback).
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array|\WP_Error [ \Elementor\Core\Base\Document, array $elements ] or an error.
	 */
	public static function resolve_document( $post_id ) {

		$post = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_Error(
				'premium_addons_invalid_post_id',
				/* translators: %d: post ID. */
				sprintf( __( 'No page/post found with ID %d.', 'premium-addons-for-elementor' ), $post_id )
			);
		}

		$edit_mode = get_post_meta( $post_id, '_elementor_edit_mode', true );

		if ( 'builder' !== $edit_mode && 'elementor_library' !== $post->post_type ) {
			return new \WP_Error(
				'premium_addons_invalid_post_id',
				/* translators: %d: post ID. */
				sprintf( __( 'The post with ID %d is not built with Elementor. Use premium-addons/create-page to create an Elementor document first.', 'premium-addons-for-elementor' ), $post_id )
			);
		}

		$document = \Elementor\Plugin::$instance->documents->get( $post_id );

		if ( ! $document ) {
			return new \WP_Error(
				'premium_addons_document_not_found',
				/* translators: %d: post ID. */
				sprintf( __( 'Elementor could not resolve a document for the post with ID %d.', 'premium-addons-for-elementor' ), $post_id )
			);
		}

		$elements = $document->get_elements_data();

		if ( empty( $elements ) ) {
			$raw      = get_post_meta( $post_id, '_elementor_data', true );
			$elements = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : $raw;
		}

		if ( ! is_array( $elements ) ) {
			$elements = array();
		}

		return array( $document, $elements );
	}

	/**
	 * Shared permission callback for per-post abilities.
	 *
	 * map_meta_cap() fires _doing_it_wrong for edit_post on a missing post, so
	 * deny nonexistent IDs here — this also avoids leaking which IDs exist.
	 *
	 * @param array|null $input The ability input.
	 *
	 * @return bool
	 */
	public static function can_edit_input_post( $input ) {

		$post_id = ! empty( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return false;
		}

		return Admin_Helper::check_user_can( 'edit_post', $post_id );
	}

	/**
	 * Format an attachment as the payload the media abilities return.
	 *
	 * These are the fields a text-only client picks an image by. Shared so
	 * list-media and upload-media hand back the same shape and either result can
	 * be passed straight to a build ability as a media control value.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array { id, url, width, height, alt, mime_type }.
	 */
	public static function format_attachment( $attachment_id ) {

		// SVGs and other vector files store no dimensions.
		$metadata = wp_get_attachment_metadata( $attachment_id );

		// Both URL and MIME come back as false for an attachment whose file is
		// missing. The abilities declare them as strings and the Abilities API
		// validates output, so an uncast false would fail the whole call — for
		// list-media, losing every other image over one broken row.
		return array(
			'id'        => (int) $attachment_id,
			'url'       => (string) wp_get_attachment_url( $attachment_id ),
			'width'     => isset( $metadata['width'] ) ? (int) $metadata['width'] : 0,
			'height'    => isset( $metadata['height'] ) ? (int) $metadata['height'] : 0,
			'alt'       => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'mime_type' => (string) get_post_mime_type( $attachment_id ),
		);
	}

	/**
	 * Collect every element id in a tree.
	 *
	 * @param array $elements The element tree.
	 *
	 * @return array Flat list of element id strings.
	 */
	public static function collect_element_ids( $elements ) {

		$ids = array();

		foreach ( $elements as $element ) {

			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( ! empty( $element['id'] ) ) {
				$ids[] = (string) $element['id'];
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$ids = array_merge( $ids, self::collect_element_ids( $element['elements'] ) );
			}
		}

		return $ids;
	}

	/**
	 * Collect the distinct widget types used in a tree.
	 *
	 * Feeds the export manifest and the Destination-side availability scan, so it
	 * lists widget type keys only — structural elements carry no widgetType.
	 *
	 * @param array $elements The element tree.
	 *
	 * @return array Flat list of widget type keys.
	 */
	public static function collect_widget_types( $elements ) {

		$types = array();

		foreach ( $elements as $element ) {

			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( ! empty( $element['widgetType'] ) ) {
				$types[] = (string) $element['widgetType'];
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$types = array_merge( $types, self::collect_widget_types( $element['elements'] ) );
			}
		}

		return array_values( array_unique( $types ) );
	}

	/**
	 * Check a URL resolves to an address only this server can reach.
	 *
	 * wp_http_validate_url() — the whole of what wp_safe_remote_get() enforces —
	 * blocks 127/8, 10/8, 0/8, 172.16/12 and 192.168/16 and treats every other
	 * address as public. So http://169.254.169.254/, the instance metadata service
	 * on every major host, passes it, as do the CGNAT and container ranges. Any
	 * ability that fetches a caller-supplied URL server-side runs this on top.
	 *
	 * IPv6 needs no handling: core rejects bracketed literals on the ':' in its
	 * host check, and gethostbyname() fails closed for AAAA-only names.
	 *
	 * Two residuals are deliberate. Core skips its address check entirely when the
	 * host matches this site's own, and this does not re-add it — an image URL on
	 * the site's own domain is a legitimate upload source. And resolution here is
	 * separate from the transport's, so a name re-pointed between the two calls
	 * (DNS rebinding) is not covered; that needs connect-time IP pinning.
	 *
	 * @param string $url The URL about to be requested.
	 *
	 * @return bool
	 */
	public static function is_internal_address( $url ) {

		$host = (string) wp_parse_url( $url, PHP_URL_HOST );

		// A name that does not resolve comes back unchanged; padding keeps a host
		// that splits into fewer than four parts reading as 0.0.0.0.
		$ip    = filter_var( $host, FILTER_VALIDATE_IP ) ? $host : gethostbyname( $host );
		$parts = array_pad( array_map( 'intval', explode( '.', $ip ) ), 4, 0 );

		return ( 169 === $parts[0] && 254 === $parts[1] )                   // 169.254/16 link-local, incl. instance metadata.
			|| ( 100 === $parts[0] && 64 <= $parts[1] && 127 >= $parts[1] ) // 100.64/10 CGNAT and container networks.
			|| ( 198 === $parts[0] && 18 <= $parts[1] && 19 >= $parts[1] )  // 198.18/15 benchmarking.
			|| ( 192 === $parts[0] && 0 === $parts[1] && 0 === $parts[2] ); // 192.0.0/24 IETF protocol assignments.
	}

	/**
	 * Run a remote fetch with internal addresses rejected on every hop.
	 *
	 * Hooks pre_http_request rather than checking the URL once up front because
	 * core re-validates each redirect with the same permissive wp_http_validate_url()
	 * — a one-off pre-check is bypassed by a 302. pre_http_request fires again for
	 * every hop WP_Http::handle_redirects() issues, and returning a WP_Error there
	 * short-circuits the request before it leaves the box.
	 *
	 * @param string   $error_code The WP_Error code to return when a hop is internal.
	 * @param callable $fetch      The fetch to run.
	 *
	 * @return mixed The fetch result, or a WP_Error when a hop was rejected.
	 */
	public static function fetch_public_url( $error_code, $fetch ) {

		$block_internal = function ( $response, $args, $url ) use ( $error_code ) {

			if ( ! self::is_internal_address( $url ) ) {
				return $response;
			}

			return new \WP_Error(
				$error_code,
				__( 'The URL must be a public http(s) address. Local, private and internal addresses are not allowed.', 'premium-addons-for-elementor' )
			);
		};

		add_filter( 'pre_http_request', $block_internal, 10, 3 );

		$result = call_user_func( $fetch );

		remove_filter( 'pre_http_request', $block_internal, 10 );

		return $result;
	}

	/**
	 * Fetch and validate a cross-domain copy payload from the Source site.
	 *
	 * Runs server to server so the content never passes through the AI client.
	 * wp_safe_remote_get() rejects non-http(s) and the private ranges core knows
	 * about; is_internal_address() covers the ones it does not.
	 *
	 * @param string $transfer_url The signed transfer URL.
	 * @param bool   $consume      Whether to consume the single-use token.
	 *
	 * @return array|\WP_Error The payload, or an error.
	 */
	public static function fetch_transfer_payload( $transfer_url, $consume = false ) {

		// Set the consume flag from the caller's intent, never from the URL —
		// a pasted URL that already carried consume=1 would otherwise let the
		// read-only pre-flight burn the token.
		$url = $consume
			? add_query_arg( 'consume', 1, $transfer_url )
			: remove_query_arg( 'consume', $transfer_url );

		// A whole page's elements is a large body coming off another site's REST
		// API, so the usual 3s ceiling is not enough. This runs from an ability
		// call, not a page render, so the wait costs a visitor nothing.
		$response = self::fetch_public_url(
			'premium_addons_invalid_transfer_url',
			function () use ( $url ) {
				return wp_safe_remote_get( $url, array( 'timeout' => 15 ) ); // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
			}
		);

		// Surfaced on its own rather than folded into the generic failure below:
		// the caller pointed at an address this site will not fetch, which is a
		// bad input to correct, not a source site that might come back.
		if ( is_wp_error( $response ) && 'premium_addons_invalid_transfer_url' === $response->get_error_code() ) {
			return $response;
		}

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error(
				'premium_addons_transfer_data_unavailable',
				__( 'The transfer could not be fetched from the source site. The link may have expired, may have already been imported, or the source site may be unreachable.', 'premium-addons-for-elementor' )
			);
		}

		$payload = json_decode( wp_remote_retrieve_body( $response ), true );

		$is_valid = is_array( $payload )
			&& isset( $payload['pa_transfer_version'] )
			&& self::TRANSFER_VERSION === (int) $payload['pa_transfer_version']
			&& ! empty( $payload['content'] )
			&& is_array( $payload['content'] );

		if ( ! $is_valid ) {
			return new \WP_Error(
				'premium_addons_invalid_manifest',
				__( 'The transfer payload is empty or was produced by an unsupported version of Premium Addons.', 'premium-addons-for-elementor' )
			);
		}

		return $payload;
	}

	/**
	 * Check which of the given widget types this site can render.
	 *
	 * @param array $widget_keys Widget type keys.
	 *
	 * @return array { missing: array, pro_gated: array }.
	 */
	public static function scan_widget_availability( $widget_keys ) {

		$missing   = array();
		$pro_gated = array();

		foreach ( $widget_keys as $key ) {

			$type_object = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $key );

			if ( ! $type_object ) {
				$missing[] = $key;
				continue;
			}

			if ( is_wp_error( self::guard_widget_source( $type_object, $key ) ) ) {
				$pro_gated[] = $key;
			}
		}

		return array(
			'missing'   => $missing,
			'pro_gated' => $pro_gated,
		);
	}

	/**
	 * Generate a unique Elementor element id.
	 *
	 * Elementor's own Utils::generate_random_string() is dechex(rand()) with no
	 * collision guarantee, so generate a fixed 7-char hex id and retry against the
	 * ids already present in the tree.
	 *
	 * @param array $existing_ids Ids already used in the document.
	 *
	 * @return string A 7-character hex id unique within the document.
	 */
	public static function generate_element_id( $existing_ids ) {

		do {
			$id = dechex( wp_rand( 0x1000000, 0xFFFFFFF ) );
		} while ( in_array( $id, $existing_ids, true ) );

		return $id;
	}

	/**
	 * Insert a new element node into a tree.
	 *
	 * Empty parent_id inserts at the document root. Position is a zero-based index
	 * within the parent's children; omitted/null appends at the end.
	 *
	 * @param array    $elements  The element tree.
	 * @param string   $parent_id Parent element id, or '' for the root.
	 * @param int|null $position  Zero-based insert index, null to append.
	 * @param array    $node      The element node to insert.
	 * @param bool     $found     Set to true when the parent was found.
	 *
	 * @return array The modified tree.
	 */
	public static function insert_element( $elements, $parent_id, $position, $node, &$found ) {

		if ( '' === $parent_id ) {

			$found = true;

			if ( null === $position ) {
				$elements[] = $node;
			} else {
				array_splice( $elements, $position, 0, array( $node ) );
			}

			return $elements;
		}

		foreach ( $elements as $index => $element ) {

			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( isset( $element['id'] ) && (string) $element['id'] === $parent_id ) {

				$found    = true;
				$children = isset( $element['elements'] ) && is_array( $element['elements'] ) ? $element['elements'] : array();

				if ( null === $position ) {
					$children[] = $node;
				} else {
					array_splice( $children, $position, 0, array( $node ) );
				}

				$elements[ $index ]['elements'] = $children;

				return $elements;
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {

				$elements[ $index ]['elements'] = self::insert_element( $element['elements'], $parent_id, $position, $node, $found );

				if ( $found ) {
					return $elements;
				}
			}
		}

		return $elements;
	}

	/**
	 * Remove an element node from a tree by id.
	 *
	 * Splices the matching node out of its parent's children, so removing a
	 * container drops every element nested inside it. Walks by index like
	 * insert_element() rather than locate_element_ref(), which returns the node
	 * itself and so cannot unset it from its parent. Stops at the first match.
	 *
	 * @param array  $elements   The element tree.
	 * @param string $element_id The element id to remove.
	 * @param bool   $found      Set to true when the element was found.
	 *
	 * @return array The modified tree.
	 */
	public static function remove_element( $elements, $element_id, &$found ) {

		foreach ( $elements as $index => $element ) {

			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( isset( $element['id'] ) && (string) $element['id'] === $element_id ) {

				array_splice( $elements, $index, 1 );

				$found = true;

				return $elements;
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {

				$elements[ $index ]['elements'] = self::remove_element( $element['elements'], $element_id, $found );

				if ( $found ) {
					return $elements;
				}
			}
		}

		return $elements;
	}

	/**
	 * Locate an element node by id and return a reference to it within the tree.
	 *
	 * The single tree traversal shared by find_element() (read) and
	 * apply_to_element() (write) so the two never walk the tree differently.
	 * Returns a reference to the matching node — so callers can read a copy of it
	 * or replace it in place — or a reference to null when no node carries the id.
	 * Stops at the first match.
	 *
	 * @param array  $elements   The element tree (by reference).
	 * @param string $element_id The element id to locate.
	 *
	 * @return array|null Reference to the matching node, or to null when absent.
	 */
	private static function &locate_element_ref( &$elements, $element_id ) {

		$miss = null;

		foreach ( $elements as &$element ) {

			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( isset( $element['id'] ) && (string) $element['id'] === $element_id ) {
				return $element;
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {

				$nested = &self::locate_element_ref( $element['elements'], $element_id );

				if ( null !== $nested ) {
					return $nested;
				}

				unset( $nested );
			}
		}

		unset( $element );

		return $miss;
	}

	/**
	 * Find one element in a tree by id (read-only).
	 *
	 * Returns a copy of the matching node — id, elType, widgetType, settings,
	 * styles, nested elements — or null when the id is not present. Shares
	 * locate_element_ref() with apply_to_element() so a read and a write walk the
	 * tree identically.
	 *
	 * @param array  $elements   The element tree.
	 * @param string $element_id The element id to find.
	 *
	 * @return array|null The element node, or null when absent.
	 */
	public static function find_element( $elements, $element_id ) {

		$node = &self::locate_element_ref( $elements, $element_id );

		return $node;
	}

	/**
	 * Apply a modifier to one element in a tree.
	 *
	 * Locates the element with the given id and replaces it with the modifier's
	 * return value, leaving the rest of the tree untouched.
	 *
	 * @param array    $elements   The element tree.
	 * @param string   $element_id The element id to modify.
	 * @param callable $modify     function( array $element ): array.
	 * @param bool     $found      Set to true when the element was found.
	 *
	 * @return array The modified tree.
	 */
	public static function apply_to_element( $elements, $element_id, $modify, &$found ) {

		$node = &self::locate_element_ref( $elements, $element_id );

		if ( null !== $node ) {
			$found = true;
			$node  = call_user_func( $modify, $node );
		}

		return $elements;
	}

	/**
	 * Save an element tree through the Elementor document API.
	 *
	 * Document::save() replaces the whole _elementor_data tree, stamps
	 * _elementor_version and regenerates CSS. It returns false (without throwing)
	 * when the current user cannot edit the document, so surface that as an error.
	 * Atomic elements validate their settings/styles server-side on save and THROW
	 * on invalid data (Has_Atomic_Base::get_data_for_save), so catch and surface
	 * that too.
	 *
	 * @param \Elementor\Core\Base\Document $document The document.
	 * @param array                         $elements The full element tree to persist.
	 *
	 * @return true|\WP_Error
	 */
	public static function save_document( $document, $elements ) {

		try {
			$saved = $document->save( array( 'elements' => $elements ) );
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'premium_addons_invalid_element_data',
				/* translators: %s: validation error detail. */
				sprintf( __( 'Elementor rejected the element data: %s', 'premium-addons-for-elementor' ), $e->getMessage() )
			);
		}

		if ( ! $saved ) {
			return new \WP_Error(
				'premium_addons_save_failed',
				__( 'Elementor rejected the document save. The current user may not be allowed to edit this post.', 'premium-addons-for-elementor' )
			);
		}

		return true;
	}

	/**
	 * Create an Elementor library template holding the given element tree.
	 *
	 * The three steps a saved template needs — the post with its Elementor meta,
	 * the library-type term, and the document save that writes _elementor_data and
	 * generates CSS — shared by the create-elementor-template ability and the
	 * template bundling a cross-site copy installs.
	 *
	 * @param string $title         The template title.
	 * @param string $template_type The Elementor template type (container, section, page, …).
	 * @param array  $elements      The element tree to save into it.
	 * @param string $status        The post status.
	 *
	 * @return int|\WP_Error The new template post id, or an error.
	 */
	public static function create_library_template( $title, $template_type, $elements = array(), $status = 'publish' ) {

		$post_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_status' => $status,
				'post_type'   => 'elementor_library',
				'meta_input'  => array(
					'_elementor_edit_mode'     => 'builder',
					'_elementor_template_type' => $template_type,
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Register the template type as a term so it shows under the matching
		// Templates library filter, mirroring Elementor's own save flow.
		wp_set_object_terms( $post_id, $template_type, 'elementor_library_type' );

		$document = \Elementor\Plugin::$instance->documents->get( $post_id );

		$saved = $document
			? self::save_document( $document, $elements )
			: new \WP_Error(
				'premium_addons_document_not_found',
				__( 'Elementor could not resolve a document for the new template.', 'premium-addons-for-elementor' )
			);

		// A template that exists but holds no content would be matched by title
		// from then on and quietly render nothing, so take the failed one back out.
		if ( is_wp_error( $saved ) ) {

			wp_delete_post( $post_id, true );

			return $saved;
		}

		return $post_id;
	}

	/**
	 * Format a 4-side value as an Elementor dimensions control value.
	 *
	 * @param array $value { top?, right?, bottom?, left?, unit? } sides as numbers or strings.
	 *
	 * @return array Elementor dimensions value.
	 */
	public static function format_dimensions( $value ) {

		$sides = array( 'top', 'right', 'bottom', 'left' );
		$out   = array(
			'unit'     => ! empty( $value['unit'] ) ? $value['unit'] : 'px',
			'isLinked' => false,
		);

		foreach ( $sides as $side ) {
			$out[ $side ] = isset( $value[ $side ] ) ? (string) $value[ $side ] : '';
		}

		return $out;
	}

	/**
	 * Format a size value as an Elementor slider control value.
	 *
	 * @param array $value { size, unit? }.
	 *
	 * @return array Elementor slider value.
	 */
	public static function format_slider( $value ) {

		return array(
			'unit'  => ! empty( $value['unit'] ) ? $value['unit'] : 'px',
			'size'  => isset( $value['size'] ) ? $value['size'] : '',
			'sizes' => array(),
		);
	}

	/**
	 * Wrap a value as an atomic transformable envelope.
	 *
	 * Atomic (v4) elements store every prop as { $$type, value }.
	 *
	 * @param string $type  The prop type key (string, color, number, size, …).
	 * @param mixed  $value The inner value.
	 *
	 * @return array
	 */
	public static function atomic_value( $type, $value ) {

		return array(
			'$$type' => $type,
			'value'  => $value,
		);
	}

	/**
	 * Build an atomic size envelope.
	 *
	 * @param mixed  $size The size number.
	 * @param string $unit The unit, px by default.
	 *
	 * @return array
	 */
	public static function atomic_size( $size, $unit = 'px' ) {

		return self::atomic_value(
			'size',
			array(
				'size' => $size,
				'unit' => $unit ? $unit : 'px',
			)
		);
	}

	/**
	 * Build an atomic dimensions envelope from a top/right/bottom/left input.
	 *
	 * Maps the physical sides to the logical keys atomic styles use
	 * (top→block-start, right→inline-end, bottom→block-end, left→inline-start).
	 * Only the provided sides are included.
	 *
	 * @param array $value { top?, right?, bottom?, left?, unit? }.
	 *
	 * @return array
	 */
	public static function atomic_dimensions( $value ) {

		$map  = array(
			'top'    => 'block-start',
			'right'  => 'inline-end',
			'bottom' => 'block-end',
			'left'   => 'inline-start',
		);
		$unit = ! empty( $value['unit'] ) ? $value['unit'] : 'px';
		$out  = array();

		foreach ( $map as $side => $logical ) {
			if ( isset( $value[ $side ] ) ) {
				$out[ $logical ] = self::atomic_size( $value[ $side ], $unit );
			}
		}

		return self::atomic_value( 'dimensions', $out );
	}

	/**
	 * Map one Elementor (v3) control definition to a JSON Schema property.
	 *
	 * Pure per-control mapper for the get-widget-schema ability: turns a control's
	 * type into the JSON-Schema shape its stored value takes, carries the label as
	 * the description, the control default, and the control condition as depends_on.
	 * Returns null for structural/presentational and editor-hidden controls that
	 * hold no settable value, so callers can skip them.
	 *
	 * @param array $control One entry from Controls_Stack::get_controls().
	 *
	 * @return array|null JSON Schema property, or null when the control holds no value.
	 */
	public static function control_to_schema( $control ) {

		if ( isset( $control['classes'] ) && false !== strpos( $control['classes'], 'control-hidden' ) ) {
			return null;
		}

		$type  = isset( $control['type'] ) ? $control['type'] : '';
		$label = isset( $control['label'] ) ? $control['label'] : '';
		$prop  = array();

		switch ( $type ) {

			case 'text':
			case 'textarea':
			case 'wysiwyg':
			case 'code':
			case 'hidden':
			case 'select2':
			case 'date_time':
			case 'animation':
			case 'hover_animation':
				$prop['type'] = 'string';
				break;

			case 'number':
				$prop['type'] = 'number';
				break;

			case 'select':
			case 'choose':
				$prop['type'] = 'string';

				if ( ! empty( $control['options'] ) ) {
					$prop['enum'] = array_keys( $control['options'] );
				}
				break;

			case 'switcher':
			case 'popover_toggle':
				$prop['type'] = 'string';
				$prop['enum'] = array( 'yes', '' );
				break;

			case 'color':
				$prop['type']        = 'string';
				$prop['description'] = __( 'A CSS color value (hex, rgb, rgba, hsl).', 'premium-addons-for-elementor' );
				break;

			case 'slider':
				$prop['type']       = 'object';
				$prop['properties'] = array(
					'size' => array( 'type' => 'number' ),
					'unit' => array( 'type' => 'string' ),
				);
				break;

			case 'dimensions':
				$prop['type']       = 'object';
				$prop['properties'] = array(
					'top'      => array( 'type' => 'string' ),
					'right'    => array( 'type' => 'string' ),
					'bottom'   => array( 'type' => 'string' ),
					'left'     => array( 'type' => 'string' ),
					'unit'     => array( 'type' => 'string' ),
					'isLinked' => array( 'type' => 'boolean' ),
				);
				break;

			case 'url':
				$prop['type']       = 'object';
				$prop['properties'] = array(
					'url'         => array( 'type' => 'string' ),
					'is_external' => array( 'type' => 'string' ),
					'nofollow'    => array( 'type' => 'string' ),
				);
				break;

			case 'media':
			case 'gallery':
				$prop['type']       = 'object';
				$prop['properties'] = array(
					'url' => array( 'type' => 'string' ),
					'id'  => array( 'type' => 'integer' ),
				);
				break;

			case 'icon':
			case 'icons':
				$prop['type']       = 'object';
				$prop['properties'] = array(
					'value'   => array( 'type' => 'string' ),
					'library' => array( 'type' => 'string' ),
				);
				break;

			case 'typography':
			case 'text_shadow':
			case 'box_shadow':
			case 'border':
			case 'background':
			case 'css_filter':
			case 'text_stroke':
				// Group-control toggle — the real values live in companion {key}_* sub-controls.
				$prop['type']        = 'string';
				$prop['description'] = __( 'A group-control toggle; its values live in companion {key}_* controls (e.g. _font_family, _color, _width).', 'premium-addons-for-elementor' );
				break;

			case 'repeater':
				$prop['type']        = 'array';
				$prop['description'] = __( 'A list of repeater rows; each row is an object of the repeater fields.', 'premium-addons-for-elementor' );

				// Recurse over the row fields so each field maps to its own schema
				// (nested repeaters resolve naturally through this same mapper).
				$item_props = array();

				if ( ! empty( $control['fields'] ) && is_array( $control['fields'] ) ) {
					foreach ( $control['fields'] as $field_key => $field ) {

						// Fields carry their own name; the array key may be numeric.
						$field_name = isset( $field['name'] ) ? $field['name'] : $field_key;

						// Internal fields (_id and other _-prefixed row keys).
						if ( 0 === strpos( (string) $field_name, '_' ) ) {
							continue;
						}

						$mapped = self::control_to_schema( $field );

						if ( null !== $mapped ) {
							$item_props[ $field_name ] = $mapped;
						}
					}
				}

				$prop['items'] = array(
					'type'       => 'object',
					'properties' => (object) $item_props,
				);
				break;

			case 'section':
			case 'tab':
			case 'tabs':
			case 'divider':
			case 'heading':
			case 'raw_html':
			case 'deprecated_notice':
			case 'notice':
			case 'alert':
			case 'button':
				// Structural / presentational controls hold no settable value.
				return null;

			default:
				$prop['type'] = 'string';
				break;
		}

		if ( '' !== $label && empty( $prop['description'] ) ) {
			$prop['description'] = $label;
		}

		if ( isset( $control['default'] ) && '' !== $control['default'] && array() !== $control['default'] ) {
			$prop['default'] = $control['default'];
		}

		if ( ! empty( $control['condition'] ) ) {
			$prop['depends_on'] = $control['condition'];
		}

		// New-style nested conditions ({ relation, terms }). Kept in native shape —
		// flattening into the legacy map would lose OR/nesting/explicit operators.
		if ( ! empty( $control['conditions'] ) ) {
			$prop['depends_on'] = isset( $prop['depends_on'] )
				? array(
					// Both present: Elementor requires both to pass.
					'relation' => 'and',
					'terms'    => array( $prop['depends_on'], $control['conditions'] ),
				)
				: $control['conditions'];
		}

		return $prop;
	}

	/**
	 * Auto-set the unmet group-control prerequisite toggles for the provided keys.
	 *
	 * A group sub-control's value is inert until its master toggle is on —
	 * background_color renders nothing without background_background = classic. For
	 * each provided setting key, this reads the control's own condition to find the
	 * gating master toggle, and — when that toggle is a background/border/typography
	 * master present in the stack and the caller left it unset — fills it with the
	 * value that re-enables it, echoing what it set back to the caller.
	 *
	 * The enabling values are the documented fallback trio (background => classic,
	 * border => solid, typography => custom), hard-coded rather than derived
	 * from Elementor's group-control metadata.
	 *
	 * @param array $controls The control stack from Controls_Stack::get_controls().
	 * @param array $settings The caller-provided settings.
	 *
	 * @return array [ array $settings (with toggles filled), array $auto_set ].
	 */
	public static function apply_prerequisite_toggles( $controls, $settings ) {

		$enable_by_suffix = array(
			'typography' => 'custom',
			'border'     => 'solid',
			'background' => 'classic',
		);

		$auto_set = array();

		foreach ( array_keys( $settings ) as $key ) {

			if ( ! isset( $controls[ $key ]['condition'] ) || ! is_array( $controls[ $key ]['condition'] ) ) {
				continue;
			}

			foreach ( $controls[ $key ]['condition'] as $cond_key => $cond_value ) {

				// A trailing "!" marks a not-equal condition; the toggle key is the
				// same either way.
				$toggle_key   = rtrim( $cond_key, '!' );
				$enable_value = null;

				foreach ( $enable_by_suffix as $suffix => $value ) {
					if ( $suffix === substr( $toggle_key, - strlen( $suffix ) ) ) {
						$enable_value = $value;
						break;
					}
				}

				// Only fill a real group master toggle that exists in the stack —
				// never a content condition (icon_type, show_icon, …) — and never
				// override a toggle the caller set themselves.
				if ( null === $enable_value || ! isset( $controls[ $toggle_key ] ) || array_key_exists( $toggle_key, $settings ) ) {
					continue;
				}

				$settings[ $toggle_key ] = $enable_value;
				$auto_set[ $toggle_key ] = $enable_value;
			}
		}

		return array( $settings, $auto_set );
	}

	/**
	 * Collect the ids of the Premium Addons global-addon sections injected into a
	 * control stack.
	 *
	 * The global addons (Floating Effects, Tooltips, Display Conditions, Liquid
	 * Glass, … plus the Pro cursor/scroll/gradient family) are cross-widget
	 * extensions, not widget-owned settings — they flood every widget's Advanced
	 * tab. Each opens its section with the shared `pa-extension-icon` label marker,
	 * and a section-opener control's key IS its section id, so one pass over the
	 * stack finds them all with no hardcoded list — free and Pro alike.
	 *
	 * @param array $controls The control stack from Controls_Stack::get_controls().
	 *
	 * @return array Map of injected section id => true.
	 */
	public static function global_addon_section_ids( $controls ) {

		$sections = array();

		foreach ( $controls as $key => $control ) {

			$type  = isset( $control['type'] ) ? $control['type'] : '';
			$label = isset( $control['label'] ) ? $control['label'] : '';

			if ( 'section' === $type && false !== strpos( $label, 'pa-extension-icon' ) ) {
				$sections[ $key ] = true;
			}
		}

		return $sections;
	}

	/**
	 * Map one atomic (v4) prop type to a JSON Schema property.
	 *
	 * get_props_schema() returns Prop_Type OBJECTS, not arrays. Each exposes its
	 * transformable key (the $$type an update must envelope the value with) and a
	 * default via jsonSerialize(). Falls back gracefully for any shape that predates
	 * the object API.
	 *
	 * @param mixed $prop_type One entry from a type's get_props_schema().
	 *
	 * @return array JSON Schema property carrying the atomic prop $$type.
	 */
	public static function atomic_prop_to_schema( $prop_type ) {

		if ( is_object( $prop_type ) && method_exists( $prop_type, 'jsonSerialize' ) ) {

			$data  = $prop_type->jsonSerialize();
			$entry = array(
				'type'   => 'object',
				'$$type' => isset( $data['key'] ) ? $data['key'] : '',
			);

			if ( isset( $data['kind'] ) ) {
				$entry['kind'] = $data['kind'];
			}

			if ( array_key_exists( 'default', $data ) && null !== $data['default'] ) {
				$entry['default'] = $data['default'];
			}

			return $entry;
		}

		if ( is_array( $prop_type ) ) {
			return $prop_type;
		}

		return array( 'type' => 'object' );
	}
}
