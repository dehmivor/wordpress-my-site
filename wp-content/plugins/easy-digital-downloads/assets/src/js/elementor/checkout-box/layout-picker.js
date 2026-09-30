/**
 * EDD Checkout box panel layout-picker control and section toggles for Elementor.
 *
 * @package     EDD\Elementor
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

import { PATTERNS, SECTION_TOGGLE_CONTROLS } from './patterns';
import { pickerBox, boxContainsType } from './container-utils';
import { switchPattern, reAddSection, removeSection, isSeeding } from './pattern-seeding';

/**
 * Guard set while the module programmatically syncs a section switcher's value.
 *
 * The native `switcher` controls (edd_section_*) store their ON/OFF state in the
 * box's settings model. When a section is added/removed by any OTHER path (pattern
 * switch, seed, a canvas delete of the cart), the module writes the matching value
 * back onto the switcher so its slider reflects reality. That write fires the
 * settings `change:` event the toggle handler also listens on; this flag lets the
 * handler distinguish a programmatic sync (skip — the section already matches) from
 * a genuine user flip (act — add/remove the section). Always cleared in a `finally`
 * so a throw mid-sync can never strand it `true`.
 *
 * @since 3.7.0
 * @type {boolean}
 */
let eddSyncingToggle = false;

/**
 * The CSS class for the panel-hosted layout-picker / re-add affordance root.
 *
 * @since 3.7.0
 * @type {string}
 */
const PICKER_CLASS = 'edd-checkout-box-layout-picker';

/**
 * The Elementor control type the panel picker view is registered against.
 *
 * Must equal LayoutPicker::get_type() (src/Elementor/Controls/LayoutPicker.php)
 * and the type added in CheckoutBox::register_controls().
 *
 * @since 3.7.0
 * @type {string}
 */
const PICKER_CONTROL_TYPE = 'edd-layout-picker';

/**
 * The inline SVG thumbnail markup for each pattern, keyed by bare slug.
 *
 * The raw SVGs from includes/blocks/src/checkout/icons/*.svg (the same thumbnails
 * the Gutenberg block's LayoutPicker uses), inlined so the picker renders them
 * without a React/SVGR pipeline (Elementor's native CHOOSE control is font-icon
 * only and cannot host these). Slug -> file: single-column -> single-column.svg,
 * two-column-50-50 -> 50-50-split.svg, two-column-70-30 -> 70-30-split.svg,
 * two-column-80-20 -> 80-20-split.svg, cart-top-two-column -> combined.svg.
 *
 * @since 3.7.0
 * @type {Object}
 */
