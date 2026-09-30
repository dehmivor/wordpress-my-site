<?php
/**
 * HTML elements
 *
 * A helper class for outputting common HTML elements, such as product drop downs
 *
 * @package     EDD\HTML
 * @copyright   Copyright (c) 2018, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.5
 */

namespace EDD\HTML;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Elements Class
 *
 * @since 1.5
 */
class Elements {

	/**
	 * Renders an HTML Dropdown of all the Products (Downloads)
	 *
	 * @since 1.5
	 * @since 3.2.8 Updated to use the ProductSelect class.
	 * @param array $args Arguments for the dropdown.
	 *
	 * @return string $output Product dropdown
	 */
	public function product_dropdown( $args = array() ) {
		$select = new ProductSelect( $args );

		return $select->get();
	}

	/**
	 * Get EDD products for the product dropdown.
	 *
	 * @since 3.7.1 Results are held to the same per-caller readability as the AJAX
	 *                       search that refines this same dropdown.
	 *
	 * @param array $args     Parameters for the get_posts function.
	 * @return array WP_Post[] Array of download objects.
	 */
	public function get_products( $args = array() ) {
		$defaults = array(
			'number'  => 30,
			'bundles' => true,
		);

		$args           = wp_parse_args( $args, $defaults );
		$args['number'] = (int) $args['number'];

		// get_posts()'s own convention: only -1 means "every matching product".
		if ( -1 !== $args['number'] && $args['number'] < 0 ) {
			$args['number'] = abs( $args['number'] );
		}

		$product_args = $this->get_product_args( $args );

		// One query answers the request when it is unlimited, when a callback changed the count
		// from what the caller asked for, or when the caller can read every product and bundles
		// are included, because then nothing can be removed after the query.
		if (
			-1 === $args['number']
			|| (int) ( $product_args['posts_per_page'] ?? 0 ) !== $args['number']
			|| ( \EDD\Downloads\Search::can_read_all_products() && ! empty( $args['bundles'] ) )
		) {
			return $this->filter_products( get_posts( $product_args ), $args );
		}

		return $this->get_paged_products( $product_args, $args );
	}

	/**
	 * Builds the query arguments for the product dropdown.
	 *
	 * @since 3.7.1
	 *
	 * @param array $args The get_products() arguments.
	 * @return array
	 */
	private function get_product_args( $args ) {
		$product_args = array(
			'post_type'      => 'download',
			// A string keeps a later 'order' override effective; title alone also isn't
			// unique, so ID is a second column rather than a second array key.
			'orderby'        => 'title ID',
			'order'          => 'ASC',
			'posts_per_page' => $args['number'],
			'offset'         => 0,
		);

		if ( ! current_user_can( 'edit_products' ) ) {
			$product_args['post_status'] = apply_filters( 'edd_product_dropdown_status_nopriv', array( 'publish' ) );
		} else {
			$product_args['post_status'] = apply_filters(
				'edd_product_dropdown_status',
				array(
					'publish',
					'draft',
					'private',
					'future',
				)
			);
		}

		if ( is_array( $product_args['post_status'] ) ) {

			// Given the array, sanitize them.
			$product_args['post_status'] = array_map( 'sanitize_text_field', $product_args['post_status'] );
		} else {

			// If we didn't get an array, fallback to 'publish'.
			$product_args['post_status'] = array( 'publish' );
		}

		// Applied once, before pagination begins.
		return apply_filters( 'edd_product_dropdown_args', $product_args );
	}

