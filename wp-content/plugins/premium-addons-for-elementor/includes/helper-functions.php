<?php
/**
 * PA Helper Functions.
 */

namespace PremiumAddons\Includes;

// Premium Addons Pro Classes.
use PremiumAddonsPro\Includes\White_Label\Helper;
use PremiumAddons\Admin\Includes\Admin_Helper;

// Elementor Classes.
use Elementor\Icons_Manager;
use Elementor\Core\Settings\Manager as SettingsManager;
use Elementor\Plugin;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;
use Elementor\Control_Media;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Helper_Functions.
 */
class Helper_Functions {

	/**
	 * A list of safe tags for `validate_html_tag` method.
	 */
	const ALLOWED_HTML_WRAPPER_TAGS = array(
		'article',
		'aside',
		'div',
		'footer',
		'h1',
		'h2',
		'h3',
		'h4',
		'h5',
		'h6',
		'header',
		'main',
		'nav',
		'p',
		'section',
		'span',
	);

	/**
	 * Theme
	 *
	 * @var theme
	 */
	private static $current_theme = null;

	/**
	 * Google maps prefixes
	 *
	 * @var google_localize
	 */
	private static $google_localize = null;

	/**
	 * SVG Shapes
	 *
	 * @var shapes
	 */
	private static $shapes = null;

	/**
	 * WP lang prefixes
	 *
	 * @var lang_locales
	 */
	private static $lang_locales = null;

	/**
	 * Script debug enabled
	 *
	 * @var script_debug
	 */
	private static $script_debug = null;

	/**
	 * JS scripts directory
	 *
	 * @var js_dir
	 */
	private static $js_dir = null;

	/**
	 * CSS files directory
	 *
	 * @var js_dir
	 */
	private static $css_dir = null;

	/**
	 * JS Suffix
	 *
	 * @var js_suffix
	 */
	private static $assets_suffix = null;

	/**
	 * Get Icon SVG Data
	 *
	 * @since 4.10.72
	 * @access private
	 *
	 * @return array icon data.
	 */
	private static function get_icon_svg_data( $icon ) {

		preg_match( '/fa(.*) fa-/', $icon['value'], $icon_name_matches );

		if ( empty( $icon_name_matches ) ) {
			return;
		}

		$icon_name = str_replace( $icon_name_matches[0], '', $icon['value'] );

		$icon_key = str_replace( ' fa-', '-', $icon['value'] );

		$icon_file_name = str_replace( 'fa-', '', $icon['library'] );

		$path = ELEMENTOR_ASSETS_PATH . 'lib/font-awesome/json/' . $icon_file_name . '.json';

		$data = file_get_contents( $path );

		if ( ! $data ) {
			return;
		}

		$data = json_decode( $data, true );

		$svg_data = $data['icons'][ $icon_name ];

		return array(
			'width'  => $svg_data[0],
			'height' => $svg_data[1],
			'path'   => $svg_data[4],
		);
	}

	/**
	 * Check if white labeling - Free version author field is set
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return string
	 */
	public static function author() {

		$author_free = 'Leap13';

		if ( self::check_papro_version() ) {

			$white_label = Helper::get_white_labeling_settings();

			$author_free = $white_label['premium-wht-lbl-name'];

		}

		return '' !== $author_free ? $author_free : 'Leap13';
	}

	/**
	 * Check if white labeling - Free version name field is set
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return string
	 */
	public static function name() {

		$name_free = 'Premium Addons';

		if ( self::check_papro_version() ) {

			$white_label = Helper::get_white_labeling_settings();

			$name_free = $white_label['premium-wht-lbl-plugin-name'];

		}

		return '' !== $name_free ? $name_free : 'Premium Addons';
	}

	/**
	 * Check if white labeling - Hide row meta option is checked
	 *
	 * @since 1.0.0
	 * @return boolean
	 */
	public static function is_hide_row_meta() {

		$hide_meta = false;

		if ( self::check_papro_version() ) {

			$white_label = Helper::get_white_labeling_settings();

			$hide_meta = $white_label['premium-wht-lbl-row'];

		}

		return $hide_meta;
	}

	/**
	 * Check if white labeling - Hide plugin logo option is checked
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return boolean
	 */
	public static function is_hide_logo() {

		if ( self::check_papro_version() ) {

			if ( isset( get_option( 'pa_wht_lbl_save_settings' )['premium-wht-lbl-logo'] ) ) {

				$hide_logo = get_option( 'pa_wht_lbl_save_settings' )['premium-wht-lbl-logo'];

			}
		}

		return isset( $hide_logo ) ? $hide_logo : false;
	}

	/**
	 * Get White Labeling - Widgets Category string
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return string
	 */
	public static function get_category() {

		$category = __( 'Premium Addons', 'premium-addons-for-elementor' );

		if ( self::check_papro_version() ) {

			$white_label = Helper::get_white_labeling_settings();

			$category = $white_label['premium-wht-lbl-short-name'];

		}

		return '' !== $category ? $category : __( 'Premium Addons', 'premium-addons-for-elementor' );
	}

	/**
	 * Get White Labeling - Widgets Prefix string
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return string
	 */
	public static function get_prefix() {

		$prefix = __( 'Premium', 'premium-addons-for-elementor' );

		if ( self::check_papro_version() ) {

			$white_label = Helper::get_white_labeling_settings();

			$prefix = $white_label['premium-wht-lbl-prefix'];

		}

		return '' !== $prefix ? $prefix : __( 'Premium', 'premium-addons-for-elementor' );
	}

	/**
	 * Check if white labeling - Future notification checked
	 *
	 * @since 1.0.0
	 * @return boolean
	 */
	public static function check_hide_notifications() {

		if ( self::check_papro_version() ) {

			$white_label = Helper::get_white_labeling_settings();

			$hide_notification = isset( $white_label['premium-wht-lbl-not'] ) ? $white_label['premium-wht-lbl-not'] : false;

		}

		return isset( $hide_notification ) ? $hide_notification : false;
	}

	/**
	 * Get White Labeling - Widgets Badge string
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return string
	 */
	public static function get_badge() {

		$badge = 'PA';

		if ( self::check_papro_version() ) {

			$white_label = Helper::get_white_labeling_settings();

			$badge = $white_label['premium-wht-lbl-badge'];

		}

		return '' !== $badge ? $badge : 'PA';
	}

	/**
	 * Get Google Maps localization prefixes
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return array
	 */
	public static function get_google_maps_prefixes() {

		if ( null === self::$google_localize ) {

			self::$google_localize = array(
				'ar'    => __( 'Arabic', 'premium-addons-for-elementor' ),
				'eu'    => __( 'Basque', 'premium-addons-for-elementor' ),
				'bg'    => __( 'Bulgarian', 'premium-addons-for-elementor' ),
				'bn'    => __( 'Bengali', 'premium-addons-for-elementor' ),
				'ca'    => __( 'Catalan', 'premium-addons-for-elementor' ),
				'cs'    => __( 'Czech', 'premium-addons-for-elementor' ),
				'da'    => __( 'Danish', 'premium-addons-for-elementor' ),
				'de'    => __( 'German', 'premium-addons-for-elementor' ),
				'el'    => __( 'Greek', 'premium-addons-for-elementor' ),
				'en'    => __( 'English', 'premium-addons-for-elementor' ),
				'en-AU' => __( 'English (australian)', 'premium-addons-for-elementor' ),
				'en-GB' => __( 'English (great britain)', 'premium-addons-for-elementor' ),
				'es'    => __( 'Spanish', 'premium-addons-for-elementor' ),
				'fa'    => __( 'Farsi', 'premium-addons-for-elementor' ),
				'fi'    => __( 'Finnish', 'premium-addons-for-elementor' ),
				'fil'   => __( 'Filipino', 'premium-addons-for-elementor' ),
				'fr'    => __( 'French', 'premium-addons-for-elementor' ),
				'gl'    => __( 'Galician', 'premium-addons-for-elementor' ),
				'gu'    => __( 'Gujarati', 'premium-addons-for-elementor' ),
				'hi'    => __( 'Hindi', 'premium-addons-for-elementor' ),
				'hr'    => __( 'Croatian', 'premium-addons-for-elementor' ),
				'hu'    => __( 'Hungarian', 'premium-addons-for-elementor' ),
				'id'    => __( 'Indonesian', 'premium-addons-for-elementor' ),
				'it'    => __( 'Italian', 'premium-addons-for-elementor' ),
				'iw'    => __( 'Hebrew', 'premium-addons-for-elementor' ),
				'ja'    => __( 'Japanese', 'premium-addons-for-elementor' ),
				'kn'    => __( 'Kannada', 'premium-addons-for-elementor' ),
				'ko'    => __( 'Korean', 'premium-addons-for-elementor' ),
				'lt'    => __( 'Lithuanian', 'premium-addons-for-elementor' ),
				'lv'    => __( 'Latvian', 'premium-addons-for-elementor' ),
				'ml'    => __( 'Malayalam', 'premium-addons-for-elementor' ),
				'mr'    => __( 'Marathi', 'premium-addons-for-elementor' ),
				'nl'    => __( 'Dutch', 'premium-addons-for-elementor' ),
				'no'    => __( 'Norwegian', 'premium-addons-for-elementor' ),
				'pl'    => __( 'Polish', 'premium-addons-for-elementor' ),
				'pt'    => __( 'Portuguese', 'premium-addons-for-elementor' ),
				'pt-BR' => __( 'Portuguese (brazil)', 'premium-addons-for-elementor' ),
				'pt-PT' => __( 'Portuguese (portugal)', 'premium-addons-for-elementor' ),
				'ro'    => __( 'Romanian', 'premium-addons-for-elementor' ),
				'ru'    => __( 'Russian', 'premium-addons-for-elementor' ),
				'sk'    => __( 'Slovak', 'premium-addons-for-elementor' ),
				'sl'    => __( 'Slovenian', 'premium-addons-for-elementor' ),
				'sr'    => __( 'Serbian', 'premium-addons-for-elementor' ),
				'sv'    => __( 'Swedish', 'premium-addons-for-elementor' ),
				'tl'    => __( 'Tagalog', 'premium-addons-for-elementor' ),
				'ta'    => __( 'Tamil', 'premium-addons-for-elementor' ),
				'te'    => __( 'Telugu', 'premium-addons-for-elementor' ),
				'th'    => __( 'Thai', 'premium-addons-for-elementor' ),
				'tr'    => __( 'Turkish', 'premium-addons-for-elementor' ),
				'uk'    => __( 'Ukrainian', 'premium-addons-for-elementor' ),
				'vi'    => __( 'Vietnamese', 'premium-addons-for-elementor' ),
				'zh-CN' => __( 'Chinese (simplified)', 'premium-addons-for-elementor' ),
				'zh-TW' => __( 'Chinese (traditional)', 'premium-addons-for-elementor' ),
			);
		}

		return self::$google_localize;
	}