const PATTERN_ICONS = {
	'single-column': "<svg width=\"100%\" height=\"100%\" viewBox=\"0 0 484 505\" version=\"1.1\" xmlns=\"http://www.w3.org/2000/svg\" xmlns:xlink=\"http://www.w3.org/1999/xlink\" xml:space=\"preserve\" xmlns:serif=\"http://www.serif.com/\" style=\"fill-rule:evenodd;clip-rule:evenodd;\"><g><path d=\"M0,-20.573c0,-26.109 19.487,-47.274 43.524,-47.274l417.834,0c24.039,0 43.524,21.165 43.524,47.274l0,524.74l-504.883,0l0,-524.74Z\" style=\"fill:#f3f3f3;fill-rule:nonzero;\"/><path d=\"M457.292,43.75l0,8.333c0,6.324 -5.134,11.458 -11.458,11.458l-408.333,0c-6.324,0 -11.458,-5.134 -11.458,-11.458l0,-8.333c0,-6.324 5.134,-11.458 11.458,-11.458l408.333,0c6.324,0 11.458,5.134 11.458,11.458Z\" style=\"fill:#fff;\"/><path d=\"M457.292,43.75l0,8.333c0,6.324 -5.134,11.458 -11.458,11.458l-408.333,0c-6.324,0 -11.458,-5.134 -11.458,-11.458l0,-8.333c0,-6.324 5.134,-11.458 11.458,-11.458l408.333,0c6.324,0 11.458,5.134 11.458,11.458Z\" style=\"fill:none;stroke:#cfcfcf;stroke-width:2.08px;\"/><path d=\"M457.292,97.917l0,187.5c0,6.324 -5.134,11.458 -11.458,11.458l-408.333,0c-6.324,0 -11.458,-5.134 -11.458,-11.458l0,-187.5c0,-6.324 5.134,-11.458 11.458,-11.458l408.333,0c6.324,0 11.458,5.134 11.458,11.458Z\" style=\"fill:#fff;\"/><path d=\"M457.292,97.917l0,187.5c0,6.324 -5.134,11.458 -11.458,11.458l-408.333,0c-6.324,0 -11.458,-5.134 -11.458,-11.458l0,-187.5c0,-6.324 5.134,-11.458 11.458,-11.458l408.333,0c6.324,0 11.458,5.134 11.458,11.458Z\" style=\"fill:none;stroke:#cfcfcf;stroke-width:2.08px;\"/><path d=\"M100,118.75l0,20.833c0,6.899 -5.601,12.5 -12.5,12.5l-37.5,0c-6.899,0 -12.5,-5.601 -12.5,-12.5l0,-20.833c0,-6.899 5.601,-12.5 12.5,-12.5l37.5,0c6.899,0 12.5,5.601 12.5,12.5Z\" style=\"fill:#cfcfcf;\"/><path d=\"M110.417,118.75l70.833,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M110.417,135.417l45.833,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M397.917,127.083l37.5,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M100,177.083l0,20.833c0,6.899 -5.601,12.5 -12.5,12.5l-37.5,0c-6.899,0 -12.5,-5.601 -12.5,-12.5l0,-20.833c0,-6.899 5.601,-12.5 12.5,-12.5l37.5,0c6.899,0 12.5,5.601 12.5,12.5Z\" style=\"fill:#cfcfcf;\"/><path d=\"M110.417,177.083l70.833,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M110.417,193.75l45.833,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M397.917,185.417l37.5,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M45.833,227.083l391.667,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M397.917,241.667l37.5,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M45.833,260.417l391.667,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M457.292,331.25l0,229.167c0,6.324 -5.134,11.458 -11.458,11.458l-408.333,0c-6.324,0 -11.458,-5.134 -11.458,-11.458l0,-229.167c0,-6.324 5.134,-11.458 11.458,-11.458l408.333,0c6.324,0 11.458,5.134 11.458,11.458Z\" style=\"fill:#fff;\"/><path d=\"M457.292,331.25l0,229.167c0,6.324 -5.134,11.458 -11.458,11.458l-408.333,0c-6.324,0 -11.458,-5.134 -11.458,-11.458l0,-229.167c0,-6.324 5.134,-11.458 11.458,-11.458l408.333,0c6.324,0 11.458,5.134 11.458,11.458Z\" style=\"fill:none;stroke:#cfcfcf;stroke-width:2.08px;\"/><path d=\"M177.083,350c0,4.599 -3.734,8.333 -8.333,8.333l-116.667,0c-4.599,0 -8.333,-3.734 -8.333,-8.333c0,-4.599 3.734,-8.333 8.333,-8.333l116.667,0c4.599,0 8.333,3.734 8.333,8.333Z\" style=\"fill:#cfcfcf;\"/><path d=\"M45.833,372.917l70.833,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M443.75,400l0,8.333c0,5.749 -4.668,10.417 -10.417,10.417l-377.083,0c-5.749,0 -10.417,-4.668 -10.417,-10.417l0,-8.333c0,-5.749 4.668,-10.417 10.417,-10.417l377.083,0c5.749,0 10.417,4.668 10.417,10.417Z\" style=\"fill:none;stroke:#cfcfcf;stroke-width:4.17px;\"/><path d=\"M45.833,435.417l70.833,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M239.583,462.5l0,8.333c0,5.749 -4.668,10.417 -10.417,10.417l-172.917,0c-5.749,0 -10.417,-4.668 -10.417,-10.417l0,-8.333c0,-5.749 4.668,-10.417 10.417,-10.417l172.917,0c5.749,0 10.417,4.668 10.417,10.417Z\" style=\"fill:none;stroke:#cfcfcf;stroke-width:4.17px;\"/><path d=\"M252.083,435.417l70.833,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M443.75,462.5l0,8.333c0,5.749 -4.668,10.417 -10.417,10.417l-170.833,0c-5.749,0 -10.417,-4.668 -10.417,-10.417l0,-8.333c0,-5.749 4.668,-10.417 10.417,-10.417l170.833,0c5.749,0 10.417,4.668 10.417,10.417Z\" style=\"fill:none;stroke:#cfcfcf;stroke-width:4.17px;\"/></g></svg>",
	'two-column-50-50': "<svg width=\"100%\" height=\"100%\" viewBox=\"0 0 116 121\" version=\"1.1\" xmlns=\"http://www.w3.org/2000/svg\" xmlns:xlink=\"http://www.w3.org/1999/xlink\" xml:space=\"preserve\" xmlns:serif=\"http://www.serif.com/\" style=\"fill-rule:evenodd;clip-rule:evenodd;\">\n    <g>\n        <g transform=\"matrix(1.181127,0,0,1,-9.589492,0)\">\n            <path d=\"M0,10C0,4.477 4.477,0 10,0L106,0C111.523,0 116,4.477 116,10L116,121L0,121L0,10Z\" style=\"fill:rgb(243,243,243);fill-rule:nonzero;\"/>\n        </g>\n        <path d=\"M9,6.25L107,6.25C108.519,6.25 109.75,7.481 109.75,9L109.75,11C109.75,12.519 108.519,13.75 107,13.75L9,13.75C7.481,13.75 6.25,12.519 6.25,11L6.25,9C6.25,7.481 7.481,6.25 9,6.25Z\" style=\"fill:white;fill-rule:nonzero;\"/>\n        <path d=\"M9,6.25L107,6.25C108.519,6.25 109.75,7.481 109.75,9L109.75,11C109.75,12.519 108.519,13.75 107,13.75L9,13.75C7.481,13.75 6.25,12.519 6.25,11L6.25,9C6.25,7.481 7.481,6.25 9,6.25Z\" style=\"fill:none;fill-rule:nonzero;stroke:rgb(207,207,207);stroke-width:0.5px;\"/>\n        <path d=\"M55.75,22L55.75,114C55.75,115.518 54.518,116.75 53,116.75L9,116.75C7.482,116.75 6.25,115.518 6.25,114L6.25,22C6.25,20.482 7.482,19.25 9,19.25L53,19.25C54.518,19.25 55.75,20.482 55.75,22Z\" style=\"fill:white;\"/>\n        <path d=\"M55.75,22L55.75,114C55.75,115.518 54.518,116.75 53,116.75L9,116.75C7.482,116.75 6.25,115.518 6.25,114L6.25,22C6.25,20.482 7.482,19.25 9,19.25L53,19.25C54.518,19.25 55.75,20.482 55.75,22Z\" style=\"fill:none;stroke:rgb(207,207,207);stroke-width:0.5px;\"/>\n        <path d=\"M41,25C41,26.104 40.104,27 39,27L11,27C9.896,27 9,26.104 9,25C9,23.896 9.896,23 11,23L39,23C40.104,23 41,23.896 41,25Z\" style=\"fill:rgb(207,207,207);\"/>\n        <path d=\"M9.5,32.5L23.5,32.5\" style=\"fill:none;fill-rule:nonzero;stroke:rgb(207,207,207);stroke-width:1px;stroke-linecap:round;\"/>\n        <path d=\"M52.5,39L52.5,41C52.5,42.38 51.38,43.5 50,43.5L12,43.5C10.62,43.5 9.5,42.38 9.5,41L9.5,39C9.5,37.62 10.62,36.5 12,36.5L50,36.5C51.38,36.5 52.5,37.62 52.5,39Z\" style=\"fill:white;\"/>\n        <path d=\"M52.5,39L52.5,41C52.5,42.38 51.38,43.5 50,43.5L12,43.5C10.62,43.5 9.5,42.38 9.5,41L9.5,39C9.5,37.62 10.62,36.5 12,36.5L50,36.5C51.38,36.5 52.5,37.62 52.5,39Z\" style=\"fill:none;stroke:rgb(207,207,207);stroke-width:1px;\"/>\n        <path d=\"M9.5,49.5L23.5,49.5\" style=\"fill:none;fill-rule:nonzero;stroke:rgb(207,207,207);stroke-width:1px;stroke-linecap:round;\"/>\n        <path d=\"M29.5,56L29.5,58C29.5,59.38 28.38,60.5 27,60.5L12,60.5C10.62,60.5 9.5,59.38 9.5,58L9.5,56C9.5,54.62 10.62,53.5 12,53.5L27,53.5C28.38,53.5 29.5,54.62 29.5,56Z\" style=\"fill:white;\"/>\n        <path d=\"M29.5,56L29.5,58C29.5,59.38 28.38,60.5 27,60.5L12,60.5C10.62,60.5 9.5,59.38 9.5,58L9.5,56C9.5,54.62 10.62,53.5 12,53.5L27,53.5C28.38,53.5 29.5,54.62 29.5,56Z\" style=\"fill:none;stroke:rgb(207,207,207);stroke-width:1px;\"/>\n        <path d=\"M32.5,49.5L46.5,49.5\" style=\"fill:none;fill-rule:nonzero;stroke:rgb(207,207,207);stroke-width:1px;stroke-linecap:round;\"/>\n        <path d=\"M52.5,56L52.5,58C52.5,59.38 51.38,60.5 50,60.5L35,60.5C33.62,60.5 32.5,59.38 32.5,58L32.5,56C32.5,54.62 33.62,53.5 35,53.5L50,53.5C51.38,53.5 52.5,54.62 52.5,56Z\" style=\"fill:white;\"/>\n        <path d=\"M52.5,56L52.5,58C52.5,59.38 51.38,60.5 50,60.5L35,60.5C33.62,60.5 32.5,59.38 32.5,58L32.5,56C32.5,54.62 33.62,53.5 35,53.5L50,53.5C51.38,53.5 52.5,54.62 52.5,56Z\" style=\"fill:none;stroke:rgb(207,207,207);stroke-width:1px;\"/>\n        <path d=\"M53,69L53,99C53,100.104 52.104,101 51,101L11,101C9.896,101 9,100.104 9,99L9,69C9,67.896 9.896,67 11,67L51,67C52.104,67 53,67.896 53,69Z\" style=\"fill:rgb(207,207,207);\"/>\n        <rect x=\"13\" y=\"71\" width=\"27\" height=\"4.081\" style=\"fill:none;\"/>\n        <path d=\"M13,82.5L50,82.5\" style=\"fill:none;fill-rule:nonzero;stroke:white;stroke-width:1px;stroke-linecap:round;\"/>\n        <path d=\"M13,90L28,90\" style=\"fill:none;fill-rule:nonzero;stroke:white;stroke-width:1px;stroke-linecap:round;\"/>\n        <path d=\"M34,90L50,90\" style=\"fill:none;fill-rule:nonzero;stroke:white;stroke-width:1px;stroke-linecap:round;\"/>\n        <path d=\"M51,108C51,110.208 49.208,112 47,112L15,112C12.792,112 11,110.208 11,108C11,105.792 12.792,104 15,104L47,104C49.208,104 51,105.792 51,108Z\" style=\"fill:rgb(8,158,9);\"/>\n        <path d=\"M109.75,22L109.75,86C109.75,87.518 108.518,88.75 107,88.75L63,88.75C61.482,88.75 60.25,87.518 60.25,86L60.25,22C60.25,20.482 61.482,19.25 63,19.25L107,19.25C108.518,19.25 109.75,20.482 109.75,22Z\" style=\"fill:white;\"/>\n        <path d=\"M109.75,22L109.75,86C109.75,87.518 108.518,88.75 107,88.75L63,88.75C61.482,88.75 60.25,87.518 60.25,86L60.25,22C60.25,20.482 61.482,19.25 63,19.25L107,19.25C108.518,19.25 109.75,20.482 109.75,22Z\" style=\"fill:none;stroke:rgb(207,207,207);stroke-width:0.5px;\"/>\n        <path d=\"M95,26C95,27.104 94.104,28 93,28L65,28C63.896,28 63,27.104 63,26C63,24.896 63.896,24 65,24L93,24C94.104,24 95,24.896 95,26Z\" style=\"fill:rgb(207,207,207);\"/>\n        <path d=\"M78,34L78,39C78,40.656 76.656,42 75,42L66,42C64.344,42 63,40.656 63,39L63,34C63,32.344 64.344,31 66,31L75,31C76.656,31 78,32.344 78,34Z\" style=\"fill:rgb(207,207,207);\"/>\n        <path d=\"M63.5,45.5L80.5,45.5\" style=\"fill:none;fill-rule:nonzero;stroke:rgb(207,207,207);stroke-width:1px;stroke-linecap:round;\"/>\n        <path d=\"M63.5,49.5L74.5,49.5\" style=\"fill:none;fill-rule:nonzero;stroke:rgb(207,207,207);stroke-width:1px;stroke-linecap:round;\"/>\n        <path d=\"M97.5,40L106.5,40\" style=\"fill:none;fill-rule:nonzero;stroke:rgb(207,207,207);stroke-width:1px;stroke-linecap:round;\"/>\n        <path d=\"M78,56L78,61C78,62.656 76.656,64 75,64L66,64C64.344,64 63,62.656 63,61L63,56C63,54.344 64.344,53 66,53L75,53C76.656,53 78,54.344 78,56Z\" style=\"fill:rgb(207,207,207);\"/>\n        <path d=\"M63.5,67.5L80.5,67.5\" style=\"fill:none;fill-rule:nonzero;stroke:rgb(207,207,207);stroke-width:1px;stroke-linecap:round;\"/>\n        <path d=\"M63.5,71.5L74.5,71.5\" style=\"fill:none;fill-rule:nonzero;stroke:rgb(207,207,207);stroke-width:1px;stroke-linecap:round;\"/>\n        <path d=\"M97.5,62L106.5,62\" style=\"fill:none;fill-rule:nonzero;stroke:rgb(207,207,207);stroke-width:1px;stroke-linecap:round;\"/>\n        <path d=\"M63,76L107,76\" style=\"fill:none;fill-rule:nonzero;stroke:rgb(207,207,207);stroke-width:1px;stroke-linecap:round;\"/>\n        <path d=\"M97.5,79.5L106.5,79.5\" style=\"fill:none;fill-rule:nonzero;stroke:rgb(207,207,207);stroke-width:1px;stroke-linecap:round;\"/>\n        <path d=\"M63,84L107,84\" style=\"fill:none;fill-rule:nonzero;stroke:rgb(207,207,207);stroke-width:1px;stroke-linecap:round;\"/>\n        <path d=\"M109.75,95L109.75,119C109.75,120.518 108.518,121.75 107,121.75L63,121.75C61.482,121.75 60.25,120.518 60.25,119L60.25,95C60.25,93.482 61.482,92.25 63,92.25L107,92.25C108.518,92.25 109.75,93.482 109.75,95Z\" style=\"fill:white;\"/>\n        <path d=\"M109.75,95L109.75,119C109.75,120.518 108.518,121.75 107,121.75L63,121.75C61.482,121.75 60.25,120.518 60.25,119L60.25,95C60.25,93.482 61.482,92.25 63,92.25L107,92.25C108.518,92.25 109.75,93.482 109.75,95Z\" style=\"fill:none;stroke:rgb(207,207,207);stroke-width:0.5px;\"/>\n        <path d=\"M63,96.5L107,96.5\" style=\"fill:none;fill-rule:nonzero;stroke:rgb(207,207,207);stroke-width:1px;\"/>\n        <path d=\"M63,100.5L107,100.5\" style=\"fill:none;fill-rule:nonzero;stroke:rgb(207,207,207);stroke-width:1px;\"/>\n        <path d=\"M63,104.5L107,104.5\" style=\"fill:none;fill-rule:nonzero;stroke:rgb(207,207,207);stroke-width:1px;\"/>\n        <path d=\"M63,108.5L84,108.5\" style=\"fill:none;fill-rule:nonzero;stroke:rgb(207,207,207);stroke-width:1px;\"/>\n        <path d=\"M65.5,114L66.061,115.727L67.878,115.727L66.408,116.795L66.969,118.523L65.5,117.455L64.031,118.523L64.592,116.795L63.122,115.727L64.939,115.727L65.5,114Z\" style=\"fill:rgb(254,176,0);fill-rule:nonzero;\"/>\n        <path d=\"M72.167,114L72.728,115.727L74.544,115.727L73.075,116.795L73.636,118.523L72.167,117.455L70.697,118.523L71.258,116.795L69.789,115.727L71.605,115.727L72.167,114Z\" style=\"fill:rgb(254,176,0);fill-rule:nonzero;\"/>\n        <path d=\"M78.833,114L79.395,115.727L81.211,115.727L79.742,116.795L80.303,118.523L78.833,117.455L77.364,118.523L77.925,116.795L76.456,115.727L78.272,115.727L78.833,114Z\" style=\"fill:rgb(254,176,0);fill-rule:nonzero;\"/>\n        <path d=\"M85.5,114L86.061,115.727L87.878,115.727L86.408,116.795L86.969,118.523L85.5,117.455L84.031,118.523L84.592,116.795L83.122,115.727L84.939,115.727L85.5,114Z\" style=\"fill:rgb(254,176,0);fill-rule:nonzero;\"/>\n    </g>\n</svg>",
	'two-column-70-30': "<svg width=\"100%\" height=\"100%\" viewBox=\"0 0 484 505\" version=\"1.1\" xmlns=\"http://www.w3.org/2000/svg\" xmlns:xlink=\"http://www.w3.org/1999/xlink\" xml:space=\"preserve\" xmlns:serif=\"http://www.serif.com/\" style=\"fill-rule:evenodd;clip-rule:evenodd;\"><g><path d=\"M-50.282,41.667c0,-23.012 22.64,-41.667 50.568,-41.667l485.455,0c27.929,0 50.568,18.655 50.568,41.667l0,462.5l-586.591,0l-0,-462.5Z\" style=\"fill:#f3f3f3;fill-rule:nonzero;\"/><path d=\"M37.5,26.042l408.333,0c6.329,0 11.458,5.13 11.458,11.458l0,8.333c0,6.328 -5.129,11.458 -11.458,11.458l-408.333,0c-6.328,0 -11.458,-5.13 -11.458,-11.458l0,-8.333c0,-6.328 5.13,-11.458 11.458,-11.458Z\" style=\"fill:#fff;fill-rule:nonzero;\"/><path d=\"M37.5,26.042l408.333,0c6.329,0 11.458,5.13 11.458,11.458l0,8.333c0,6.328 -5.129,11.458 -11.458,11.458l-408.333,0c-6.328,0 -11.458,-5.13 -11.458,-11.458l0,-8.333c0,-6.328 5.13,-11.458 11.458,-11.458Z\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:2.08px;\"/><path d=\"M265.625,91.667l0,383.333c0,6.324 -5.134,11.458 -11.458,11.458l-216.667,0c-6.324,0 -11.458,-5.134 -11.458,-11.458l0,-383.333c0,-6.324 5.134,-11.458 11.458,-11.458l216.667,0c6.324,0 11.458,5.134 11.458,11.458Z\" style=\"fill:#fff;\"/><path d=\"M265.625,91.667l0,383.333c0,6.324 -5.134,11.458 -11.458,11.458l-216.667,0c-6.324,0 -11.458,-5.134 -11.458,-11.458l0,-383.333c0,-6.324 5.134,-11.458 11.458,-11.458l216.667,0c6.324,0 11.458,5.134 11.458,11.458Z\" style=\"fill:none;stroke:#cfcfcf;stroke-width:2.08px;\"/><path d=\"M175,104.167c0,4.599 -3.734,8.333 -8.333,8.333l-116.667,0c-4.599,0 -8.333,-3.734 -8.333,-8.333c0,-4.599 3.734,-8.333 8.333,-8.333l116.667,0c4.599,0 8.333,3.734 8.333,8.333Z\" style=\"fill:#cfcfcf;\"/><path d=\"M43.75,135.417l58.333,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M247.917,162.5l0,8.333c0,5.749 -4.668,10.417 -10.417,10.417l-183.333,0c-5.749,0 -10.417,-4.668 -10.417,-10.417l0,-8.333c0,-5.749 4.668,-10.417 10.417,-10.417l183.333,0c5.749,0 10.417,4.668 10.417,10.417Z\" style=\"fill:#fff;\"/><path d=\"M247.917,162.5l0,8.333c0,5.749 -4.668,10.417 -10.417,10.417l-183.333,0c-5.749,0 -10.417,-4.668 -10.417,-10.417l0,-8.333c0,-5.749 4.668,-10.417 10.417,-10.417l183.333,0c5.749,0 10.417,4.668 10.417,10.417Z\" style=\"fill:none;stroke:#cfcfcf;stroke-width:4.17px;\"/><path d=\"M43.75,206.25l58.333,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M139.583,233.333l0,8.333c0,5.749 -4.668,10.417 -10.417,10.417l-75,0c-5.749,0 -10.417,-4.668 -10.417,-10.417l0,-8.333c0,-5.749 4.668,-10.417 10.417,-10.417l75,0c5.749,0 10.417,4.668 10.417,10.417Z\" style=\"fill:#fff;\"/><path d=\"M139.583,233.333l0,8.333c0,5.749 -4.668,10.417 -10.417,10.417l-75,0c-5.749,0 -10.417,-4.668 -10.417,-10.417l0,-8.333c0,-5.749 4.668,-10.417 10.417,-10.417l75,0c5.749,0 10.417,4.668 10.417,10.417Z\" style=\"fill:none;stroke:#cfcfcf;stroke-width:4.17px;\"/><path d=\"M152.083,206.25l58.333,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M247.917,233.333l0,8.333c0,5.749 -4.668,10.417 -10.417,10.417l-75,0c-5.749,0 -10.417,-4.668 -10.417,-10.417l0,-8.333c0,-5.749 4.668,-10.417 10.417,-10.417l75,0c5.749,0 10.417,4.668 10.417,10.417Z\" style=\"fill:#fff;\"/><path d=\"M247.917,233.333l0,8.333c0,5.749 -4.668,10.417 -10.417,10.417l-75,0c-5.749,0 -10.417,-4.668 -10.417,-10.417l0,-8.333c0,-5.749 4.668,-10.417 10.417,-10.417l75,0c5.749,0 10.417,4.668 10.417,10.417Z\" style=\"fill:none;stroke:#cfcfcf;stroke-width:4.17px;\"/><path d=\"M250,287.5l0,125c0,4.599 -3.734,8.333 -8.333,8.333l-191.667,0c-4.599,0 -8.333,-3.734 -8.333,-8.333l0,-125c0,-4.599 3.734,-8.333 8.333,-8.333l191.667,0c4.599,0 8.333,3.734 8.333,8.333Z\" style=\"fill:#cfcfcf;\"/><rect x=\"58.333\" y=\"295.833\" width=\"112.5\" height=\"17.006\" style=\"fill:none;\"/><path d=\"M58.333,343.75l154.167,0\" style=\"fill:none;fill-rule:nonzero;stroke:#fff;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M58.333,375l62.5,0\" style=\"fill:none;fill-rule:nonzero;stroke:#fff;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M145.833,375l66.667,0\" style=\"fill:none;fill-rule:nonzero;stroke:#fff;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M229.167,450c0,9.199 -7.468,16.667 -16.667,16.667l-133.333,0c-9.199,0 -16.667,-7.468 -16.667,-16.667c0,-9.199 7.468,-16.667 16.667,-16.667l133.333,0c9.199,0 16.667,7.468 16.667,16.667Z\" style=\"fill:#089e09;\"/><path d=\"M457.292,91.667l0,266.667c0,6.324 -5.134,11.458 -11.458,11.458l-150,0c-6.324,0 -11.458,-5.134 -11.458,-11.458l0,-266.667c0,-6.324 5.134,-11.458 11.458,-11.458l150,0c6.324,0 11.458,5.134 11.458,11.458Z\" style=\"fill:#fff;\"/><path d=\"M457.292,91.667l0,266.667c0,6.324 -5.134,11.458 -11.458,11.458l-150,0c-6.324,0 -11.458,-5.134 -11.458,-11.458l0,-266.667c0,-6.324 5.134,-11.458 11.458,-11.458l150,0c6.324,0 11.458,5.134 11.458,11.458Z\" style=\"fill:none;stroke:#cfcfcf;stroke-width:2.08px;\"/><path d=\"M425,108.333c0,4.599 -3.734,8.333 -8.333,8.333l-116.667,0c-4.599,0 -8.333,-3.734 -8.333,-8.333c0,-4.599 3.734,-8.333 8.333,-8.333l116.667,0c4.599,0 8.333,3.734 8.333,8.333Z\" style=\"fill:#cfcfcf;\"/><path d=\"M354.167,141.667l0,20.833c0,6.899 -5.601,12.5 -12.5,12.5l-37.5,0c-6.899,0 -12.5,-5.601 -12.5,-12.5l0,-20.833c0,-6.899 5.601,-12.5 12.5,-12.5l37.5,0c6.899,0 12.5,5.601 12.5,12.5Z\" style=\"fill:#cfcfcf;\"/><path d=\"M293.75,189.583l70.833,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M293.75,206.25l45.833,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M410.417,166.667l37.5,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M354.167,233.333l0,20.833c0,6.899 -5.601,12.5 -12.5,12.5l-37.5,0c-6.899,0 -12.5,-5.601 -12.5,-12.5l0,-20.833c0,-6.899 5.601,-12.5 12.5,-12.5l37.5,0c6.899,0 12.5,5.601 12.5,12.5Z\" style=\"fill:#cfcfcf;\"/><path d=\"M293.75,281.25l70.833,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M293.75,297.917l45.833,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M410.417,258.333l37.5,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M291.667,316.667l158.333,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M410.417,331.25l37.5,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M291.667,350l158.333,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><g><clipPath id=\"_clip1\"><path d=\"M458.333,395.833l0,100c0,6.899 -5.601,12.5 -12.5,12.5l-150,0c-6.899,0 -12.5,-5.601 -12.5,-12.5l0,-100c0,-6.899 5.601,-12.5 12.5,-12.5l150,0c6.899,0 12.5,5.601 12.5,12.5Z\"/></clipPath><g clip-path=\"url(#_clip1)\"><path d=\"M458.333,395.833l0,100c0,6.899 -5.601,12.5 -12.5,12.5l-150,0c-6.899,0 -12.5,-5.601 -12.5,-12.5l0,-100c0,-6.899 5.601,-12.5 12.5,-12.5l150,0c6.899,0 12.5,5.601 12.5,12.5Z\" style=\"fill:#fff;\"/><path d=\"M291.667,402.083l158.333,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;\"/><path d=\"M291.667,418.75l158.333,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;\"/><path d=\"M291.667,435.417l158.333,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;\"/><path d=\"M291.667,452.083l87.5,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;\"/><path d=\"M302.083,475l2.339,7.196l7.568,0l-6.123,4.45l2.339,7.2l-6.123,-4.45l-6.123,4.45l2.339,-7.2l-6.123,-4.45l7.568,0l2.339,-7.196Z\" style=\"fill:#feb000;fill-rule:nonzero;\"/><path d=\"M329.861,475l2.339,7.196l7.568,0l-6.123,4.45l2.339,7.2l-6.123,-4.45l-6.123,4.45l2.338,-7.2l-6.122,-4.45l7.568,0l2.339,-7.196Z\" style=\"fill:#feb000;fill-rule:nonzero;\"/><path d=\"M357.639,475l2.339,7.196l7.568,0l-6.122,4.45l2.338,7.2l-6.123,-4.45l-6.123,4.45l2.339,-7.2l-6.123,-4.45l7.568,0l2.339,-7.196Z\" style=\"fill:#feb000;fill-rule:nonzero;\"/><path d=\"M385.417,475l2.339,7.196l7.568,0l-6.123,4.45l2.339,7.2l-6.123,-4.45l-6.123,4.45l2.339,-7.2l-6.123,-4.45l7.568,0l2.339,-7.196Z\" style=\"fill:#feb000;fill-rule:nonzero;\"/></g></g><path d=\"M457.292,395.833l0,100c0,6.324 -5.134,11.458 -11.458,11.458l-150,0c-6.324,0 -11.458,-5.134 -11.458,-11.458l0,-100c0,-6.324 5.134,-11.458 11.458,-11.458l150,0c6.324,0 11.458,5.134 11.458,11.458Z\" style=\"fill:none;stroke:#cfcfcf;stroke-width:2.08px;\"/></g></svg>",
	'two-column-80-20': "<svg width=\"100%\" height=\"100%\" viewBox=\"0 0 484 505\" version=\"1.1\" xmlns=\"http://www.w3.org/2000/svg\" xmlns:xlink=\"http://www.w3.org/1999/xlink\" xml:space=\"preserve\" xmlns:serif=\"http://www.serif.com/\" style=\"fill-rule:evenodd;clip-rule:evenodd;\"><g><path d=\"M-50.282,41.667c0,-23.012 22.64,-41.667 50.568,-41.667l485.455,0c27.929,0 50.568,18.655 50.568,41.667l0,462.5l-586.591,0l-0,-462.5Z\" style=\"fill:#f3f3f3;fill-rule:nonzero;\"/><path d=\"M37.5,26.042l408.333,0c6.329,0 11.458,5.13 11.458,11.458l0,8.333c0,6.328 -5.129,11.458 -11.458,11.458l-408.333,0c-6.328,0 -11.458,-5.13 -11.458,-11.458l0,-8.333c0,-6.328 5.13,-11.458 11.458,-11.458Z\" style=\"fill:#fff;fill-rule:nonzero;\"/><path d=\"M37.5,26.042l408.333,0c6.329,0 11.458,5.13 11.458,11.458l0,8.333c0,6.328 -5.129,11.458 -11.458,11.458l-408.333,0c-6.328,0 -11.458,-5.13 -11.458,-11.458l0,-8.333c0,-6.328 5.13,-11.458 11.458,-11.458Z\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:2.08px;\"/><path d=\"M265.625,91.667l0,383.333c0,6.324 -5.134,11.458 -11.458,11.458l-216.667,0c-6.324,0 -11.458,-5.134 -11.458,-11.458l0,-383.333c0,-6.324 5.134,-11.458 11.458,-11.458l216.667,0c6.324,0 11.458,5.134 11.458,11.458Z\" style=\"fill:#fff;\"/><path d=\"M265.625,91.667l0,383.333c0,6.324 -5.134,11.458 -11.458,11.458l-216.667,0c-6.324,0 -11.458,-5.134 -11.458,-11.458l0,-383.333c0,-6.324 5.134,-11.458 11.458,-11.458l216.667,0c6.324,0 11.458,5.134 11.458,11.458Z\" style=\"fill:none;stroke:#cfcfcf;stroke-width:2.08px;\"/><path d=\"M175,104.167c0,4.599 -3.734,8.333 -8.333,8.333l-116.667,0c-4.599,0 -8.333,-3.734 -8.333,-8.333c0,-4.599 3.734,-8.333 8.333,-8.333l116.667,0c4.599,0 8.333,3.734 8.333,8.333Z\" style=\"fill:#cfcfcf;\"/><path d=\"M43.75,135.417l58.333,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M247.917,162.5l0,8.333c0,5.749 -4.668,10.417 -10.417,10.417l-183.333,0c-5.749,0 -10.417,-4.668 -10.417,-10.417l0,-8.333c0,-5.749 4.668,-10.417 10.417,-10.417l183.333,0c5.749,0 10.417,4.668 10.417,10.417Z\" style=\"fill:#fff;\"/><path d=\"M247.917,162.5l0,8.333c0,5.749 -4.668,10.417 -10.417,10.417l-183.333,0c-5.749,0 -10.417,-4.668 -10.417,-10.417l0,-8.333c0,-5.749 4.668,-10.417 10.417,-10.417l183.333,0c5.749,0 10.417,4.668 10.417,10.417Z\" style=\"fill:none;stroke:#cfcfcf;stroke-width:4.17px;\"/><path d=\"M43.75,206.25l58.333,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M139.583,233.333l0,8.333c0,5.749 -4.668,10.417 -10.417,10.417l-75,0c-5.749,0 -10.417,-4.668 -10.417,-10.417l0,-8.333c0,-5.749 4.668,-10.417 10.417,-10.417l75,0c5.749,0 10.417,4.668 10.417,10.417Z\" style=\"fill:#fff;\"/><path d=\"M139.583,233.333l0,8.333c0,5.749 -4.668,10.417 -10.417,10.417l-75,0c-5.749,0 -10.417,-4.668 -10.417,-10.417l0,-8.333c0,-5.749 4.668,-10.417 10.417,-10.417l75,0c5.749,0 10.417,4.668 10.417,10.417Z\" style=\"fill:none;stroke:#cfcfcf;stroke-width:4.17px;\"/><path d=\"M152.083,206.25l58.333,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M247.917,233.333l0,8.333c0,5.749 -4.668,10.417 -10.417,10.417l-75,0c-5.749,0 -10.417,-4.668 -10.417,-10.417l0,-8.333c0,-5.749 4.668,-10.417 10.417,-10.417l75,0c5.749,0 10.417,4.668 10.417,10.417Z\" style=\"fill:#fff;\"/><path d=\"M247.917,233.333l0,8.333c0,5.749 -4.668,10.417 -10.417,10.417l-75,0c-5.749,0 -10.417,-4.668 -10.417,-10.417l0,-8.333c0,-5.749 4.668,-10.417 10.417,-10.417l75,0c5.749,0 10.417,4.668 10.417,10.417Z\" style=\"fill:none;stroke:#cfcfcf;stroke-width:4.17px;\"/><path d=\"M250,287.5l0,125c0,4.599 -3.734,8.333 -8.333,8.333l-191.667,0c-4.599,0 -8.333,-3.734 -8.333,-8.333l0,-125c0,-4.599 3.734,-8.333 8.333,-8.333l191.667,0c4.599,0 8.333,3.734 8.333,8.333Z\" style=\"fill:#cfcfcf;\"/><rect x=\"58.333\" y=\"295.833\" width=\"112.5\" height=\"17.006\" style=\"fill:none;\"/><path d=\"M58.333,343.75l154.167,0\" style=\"fill:none;fill-rule:nonzero;stroke:#fff;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M58.333,375l62.5,0\" style=\"fill:none;fill-rule:nonzero;stroke:#fff;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M145.833,375l66.667,0\" style=\"fill:none;fill-rule:nonzero;stroke:#fff;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M229.167,450c0,9.199 -7.468,16.667 -16.667,16.667l-133.333,0c-9.199,0 -16.667,-7.468 -16.667,-16.667c0,-9.199 7.468,-16.667 16.667,-16.667l133.333,0c9.199,0 16.667,7.468 16.667,16.667Z\" style=\"fill:#089e09;\"/><path d=\"M457.292,91.667l0,266.667c0,6.324 -5.134,11.458 -11.458,11.458l-150,0c-6.324,0 -11.458,-5.134 -11.458,-11.458l0,-266.667c0,-6.324 5.134,-11.458 11.458,-11.458l150,0c6.324,0 11.458,5.134 11.458,11.458Z\" style=\"fill:#fff;\"/><path d=\"M457.292,91.667l0,266.667c0,6.324 -5.134,11.458 -11.458,11.458l-150,0c-6.324,0 -11.458,-5.134 -11.458,-11.458l0,-266.667c0,-6.324 5.134,-11.458 11.458,-11.458l150,0c6.324,0 11.458,5.134 11.458,11.458Z\" style=\"fill:none;stroke:#cfcfcf;stroke-width:2.08px;\"/><path d=\"M425,108.333c0,4.599 -3.734,8.333 -8.333,8.333l-116.667,0c-4.599,0 -8.333,-3.734 -8.333,-8.333c0,-4.599 3.734,-8.333 8.333,-8.333l116.667,0c4.599,0 8.333,3.734 8.333,8.333Z\" style=\"fill:#cfcfcf;\"/><path d=\"M354.167,141.667l0,20.833c0,6.899 -5.601,12.5 -12.5,12.5l-37.5,0c-6.899,0 -12.5,-5.601 -12.5,-12.5l0,-20.833c0,-6.899 5.601,-12.5 12.5,-12.5l37.5,0c6.899,0 12.5,5.601 12.5,12.5Z\" style=\"fill:#cfcfcf;\"/><path d=\"M293.75,189.583l70.833,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M293.75,206.25l45.833,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M410.417,166.667l37.5,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M354.167,233.333l0,20.833c0,6.899 -5.601,12.5 -12.5,12.5l-37.5,0c-6.899,0 -12.5,-5.601 -12.5,-12.5l0,-20.833c0,-6.899 5.601,-12.5 12.5,-12.5l37.5,0c6.899,0 12.5,5.601 12.5,12.5Z\" style=\"fill:#cfcfcf;\"/><path d=\"M293.75,281.25l70.833,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M293.75,297.917l45.833,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M410.417,258.333l37.5,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M291.667,316.667l158.333,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M410.417,331.25l37.5,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M291.667,350l158.333,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><g><clipPath id=\"_clip1\"><path d=\"M458.333,395.833l0,100c0,6.899 -5.601,12.5 -12.5,12.5l-150,0c-6.899,0 -12.5,-5.601 -12.5,-12.5l0,-100c0,-6.899 5.601,-12.5 12.5,-12.5l150,0c6.899,0 12.5,5.601 12.5,12.5Z\"/></clipPath><g clip-path=\"url(#_clip1)\"><path d=\"M458.333,395.833l0,100c0,6.899 -5.601,12.5 -12.5,12.5l-150,0c-6.899,0 -12.5,-5.601 -12.5,-12.5l0,-100c0,-6.899 5.601,-12.5 12.5,-12.5l150,0c6.899,0 12.5,5.601 12.5,12.5Z\" style=\"fill:#fff;\"/><path d=\"M291.667,402.083l158.333,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;\"/><path d=\"M291.667,418.75l158.333,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;\"/><path d=\"M291.667,435.417l158.333,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;\"/><path d=\"M291.667,452.083l87.5,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;\"/><path d=\"M302.083,475l2.339,7.196l7.568,0l-6.123,4.45l2.339,7.2l-6.123,-4.45l-6.123,4.45l2.339,-7.2l-6.123,-4.45l7.568,0l2.339,-7.196Z\" style=\"fill:#feb000;fill-rule:nonzero;\"/><path d=\"M329.861,475l2.339,7.196l7.568,0l-6.123,4.45l2.339,7.2l-6.123,-4.45l-6.123,4.45l2.338,-7.2l-6.122,-4.45l7.568,0l2.339,-7.196Z\" style=\"fill:#feb000;fill-rule:nonzero;\"/><path d=\"M357.639,475l2.339,7.196l7.568,0l-6.122,4.45l2.338,7.2l-6.123,-4.45l-6.123,4.45l2.339,-7.2l-6.123,-4.45l7.568,0l2.339,-7.196Z\" style=\"fill:#feb000;fill-rule:nonzero;\"/><path d=\"M385.417,475l2.339,7.196l7.568,0l-6.123,4.45l2.339,7.2l-6.123,-4.45l-6.123,4.45l2.339,-7.2l-6.123,-4.45l7.568,0l2.339,-7.196Z\" style=\"fill:#feb000;fill-rule:nonzero;\"/></g></g><path d=\"M457.292,395.833l0,100c0,6.324 -5.134,11.458 -11.458,11.458l-150,0c-6.324,0 -11.458,-5.134 -11.458,-11.458l0,-100c0,-6.324 5.134,-11.458 11.458,-11.458l150,0c6.324,0 11.458,5.134 11.458,11.458Z\" style=\"fill:none;stroke:#cfcfcf;stroke-width:2.08px;\"/></g></svg>",
	'cart-top-two-column': "<svg width=\"100%\" height=\"100%\" viewBox=\"0 0 484 505\" version=\"1.1\" xmlns=\"http://www.w3.org/2000/svg\" xmlns:xlink=\"http://www.w3.org/1999/xlink\" xml:space=\"preserve\" xmlns:serif=\"http://www.serif.com/\" style=\"fill-rule:evenodd;clip-rule:evenodd;\"><g><path d=\"M-83.055,41.667c0,-23.012 24.269,-41.667 54.206,-41.667l520.38,0c29.938,0 54.206,18.655 54.206,41.667l0,462.5l-628.792,0l-0,-462.5Z\" style=\"fill:#f3f3f3;fill-rule:nonzero;\"/><path d=\"M457.292,31.25l0,8.333c0,6.324 -5.134,11.458 -11.458,11.458l-408.333,0c-6.324,0 -11.458,-5.134 -11.458,-11.458l0,-8.333c0,-6.324 5.134,-11.458 11.458,-11.458l408.333,0c6.324,0 11.458,5.134 11.458,11.458Z\" style=\"fill:#fff;\"/><path d=\"M457.292,31.25l0,8.333c0,6.324 -5.134,11.458 -11.458,11.458l-408.333,0c-6.324,0 -11.458,-5.134 -11.458,-11.458l0,-8.333c0,-6.324 5.134,-11.458 11.458,-11.458l408.333,0c6.324,0 11.458,5.134 11.458,11.458Z\" style=\"fill:none;stroke:#cfcfcf;stroke-width:2.08px;\"/><path d=\"M457.292,85.417l0,187.5c0,6.324 -5.134,11.458 -11.458,11.458l-408.333,0c-6.324,0 -11.458,-5.134 -11.458,-11.458l0,-187.5c0,-6.324 5.134,-11.458 11.458,-11.458l408.333,0c6.324,0 11.458,5.134 11.458,11.458Z\" style=\"fill:#fff;\"/><path d=\"M457.292,85.417l0,187.5c0,6.324 -5.134,11.458 -11.458,11.458l-408.333,0c-6.324,0 -11.458,-5.134 -11.458,-11.458l0,-187.5c0,-6.324 5.134,-11.458 11.458,-11.458l408.333,0c6.324,0 11.458,5.134 11.458,11.458Z\" style=\"fill:none;stroke:#cfcfcf;stroke-width:2.08px;\"/><path d=\"M100,106.25l0,20.833c0,6.899 -5.601,12.5 -12.5,12.5l-37.5,0c-6.899,0 -12.5,-5.601 -12.5,-12.5l0,-20.833c0,-6.899 5.601,-12.5 12.5,-12.5l37.5,0c6.899,0 12.5,5.601 12.5,12.5Z\" style=\"fill:#cfcfcf;\"/><path d=\"M110.417,106.25l70.833,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M110.417,122.917l45.833,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M397.917,114.583l37.5,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M100,164.583l0,20.833c0,6.899 -5.601,12.5 -12.5,12.5l-37.5,0c-6.899,0 -12.5,-5.601 -12.5,-12.5l0,-20.833c0,-6.899 5.601,-12.5 12.5,-12.5l37.5,0c6.899,0 12.5,5.601 12.5,12.5Z\" style=\"fill:#cfcfcf;\"/><path d=\"M110.417,164.583l70.833,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M110.417,181.25l45.833,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M397.917,172.917l37.5,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M45.833,214.583l391.667,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M397.917,229.167l37.5,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M45.833,247.917l391.667,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M25,318.75c0,-6.904 5.596,-12.5 12.5,-12.5l183.333,0c6.904,0 12.5,5.596 12.5,12.5l0,170.833c0,6.904 -5.596,12.5 -12.5,12.5l-183.333,0c-6.904,0 -12.5,-5.596 -12.5,-12.5l0,-170.833Z\" style=\"fill:#eff9ff;fill-rule:nonzero;\"/><path d=\"M37.5,307.292l183.333,0c6.328,0 11.458,5.13 11.458,11.458l0,175c0,6.329 -5.13,11.458 -11.458,11.458l-183.333,0c-6.328,0 -11.458,-5.129 -11.458,-11.458l0,-175c0,-6.328 5.13,-11.458 11.458,-11.458Z\" style=\"fill:#fff;fill-rule:nonzero;\"/><path d=\"M37.5,307.292l183.333,0c6.328,0 11.458,5.13 11.458,11.458l0,175c0,6.329 -5.13,11.458 -11.458,11.458l-183.333,0c-6.328,0 -11.458,-5.129 -11.458,-11.458l0,-175c0,-6.328 5.13,-11.458 11.458,-11.458Z\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:2.08px;\"/><path d=\"M166.667,335.417c0,4.599 -3.734,8.333 -8.333,8.333l-116.667,0c-4.599,0 -8.333,-3.734 -8.333,-8.333c0,-4.599 3.734,-8.333 8.333,-8.333l116.667,0c4.599,0 8.333,3.734 8.333,8.333Z\" style=\"fill:#cfcfcf;\"/><path d=\"M35.417,366.667l58.333,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M222.917,393.75l0,8.333c0,5.749 -4.668,10.417 -10.417,10.417l-166.667,0c-5.749,0 -10.417,-4.668 -10.417,-10.417l0,-8.333c0,-5.749 4.668,-10.417 10.417,-10.417l166.667,0c5.749,0 10.417,4.668 10.417,10.417Z\" style=\"fill:#fff;\"/><path d=\"M222.917,393.75l0,8.333c0,5.749 -4.668,10.417 -10.417,10.417l-166.667,0c-5.749,0 -10.417,-4.668 -10.417,-10.417l0,-8.333c0,-5.749 4.668,-10.417 10.417,-10.417l166.667,0c5.749,0 10.417,4.668 10.417,10.417Z\" style=\"fill:none;stroke:#cfcfcf;stroke-width:4.17px;\"/><path d=\"M35.417,437.5l58.333,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M122.917,464.583l0,8.333c0,5.749 -4.668,10.417 -10.417,10.417l-66.667,0c-5.749,0 -10.417,-4.668 -10.417,-10.417l0,-8.333c0,-5.749 4.668,-10.417 10.417,-10.417l66.667,0c5.749,0 10.417,4.668 10.417,10.417Z\" style=\"fill:#fff;\"/><path d=\"M122.917,464.583l0,8.333c0,5.749 -4.668,10.417 -10.417,10.417l-66.667,0c-5.749,0 -10.417,-4.668 -10.417,-10.417l0,-8.333c0,-5.749 4.668,-10.417 10.417,-10.417l66.667,0c5.749,0 10.417,4.668 10.417,10.417Z\" style=\"fill:none;stroke:#cfcfcf;stroke-width:4.17px;\"/><path d=\"M135.417,437.5l58.333,0\" style=\"fill:none;fill-rule:nonzero;stroke:#cfcfcf;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M222.917,464.583l0,8.333c0,5.749 -4.668,10.417 -10.417,10.417l-66.667,0c-5.749,0 -10.417,-4.668 -10.417,-10.417l0,-8.333c0,-5.749 4.668,-10.417 10.417,-10.417l66.667,0c5.749,0 10.417,4.668 10.417,10.417Z\" style=\"fill:#fff;\"/><path d=\"M222.917,464.583l0,8.333c0,5.749 -4.668,10.417 -10.417,10.417l-66.667,0c-5.749,0 -10.417,-4.668 -10.417,-10.417l0,-8.333c0,-5.749 4.668,-10.417 10.417,-10.417l66.667,0c5.749,0 10.417,4.668 10.417,10.417Z\" style=\"fill:none;stroke:#cfcfcf;stroke-width:4.17px;\"/><path d=\"M457.292,318.75l0,195.833c0,6.324 -5.134,11.458 -11.458,11.458l-183.333,0c-6.324,0 -11.458,-5.134 -11.458,-11.458l0,-195.833c0,-6.324 5.134,-11.458 11.458,-11.458l183.333,0c6.324,0 11.458,5.134 11.458,11.458Z\" style=\"fill:#fff;\"/><path d=\"M457.292,318.75l0,195.833c0,6.324 -5.134,11.458 -11.458,11.458l-183.333,0c-6.324,0 -11.458,-5.134 -11.458,-11.458l0,-195.833c0,-6.324 5.134,-11.458 11.458,-11.458l183.333,0c6.324,0 11.458,5.134 11.458,11.458Z\" style=\"fill:none;stroke:#cfcfcf;stroke-width:2.08px;\"/><path d=\"M445.833,331.25l0,125c0,4.599 -3.734,8.333 -8.333,8.333l-166.667,0c-4.599,0 -8.333,-3.734 -8.333,-8.333l0,-125c0,-4.599 3.734,-8.333 8.333,-8.333l166.667,0c4.599,0 8.333,3.734 8.333,8.333Z\" style=\"fill:#cfcfcf;\"/><rect x=\"279.167\" y=\"339.583\" width=\"112.5\" height=\"17.006\" style=\"fill:none;\"/><path d=\"M279.167,387.5l154.167,0\" style=\"fill:none;fill-rule:nonzero;stroke:#fff;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M279.167,418.75l62.5,0\" style=\"fill:none;fill-rule:nonzero;stroke:#fff;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M366.667,418.75l66.667,0\" style=\"fill:none;fill-rule:nonzero;stroke:#fff;stroke-width:4.17px;stroke-linecap:round;\"/><path d=\"M437.5,493.75c0,9.199 -7.468,16.667 -16.667,16.667l-133.333,0c-9.199,0 -16.667,-7.468 -16.667,-16.667c0,-9.199 7.468,-16.667 16.667,-16.667l133.333,0c9.199,0 16.667,7.468 16.667,16.667Z\" style=\"fill:#089e09;\"/></g></svg>",
};