	/**
	 * Queries products a page at a time until the requested number survives filtering.
	 *
	 * @since 3.7.1
	 *
	 * @param array $product_args The query arguments.
	 * @param array $args         The get_products() arguments.
	 * @return \WP_Post[]
	 */
	private function get_paged_products( $product_args, $args ) {

		// Batched in a size the filter never saw: readability (and bundle exclusion) apply after
		// the query, and could otherwise leave a single page short of what was asked for.
		$batch_size = max( $args['number'] * 2, 30 );
		$offset     = absint( $product_args['offset'] );
		$products   = array();
		$batches    = 0;

		/**
		 * How many batches the dropdown will read before it answers with what it has.
		 *
		 * A batch which filters down to nothing is indistinguishable from one crowded with
		 * products this caller may not see, so paging cannot stop on an empty result: it stops
		 * on a bound instead. Without one, a callback which changes the row shape enough that
		 * nothing survives filtering walks the whole catalog on every render.
		 *
		 * @since 3.7.1
		 *
		 * @param int   $limit The maximum number of batches to read.
		 * @param array $args  The get_products() arguments.
		 */
		$batch_limit = (int) apply_filters( 'edd/product_dropdown/batch_limit', 5, $args );
		$batch_limit = max( 1, $batch_limit );

		do {
			$batch = get_posts(
				array_merge(
					$product_args,
					array(
						'posts_per_page'         => $batch_size,
						'offset'                 => $offset,
						// Nothing here reads pagination totals or terms.
						'no_found_rows'          => true,
						'update_post_term_cache' => false,
					)
				)
			);

			$products = array_merge( $products, $this->filter_products( $batch, $args ) );
			$offset  += $batch_size;
			++$batches;
		} while (
			count( $products ) < $args['number']
			&& count( $batch ) === $batch_size
			&& $batches < $batch_limit
		);

		return array_slice( $products, 0, $args['number'] );
	}

	/**
	 * Applies readability and bundle exclusion to a batch of products.
	 *
	 * @since 3.7.1
	 *
	 * @param \WP_Post[] $products The products to filter.
	 * @param array      $args     The get_products() arguments.
	 * @return \WP_Post[]
	 */
	private function filter_products( $products, $args ) {
		$products = \EDD\Downloads\Search::filter_by_readability( $products );

		if ( empty( $args['bundles'] ) ) {
			$products = array_values(
				array_filter(
					$products,
					function ( $product ) {
						return 'bundle' !== edd_get_download_type( $product->ID );
					}
				)
			);
		}

		return $products;
	}

	/**
	 * Renders an HTML Dropdown of all customers
	 *
	 * @since 2.2
	 *
	 * @param array $args Arguments for the dropdown.
	 *
	 * @return string $output Customer dropdown
	 */
	public function customer_dropdown( $args = array() ) {
		$defaults = array(
			'name'          => 'customers',
			'id'            => 'customers',
			'class'         => '',
			'multiple'      => false,
			'selected'      => 0,
			'chosen'        => true,
			'placeholder'   => __( 'Choose a Customer', 'easy-digital-downloads' ),
			'number'        => 30,
			'data'          => array(
				'search-type'        => 'customer',
				'search-placeholder' => __( 'Search Customers', 'easy-digital-downloads' ),
			),
			'none_selected' => __( 'No customer attached', 'easy-digital-downloads' ),
			'required'      => false,
		);

		$args = wp_parse_args( $args, $defaults );

		$customers = edd_get_customers(
			array(
				'number' => $args['number'],
			)
		);

		$options = array();

		if ( $customers ) {
			$options[0] = $args['none_selected'];
			foreach ( $customers as $customer ) {
				$options[ absint( $customer->id ) ] = esc_html( $customer->name . ' (' . $customer->email . ')' );
			}
		} else {
			$options[0] = __( 'No customers found', 'easy-digital-downloads' );
		}

		// If a selected customer has been specified, we need to ensure it's in the initial list of customers displayed.
		if ( ! empty( $args['selected'] ) && ! array_key_exists( $args['selected'], $options ) ) {
			$customer = edd_get_customer( $args['selected'] );

			if ( $customer ) {
				$options[ absint( $args['selected'] ) ] = esc_html( $customer->name . ' (' . $customer->email . ')' );
			}
		}

		return $this->select(
			array(
				'name'             => $args['name'],
				'selected'         => $args['selected'],
				'id'               => $args['id'],
				'class'            => $args['class'] . ' edd-customer-select',
				'options'          => $options,
				'multiple'         => $args['multiple'],
				'placeholder'      => $args['placeholder'],
				'chosen'           => $args['chosen'],
				'show_option_all'  => false,
				'show_option_none' => false,
				'data'             => $args['data'],
				'required'         => $args['required'],
			)
		);
	}