	/**
	 * Checks if a plugin is installed
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param string $plugin_path plugin path.
	 *
	 * @return boolean
	 */
	public static function is_plugin_installed( $plugin_path ) {

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugins = get_plugins();

		return isset( $plugins[ $plugin_path ] );
	}

	/**
	 * Check if script debug mode enabled.
	 *
	 * @since 3.11.1
	 * @access public
	 *
	 * @return boolean is debug mode enabled
	 */
	public static function is_debug_enabled() {

		if ( null === self::$script_debug ) {

			self::$script_debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
		}

		return self::$script_debug;
	}

	/**
	 * Get scripts dir.
	 *
	 * @access public
	 *
	 * @return string JS scripts directory.
	 */
	public static function get_scripts_dir() {

		if ( null === self::$js_dir ) {

			self::$js_dir = self::is_debug_enabled() ? 'js' : 'min-js';
		}

		return self::$js_dir;
	}

	/**
	 * Get styles dir.
	 *
	 * @access public
	 *
	 * @return string CSS files directory.
	 */
	public static function get_styles_dir() {

		if ( null === self::$css_dir ) {

			self::$css_dir = self::is_debug_enabled() ? 'css' : 'min-css';
		}

		return self::$css_dir;
	}

	/**
	 * Get assets suffix.
	 *
	 * @access public
	 *
	 * @return string JS scripts suffix.
	 */
	public static function get_assets_suffix() {

		if ( null === self::$assets_suffix ) {

			self::$assets_suffix = self::is_debug_enabled() ? '' : '.min';
		}

		return self::$assets_suffix;
	}

	/**
	 * Get Installed Theme
	 *
	 * Returns the active theme slug
	 *
	 * @access public
	 *
	 * @return string theme slug
	 */
	public static function get_installed_theme() {

		if ( null === self::$current_theme ) {

			$theme = wp_get_theme();

			if ( $theme->parent() ) {

				$theme_name = sanitize_key( $theme->parent()->get( 'Name' ) );

			} else {

				$theme_name = $theme->get( 'Name' );

				$theme_name = sanitize_key( $theme_name );

			}

			self::$current_theme = $theme_name;

		}

		return self::$current_theme;
	}

	/**
	 * Get Vimeo Video Data
	 *
	 * Get video data using Vimeo API
	 *
	 * @since 3.11.4
	 * @access public
	 *
	 * @param string $video_id video ID.
	 */
	public static function get_vimeo_video_data( $video_id ) {

		$vimeo_data = get_transient( 'premium_vimeo_' . $video_id );

		if ( false === $vimeo_data ) {

			$response = wp_remote_get(
				'https://vimeo.com/api/oembed.json?url=' . rawurlencode( 'https://vimeo.com/' . intval( $video_id ) ) . '&width=1280',
				array(
					'timeout'   => 5,
					'sslverify' => true,
				)
			);

			if ( is_wp_error( $response ) ) {
				return false;
			}

			$status_code = wp_remote_retrieve_response_code( $response );

			if ( 200 === $status_code ) {

				$body = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( ! is_array( $body ) ) {
					return false;
				}

				$vimeo_data = array(
					'src'      => isset( $body['thumbnail_url'] ) ? $body['thumbnail_url'] : false,
					'url'      => isset( $body['author_url'] ) ? $body['author_url'] : false,
					'portrait' => false,
					'title'    => isset( $body['title'] ) ? $body['title'] : false,
					'user'     => isset( $body['author_name'] ) ? $body['author_name'] : false,
				);

				set_transient( 'premium_vimeo_' . $video_id, $vimeo_data, WEEK_IN_SECONDS );

				return $vimeo_data;

			}
		}

		return $vimeo_data;
	}

	/**
	 * Get Video Thumbnail
	 *
	 * Get thumbnail URL for embed or self hosted
	 *
	 * @since 3.7.0
	 * @access public
	 *
	 * @param string $video_id video ID.
	 * @param string $type embed type.
	 * @param string $size youtube thumbnail size.
	 */
	public static function get_video_thumbnail( $video_id, $type, $size = '' ) {

		$thumbnail_src = 'transparent';

		if ( 'youtube' === $type ) {
			if ( '' === $size ) {
				$size = 'maxresdefault';
			}
			$thumbnail_src = sprintf( 'https://i.ytimg.com/vi/%s/%s.jpg', $video_id, $size );

		} elseif ( 'vimeo' === $type ) {

			$vimeo = self::get_vimeo_video_data( $video_id );

			$thumbnail_src = is_array( $vimeo ) ? $vimeo['src'] : '';

		} elseif ( 'dailymotion' === $type ) {

			$cache_key = 'pa_dm_' . $video_id;

			$thumbnail_src = get_transient( $cache_key );

			if ( false === $thumbnail_src ) {

				$video_data = wp_remote_get(
					'https://api.dailymotion.com/video/' . $video_id . '?fields=thumbnail_url',
					array(
						'timeout'   => 5,
						'sslverify' => true,
					)
				);

				if ( is_wp_error( $video_data ) || 200 !== wp_remote_retrieve_response_code( $video_data ) ) {
					$thumbnail_src = 'transparent';
				} else {
					$video_data = wp_remote_retrieve_body( $video_data );
					$video_data = json_decode( $video_data );

					$thumbnail_src = isset( $video_data->thumbnail_url ) ? $video_data->thumbnail_url : 'transparent';
				}

				set_transient( $cache_key, $thumbnail_src, WEEK_IN_SECONDS );

			}
		}

		return $thumbnail_src;
	}

	/**
	 * Transient Expire
	 *
	 * Gets expire time of transient.
	 *
	 * @since 3.20.8
	 * @access public
	 *
	 * @param string $period transient expiration period.
	 *
	 * @return int $expire_time expire time in seconds.
	 */
	public static function transient_expire( $period ) {

		$expire_time = 24 * HOUR_IN_SECONDS;

		switch ( $period ) {
			case 'minute':
				$expire_time = MINUTE_IN_SECONDS;
				break;
			case 'minutes':
				$expire_time = 5 * MINUTE_IN_SECONDS;
				break;
			case 'hour':
				$expire_time = 60 * MINUTE_IN_SECONDS;
				break;
			case 'week':
				$expire_time = 7 * DAY_IN_SECONDS;
				break;
			case 'month':
				$expire_time = 30 * DAY_IN_SECONDS;
				break;
			case 'year':
				$expire_time = 365 * DAY_IN_SECONDS;
				break;
			default:
				$expire_time = 24 * HOUR_IN_SECONDS;
		}

		return $expire_time;
	}