/**
 * Human-readable pattern titles (reusing the block pattern titles).
 *
 * Same msgids as Patterns.php's registered pattern titles, so no new translatable
 * strings are introduced. Used as the thumbnail buttons' accessible labels.
 *
 * @since 3.7.0
 * @return {Object} Map of bare slug to translated title.
 */
const patternTitles = () => {
	const i18n = globalThis.wp?.i18n;

	if ( ! i18n ) {
		return {
			'single-column': 'Single Column',
			'two-column-50-50': 'Two Columns: 50/50',
			'two-column-70-30': 'Two Columns: 70/30',
			'two-column-80-20': 'Two Columns: 80/20',
			'cart-top-two-column': 'Cart Top, Two-Column Form',
		};
	}

	return {
		'single-column': i18n.__( 'Single Column', 'easy-digital-downloads' ),
		'two-column-50-50': i18n.__( 'Two Columns: 50/50', 'easy-digital-downloads' ),
		'two-column-70-30': i18n.__( 'Two Columns: 70/30', 'easy-digital-downloads' ),
		'two-column-80-20': i18n.__( 'Two Columns: 80/20', 'easy-digital-downloads' ),
		'cart-top-two-column': i18n.__( 'Cart Top, Two-Column Form', 'easy-digital-downloads' ),
	};
};

