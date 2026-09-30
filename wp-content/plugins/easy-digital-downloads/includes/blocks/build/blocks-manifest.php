<?php
// This file is generated. Do not modify it manually.
return array(
	'buy-button' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'edd/buy-button',
		'version' => '2.0.0',
		'title' => 'EDD Buy Button',
		'category' => 'easy-digital-downloads',
		'icon' => 'button',
		'description' => 'Quickly add a "buy now" button for any EDD product.',
		'keywords' => array(
			'easy digital downloads',
			'edd',
			'button'
		),
		'supports' => array(
			'html' => false,
			'align' => array(
				'center',
				'left',
				'right',
				'wide'
			)
		),
		'textdomain' => 'easy-digital-downloads',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'attributes' => array(
			'download_id' => array(
				'type' => 'string'
			),
			'width' => array(
				'type' => 'string'
			),
			'show_price' => array(
				'type' => 'boolean',
				'default' => true
			),
			'direct' => array(
				'type' => 'boolean',
				'default' => false
			),
			'discount' => array(
				'type' => 'string',
				'default' => ''
			)
		),
		'example' => array(
			'attributes' => array(
				
			)
		)
	),
	'cart' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'edd/cart',
		'version' => '2.0.0',
		'title' => 'EDD Cart',
		'category' => 'easy-digital-downloads',
		'icon' => 'cart',
		'description' => 'Display a mini or full shopping cart outside of checkout for Easy Digital Downloads.',
		'keywords' => array(
			'easy digital downloads',
			'edd',
			'cart'
		),
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'easy-digital-downloads',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'attributes' => array(
			'hide_on_checkout' => array(
				'type' => 'boolean',
				'default' => true
			),
			'mini' => array(
				'type' => 'boolean',
				'default' => true
			),
			'link' => array(
				'type' => 'boolean',
				'default' => true
			),
			'show_quantity' => array(
				'type' => 'boolean',
				'default' => true
			),
			'show_total' => array(
				'type' => 'boolean',
				'default' => true
			),
			'hide_empty' => array(
				'type' => 'boolean',
				'default' => false
			),
			'title' => array(
				'type' => 'string'
			)
		),
		'example' => array(
			'attributes' => array(
				
			)
		)
	),
	'checkout' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'edd/checkout',
		'version' => '2.0.0',
		'title' => 'EDD Checkout',
		'category' => 'easy-digital-downloads',
		'icon' => 'products',
		'description' => 'Full checkout block for Easy Digital Downloads.',
		'keywords' => array(
			'easy digital downloads',
			'edd',
			'checkout'
		),
		'supports' => array(
			'html' => false,
			'innerBlocks' => true
		),
		'textdomain' => 'easy-digital-downloads',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'example' => array(
			'attributes' => array(
				
			)
		)
	),
	'checkout-cart' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'edd/checkout-cart',
		'version' => '1.0.0',
		'title' => 'EDD Checkout Cart',
		'category' => 'easy-digital-downloads',
		'icon' => 'cart',
		'description' => 'Shopping cart for checkout with items, totals, and fees.',
		'keywords' => array(
			'easy digital downloads',
			'edd',
			'cart',
			'checkout'
		),
		'ancestor' => array(
			'edd/checkout'
		),
		'usesContext' => array(
			'edd/previewMode',
			'edd/previewDownload'
		),
		'supports' => array(
			'html' => false,
			'reusable' => false
		),
		'textdomain' => 'easy-digital-downloads',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'attributes' => array(
			'show_header' => array(
				'type' => 'boolean',
				'default' => true
			),
			'show_thumbnails' => array(
				'type' => 'boolean',
				'default' => true
			),
			'thumbnail_width' => array(
				'type' => 'number',
				'default' => 25
			),
			'show_quantity_controls' => array(
				'type' => 'boolean',
				'default' => true
			),
			'show_discount_form' => array(
				'type' => 'boolean',
				'default' => true
			)
		)
	),
	'checkout-discount-form' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'edd/checkout-discount-form',
		'version' => '1.0.0',
		'title' => 'EDD Discount Form',
		'category' => 'easy-digital-downloads',
		'icon' => 'tag',
		'description' => 'Discount code form for checkout.',
		'keywords' => array(
			'easy digital downloads',
			'edd',
			'discount',
			'coupon'
		),
		'ancestor' => array(
			'edd/checkout'
		),
		'supports' => array(
			'html' => false,
			'reusable' => false
		),
		'textdomain' => 'easy-digital-downloads',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css'
	),
	'checkout-payment-info' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'edd/checkout-payment-info',
		'version' => '1.0.0',
		'title' => 'EDD Payment Info',
		'category' => 'easy-digital-downloads',
		'icon' => 'money-alt',
		'description' => 'Payment gateway selection and credit card form for checkout.',
		'keywords' => array(
			'easy digital downloads',
			'edd',
			'payment',
			'gateway',
			'credit card'
		),
		'ancestor' => array(
			'edd/checkout'
		),
		'usesContext' => array(
			'edd/previewMode',
			'edd/previewDownload'
		),
		'supports' => array(
			'html' => false,
			'reusable' => false
		),
		'textdomain' => 'easy-digital-downloads',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css'
	),
	'checkout-personal-info' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'edd/checkout-personal-info',
		'version' => '1.0.0',
		'title' => 'EDD Personal Info & Billing',
		'category' => 'easy-digital-downloads',
		'icon' => 'admin-users',
		'description' => 'Personal information, login forms, and billing address for checkout.',
		'keywords' => array(
			'easy digital downloads',
			'edd',
			'login',
			'register',
			'personal',
			'billing',
			'address'
		),
		'ancestor' => array(
			'edd/checkout'
		),
		'usesContext' => array(
			'edd/previewMode',
			'edd/previewDownload'
		),
		'supports' => array(
			'html' => false,
			'reusable' => false
		),
		'textdomain' => 'easy-digital-downloads',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'attributes' => array(
			'name_single_line' => array(
				'type' => 'boolean',
				'default' => false
			)
		)
	),
	'confirmation' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'edd/confirmation',
		'version' => '2.0.0',
		'title' => 'EDD Confirmation',
		'category' => 'easy-digital-downloads',
		'icon' => 'yes-alt',
		'description' => 'A brief confirmation screen to show to customers immediately after a successful purchase.',
		'keywords' => array(
			'easy digital downloads',
			'edd',
			'orders'
		),
		'supports' => array(
			'html' => false,
			'innerBlocks' => true
		),
		'textdomain' => 'easy-digital-downloads',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'attributes' => array(
			'payment_key' => array(
				'type' => 'boolean',
				'default' => false
			),
			'payment_method' => array(
				'type' => 'boolean',
				'default' => true
			)
		),
		'example' => array(
			'attributes' => array(
				
			)
		),
		'allowedBlocks' => array(
			'core/paragraph',
			'core/heading'
		)
	),
	'downloads' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'edd/downloads',
		'version' => '2.0.0',
		'title' => 'EDD Products',
		'category' => 'easy-digital-downloads',
		'icon' => 'download',
		'description' => 'A block to show your Easy Digital Download products based on visual customizations and query parameters.',
		'keywords' => array(
			'easy digital downloads',
			'edd',
			'downloads',
			'featured downloads',
			'featured products'
		),
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide',
				'full'
			)
		),
		'textdomain' => 'easy-digital-downloads',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'attributes' => array(
			'image_location' => array(
				'type' => 'string',
				'default' => 'before_entry_title'
			),
			'image_size' => array(
				'type' => 'string',
				'default' => 'large'
			),
			'image_alignment' => array(
				'type' => 'string',
				'default' => 'center'
			),
			'content' => array(
				'type' => 'string',
				'default' => 'excerpt'
			),
			'columns' => array(
				'type' => 'number',
				'default' => 3
			),
			'number' => array(
				'type' => 'number',
				'default' => 6
			),
			'align' => array(
				'type' => 'string',
				'default' => ''
			),
			'order' => array(
				'type' => 'string',
				'default' => 'DESC'
			),
			'orderby' => array(
				'type' => 'string',
				'default' => 'date'
			),
			'title' => array(
				'type' => 'boolean',
				'default' => true
			),
			'purchase_link' => array(
				'type' => 'boolean',
				'default' => true
			),
			'price' => array(
				'type' => 'boolean',
				'default' => true
			),
			'category' => array(
				'type' => 'array',
				'default' => array(
					''
				)
			),
			'tag' => array(
				'type' => 'array',
				'default' => array(
					''
				)
			),
			'pagination' => array(
				'type' => 'boolean',
				'default' => true
			),
			'image_link' => array(
				'type' => 'boolean',
				'default' => true
			),
			'purchase_link_align' => array(
				'type' => 'string',
				'default' => 'none'
			),
			'show_price' => array(
				'type' => 'boolean',
				'default' => true
			),
			'all_access' => array(
				'type' => 'boolean',
				'default' => false
			),
			'author' => array(
				'type' => 'string',
				'default' => ''
			),
			'featured' => array(
				'type' => 'string',
				'default' => ''
			),
			'featured_styling_enabled' => array(
				'type' => 'boolean',
				'default' => false
			),
			'featured_badge_enabled' => array(
				'type' => 'boolean',
				'default' => false
			),
			'featured_border_enabled' => array(
				'type' => 'boolean',
				'default' => false
			),
			'featured_badge_text' => array(
				'type' => 'string',
				'default' => 'Featured'
			),
			'featuredBadgeColor' => array(
				'type' => 'string'
			),
			'customFeaturedBadgeColor' => array(
				'type' => 'string',
				'default' => ''
			),
			'featuredBadgeBackgroundColor' => array(
				'type' => 'string'
			),
			'customFeaturedBadgeBackgroundColor' => array(
				'type' => 'string',
				'default' => ''
			),
			'featured_border_color' => array(
				'type' => 'string',
				'default' => ''
			),
			'featured_border_width' => array(
				'type' => 'string',
				'default' => '2px'
			),
			'featured_border_style' => array(
				'type' => 'string',
				'default' => 'solid'
			),
			'featured_border_radius' => array(
				'type' => 'string',
				'default' => '3px'
			)
		),
		'example' => array(
			'attributes' => array(
				'columns' => 1,
				'number' => 2,
				'pagination' => false
			)
		)
	),
	'login' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'edd/login',
		'version' => '2.0.0',
		'title' => 'EDD Login Form',
		'category' => 'easy-digital-downloads',
		'icon' => 'unlock',
		'description' => 'Login form for Easy Digital Downloads.',
		'keywords' => array(
			'easy digital downloads',
			'edd',
			'login'
		),
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'easy-digital-downloads',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'attributes' => array(
			'current' => array(
				'type' => 'boolean',
				'default' => false
			),
			'redirect' => array(
				'type' => 'string',
				'default' => ''
			)
		),
		'example' => array(
			'attributes' => array(
				
			)
		)
	),
	'order-history' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'edd/order-history',
		'version' => '2.0.0',
		'title' => 'EDD Order History',
		'category' => 'easy-digital-downloads',
		'icon' => 'editor-table',
		'description' => 'Display the Easy Digital Downloads order history of a logged in user.',
		'keywords' => array(
			'easy digital downloads',
			'edd',
			'orders'
		),
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'easy-digital-downloads',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'attributes' => array(
			'columns' => array(
				'type' => 'number',
				'default' => 2
			),
			'number' => array(
				'type' => 'number',
				'default' => 20
			),
			'recurring' => array(
				'type' => 'boolean',
				'default' => false
			)
		),
		'example' => array(
			'attributes' => array(
				'columns' => 1,
				'number' => 2
			)
		)
	),
	'profile-editor' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'edd/profile-editor',
		'version' => '2.0.0',
		'title' => 'EDD Profile Editor',
		'category' => 'easy-digital-downloads',
		'icon' => 'id-alt',
		'description' => 'Profile editor form for Easy Digital Downloads.',
		'keywords' => array(
			'easy digital downloads',
			'edd',
			'profile',
			'account'
		),
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'easy-digital-downloads',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'attributes' => array(
			'show_address_line1' => array(
				'type' => 'boolean',
				'default' => true
			),
			'show_address_line2' => array(
				'type' => 'boolean',
				'default' => true
			),
			'show_address_city' => array(
				'type' => 'boolean',
				'default' => true
			),
			'show_address_postal_code' => array(
				'type' => 'boolean',
				'default' => true
			),
			'show_address_country' => array(
				'type' => 'boolean',
				'default' => true
			),
			'show_address_state' => array(
				'type' => 'boolean',
				'default' => true
			),
			'show_company' => array(
				'type' => 'boolean',
				'default' => false
			),
			'show_phone' => array(
				'type' => 'boolean',
				'default' => false
			),
			'field_order' => array(
				'type' => 'array',
				'default' => array(
					'country',
					'line1',
					'line2',
					'city',
					'postal_code',
					'state',
					'company',
					'phone'
				)
			)
		),
		'example' => array(
			'attributes' => array(
				
			)
		)
	),
	'receipt' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'edd/receipt',
		'version' => '2.0.0',
		'title' => 'EDD Receipt',
		'category' => 'easy-digital-downloads',
		'icon' => 'money',
		'description' => 'Show customers their detailed receipt. Supports guest orders.',
		'keywords' => array(
			'easy digital downloads',
			'edd',
			'orders'
		),
		'supports' => array(
			'html' => false,
			'innerBlocks' => true
		),
		'textdomain' => 'easy-digital-downloads',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'attributes' => array(
			'payment_key' => array(
				'type' => 'boolean',
				'default' => false
			),
			'payment_method' => array(
				'type' => 'boolean',
				'default' => true
			)
		),
		'example' => array(
			'attributes' => array(
				
			)
		),
		'allowedBlocks' => array(
			'core/paragraph',
			'core/heading'
		)
	),
	'register' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'edd/register',
		'version' => '2.0.0',
		'title' => 'EDD Registration Form',
		'category' => 'easy-digital-downloads',
		'icon' => 'id',
		'description' => 'Registration form for Easy Digital Downloads.',
		'keywords' => array(
			'easy digital downloads',
			'edd',
			'registration'
		),
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'easy-digital-downloads',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'attributes' => array(
			'current' => array(
				'type' => 'boolean',
				'default' => true
			),
			'redirect' => array(
				'type' => 'string',
				'default' => ''
			),
			'username' => array(
				'type' => 'boolean',
				'default' => true
			)
		),
		'example' => array(
			'attributes' => array(
				
			)
		)
	),
	'terms' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'edd/terms',
		'version' => '2.0.0',
		'title' => 'EDD Download Terms',
		'category' => 'easy-digital-downloads',
		'icon' => 'category',
		'description' => 'Show categories, or tags for Easy Digital Download products.',
		'keywords' => array(
			'easy digital downloads',
			'edd',
			'downloads'
		),
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide',
				'full'
			)
		),
		'textdomain' => 'easy-digital-downloads',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'attributes' => array(
			'taxonomy' => array(
				'type' => 'string',
				'default' => 'download_category'
			),
			'thumbnails' => array(
				'type' => 'boolean',
				'default' => true
			),
			'description' => array(
				'type' => 'boolean',
				'default' => true
			),
			'columns' => array(
				'type' => 'number',
				'default' => 3
			),
			'align' => array(
				'type' => 'string',
				'default' => ''
			),
			'order' => array(
				'type' => 'string',
				'default' => 'DESC'
			),
			'orderby' => array(
				'type' => 'string',
				'default' => 'count'
			),
			'title' => array(
				'type' => 'boolean',
				'default' => true
			),
			'count' => array(
				'type' => 'boolean',
				'default' => true
			),
			'show_empty' => array(
				'type' => 'boolean',
				'default' => false
			),
			'image_size' => array(
				'type' => 'string',
				'default' => 'large'
			),
			'image_alignment' => array(
				'type' => 'string',
				'default' => 'center'
			)
		),
		'example' => array(
			'attributes' => array(
				'columns' => 1
			)
		)
	),
	'user-downloads' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'edd/user-downloads',
		'version' => '2.0.0',
		'title' => 'EDD User Downloads',
		'category' => 'easy-digital-downloads',
		'icon' => 'admin-links',
		'description' => 'Allows a user to access the Easy Digital Downloads products they have purchased.',
		'keywords' => array(
			'easy digital downloads',
			'edd',
			'orders'
		),
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'easy-digital-downloads',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'attributes' => array(
			'search' => array(
				'type' => 'boolean',
				'default' => false
			),
			'variations' => array(
				'type' => 'boolean',
				'default' => true
			),
			'nofiles' => array(
				'type' => 'string',
				'default' => 'No downloadable files found.'
			),
			'hide_empty' => array(
				'type' => 'boolean',
				'default' => true
			)
		)
	),
	'currency-selector' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'edd/currency-selector',
		'version' => '2.0.0',
		'title' => 'EDD Currency Selector',
		'category' => 'easy-digital-downloads',
		'icon' => 'money',
		'description' => 'Allows your customers to switch the store\'s currency.',
		'keywords' => array(
			'easy digital downloads',
			'edd',
			'currency'
		),
		'supports' => array(
			'html' => false,
			'align' => array(
				'center',
				'left',
				'right'
			)
		),
		'textdomain' => 'easy-digital-downloads',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'attributes' => array(
			'widget_type' => array(
				'type' => 'string',
				'default' => 'buttons'
			)
		)
	)
);
