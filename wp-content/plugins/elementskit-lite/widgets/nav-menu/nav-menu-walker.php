<?php

namespace ElementsKit_Lite;

class ElementsKit_Seconday_Menu_Walker extends \Walker_Nav_Menu
{
	/**
	 * ID of the item most recently opened by start_el().
	 *
	 * The walker visits a parent item before descending into its children, so
	 * this holds the parent's ID by the time start_lvl() runs for that submenu.
	 * It lets the panel carry an id that the parent link can point aria-controls
	 * at, without changing any markup that already existed.
	 *
	 * @var int
	 */
	private $ekit_parent_item_id = 0;

	// Start Level
	public function start_lvl(&$output, $depth = 0, $args = null)
	{
		$indent = str_repeat("\t", $depth);
		$classes = ['elementskit-dropdown elementskit-submenu-panel'];
		$class_names = join(' ', apply_filters('nav_menu_submenu_css_class', $classes, $args, $depth));
		$class_names = $class_names ? ' class="' . esc_attr($class_names) . '"' : '';
		$submenu_id = $this->ekit_parent_item_id
			? ' id="ekit-submenu-' . esc_attr($this->ekit_parent_item_id) . '"'
			: '';
		$output .= "\n$indent<ul$class_names$submenu_id>\n";
	}

	// Start Element
	public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0)
	{
		$indent = ($depth) ? str_repeat("\t", $depth) : '';
		$classes = empty($item->classes) ? [] : (array) $item->classes;
		if (in_array('menu-item-has-children', $classes)) {
			$classes[] = 'elementskit-dropdown-has';
		}
		$class_names = join(' ', apply_filters('nav_menu_css_class', array_filter($classes), $item, $args, $depth));
		$class_names = $class_names ? ' class="' . esc_attr($class_names) . '"' : '';

		$id = apply_filters('nav_menu_item_id', 'menu-item-' . $item->ID, $item, $args, $depth);
		$id = $id ? ' id="' . esc_attr($id) . '"' : '';

		$output .= $indent . '<li' . $id . $class_names . '>';

		$atts = [];
		$atts['title'] = !empty($item->attr_title) ? $item->attr_title : '';
		$atts['target'] = !empty($item->target) ? $item->target : '';
		$atts['rel'] = !empty($item->xfn) ? $item->xfn : '';
		$atts['href'] = !empty($item->url) ? $item->url : '';

		// Announce that this link opens a submenu, and whether it is currently
		// open. Added before the nav_menu_link_attributes filter so a theme can
		// still override them. Attributes only — no markup or class changes.
		$this->ekit_parent_item_id = $item->ID;

		if (in_array('menu-item-has-children', $classes)) {
			$atts['aria-haspopup'] = 'true';
			$atts['aria-expanded'] = 'false';
			$atts['aria-controls'] = 'ekit-submenu-' . $item->ID;
		}

		$atts = apply_filters('nav_menu_link_attributes', $atts, $item, $args, $depth);
		$attributes = '';
		foreach ($atts as $attr => $value) {
			if (!empty($value)) {
				$attributes .= ' ' . $attr . '="' . esc_attr($value) . '"';
			}
		}

		$title = apply_filters('the_title', $item->title, $item->ID);
		$title = apply_filters('nav_menu_item_title', $title, $item, $args, $depth);

		$item_output = $args->before;
		$item_output .= '<a' . $attributes . '>';

		$submenu_indicator = '';
		if ( in_array( 'menu-item-has-children', $classes ) ) {
			// Use an if statement to conditionally display the submenu indicator icon
			if(!empty($args->submenu_indicator_icon)) {
				$submenu_indicator .= $args->submenu_indicator_icon;
			} else {
				$submenu_indicator .= '<i class="icon icon-down-arrow1 elementskit-submenu-indicator" aria-hidden="true"></i>';
			}
		}

		$item_output .= $args->link_before . $title . $submenu_indicator .$args->link_after;
		$item_output .= '</a>';
		$item_output .= $args->after;

		$output .= apply_filters('walker_nav_menu_start_el', $item_output, $item, $depth, $args);
	}
}