/**
 * The layout-picker control view currently rendered in an open box panel.
 *
 * Tracked so a box lifecycle event (a child section added/removed while the panel
 * is open) can re-sync the box's native section switchers in place, keeping each
 * slider's ON/OFF state in step with the section's presence. Set in the view's
 * onRender, cleared in its onDestroy (panel closed / switched to another element).
 *
 * @since 3.7.0
 * @type {?Object}
 */
let activePickerView = null;

/**
 * Read the layout-picker control view currently rendered in an open box panel.
 *
 * The exported read-only accessor for the module-owned `activePickerView` so other
 * modules (the overlay lifecycle handler) can reach the open picker's box without
 * importing (and never reassigning) the raw `let`.
 *
 * @since 3.7.0
 * @return {?Object} The active picker control view, or null.
 */
const getActivePickerView = () => activePickerView;

/**
 * Build (or rebuild) the picker UI inside a control view's panel element.
 *
 * Renders the panel-hosted affordance: a "Layout" heading, the passive "Switching
 * layouts replaces all inner blocks." warning (the block's verbatim edit.js
 * literal), and the five pattern thumbnails. Selecting a thumbnail switches the box
 * to that pattern (switchPattern). The per-section show/hide toggles are NOT built
 * here — they are native Elementor `switcher` controls registered in PHP
 * (edd_section_*) that render themselves in the same panel section; this module
 * wires their settings change to reAddSection/removeSection (see bindSectionToggles
 * / handleSectionToggle) and keeps them in sync with section presence
 * (syncSectionToggles).
 *
 * @since 3.7.0
 * @param {Object} view The picker control view (has a DOM `el` in the panel).
 * @return {void}
 */