	/**
	 * Renders an HTML Dropdown of all the Users
	 *
	 * @since 2.6.9
	 * @since 3.6.7 This is now just a wrapper for UserSelect.
	 * @param array $args Arguments for the dropdown.
	 * @return string $output User dropdown
	 */
	public function user_dropdown( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'name'        => 'users',
				'id'          => 'users',
				'chosen'      => true,
				'placeholder' => __( 'Select a User', 'easy-digital-downloads' ),
				'multiple'    => false,
				'selected'    => 0,
				'number'      => 30,
			)
		);

		$user_args = array(
			'number' => $args['number'],
		);

		$users = get_users( $user_args );

		$options = array();

		foreach ( $users as $user ) {
			$options[ $user->ID ] = esc_html( $user->display_name );
		}

		$args['options'] = $options;

		return ( new UserSelect( $args ) )->get();
	}

	/**
	 * Renders an HTML Dropdown of all the Discounts
	 *
	 * @since 1.5.2
	 * @since 3.0 Allow $args to be passed.
	 *
	 * @param string $args     The arguments for the dropdown.
	 * @param int    $selected Discount to select automatically (deprecated).
	 * @param string $status   Discount post_status to retrieve (deprecated).
	 *
	 * @return string $output Discount dropdown
	 */
	public function discount_dropdown( $args = array(), $selected = 0, $status = '' ) {
		if ( ! is_array( $args ) ) {
			$args = array(
				'name'     => $args,
				'selected' => $selected,
				'status'   => $status,
			);
		}

		$discount_select = new DiscountSelect( $args );

		return $discount_select->get();
	}

	/**
	 * Renders an HTML Dropdown of all the Categories
	 *
	 * @since 1.5.2
	 *
	 * @param string $name     Name attribute of the dropdown.
	 * @param int    $selected Category to select automatically.
	 *
	 * @return string $output Category dropdown
	 */
	public function category_dropdown( $name = 'edd_categories', $selected = 0 ) {
		$categories = get_terms( 'download_category', apply_filters( 'edd_category_dropdown', array() ) );
		$options    = array();

		foreach ( $categories as $category ) {
			$options[ absint( $category->term_id ) ] = esc_html( $category->name );
		}

		$category_labels = edd_get_taxonomy_labels( 'download_category' );

		return $this->select(
			array(
				'name'             => $name,
				'selected'         => $selected,
				'options'          => $options,
				'show_option_all'  => sprintf(
					/* translators: %s: Download Category taxonomy name */
					_x( 'All %s', 'plural: Example: "All Categories"', 'easy-digital-downloads' ),
					$category_labels['name']
				),
				'show_option_none' => false,
			)
		);
	}

	/**
	 * Renders an HTML Dropdown of years
	 *
	 * @since 1.5.2
	 *
	 * @param string $name         Name attribute of the dropdown.
	 * @param int    $selected     Year to select automatically.
	 * @param int    $years_before Number of years before the current year the dropdown should start with.
	 * @param int    $years_after  Number of years after the current year the dropdown should finish at.
	 * @param string $id           A unique identifier for the field.
	 * @return string $output Year dropdown
	 */
	public function year_dropdown( $name = 'year', $selected = 0, $years_before = 5, $years_after = 0, $id = 'edd_year_select' ) {
		$current    = date( 'Y' );
		$start_year = $current - absint( $years_before );
		$end_year   = $current + absint( $years_after );
		$selected   = empty( $selected ) ? date( 'Y' ) : $selected;
		$options    = array();

		while ( $start_year <= $end_year ) {
			$options[ absint( $start_year ) ] = $start_year;
			++$start_year;
		}

		return $this->select(
			array(
				'name'             => $name,
				'id'               => $id . '_' . $name,
				'selected'         => $selected,
				'options'          => $options,
				'show_option_all'  => false,
				'show_option_none' => false,
			)
		);
	}

	/**
	 * Renders an HTML Dropdown of months
	 *
	 * @since 1.5.2
	 *
	 * @param string  $name             Name attribute of the dropdown.
	 * @param int     $selected         Month to select automatically.
	 * @param string  $id               A unique identifier for the field.
	 * @param boolean $return_long_name Whether to use the long name for the month.
	 *
	 * @return string $output Month dropdown
	 */
	public function month_dropdown( $name = 'month', $selected = 0, $id = 'edd_month_select', $return_long_name = false ) {
		$month    = 1;
		$options  = array();
		$selected = empty( $selected ) ? date( 'n' ) : $selected;

		while ( $month <= 12 ) {
			$options[ absint( $month ) ] = edd_month_num_to_name( $month, $return_long_name );
			++$month;
		}

		return $this->select(
			array(
				'name'             => $name,
				'id'               => $id . '_' . $name,
				'selected'         => $selected,
				'options'          => $options,
				'show_option_all'  => false,
				'show_option_none' => false,
			)
		);
	}

	/**
	 * Gets the countries dropdown.
	 *
	 * @since  3.0
	 * @param  array  $args    The array of parameters passed to the method.
	 * @param  string $country The selected country.
	 * @return string
	 */
	public function country_select( $args = array(), $country = '' ) {
		if ( ! empty( $country ) ) {
			$args['selected'] = $country;
		}

		$country_select = new CountrySelect( $args );

		return $country_select->get();
	}

	/**
	 * Gets the regions dropdown.
	 *
	 * @since  3.0
	 * @param  array  $args     The array of parameters passed to the method.
	 * @param  string $country  The country from which to populate the regions.
	 * @param  string $region   The selected region.
	 * @return string
	 */
	public function region_select( $args = array(), $country = '', $region = '' ) {
		$args['country']  = $country;
		$args['selected'] = $region;
		$region_select    = new RegionSelect( $args );

		return $region_select->get();
	}

	/**
	 * Renders an HTML Dropdown
	 *
	 * @since 1.6
	 * @since 3.2.8 Updated to use the Select class.
	 * @param array $args The arguments for the dropdown.
	 * @return string
	 */
	public function select( $args = array() ) {
		$select = new Select( $args );

		return $select->get();
	}

	/**
	 * Renders an HTML Checkbox
	 *
	 * @since 1.9
	 * @since 3.0 Added `label` argument.
	 * @since 3.2.8 Updated to use the Checkbox class.
	 * @param array $args Arguments for the checkbox.
	 * @return string Checkbox HTML code
	 */
	public function checkbox( $args = array() ) {
		$checkbox = new Checkbox( $args );

		return $checkbox->get();
	}

	/**
	 * Renders an HTML Text field
	 *
	 * @since 1.5.2
	 * @since 3.2.8 Updated to use the Text class.
	 * @param array $args Arguments for the text field.
	 * @return string Text field
	 */
	public function text( $args = array() ) {
		if ( func_num_args() > 1 ) {
			$legacy_args = func_get_args();
			$args        = array(
				'name'  => $legacy_args[0],
				'value' => isset( $legacy_args[1] ) ? $legacy_args[1] : '',
				'label' => isset( $legacy_args[2] ) ? $legacy_args[2] : '',
				'desc'  => isset( $legacy_args[3] ) ? $legacy_args[3] : '',
			);
		}

		$text = new Text( $args );

		return $text->get();
	}

	/**
	 * Renders a date picker
	 *
	 * @since 2.4
	 *
	 * @param array $args Arguments for the text field.
	 *
	 * @return string Datepicker field
	 */
	public function date_field( $args = array() ) {

		if ( empty( $args['class'] ) ) {
			$args['class']          = 'edd_datepicker';
			$args['data']['format'] = edd_get_date_picker_format();

		} elseif ( ! strpos( $args['class'], 'edd_datepicker' ) ) {
			$args['class']         .= ' edd_datepicker';
			$args['data']['format'] = edd_get_date_picker_format();
		}

		return $this->text( $args );
	}

	/**
	 * Renders an HTML textarea
	 *
	 * @since 1.9
	 * @since 3.2.8 Updated to use the Textarea class.
	 * @param array $args Arguments for the textarea.
	 * @return string textarea
	 */
	public function textarea( $args = array() ) {
		$textarea = new Textarea( $args );

		return $textarea->get();
	}

	/**
	 * Renders an ajax user search field
	 *
	 * @since 2.0
	 * @since 3.6.7 This is now just a wrapper for UserSelect.
	 * @param array $args Arguments for the field.
	 * @return string User select HTML.
	 */
	public function ajax_user_search( $args = array() ) {
		if ( isset( $args['value'] ) ) {
			$args['selected'] = $args['value'];
			unset( $args['value'] );
		}

		return ( new UserSelect( $args ) )->get();
	}

	/**
	 * Show a required indicator on a field.
	 *
	 * @return string
	 */
	public function show_required() {

		$output  = '<span class="edd-required-indicator" aria-hidden="true">*</span>';
		$output .= sprintf( '<span class="screen-reader-text">%s</span>', __( 'Required', 'easy-digital-downloads' ) );

		return $output;
	}
}