	/**
	 * Get Campaign Link
	 *
	 * @since 3.20.9
	 * @access public
	 *
	 * @param string $link page link.
	 * @param string $source source.
	 * @param string $medium  media.
	 * @param string $campaign campaign name.
	 * @param string $content content identifier, becomes utm_content.
	 *
	 * @return string $link campaign URL
	 */
	public static function get_campaign_link( $link, $source, $medium, $campaign = '', $content = '' ) {

		if ( null === self::$current_theme ) {
			self::get_installed_theme();
		}

		$args = array(
			'utm_source'   => $source,
			'utm_medium'   => $medium,
			'utm_campaign' => $campaign,
			'utm_term'     => self::$current_theme,
			'utm_content'  => $content,
		);

		$args = array_filter( $args );

		$url = add_query_arg( $args, $link );

		return $url;
	}

	/**
	 * Get Elementor UI Theme
	 *
	 * Detects user setting for UI theme
	 *
	 * @since 3.21.1
	 * @access public
	 *
	 * @return string $theme UI Theme
	 */
	public static function get_elementor_ui_theme() {

		$theme = SettingsManager::get_settings_managers( 'editorPreferences' )->get_model()->get_settings( 'ui_theme' );

		return $theme;
	}

	/**
	 * Check PAPRO Version
	 *
	 * Check if PAPRO version is updated
	 *
	 * @since 3.21.6
	 * @access public
	 *
	 * @return boolean
	 */
	public static function check_papro_version() {
		return defined( 'PREMIUM_PRO_ADDONS_VERSION' );
	}

	/**
	 * Check Elementor Version
	 *
	 * Check if Elementor is installed and activated
	 *
	 * @since 4.11.54
	 * @access public
	 *
	 * @return boolean
	 */
	public static function check_elementor_version() {
		return defined( 'ELEMENTOR_VERSION' ) && class_exists( 'Elementor\Plugin' );
	}

	/**
	 * Validate HTML Tag
	 *
	 * Validates an HTML tag against a safe allowed list.
	 *
	 * @param string $tag HTML tag.
	 *
	 * @return string
	 */
	public static function validate_html_tag( $tag ) {
		$tag = strtolower( (string) $tag );
		return in_array( $tag, self::ALLOWED_HTML_WRAPPER_TAGS, true ) ? $tag : 'div';
	}

	/**
	 * Get Image Data
	 *
	 * Returns image data based on image id.
	 *
	 * @since 0.0.1
	 * @access public
	 *
	 * @param int          $image_id Image ID.
	 * @param string       $image_url Image URL.
	 * @param string|array $image_size Image size name or [ width, height ] array.
	 *
	 * @return array $data image data.
	 */
	public static function get_image_data( $image_id, $image_url, $image_size ) {

		if ( ! $image_id && ! $image_url ) {
			return false;
		}

		$data = array();

		$image_url = esc_url_raw( $image_url );

		if ( ! empty( $image_id ) ) { // Existing attachment.

			$attachment = get_post( $image_id );

			if ( is_object( $attachment ) ) {
				$data['id']  = $image_id;
				$data['url'] = $image_url;

				$data['image']       = wp_get_attachment_image( $attachment->ID, $image_size, true );
				$data['image_size']  = $image_size;
				$data['caption']     = $attachment->post_excerpt;
				$data['title']       = $attachment->post_title;
				$data['description'] = $attachment->post_content;

			}
		} else { // Placeholder image, most likely.

			if ( empty( $image_url ) ) {
				return;
			}

			$data['id']          = false;
			$data['url']         = $image_url;
			$data['image']       = '<img src="' . $image_url . '" alt="" title="" />';
			$data['image_size']  = $image_size;
			$data['caption']     = '';
			$data['title']       = '';
			$data['description'] = '';
		}

		return $data;
	}

	/**
	 * Returns the current page id.
	 * get_the_ID returns the first product ID in the loop if it's the shop page.
	 * and it sometimes returns a wrong ID in the other cases.
	 *
	 * @access public
	 * @since 4.11.8
	 * @return int
	 */
	public static function pa_get_current_page_id() {

		if ( class_exists( 'woocommerce' ) ) {

			switch ( true ) {
				case is_shop():
					$page_id = wc_get_page_id( 'shop' );
					break;

				case is_cart():
					$page_id = wc_get_page_id( 'cart' );
					break;

				case is_checkout():
					$page_id = wc_get_page_id( 'checkout' );
					break;

				case is_account_page():
					$page_id = wc_get_page_id( 'myaccount' );
					break;

				default:
					$page_id = get_the_ID();
					break;
			}
		} else {
			$page_id = get_the_ID();
		}

		return $page_id;
	}

	/**
	 * Get Final Result.
	 *
	 * @access public
	 * @since 4.4.8
	 *
	 * @param bool   $condition_result  result.
	 * @param string $operator          operator.
	 *
	 * @return bool
	 */
	public static function get_final_result( $condition_result, $operator ) {

		if ( 'is' === $operator ) {
			return true === $condition_result;
		} else {
			return true !== $condition_result;
		}
	}

	/**
	 * Get Local Time ( WordPress TimeZone Setting ).
	 *
	 * @access public
	 * @since 4.4.8
	 *
	 * @param string $format  format.
	 */
	public static function get_local_time( $format ) {

		$local_time_zone = isset( $_COOKIE['localTimeZone'] ) && ! empty( $_COOKIE['localTimeZone'] ) ?
			str_replace( 'GMT ', 'GMT+', sanitize_text_field( wp_unslash( $_COOKIE['localTimeZone'] ) ) )
				: self::get_location_time_zone();

		// $today = new \DateTime( 'now', new \DateTimeZone( $local_time_zone ) );

		try {
			$today = new \DateTime( 'now', new \DateTimeZone( $local_time_zone ) );
		} catch ( \Exception $e ) {
			$today = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
		}

		return $today->format( $format );
	}

	/**
	 * Gets the user's timezone based on his ip address.
	 *
	 * @access public
	 * @since 4.10.26
	 *
	 * @return string
	 */
	public static function get_location_time_zone() {

		static $cached_timezone = null;

		if ( null !== $cached_timezone ) {
			return $cached_timezone;
		}

		$ip_address = self::get_user_ip_address();

		$cached_timezone = self::get_timezone_by_ip( $ip_address );

		return $cached_timezone;
	}

	/**
	 * Get user's IP address.
	 *
	 * @access public
	 * @since 4.10.26
	 *
	 * @return string
	 */
	public static function get_user_ip_address() {

		$ip_address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {

			$x_forward = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );

			$ips = explode( ',', $x_forward );

			if ( ! empty( $ips ) ) {
				$ip_address = trim( $ips[0] );
			}
		}