const buildLayoutPicker = ( view ) => {
	const rootEl = view?.el;
	if ( ! rootEl ) {
		return;
	}

	const doc = rootEl.ownerDocument ?? globalThis.document;
	const i18n = globalThis.wp?.i18n;
	const titles = patternTitles();

	// Rebuild from scratch each time so absent-section buttons stay accurate.
	rootEl.querySelector( `.${ PICKER_CLASS }` )?.remove();

	const root = doc.createElement( 'div' );
	root.className = PICKER_CLASS;
	root.style.cssText = 'padding:4px 0 2px;font-size:12px;line-height:1.5;color:#3c434a;';

	const heading = doc.createElement( 'p' );
	heading.style.cssText = 'margin:0 0 6px;font-weight:600;';
	heading.textContent = i18n ? i18n.__( 'Layout', 'easy-digital-downloads' ) : 'Layout';
	root.appendChild( heading );

	const warning = doc.createElement( 'p' );
	warning.style.cssText = 'margin:0 0 8px;color:#646970;';
	warning.textContent = i18n
		? i18n.__( 'Switching layout replaces all widget contents.', 'easy-digital-downloads' )
		: 'Switching layout replaces all widget contents.';
	root.appendChild( warning );

	const grid = doc.createElement( 'div' );
	grid.style.cssText = 'display:flex;flex-wrap:wrap;gap:10px;';
	PATTERNS.forEach( ( pattern ) => {
		const button = doc.createElement( 'button' );
		button.type = 'button';
		button.setAttribute( 'aria-label', titles[ pattern.slug ] ?? pattern.slug );
		button.title = titles[ pattern.slug ] ?? pattern.slug;
		button.style.cssText = 'width:96px;height:96px;padding:4px;border:1px solid #c3c4c7;background:#fff;cursor:pointer;border-radius:2px;';
		button.innerHTML = PATTERN_ICONS[ pattern.slug ] ?? '';
		button.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			switchPattern( pickerBox( view ), pattern.slug );
		} );
		grid.appendChild( button );
	} );
	root.appendChild( grid );

	rootEl.appendChild( root );
};

