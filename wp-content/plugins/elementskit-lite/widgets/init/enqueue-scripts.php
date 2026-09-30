<?php
namespace ElementsKit_Lite\Widgets\Init;
use ElementsKit_Lite\Libs\Framework\Attr;

defined( 'ABSPATH' ) || exit;

class Enqueue_Scripts {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [$this, 'register_scripts'], 9 );
		add_action( 'elementor/frontend/after_enqueue_scripts', [$this, 'enqueue_scripts'] );

		add_action( 'elementor/frontend/after_register_styles', [$this, 'register_frontend_css'] );
		add_action( 'wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts'], 99 );
		add_action( 'wp_enqueue_scripts', [$this, 'enqueue_frontend_css'], 99 );

		/**
		 * Documents ElementsKit renders itself must reach Elementor's style pipeline
		 * before it runs, so this sits just ahead of Elementor's own priority 20 pass.
		 * The priority 21 pass is the fallback for pages where Elementor never runs
		 * that pass at all. Both are handled by Nested_Document_Assets.
		 */
		add_action( 'wp_enqueue_scripts', [$this, 'enqueue_nested_document_assets'], 19 );
		add_action( 'elementor/frontend/after_enqueue_post_styles', [ Nested_Document_Assets::class, 'mark_post_styles_flushed' ], 1 );
		add_action( 'wp_enqueue_scripts', [ Nested_Document_Assets::class, 'maybe_flush_post_styles' ], 21 );

		add_action( 'elementor/preview/enqueue_styles', [ $this, 'enqueue_3rd_party_style' ] );
		add_action( 'elementor/editor/after_enqueue_styles', [$this, 'elementor_editor_css'] );
	}

	public function is_plugin_active($plugin) {
		return in_array( $plugin, (array) get_option( 'active_plugins', array() ), true ) || $this->is_plugin_active_for_network( $plugin );
	}

	public function is_plugin_active_for_network($plugin) {
		if ( ! is_multisite() ) {
			return false;
		}

		$plugins = get_site_option( 'active_sitewide_plugins' );
		if ( isset( $plugins[ $plugin ] ) ) {
			return true;
		}

		return false;
	}

	public function register_scripts() {
		if( is_admin() ) {
			return;
		}

		//third party scripts for widgets
		// these both library is for image comparison widget
		wp_register_script('event.move', \ElementsKit_Lite::plugin_url() . 'assets/libs/event-move-js/jquery.event.move.js', ['jquery'], \ElementsKit_Lite::version(), true);
		wp_register_script('twentytwenty', \ElementsKit_Lite::plugin_url() . 'assets/libs/twentytwenty/jquery.twentytwenty.js', ['jquery'], \ElementsKit_Lite::version(), true);

		//ekit masonry layout
		wp_register_script('ekit-masonry', \ElementsKit_Lite::widget_url() . 'init/assets/js/ekit-masonry.js', [], \ElementsKit_Lite::version(), true);

		// ui script alternative of bootstrap. Used in [Tab, Advanced Tab, Accordion, Advanced Accordion, Advanced Toggle] widget
		wp_register_script('ui-slim', \ElementsKit_Lite::widget_url() . 'init/assets/js/ekit-ui.js', ['jquery'], \ElementsKit_Lite::version(), true);

		// register script for countdown timer
		wp_register_script( 'final-countdown', \ElementsKit_Lite::plugin_url() . 'assets/libs/final-countdown/jquery.countdown.min.js', array(), \ElementsKit_Lite::version(), true );

		// register script for piechart
		wp_register_script( 'easy-pie-chart', \ElementsKit_Lite::plugin_url() . 'assets/libs/easy-pie-chart/jquery.easypiechart.min.js', array(), \ElementsKit_Lite::version(), true );

		// register script for magnific-popup. Used in [video, team, header-search, team-slider, video-gallery, woo-product-carousel, woo-product-list] widget
		wp_register_script( 'magnific-popup', \ElementsKit_Lite::plugin_url() . 'assets/libs/magnific-popup/jquery.magnific-popup.min.js', array(), \ElementsKit_Lite::version(), true );

		// register script for mailchimp
		wp_register_script( 'ekit-mailchimp', \ElementsKit_Lite::widget_url() . 'init/assets/js/mail-chimp.js', array(), \ElementsKit_Lite::version(), true );

		// register script for pricing table
		wp_register_script( 'ekit-info-tip', \ElementsKit_Lite::widget_url() . 'init/assets/js/info-tip.js', array(), \ElementsKit_Lite::version(), true );

		// social share
		wp_register_script( 'goodshare', \ElementsKit_Lite::plugin_url() . 'assets/libs/goodshare/goodshare.min.js', array( 'jquery' ), \ElementsKit_Lite::version(), true );

		// funfact widget
		wp_register_script( 'odometer', \ElementsKit_Lite::plugin_url() . 'assets/libs/odometer/odometer.min.js', array('jquery'), \ElementsKit_Lite::version(), true );

		// Animate Circle Script. Used in [Back to Top] widget
		wp_register_script( 'animate-circle', \ElementsKit_Lite::widget_url() . 'init/assets/js/animate-circle.js', [], \ElementsKit_Lite::version(), true );

		// register scripts for lottie widget
		wp_register_script( 'lottie', \ElementsKit_Lite::plugin_url() . 'assets/libs/lottie/lottie.min.js', [], \ElementsKit_Lite::version(), true );
		wp_register_script( 'lottie-init', \ElementsKit_Lite::widget_url() . 'init/assets/js/lottie.init.js', ['lottie', 'elementor-frontend'], \ElementsKit_Lite::version(), true );

		// Nav menu & vertical menu widget script
		wp_register_script( 'ekit-menu', \ElementsKit_Lite::widget_url() . 'init/assets/js/nav-menu.js', ['jquery'], \ElementsKit_Lite::version(), true );

		// Register split Elementor widget scripts.
		wp_register_script( 'ekit-core', \ElementsKit_Lite::widget_url() . 'init/assets/js/widgets/core.js', ['jquery', 'elementor-frontend'], \ElementsKit_Lite::version(), true );
		wp_register_script( 'ekit-admin-toolbar', \ElementsKit_Lite::widget_url() . 'init/assets/js/widgets/admin-toolbar.js', ['jquery'], \ElementsKit_Lite::version(), true );
		wp_register_script( 'ekit-animate-numbers', \ElementsKit_Lite::widget_url() . 'init/assets/js/widgets/animate-numbers.js', ['jquery'], \ElementsKit_Lite::version(), true );

		$widget_list = \ElementsKit_Lite\Config\Widget_List::instance()->get_list( 'all' );

		wp_register_style( 'ekit-widget-common', \ElementsKit_Lite::widget_url() . 'init/assets/css/common.css', [], \ElementsKit_Lite::version() );

		foreach ( $widget_list as $widget_slug => $widget ) {
			if ( ! empty( $widget['hasJS'] ) ) {
				$script_handle = 'ekit-' . $widget_slug;
				$script_file   = $widget_slug . '.js';

				wp_register_script( $script_handle, \ElementsKit_Lite::widget_url() . 'init/assets/js/widgets/' . $script_file, [ 'ekit-core' ], \ElementsKit_Lite::version(), true );
			}

			$css_file = $widget_slug . '.css';
			if ( file_exists( \ElementsKit_Lite::widget_dir() . 'init/assets/css/' . $css_file ) ) {
				wp_register_style( 'ekit-' . $widget_slug, \ElementsKit_Lite::widget_url() . 'init/assets/css/' . $css_file, [ 'ekit-widget-common' ], \ElementsKit_Lite::version() );
			}
		}
	}

	public function enqueue_scripts() {
		/**
         * Localize frontend configuration for ElementsKit.
         */
        $config = apply_filters(
            'elementskit/common/localize_settings',
            [
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'ekit_pro' ),
                // Strings needed by markup that is built in JS and so cannot use
                // the PHP translation functions. Consumers must read these
                // defensively and fall back, since a widget script can run before
                // this is localized depending on which plugin registers ekit-core.
                'i18n'    => [
                    'video_frame'  => __( 'Video player', 'elementskit-lite' ),
                    'close'        => __( 'Close', 'elementskit-lite' ),

                    // Swiper's A11y module is enabled by default and already names
                    // the slider controls, but only in hardcoded English. These are
                    // the same messages, translatable.
                    'slider_prev'  => __( 'Previous slide', 'elementskit-lite' ),
                    'slider_next'  => __( 'Next slide', 'elementskit-lite' ),
                    'slider_first' => __( 'This is the first slide', 'elementskit-lite' ),
                    'slider_last'  => __( 'This is the last slide', 'elementskit-lite' ),
                    /* translators: {{index}} is replaced by Swiper with the slide number. Keep it as-is. */
                    'slider_bullet' => __( 'Go to slide {{index}}', 'elementskit-lite' ),
                ],
            ]
        );

		wp_localize_script( 'ekit-core', 'ekit_config', $config );

		// compatibility
		if($this->is_plugin_active('elementskit/elementskit.php') && version_compare(\Elementskit::version(), '3.2.0', '<=')) {
			// added swiper js - elementor remove it when "Improved Asset Loading" is active
			if(defined('ELEMENTOR_ASSETS_URL')) {
				wp_enqueue_script(
					'swiper',
					ELEMENTOR_ASSETS_URL . 'lib/swiper/swiper.min.js',
					[],
					\ElementsKit_Lite::version(),
					true
				);
			}
		}

		// added fluent form styles on the editor
		if (in_array('fluentform/fluentform.php', apply_filters('active_plugins', get_option('active_plugins')))) {
			wp_enqueue_style( 'fluent-form-styles' );
			wp_enqueue_style( 'fluentform-public-default' );
		}

		// Enqueue admin toolbar script for logged-in users
		if(is_user_logged_in()) {
			wp_enqueue_script( 'ekit-admin-toolbar' );
		}
	}

	public function register_frontend_css() {
		//third party styles for widgets
		// odometer styles
		wp_register_style( 'odometer', \ElementsKit_Lite::plugin_url() . 'assets/libs/odometer/odometer-theme-default.css', [], \ElementsKit_Lite::version() );
		// twentytwenty styles - this library is used for image comparison widget
		wp_register_style( 'twentytwenty', \ElementsKit_Lite::plugin_url() . 'assets/libs/twentytwenty/twentytwenty.css', [], \ElementsKit_Lite::version() );

		//maginific-popup styles
		wp_register_style( 'magnific-popup', \ElementsKit_Lite::plugin_url() . 'assets/libs/magnific-popup/magnific-popup.css', [], time() );
	}

	/**
	 * Enqueues scripts required by interactive Header/Footer Builder widgets.
	 *
	 * Header content can render before normal widget-level asset detection runs.
	 * These scripts are intentionally enqueued as a narrow exception so header
	 * controls initialize reliably; all other widget scripts remain dynamically
	 * loaded only when required.
	 *
	 * @return void
	 */
	public function enqueue_frontend_scripts() {
		$header_script_handles = [
			'ekit-core',
			'ekit-nav-menu',
			'magnific-popup',
			'ekit-menu',
			'countdown-timer',
			'ekit-header-search',
			'ekit-header-offcanvas',
		];

		foreach ( $header_script_handles as $script_handle ) {
			if ( wp_script_is( $script_handle, 'registered' ) ) {
				wp_enqueue_script( $script_handle );
			}
		}
	}

	/**
	 * Enqueues frontend styles that must be available before header markup renders.
	 *
	 * Header/Footer Builder content can render before normal widget-level asset
	 * detection enqueues the nav menu, header search, offcanvas, and info styles.
	 * These styles are intentionally enqueued in the document head to prevent unstyled
	 * header content, layout shifts, and flashes of unstyled content. This is a
	 * narrow exception to the dynamic CSS loading strategy; all other widget styles
	 * continue to load only when their widgets require them.
	 *
	 * @return void
	 */
	public function enqueue_frontend_css() {
		$header_style_handles = [
			'ekit-nav-menu',
			'ekit-header-search',
			'ekit-header-offcanvas',
			'ekit-header-info',
			'countdown-timer',
		];

		foreach ( $header_style_handles as $style_handle ) {
			if ( wp_style_is( $style_handle, 'registered' ) ) {
				wp_enqueue_style( $style_handle );
			}
		}

		// RTL styles
		if ( is_rtl() ) {
			wp_enqueue_style( 'elementskit-rtl', \ElementsKit_Lite::widget_url() . 'init/assets/css/rtl.css', [], \ElementsKit_Lite::version() );
		}
	}

	/**
	 * Preloads the assets of every Elementor document ElementsKit renders itself.
	 *
	 * Runs before Elementor's own style pass (priority 20) so that nested documents -
	 * Widget Area content, Mega Menu panels, Header/Footer templates - are announced in
	 * time for their Atomic (V4) style files to be generated and enqueued.
	 *
	 * @return void
	 */
	public function enqueue_nested_document_assets() {
		( new Nested_Document_Assets() )->enqueue();
	}

	public function enqueue_3rd_party_style() {
		if (function_exists( 'weforms' )) {
			wp_enqueue_style( 'weforms', plugins_url('/weforms/assets/wpuf/css/frontend-forms.css', 'weforms' ), [], \ElementsKit_Lite::version() );
		}

		if(defined('WPFORMS_PLUGIN_SLUG')){
			wp_enqueue_style( 'wpforms', plugins_url( '/'. WPFORMS_PLUGIN_SLUG . '/assets/css/wpforms-full.css', WPFORMS_PLUGIN_SLUG ), [], \ElementsKit_Lite::version() );
		}
	}

	public function elementor_editor_css() {
		wp_enqueue_style( 'elementskit-panel', \ElementsKit_Lite::widget_url() . 'init/assets/css/editor.css', [], \ElementsKit_Lite::version() );
	}
}