		return '::1' === $ip_address ? '127.0.0.1' : $ip_address;
	}

	/**
	 * Get IP location data.
	 *
	 * Uses multi-layered caching (object cache + transients) to optimize performance.
	 * A failed lookup is cached as an empty array so a down endpoint is not retried on every call.
	 *
	 * @access public
	 * @since 4.11.54
	 *
	 * @param string $ip_address user's ip address.
	 *
	 * @return array|false
	 */
	public static function get_ip_location_data( $ip_address ) {

		if ( '127.0.0.1' === $ip_address || empty( $ip_address ) ) {
			return false;
		}

		static $request_memo = array();

		if ( isset( $request_memo[ $ip_address ] ) ) {
			return $request_memo[ $ip_address ];
		}

		$cache_key = 'pa_ip_loc_' . md5( $ip_address );

		$location_data = wp_cache_get( $cache_key, 'premium_addons' );

		if ( false === $location_data ) {

			$location_data = get_transient( $cache_key );

			if ( false === $location_data ) {

				$response = wp_remote_get(
					'https://api.findip.net/' . $ip_address . '/?token=e6624afe9983128700bc94e55d5227cb',
					array(
						'timeout'   => 3,
						'sslverify' => true,
					)
				);

				if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
					$location_data = array();

					set_transient( $cache_key, $location_data, 5 * MINUTE_IN_SECONDS );
				} else {
					$location_data = json_decode( wp_remote_retrieve_body( $response ), true );

					set_transient( $cache_key, $location_data, 24 * HOUR_IN_SECONDS );
				}
			}

			wp_cache_set( $cache_key, $location_data, 'premium_addons' );

		}

		$request_memo[ $ip_address ] = $location_data;

		return $location_data;
	}

	/**
	 * Get timezone by ip address.
	 *
	 * @access public
	 * @since 4.10.26
	 *
	 * @param string $ip_address user's ip address.
	 *
	 * @return string
	 */
	public static function get_timezone_by_ip( $ip_address ) {

		$location_data = self::get_ip_location_data( $ip_address );

		if ( ! $location_data || ! isset( $location_data['location']['time_zone'] ) ) {
			return date_default_timezone_get();
		}

		return strtolower( $location_data['location']['time_zone'] );
	}

	/**
	 * Get Site Server Time ( WordPress TimeZone Setting ).
	 *
	 * @access public
	 * @since 4.4.8
	 *
	 * @param string $format  format.
	 */
	public static function get_site_server_time( $format ) {

		$today = gmdate( $format, strtotime( 'now' ) + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );

		return $today;
	}

	/**
	 * Get All Breakpoints.
	 *
	 * @param string $type result return type.
	 *
	 * @access public
	 * @since 4.6.1
	 *
	 * @return array $devices enabled breakpoints.
	 */
	public static function get_all_breakpoints( $type = 'assoc' ) {

		$devices = array(
			'desktop' => __( 'Desktop', 'elementor' ),
			'tablet'  => __( 'Tablet', 'elementor' ),
			'mobile'  => __( 'Mobile', 'elementor' ),
		);

		$method_available = method_exists( Plugin::$instance->breakpoints, 'has_custom_breakpoints' );

		if ( ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.4.0', '>' ) ) && $method_available ) {

			if ( Plugin::$instance->breakpoints->has_custom_breakpoints() ) {
				$devices = array_merge(
					$devices,
					array(
						'widescreen'   => __( 'Widescreen', 'elementor' ),
						'laptop'       => __( 'Laptop', 'elementor' ),
						'tablet_extra' => __( 'Tablet Extra', 'elementor' ),
						'mobile_extra' => __( 'Mobile Extra', 'elementor' ),
					)
				);
			}
		}

		if ( 'keys' === $type ) {
			$devices = array_keys( $devices );
		}

		return $devices;
	}

	/**
	 * Get WordPress language prefixes.
	 *
	 * @since 4.4.8
	 * @access public
	 *
	 * @return array
	 */
	public static function get_lang_prefixes() {

		if ( null === self::$lang_locales ) {

			$langs = require_once PREMIUM_ADDONS_PATH . 'includes/pa-display-conditions/lang-locale.php';

			foreach ( $langs as $lang => $props ) {
				/* translators: %s: Language Name */
				$val                         = ucwords( $props['name'] );
				self::$lang_locales[ $lang ] = $val;
			}
		}

		return self::$lang_locales;
	}

	/**
	 * Get Woocommerce Categories.
	 *
	 * @access public
	 * @since 4.4.8
	 *
	 * @param string $id array key.
	 *
	 * @return array
	 */
	public static function get_woo_categories( $id = 'slug' ) {

		$product_cat = array();

		$cat_args = array(
			'taxonomy'   => 'product_cat',
			'orderby'    => 'name',
			'order'      => 'asc',
			'hide_empty' => false,
		);

		$product_categories = get_terms( $cat_args );

		if ( ! empty( $product_categories ) ) {

			foreach ( $product_categories as $key => $category ) {

				$cat_id                 = 'slug' === $id ? $category->slug : $category->term_id;
				$product_cat[ $cat_id ] = $category->name;

			}
		}

		return $product_cat;
	}

	/**
	 * Check Elementor Experiment
	 *
	 * Check if an Elementor experiment is enabled.
	 *
	 * @since 4.8.6
	 * @access public
	 *
	 * @param string $experiment feature ID.
	 *
	 * @return boolean $is_enabled is feature enabled.
	 */
	public static function check_elementor_experiment( $experiment ) {

		$experiments_manager = Plugin::$instance->experiments;

		$is_enabled = $experiments_manager->is_feature_active( $experiment );

		return $is_enabled;
	}

	/**
	 * Is Edit Mode.
	 *
	 * @access public
	 * @since 4.6.1
	 *
	 * @return boolean
	 */
	public static function is_edit_mode() {
		return isset( $_REQUEST['elementor-preview'] ) && ! empty( $_REQUEST['elementor-preview'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Generate Unique ID
	 *
	 * Generates a unique ID for the current page.
	 *
	 * @since 4.6.9
	 * @access public
	 *
	 * @param string $id page ID.
	 *
	 * @return string unique ID.
	 */
	public static function generate_unique_id( $id ) {
		return substr( md5( $id ), 0, 9 );
	}

	/**
	 * Get Safe Path
	 *
	 * @since 4.6.9
	 * @access public
	 *
	 * @param string $file_path unsafe file path.
	 *
	 * @return string safe file path.
	 */
	public static function get_safe_path( $file_path ) {

		$path = str_replace( array( '//', '\\\\' ), array( '/', '\\' ), $file_path );

		return str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $path );
	}

	public static function get_safe_url( $url ) {
		if ( is_ssl() ) {
			$url = wp_parse_url( $url );

			if ( ! empty( $url['host'] ) ) {
				$url['scheme'] = 'https';
			}

			return self::unparse_url( $url );
		}

		return $url;
	}

	public static function unparse_url( $parsed_url ) {
		$scheme   = isset( $parsed_url['scheme'] ) ? $parsed_url['scheme'] . '://' : '';
		$host     = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
		$port     = isset( $parsed_url['port'] ) ? ':' . $parsed_url['port'] : '';
		$user     = isset( $parsed_url['user'] ) ? $parsed_url['user'] : '';
		$pass     = isset( $parsed_url['pass'] ) ? ':' . $parsed_url['pass'] : '';
		$pass     = ( $user || $pass ) ? "$pass@" : '';
		$path     = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '';
		$query    = isset( $parsed_url['query'] ) ? '?' . $parsed_url['query'] : '';
		$fragment = isset( $parsed_url['fragment'] ) ? '#' . $parsed_url['fragment'] : '';

		return "$scheme$user$pass$host$port$path$query$fragment";
	}

	/**
	 * Check if the current post type should include addons.
	 *
	 * @param string $id current post ID.
	 *
	 * @since  4.9.18
	 * @access public
	 */
	public static function check_post_type( $id ) {

		if ( ! $id ) {
			return false;
		}

		$template_name = get_post_meta( $id, '_elementor_template_type', true );

		$template_list = array(
			'header',
			'footer',
			'single',
			'post',
			'page',
			'archive',
			'search-results',
			'error-404',
			'product',
			'product-archive',
			'section',
		);

		return in_array( $template_name, $template_list, true );
	}

	/**
	 * Get Draw SVG Notice
	 *
	 * @since 4.9.26
	 * @access public
	 *
	 * @param object $elem element object.
	 * @param string $search search query.
	 * @param array  $conditions control conditions
	 */
	public static function get_draw_svg_notice( $elem, $search, $conditions, $index = 0, $nested = 'condition' ) {

		$url = add_query_arg(
			array(
				'page'   => 'premium-addons',
				'search' => $search,
				'#tab'   => 'elements',
			),
			esc_url( admin_url( 'admin.php' ) )
		);

		$control_attr = array(
			'type'            => Controls_Manager::RAW_HTML,
			'raw'             => __( 'You need first to enable SVG Draw option checkbox from ', 'premium-addons-for-elementor' ) . '<a href="' . esc_url( $url ) . '" target="_blank">' . __( 'here.', 'premium-addons-for-elementor' ) . '</a>',
			'classes'         => 'editor-pa-control-notice',
			'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
		);

		$control_attr[ $nested ] = $conditions;

		$elem->add_control(
			'draw_svg_notice_' . $index,
			$control_attr
		);
	}

	/**
	 * Checks if Elementor PRO 3.8 or higher is activated && if the Loop experiment is activated.
	 *
	 * @since 4.9.45
	 * @access public
	 *
	 * @return bool
	 */
	public static function is_loop_exp_enabled() {

		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {

			if ( version_compare( ELEMENTOR_PRO_VERSION, '3.16.0', '>=' ) ) {
				return true;
			} elseif ( version_compare( ELEMENTOR_PRO_VERSION, '3.8', '>=' ) ) {
				$is_loop_enabled = self::check_elementor_experiment( 'loop' );

				if ( $is_loop_enabled ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get Element Classes.
	 *
	 * @access private
	 * @since 2.8.22
	 *
	 * @param array $devices  devices to hide on.
	 *
	 * @return array
	 */
	public static function get_element_classes( $devices, $default = array() ) {

		$classes = $default;

		if ( count( $devices ) ) {
			foreach ( $devices as $index => $device ) {
				$classes[] = 'elementor-hidden-' . $device;
			}

			$classes[] = 'premium-addons-element';
		}

		return $classes;
	}

	/**
	 * Round Numbers In A Reading-friendly Format.
	 *
	 * @param integer $num followers number.
	 */
	public static function premium_format_numbers( $num ) {
		$num    = intval( $num );
		$result = '';

		if ( $num >= 1000000000 ) {
			$tmp    = round( ( $num / 1000000 ), 1 );
			$result = $tmp . 'B';
			return $result;
		}

		if ( $num >= 1000000 ) {
			$tmp    = round( ( $num / 1000000 ), 1 );
			$result = $tmp . 'M';
			return $result;
		}

		if ( $num >= 1000 ) {
			$tmp    = round( ( $num / 1000 ), 1 );
			$result = $tmp . 'K';

			return $result;
		}

		return round( $num, 1 );
	}

	/**
	 * Render Rating Stars
	 *
	 * @since 4.10.13
	 * @access public
	 *
	 * @param float  $rating rating score.
	 * @param string $fill_color fill color.
	 * @param string $empty_color empty color.
	 * @param float  $star_size star size.
	 */
	public static function render_rating_stars( $rating, $fill_color, $empty_color, $star_size ) {

		?>

		<span class="premium-fb-rev-stars">
		<?php

		foreach ( array( 1, 2, 3, 4, 5 ) as $val ) {
			$score = round( ( $rating - $val ), 2 );

			if ( $score >= -0.2 ) {

				?>
					<span class="premium-fb-rev-star"><svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" version="1.1" width="<?php echo esc_attr( $star_size ); ?>" height="<?php echo esc_attr( $star_size ); ?>" viewBox="0 0 1792 1792"><path d="M1728 647q0 22-26 48l-363 354 86 500q1 7 1 20 0 21-10.5 35.5t-30.5 14.5q-19 0-40-12l-449-236-449 236q-22 12-40 12-21 0-31.5-14.5t-10.5-35.5q0-6 2-20l86-500-364-354q-25-27-25-48 0-37 56-46l502-73 225-455q19-41 49-41t49 41l225 455 502 73q56 9 56 46z" fill="<?php echo esc_attr( $fill_color ); ?>"></path></svg></span>
				<?php
			} elseif ( $score > -0.8 && $score < -0.2 ) {
				?>
					<span class="premium-fb-rev-star"><svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" version="1.1" width="<?php echo esc_attr( $star_size ); ?>" height="<?php echo esc_attr( $star_size ); ?>" viewBox="0 0 1792 1792"><path d="M1250 957l257-250-356-52-66-10-30-60-159-322v963l59 31 318 168-60-355-12-66zm452-262l-363 354 86 500q5 33-6 51.5t-34 18.5q-17 0-40-12l-449-236-449 236q-23 12-40 12-23 0-34-18.5t-6-51.5l86-500-364-354q-32-32-23-59.5t54-34.5l502-73 225-455q20-41 49-41 28 0 49 41l225 455 502 73q45 7 54 34.5t-24 59.5z" fill="<?php echo esc_attr( $fill_color ); ?>"></path></svg></span>
			<?php } else { ?>
					<span class="premium-fb-rev-star"><svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" version="1.1" width="<?php echo esc_attr( $star_size ); ?>" height="<?php echo esc_attr( $star_size ); ?>" viewBox="0 0 1792 1792"><path d="M1201 1004l306-297-422-62-189-382-189 382-422 62 306 297-73 421 378-199 377 199zm527-357q0 22-26 48l-363 354 86 500q1 7 1 20 0 50-41 50-19 0-40-12l-449-236-449 236q-22 12-40 12-21 0-31.5-14.5t-10.5-35.5q0-6 2-20l86-500-364-354q-25-27-25-48 0-37 56-46l502-73 225-455q19-41 49-41t49 41l225 455 502 73q56 9 56 46z" fill="<?php echo esc_attr( $empty_color ); ?>"></path></svg></span>
					<?php
			}
		}
		?>
		</span>

		<?php
	}


	/**
	 * Get SVG Shapes
	 *
	 * @since 4.10.13
	 * @access public
	 */
	public static function get_svg_shapes( $shape = '' ) {

		if ( null === self::$shapes ) {

			self::$shapes = require PREMIUM_ADDONS_PATH . 'addons/shapes.php';

		}

		$shapes = self::$shapes;

		if ( empty( $shape ) ) {
			return $shapes;
		} else {
			return $shapes[ $shape ]['imagesmall'];
		}
	}

	public static function get_btn_svgs( $style = 'line1' ) {

		$html = '';

		switch ( $style ) {
			case 'line1':
				$html = '<div class="premium-btn-line-wrap"><svg class="premium-btn-svg" aria-hidden="true" width="100%" height="9" viewBox="0 0 101 9">
                <path d="M.426 1.973C4.144 1.567 17.77-.514 21.443 1.48 24.296 3.026 24.844 4.627 27.5 7c3.075 2.748 6.642-4.141 10.066-4.688 7.517-1.2 13.237 5.425 17.59 2.745C58.5 3 60.464-1.786 66 2c1.996 1.365 3.174 3.737 5.286 4.41 5.423 1.727 25.34-7.981 29.14-1.294" pathLength="1"></path>
                </svg></div>';
				break;

			case 'line3':
				$html = '<div class="premium-btn-line-wrap"><svg class="premium-btn-svg" aria-hidden="true" width="100%" height="18" viewBox="0 0 59 18">
                <path d="M.945.149C12.3 16.142 43.573 22.572 58.785 10.842" pathLength="1"></path>
                </svg></div>';
				break;

			case 'line4':
				$html = '<svg class="premium-btn-svg" aria-hidden="true" width="300%" height="100%" viewBox="0 0 1200 60" preserveAspectRatio="none">
                <path d="M0,56.5c0,0,298.666,0,399.333,0C448.336,56.5,513.994,46,597,46c77.327,0,135,10.5,200.999,10.5c95.996,0,402.001,0,402.001,0"></path>
                </svg>';
				break;

			default:
				// code...
				break;
		}

		return $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Add Button Hover Controls
	 *
	 * @since 4.10.17
	 * @access public
	 *
	 * @param object $elem widget object.
	 * @param array  $conditions controls conditions.
	 */
	public static function add_btn_hover_controls( $elem, $conditions ) {

		$elem->add_control(
			'premium_button_hover_effect',
			array(
				'label'       => __( 'Hover Effect', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'none',
				'options'     => array(
					'none'   => __( 'None', 'premium-addons-for-elementor' ),
					'style1' => __( 'Slide', 'premium-addons-for-elementor' ),
					'style2' => __( 'Shutter', 'premium-addons-for-elementor' ),
					'style5' => apply_filters( 'pa_pro_label', __( 'In & Out (Pro)', 'premium-addons-for-elementor' ) ),
					'style6' => apply_filters( 'pa_pro_label', __( 'Grow (Pro)', 'premium-addons-for-elementor' ) ),
					'style7' => apply_filters( 'pa_pro_label', __( 'Double Layers (Pro)', 'premium-addons-for-elementor' ) ),
					'style8' => apply_filters( 'pa_pro_label', __( 'Animated Underline (Pro)', 'premium-addons-for-elementor' ) ),
				),
				'separator'   => 'before',
				'label_block' => true,
				'condition'   => $conditions,
			)
		);

		$elem->add_control(
			'button_hover_effect_notice',
			array(
				'raw'             => __( 'Important: You need to set a background to the button to see the effects.', 'premium-addons-for-elementor' ),
				'type'            => Controls_Manager::RAW_HTML,
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning',
				'condition'       => array_merge(
					$conditions,
					array(
						'premium_button_hover_effect!' => 'none',
					)
				),
			)
		);

		do_action( 'pa_button_hover_controls', $elem, $conditions );

		$elem->add_control(
			'premium_button_style1_dir',
			array(
				'label'       => __( 'Slide Direction', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'bottom',
				'options'     => array(
					'bottom' => __( 'Top to Bottom', 'premium-addons-for-elementor' ),
					'top'    => __( 'Bottom to Top', 'premium-addons-for-elementor' ),
					'left'   => __( 'Right to Left', 'premium-addons-for-elementor' ),
					'right'  => __( 'Left to Right', 'premium-addons-for-elementor' ),
				),
				'condition'   => array_merge(
					$conditions,
					array(
						'premium_button_hover_effect' => 'style1',
					)
				),
				'label_block' => true,
			)
		);

		$elem->add_control(
			'premium_button_style2_dir',
			array(
				'label'       => __( 'Shutter Direction', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'shutouthor',
				'options'     => array(
					'shutinhor'    => __( 'Shutter in Horizontal', 'premium-addons-for-elementor' ),
					'shutinver'    => __( 'Shutter in Vertical', 'premium-addons-for-elementor' ),
					'shutoutver'   => __( 'Shutter out Horizontal', 'premium-addons-for-elementor' ),
					'shutouthor'   => __( 'Shutter out Vertical', 'premium-addons-for-elementor' ),
					'scshutoutver' => __( 'Scaled Shutter Vertical', 'premium-addons-for-elementor' ),
					'scshutouthor' => __( 'Scaled Shutter Horizontal', 'premium-addons-for-elementor' ),
					'dshutinver'   => __( 'Tilted Left', 'premium-addons-for-elementor' ),
					'dshutinhor'   => __( 'Tilted Right', 'premium-addons-for-elementor' ),
				),
				'condition'   => array_merge(
					$conditions,
					array(
						'premium_button_hover_effect' => 'style2',
					)
				),
				'label_block' => true,
			)
		);
	}

	/**
	 * Add Templates Controls
	 *
	 * @since 4.11.25
	 * @access public
	 *
	 * @param string $keyword keyword for templates.
	 * @param string $demo demo url.
	 */
	public static function add_templates_controls( $element, $keyword, $demo ) {

		if ( ! Admin_Helper::check_element_by_key( 'premium-templates' ) ) {
			return;
		}

		$element->add_control(
			'premium_templates_links',
			array(
				'type' => Controls_Manager::RAW_HTML,
				'raw'  => '<div class="premium-promote-box widget-box">
					<div class="premium-promote-ctas">
						<a class="premium-promote-demo elementor-button elementor-button-default" href="' . esc_url( $demo ) . '" target="_blank"> ' .
							__( 'Widget Demo', 'premium-addons-for-elementor' ) .
						'</a>
						<a class="premium-promote-upgrade premium-widget-blocks elementor-button elementor-button-default" href="#" data-keyword="' . esc_attr( $keyword ) . '">' .
							__( 'Templates', 'premium-addons-for-elementor' ) .
						'</a>
					</div>
				</div>',
			)
		);
	}

	/**
	 * Add Templates Controls
	 *
	 * @since 4.11.29
	 * @access public
	 *
	 * @param string $keyword keyword for templates.
	 * @param string $demo demo url.
	 */
	public static function register_papro_promotion_controls( $element, $keyword ) {

		if ( ! self::check_papro_version() ) {

			$pro_link = self::get_campaign_link( 'https://premiumaddons.com/pro/#get-pa-pro', $keyword, 'wp-editor', 'get-pro' );

			$element->start_controls_section(
				'section_pro_features_field',
				array(
					'label' => __( 'Go Pro for More Features', 'premium-addons-for-elementor' ),
				)
			);

			$element->add_control(
				'pa_pro_promotion_notice',
				array(
					'type'        => Controls_Manager::NOTICE,
					'notice_type' => 'info',
					'dismissible' => false,
					'content'     => __( '<b>Build smarter and faster</b> with premium widgets, 580+ container blocks, and advanced customization controls — all available in the <a href="' . esc_url( $pro_link ) . '" target="_blank">PA Pro</a>. <b>Save up to 30%!</b>.', 'premium-addons-for-elementor' ),
				)
			);

			$element->end_controls_section();
		}
	}

	/**
	 * Register Element Feedback Controls
	 *
	 * @since 4.11.36
	 * @access public
	 *
	 * @param object $element widget object.
	 */
	public static function register_element_feedback_controls( $element ) {

		$element->add_control(
			'feedback_message',
			array(
				'label'       => __( 'Feedback & Feature Request', 'premium-addons-for-elementor' ),
				'type'        => Controls_Manager::TEXTAREA,
				'placeholder' => __( 'Share your feedback or feature request here...', 'premium-addons-for-elementor' ),
				'label_block' => true,
				'render_type' => 'ui',
				'ai'          => array(
					'active' => false,
				),
			)
		);

		$element->add_control(
			'feedback_message_submit',
			array(
				'type'        => Controls_Manager::RAW_HTML,
				'raw'         => '<form onsubmit="submitFeedbackMessage(this,\'' . $element->get_title() . '\');" action="javascript:void(0);"><input type="submit" value="Send Feedback" class="elementor-button" style="background-color: rgba(207, 211, 215, 0.35); color: #000;"></form>',
				'label_block' => true,
			)
		);
	}

	/**
	 * Get Button Class
	 *
	 * @since 4.10.17
	 * @access public
	 *
	 * @param $settings object widget settings.
	 *
	 * @return string $class css class.
	 */
	public static function get_button_class( $settings ) {

		$class = '';

		$papro_activated = self::check_papro_version();

		if ( ! $papro_activated && ! in_array( $settings['premium_button_hover_effect'], array( 'none', 'style1', 'style2' ), true ) ) {
			return '';
		}

		if ( 'style1' === $settings['premium_button_hover_effect'] ) {
			$class = 'premium-button-style1-' . $settings['premium_button_style1_dir'];
		} elseif ( 'style2' === $settings['premium_button_hover_effect'] ) {
			$class = 'premium-button-style2-' . $settings['premium_button_style2_dir'];
		} elseif ( 'style5' === $settings['premium_button_hover_effect'] ) {
			$class = 'premium-button-style5-' . $settings['premium_button_style5_dir'];
		} elseif ( 'style6' === $settings['premium_button_hover_effect'] ) {
			$class = 'premium-button-style6';
		} elseif ( 'style7' === $settings['premium_button_hover_effect'] ) {
			$class = 'premium-button-style7-' . $settings['premium_button_style7_dir'];
		} elseif ( 'style8' === $settings['premium_button_hover_effect'] ) {
			$class = 'premium-button-' . $settings['underline_style'];
		}

		return 'premium-button-' . $settings['premium_button_hover_effect'] . ' ' . $class;
	}


	/**
	 * Get Empty Query Message
	 *
	 * Written in PHP and used to generate the final HTML when the query is empty
	 *
	 * @since 4.10.29
	 * @access protected
	 *
	 * @param string $notice empty query notice.
	 */
	public static function render_empty_query_message( $notice ) {

		if ( empty( $notice ) ) {
			$notice = __( 'The current query has no posts. Please make sure you have published items matching your query.', 'premium-addons-for-elementor' );
		}

		?>
		<div class="premium-error-notice">
			<?php echo wp_kses_post( $notice ); ?>
		</div>
		<?php
	}

	/**
	 * Check Capability
	 *
	 * @since 4.10.28
	 * @access public
	 *
	 * @param string $check capability.
	 */
	public static function check_capability( $capability ) {

		$post_author_id = get_the_author_meta( 'ID' );

		$current_user_can = user_can( $post_author_id, $capability );

		return $current_user_can;
	}


	/**
	 * Get Allowed Icon Tags
	 *
	 * Returns an array of allowed HTML tags.
	 *
	 * @since 4.10.69
	 * @access public
	 *
	 * @return array Array of allowed HTML tags.
	 */
	public static function get_allowed_icon_tags() {
		return array(
			'svg'   => array(
				'id'              => array(),
				'class'           => array(),
				'aria-hidden'     => array(),
				'aria-labelledby' => array(),
				'role'            => array(),
				'xmlns'           => array(),
				'width'           => array(),
				'height'          => array(),
				'viewbox'         => array(),
				'data-*'          => true,
			),
			'g'     => array( 'fill' => array() ),
			'title' => array( 'title' => array() ),
			'path'  => array(
				'd'    => array(),
				'fill' => array(),
			),
			'i'     => array(
				'class' => array(),
				'id'    => array(),
				'style' => array(),
			),
		);
	}

	/**
	 * Get SVG By Icon
	 *
	 * @since 4.10.69
	 * @access public
	 */
	public static function get_svg_by_icon( $icon, $attributes = array() ) {

		if ( empty( $icon ) || empty( $icon['value'] ) || empty( $icon['library'] ) ) {
			return '';
		}

		// If icon library is SVG, then go to Elementor. Used for widgets where this function is called in all cases.
		if ( false === strpos( $icon['library'], 'fa-' ) ) {

			if ( is_string( $attributes ) ) {
				$attributes = str_replace( '"', '', $attributes );
			}

			$svg_html = Icons_Manager::try_get_icon_html( $icon, wp_parse_args( $attributes, array( 'aria-hidden' => 'true' ) ) );

			return $svg_html;
		}

		$icon['font_family'] = 'font-awesome';

		$i_class = str_replace( ' ', '-', $icon['value'] );

		$svg_html = '<svg ';

		$icon = self::get_icon_svg_data( $icon );

		if ( ! $icon ) {
			// Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
			Icons_Manager::render_icon( $icon, wp_parse_args( $attributes, array( 'aria-hidden' => 'true' ) ) );
			return;
		}

		$view_box = '0 0 ' . $icon['width'] . ' ' . $icon['height'];

		if ( is_array( $attributes ) ) {

			foreach ( $attributes as $key => $value ) {

				if ( 'class' === $key ) {

					$svg_html .= 'class="svg-inline--' . $i_class . ' ' . $value . '" ';

				} else {
					$svg_html .= " {$key}='{$value}' ";
				}
			}
		} else {

			$attributes = str_replace( 'class="', 'class="svg-inline--' . $i_class . ' ', $attributes );

			$svg_html .= $attributes;
		}

		$svg_html .= " aria-hidden='true' xmlns='http://www.w3.org/2000/svg' viewBox='{$view_box}'>";

		$svg_html .= '<path d="' . esc_attr( $icon['path'] ) . '"></path>';
		$svg_html .= '</svg>';

		return wp_kses( $svg_html, self::get_allowed_icon_tags() );
	}

	/**
	 * Get Elementor Template ID
	 *
	 * @since 4.10.80
	 * @access public
	 *
	 * @param string $title template title.
	 *
	 * @return string $template_id template ID.
	 */
	public static function get_elementor_template_id( $title ) {

		if ( empty( $title ) ) {
			return;
		}

		$cache_key = 'pa_tpl_id_' . md5( $title );

		$post_id = wp_cache_get( $cache_key, 'premium_addons' );

		if ( false !== $post_id ) {
			return $post_id;
		}

		$args = array(
			'post_type'        => 'elementor_library',
			'post_status'      => 'publish',
			'posts_per_page'   => 1,
			'title'            => $title,
			'suppress_filters' => true,
		);

		$query = new \WP_Query( $args );

		$post_id = '';

		if ( $query->have_posts() ) {
			$post_id = $query->post->ID;

			wp_reset_postdata();
		}

		wp_cache_set( $cache_key, $post_id, 'premium_addons' );

		return $post_id;
	}

	/**
	 * Render Elementor Template
	 *
	 * @since 4.10.80
	 * @access public
	 *
	 * @param string|int $title   Template Title||id.
	 * @param bool       $id          indicates if $title is the template title or id.
	 *
	 * @return $template_content string HTML Markup of the selected template.
	 */
	public static function render_elementor_template( $title, $id = false ) {

		$frontend = Plugin::$instance->frontend;

		$custom_temp = apply_filters( 'pa_temp_id', false );

		if ( $custom_temp ) {
			$id = $title = $custom_temp;
		}

		if ( ! $id ) {
			$id = self::get_elementor_template_id( $title );

			if ( ! $id ) {
				// To replace the &#8211; in templates names with dash.
				$decoded_title = html_entity_decode( $title );
				$id            = self::get_elementor_template_id( $decoded_title );
			}

			$id = apply_filters( 'wpml_object_id', $id, 'elementor_library', true );
		} else {
			$id = $title;
		}

		// $template_content = $frontend->get_builder_content( $id, true );
		$template_content = $frontend->get_builder_content_for_display( $id, true );

		return $template_content;
	}

	/**
	 * Get Device Type
	 *
	 * @since 4.11.50
	 * @access public
	 *
	 * @return string device type.
	 */
	public static function get_device_type() {

		static $device_type = null;

		// Return cached result if already detected.
		if ( null !== $device_type ) {
			return $device_type;
		}

		// Default to desktop.
		$device_type = 'desktop';

		// Only load Device_Detector if not already loaded.
		if ( ! class_exists( 'PremiumAddons\Includes\Helpers\Device_Detector' ) ) {
			require_once PREMIUM_ADDONS_PATH . 'includes/helpers/device-detector.php';
		}

		$detect = new Helpers\Device_Detector();

		// Detect device type with priority: tablet > mobile > desktop.
		if ( $detect->isTablet() ) {
			$device_type = 'tablet';
		} elseif ( $detect->isMobile() ) {
			$device_type = 'mobile';
		}

		return $device_type;
	}

	/**
	 * Get Widget Class Name
	 *
	 * @since 4.11.51
	 * @access public
	 *
	 * @param string $widget_key Widget slug/key, e.g. 'premium-banner'.
	 * @return string|false Fully-qualified class name on success, false on failure.
	 */
	public static function get_widget_class_name( $widget_key ) {

		static $classes_list = null;

		$default_namespace = 'PremiumAddons\\Widgets\\';

		// load the map once.
		if ( null === $classes_list ) {

			$map_file = PREMIUM_ADDONS_PATH . 'includes/helpers/widget-class-map.php';

			if ( file_exists( $map_file ) ) {

				$map          = include $map_file;
				$classes_list = is_array( $map ) ? $map : array();
			} else {
				$classes_list = array();
			}
		}

		if ( empty( $widget_key ) || ! is_string( $widget_key ) ) {
			return false;
		}

		if ( ! isset( $classes_list[ $widget_key ] ) ) {
			return false;
		}

		$class_name = $classes_list[ $widget_key ];

		if ( is_string( $class_name ) && false !== strpos( $class_name, '\\' ) ) {
			return $class_name;
		}

		// Otherwise treat as short class name and prepend the default namespace.
		$short_class = (string) $class_name;
		$full_class  = rtrim( $default_namespace, '\\' ) . '\\' . ltrim( $short_class, '\\' );

		return $full_class;
	}

	/**
	 * Get Enabled Widgets
	 *
	 * @since 4.11.54
	 * @access public
	 *
	 * @return array enabled widgets.
	 */
	public static function get_enabled_widgets() {

		$enabled_elements = Admin_Helper::get_enabled_elements();

		$enabled_elements = array_filter(
			$enabled_elements,
			function ( $value, $key ) {
				return ( strpos( $key, 'premium-' ) === 0 || strpos( $key, 'mini-' ) === 0 || strpos( $key, 'woo-' ) === 0 ) && filter_var( $value, FILTER_VALIDATE_BOOLEAN );
			},
			ARRAY_FILTER_USE_BOTH
		);

		return array_keys( $enabled_elements );
	}

	/*
	 * Get Enabled Widgets Names
	 *
	 * @since 4.11.54
	 * @access public
	 *
	 * @return array enabled widgets names.
	 */
	public static function get_enabled_widgets_names() {

		static $names_list = null;

		$enabled_elements = self::get_enabled_widgets();

		$enabled_names = array();

		if ( null === $names_list ) {

			$map_file = PREMIUM_ADDONS_PATH . 'includes/helpers/widget-name-map.php';

			if ( file_exists( $map_file ) ) {

				$map        = include $map_file;
				$names_list = is_array( $map ) ? $map : array();
			} else {
				$names_list = array();
			}
		}

		foreach ( $enabled_elements as $key ) {

			$widget_name     = isset( $names_list[ $key ] ) ? $names_list[ $key ] : $key;
			$enabled_names[] = $widget_name;
		}

		return $enabled_names;
	}

	/**
	 * Sanitize SVG
	 *
	 * Strips dangerous attributes (on* event handlers, javascript: hrefs) and
	 * disallowed tags from a raw SVG string. Used as sanitize_callback for
	 * custom_svg controls to prevent Stored XSS (CVE-2026-4790).
	 *
	 * @since 4.11.71
	 * @access public
	 *
	 * @param string $svg Raw SVG input.
	 * @return string Sanitized SVG string.
	 */
	public static function sanitize_svg( $svg ) {

		$allowed_tags = array(
			'svg'      => array(
				'xmlns'       => array(),
				'xmlns:xlink' => array(),
				'viewbox'     => array(),
				'width'       => array(),
				'height'      => array(),
				'fill'        => array(),
				'stroke'      => array(),
				'class'       => array(),
				'id'          => array(),
				'role'        => array(),
				'aria-hidden' => array(),
				'aria-label'  => array(),
				'focusable'   => array(),
				'style'       => array(),
			),
			'circle'   => array(
				'cx'           => array(),
				'cy'           => array(),
				'r'            => array(),
				'fill'         => array(),
				'stroke'       => array(),
				'stroke-width' => array(),
				'class'        => array(),
				'id'           => array(),
				'style'        => array(),
			),
			'ellipse'  => array(
				'cx'           => array(),
				'cy'           => array(),
				'rx'           => array(),
				'ry'           => array(),
				'fill'         => array(),
				'stroke'       => array(),
				'stroke-width' => array(),
				'class'        => array(),
				'id'           => array(),
				'style'        => array(),
			),
			'rect'     => array(
				'x'            => array(),
				'y'            => array(),
				'width'        => array(),
				'height'       => array(),
				'rx'           => array(),
				'ry'           => array(),
				'fill'         => array(),
				'stroke'       => array(),
				'stroke-width' => array(),
				'class'        => array(),
				'id'           => array(),
				'style'        => array(),
			),
			'line'     => array(
				'x1'           => array(),
				'y1'           => array(),
				'x2'           => array(),
				'y2'           => array(),
				'stroke'       => array(),
				'stroke-width' => array(),
				'class'        => array(),
				'id'           => array(),
				'style'        => array(),
			),
			'polyline' => array(
				'points'       => array(),
				'fill'         => array(),
				'stroke'       => array(),
				'stroke-width' => array(),
				'class'        => array(),
				'id'           => array(),
				'style'        => array(),
			),
			'polygon'  => array(
				'points'       => array(),
				'fill'         => array(),
				'stroke'       => array(),
				'stroke-width' => array(),
				'class'        => array(),
				'id'           => array(),
				'style'        => array(),
			),
			'path'     => array(
				'd'            => array(),
				'fill'         => array(),
				'stroke'       => array(),
				'stroke-width' => array(),
				'fill-rule'    => array(),
				'clip-rule'    => array(),
				'class'        => array(),
				'id'           => array(),
				'style'        => array(),
			),
			'g'        => array(
				'fill'      => array(),
				'stroke'    => array(),
				'transform' => array(),
				'class'     => array(),
				'id'        => array(),
				'style'     => array(),
				'clip-path' => array(),
			),
			'defs'     => array(
				'class' => array(),
				'id'    => array(),
			),
			// Illustrator/Affinity/Boxy exports keep their class rules in <defs><style>.
			// wp_kses filters style="" attributes through safe_style_css, but never the CSS inside a <style> block.
			'style'    => array(
				'type'  => array(),
				'class' => array(),
			),
			'clippath' => array(
				'class' => array(),
				'id'    => array(),
			),
			'image'    => array(
				'x'                   => array(),
				'y'                   => array(),
				'width'               => array(),
				'height'              => array(),
				'href'                => array(),
				'xlink:href'          => array(),
				'preserveaspectratio' => array(),
				'opacity'             => array(),
				'class'               => array(),
				'id'                  => array(),
				'style'               => array(),
			),
			'text'     => array(
				'x'           => array(),
				'y'           => array(),
				'dx'          => array(),
				'dy'          => array(),
				'fill'        => array(),
				'font-size'   => array(),
				'font-family' => array(),
				'text-anchor' => array(),
				'class'       => array(),
				'id'          => array(),
				'style'       => array(),
			),
			'tspan'    => array(
				'x'     => array(),
				'y'     => array(),
				'dx'    => array(),
				'dy'    => array(),
				'class' => array(),
				'id'    => array(),
				'style' => array(),
			),
			'title'    => array(),
			'desc'     => array(),
		);

		// SVG presentation properties are not on WordPress' safe_style_css
		// allow-list, so wp_kses() would strip them out of inline style
		// attributes (e.g. fill:none, stroke-width) and break SVGs exported
		// by Illustrator/Affinity/Boxy SVG. Allow them for this call only.
		$svg_css_props = array(
			'fill',
			'fill-rule',
			'fill-opacity',
			'stroke',
			'stroke-width',
			'stroke-linecap',
			'stroke-linejoin',
			'stroke-miterlimit',
			'stroke-dasharray',
			'stroke-dashoffset',
			'stroke-opacity',
			'clip-rule',
			'color',
			'opacity',
			'stop-color',
			'stop-opacity',
			'vector-effect',
			'paint-order',
			'transform',
			'transform-origin',
		);

		$allow_svg_css = function ( $styles ) use ( $svg_css_props ) {
			return array_merge( $styles, $svg_css_props );
		};

		add_filter( 'safe_style_css', $allow_svg_css );

		$sanitized = wp_kses( $svg, $allowed_tags );

		remove_filter( 'safe_style_css', $allow_svg_css );

		return $sanitized;
	}

	/**
	 * Resolve the per-item badge text for a given post.
	 *
	 * @since 4.11.78
	 * @param int   $post_id  Post ID to resolve terms for.
	 * @param array $settings Parent widget's settings array.
	 * @return false|string
	 */
	public static function get_per_item_badge_text( $post_id, $settings ) {

		if ( 'yes' !== ( $settings['premium_global_badge_switcher'] ?? '' ) ) {
			return false;
		}

		if ( 'yes' !== ( $settings['pa_badge_per_item'] ?? '' ) ) {
			return false;
		}

		$taxonomy = isset( $settings['pa_badge_per_item_source'] ) && '' !== $settings['pa_badge_per_item_source']
			? $settings['pa_badge_per_item_source']
			: 'category';

		$terms = get_the_terms( $post_id, $taxonomy );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}

		$names = wp_list_pluck( $terms, 'name' );

		return implode( ', ', $names );
	}

	/**
	 * Get attachment image HTML.
	 *
	 * A fork of Elementor's `Group_Control_Image_Size::get_attachment_image_html()`
	 * that accepts a `$classes` argument, so the rendered `<img>` can be targeted by
	 * a dedicated class instead of a `> img` selector that image-optimization plugins
	 * break when they rewrite the tag into `<picture><img></picture>`. It also handles
	 * the `get_image_data()` shape used by repeaters, which carries the size on the
	 * image array rather than in a sibling `<key>_size` setting.
	 *
	 * Note that some widgets use the same key for the media control that allows
	 * the image selection and for the image size control that allows the user
	 * to select the image size, in this case the third parameter should be null
	 * or the same as the second parameter. But when the widget uses different
	 * keys for the media control and the image size control, when calling this
	 * method you should pass the keys.
	 *
	 * The returned markup is not escaped. Print it through
	 * `Utils::print_wp_kses_extended( $html, array( 'image' ) )`.
	 *
	 * @since 4.11.102
	 * @access public
	 * @static
	 *
	 * @param array  $settings       Control settings.
	 * @param string $image_size_key Optional. Settings key for image size.
	 *                               Default is `image`.
	 * @param string $image_key      Optional. Settings key for image. Default
	 *                               is null. If not defined uses image size key
	 *                               as the image key.
	 * @param string $classes        Optional. Classes list to be added to the image HTML markup.
	 *
	 * @return string Image HTML.
	 */
	public static function get_attachment_image_html( $settings, $image_size_key = 'image', $image_key = null, $classes = '' ) {

		if ( ! $image_key ) {
			$image_key = $image_size_key;
		}

		$image = $settings[ $image_key ];

		$is_repeater = ! isset( $settings[ $image_size_key . '_size' ] );

		if ( $is_repeater ) {
			// Used with get_image_data() method which is used with repeaters.
			$size = isset( $image['image_size'] ) ? $image['image_size'] : 'full';
		} else {
			$size = $settings[ $image_size_key . '_size' ];
		}

		/**
		 * Performance Optimization: Cache image sizes to avoid redundant
		 * 'get_intermediate_image_sizes' calls for every image.
		 */
		static $image_sizes = null;

		if ( null === $image_sizes ) {
			$image_sizes   = get_intermediate_image_sizes();
			$image_sizes[] = 'full';
		}

		if ( ! empty( $image['id'] ) && ! wp_attachment_is_image( $image['id'] ) ) {
			$image['id'] = '';
		}

		$is_lazyload_disabled = apply_filters( 'pa_disable_image_lazyload', false );

		$html = '';

		if ( ! empty( $image['id'] ) && in_array( $size, $image_sizes, true ) ) {

			$image_attr = array(
				'class' => trim( "attachment-$size size-$size wp-image-{$image['id']} " . $classes ),
			);

			if ( $is_lazyload_disabled ) {
				$image_attr['loading'] = false;
			}

			$html = wp_get_attachment_image( $image['id'], $size, false, $image_attr );

		} else { // Custom size.

			// If repeater, then we should pass the data for the current image, not the settings object.
			$size_key = $is_repeater ? 'image' : $image_size_key;
			$source   = $is_repeater ? $image : $settings;

			$image_src = Group_Control_Image_Size::get_attachment_image_src( $image['id'], $size_key, $source );

			if ( ! $image_src && isset( $image['url'] ) ) {
				$image_src = $image['url'];
			}

			if ( ! empty( $image_src ) ) {

				$html = sprintf(
					'<img src="%1$s" title="%2$s" alt="%3$s"%4$s%5$s />',
					esc_url( $image_src ),
					esc_attr( Control_Media::get_image_title( $image ) ),
					esc_attr( Control_Media::get_image_alt( $image ) ),
					! empty( $classes ) ? ' class="' . esc_attr( $classes ) . '"' : '',
					$is_lazyload_disabled ? '' : ' loading="lazy"'
				);
			}
		}

		return $html;
	}
}