/**
 * Handle a change to one of the box's native section switcher controls.
 *
 * The user-facing half of the native `switcher` wiring: flipping a switcher ON
 * (`yes`) re-seeds its section into the box (reAddSection); flipping it OFF
 * removes it (removeSection). Skips programmatic syncs — a value the module wrote
 * back to keep the switcher in step with actual presence (eddSyncingToggle) — and
 * any change made while a seed / pattern switch is in flight (eddSeeding), so only a
 * genuine user flip drives the add/remove. reAddSection/removeSection are themselves
 * idempotent (they no-op when the section is already present / already absent).
 *
 * @since 3.7.0
 * @param {Object} box        The checkout box container the switcher belongs to.
 * @param {string} widgetType The section widget type the switcher toggles.
 * @param {string} value      The new switcher value (`yes` when ON, else OFF).
 * @return {void}
 */
const handleSectionToggle = ( box, widgetType, value ) => {
	if ( eddSyncingToggle || isSeeding() ) {
		return;
	}

	if ( 'yes' === value ) {
		reAddSection( box, widgetType );
	} else {
		removeSection( box, widgetType );
	}
};

/**
 * The box settings model + change handlers the section switchers are bound to.
 *
 * Tracked so the listeners bound when a box panel opens can be cleanly removed when
 * it closes (or another box's panel opens), preventing duplicate handlers.
 *
 * @since 3.7.0
 * @type {?Object}
 */
let toggleBinding = null;

/**
 * Remove any section-switcher change listeners bound to a previous box.
 *
 * @since 3.7.0
 * @return {void}
 */
const unbindSectionToggles = () => {
	if ( ! toggleBinding ) {
		return;
	}

	toggleBinding.entries.forEach( ( { event, handler } ) => {
		toggleBinding.settings.off( event, handler );
	} );
	toggleBinding = null;
};

/**
 * Bind change listeners on a box's settings model for its section switchers.
 *
 * Listens on the box settings model `change:<control id>` for each native section
 * switcher (SECTION_TOGGLE_CONTROLS) so a user flip runs handleSectionToggle. Any
 * previous binding is removed first so only the open box's switchers are wired.
 *
 * @since 3.7.0
 * @param {Object} box The checkout box container whose switchers to wire.
 * @return {void}
 */
const bindSectionToggles = ( box ) => {
	unbindSectionToggles();

	const settings = box?.settings;
	if ( typeof settings?.on !== 'function' ) {
		return;
	}

	const entries = Object.entries( SECTION_TOGGLE_CONTROLS ).map( ( [ control, widgetType ] ) => {
		const event   = `change:${ control }`;
		const handler = ( model, value ) => handleSectionToggle( box, widgetType, value );
		settings.on( event, handler );
		return { event, handler };
	} );

	toggleBinding = { settings, entries };
};

/**
 * Sync each section switcher's value to whether the box currently holds its section.
 *
 * The presence-driven half of the native `switcher` wiring: reflects the box's real
 * contents onto the switchers so a section added/removed by any non-switcher path
 * (pattern switch, seed, a canvas delete of the cart) flips the matching slider.
 * Writes run under eddSyncingToggle so the change they fire is ignored by
 * handleSectionToggle (the section already matches). Only writes when the stored
 * value actually differs, so no redundant change events fire.
 *
 * @since 3.7.0
 * @param {Object} box The checkout box container to reflect onto its switchers.
 * @return {void}
 */
const syncSectionToggles = ( box ) => {
	const settings = box?.settings;
	if ( typeof settings?.set !== 'function' ) {
		return;
	}

	eddSyncingToggle = true;
	try {
		Object.entries( SECTION_TOGGLE_CONTROLS ).forEach( ( [ control, widgetType ] ) => {
			const value = boxContainsType( box, widgetType ) ? 'yes' : '';
			if ( settings.get( control ) !== value ) {
				settings.set( control, value );
			}
			reflectSwitcherInput( control, value );
		} );
	} finally {
		eddSyncingToggle = false;
	}
};

/**
 * Reflect a switcher's synced value onto its rendered slider input.
 *
 * Elementor's native switcher control view reads its value from the settings model
 * only at render time and on user input — it does NOT re-render on an external
 * (programmatic) model change. So after syncSectionToggles writes the model value,
 * the open panel's slider would still show the stale position. Writing `checked`
 * directly onto the rendered input (whose CSS reflects `:checked`) keeps the visible
 * slider honest without a settings command (no undo-history pollution) and without
 * firing a change event (setting `.checked` in code does not). Only one element's
 * panel is open at a time and syncSectionToggles runs only for that box, so the
 * matched control belongs to this box. A no-op when the panel is closed or the
 * control is not rendered.
 *
 * @since 3.7.0
 * @param {string} control The switcher control id (e.g. `edd_section_cart`).
 * @param {string} value   The synced value (`yes` for ON, else OFF).
 * @return {void}
 */
const reflectSwitcherInput = ( control, value ) => {
	const input = globalThis.document?.querySelector?.(
		`.elementor-control-${ control } input.elementor-switch-input`
	);
	if ( input ) {
		input.checked = 'yes' === value;
	}
};

/**
 * Register the panel-hosted layout-picker control view.
 *
 * Registers a custom Elementor control view (via elementor.addControlView) against
 * the `edd-layout-picker` type registered in PHP (LayoutPicker) and added to the
 * box's dedicated "Checkout Layout" section in CheckoutBox::register_controls(). The
 * control is data-less: its view renders the picker UI (buildLayoutPicker) into the
 * panel on render. The native Container layout controls stay visible at their
 * defaults — the picker is an additional affordance, not a replacement. Guarded
 * (feature-detects addControlView + the control base view) and run once per editor
 * session.
 *
 * @since 3.7.0
 * @return {boolean} True when the control view is registered (or already was).
 */
const registerLayoutPickerControl = () => {
	const elementor = globalThis.elementor;

	if ( typeof elementor?.addControlView !== 'function' ) {
		return false;
	}

	if ( globalThis.__eddLayoutPickerControlDone ) {
		return true;
	}

	const BaseControlView = elementor.modules?.controls?.Base;
	if ( ! BaseControlView ) {
		return false;
	}

	class LayoutPickerControlView extends BaseControlView {
		// Data-less UI control: no server template — onRender builds the whole UI.
		getTemplate() {
			return () => '';
		}

		onRender() {
			activePickerView = this;
			buildLayoutPicker( this );

			// Wire the box's native section switchers: bind their settings-change to
			// the add/remove handler, then sync each slider to the section's current
			// presence so an open panel opens on an accurate state.
			const box = pickerBox( this );
			bindSectionToggles( box );
			syncSectionToggles( box );
		}

		onDestroy() {
			if ( activePickerView === this ) {
				activePickerView = null;
				unbindSectionToggles();
			}
		}
	}

	elementor.addControlView( PICKER_CONTROL_TYPE, LayoutPickerControlView );
	globalThis.__eddLayoutPickerControlDone = true;

	return true;
};

export {
	registerLayoutPickerControl,
	getActivePickerView,
	syncSectionToggles,
};
