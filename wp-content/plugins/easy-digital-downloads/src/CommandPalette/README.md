# Command Palette

Surfaces EDD data and admin sub-views in WordPress's native Command Palette
(`Cmd+K` / `Ctrl+K`), available admin-wide as of WordPress 6.9.

EDD registers its own orders, customers, discounts, and downloads as searchable
sources. This document explains how an add-on registers **its own** data so a
store owner can jump straight to it from the palette.

## Requirements

- WordPress 6.9+ (the admin-wide palette and the Abilities API). The feature
  no-ops on older versions.
- EDD 3.7.1+.

## How it works

Each search source is registered as a read-only [WordPress ability][abilities].
A single REST endpoint (`edd/v3/command-palette/search`) aggregates every
registered source the current user is allowed to see and returns the combined
results to one command loader on the JavaScript side. Because sources are
abilities, they are also available to the REST API, MCP, and the AI client with
no extra work.

You do not touch JavaScript. You register a PHP source and return
ready-to-display results.

## Registering a source

Call `EDD\CommandPalette\Registry::register()` on the
`edd/command_palette/register_sources` action. The first argument is a
namespaced ability name; the second is the source configuration.

That action only fires when the Abilities API is available, so a callback never
needs to check the WordPress version or test for a function.

```php
add_action(
	'edd/command_palette/register_sources',
	function () {
		EDD\CommandPalette\Registry::register(
			'my-plugin/search-widgets',
			array(
				'label'           => __( 'Widgets', 'my-plugin' ),
				'capability'      => 'edit_widgets',
				'search_callback' => 'my_plugin_search_widgets',
			)
		);
	}
);
```

### Arguments

| Key               | Required | Description                                                                                     |
| ----------------- | -------- | ----------------------------------------------------------------------------------------------- |
| `label`           | Yes      | Human-readable name for the source (e.g. `Widgets`). Passed to the palette as a search keyword on every result, so a store owner can find them by typing what they are. |
| `capability`      | Yes      | Capability the user must have. Sources the user cannot access are skipped, not denied.          |
| `search_callback` | Yes      | Receives the search term and returns the results (see below).                                   |
| `numeric`         | No       | Set `true` when the source is a strong match for numeric searches. Default `false`.             |
| `description`     | No       | Longer description for the Abilities API. Defaults to a generated string.                       |
| `category`        | No       | Ability category slug. Defaults to `edd-commerce`.                                              |

The ability name (first argument) must be namespaced with your own prefix, e.g.
`edd-software-licensing/search-licenses`. Use lowercase letters, numbers,
dashes, and slashes only.

### The search callback

The callback receives the raw search term and returns an array of results. Each
result is an array with a `label` (what the user sees) and a `url` (where
selecting it navigates). You own both. The palette lists every plugin's commands
in one flat list, so a label leads with the product a store owner would name,
then the record type, then the record. EDD's own results read
`EDD Order: 1842 — jane@example.com`.

```php
function my_plugin_search_widgets( $search ) {
	$results = array();

	foreach ( my_plugin_get_widgets( array( 'search' => $search, 'number' => 10 ) ) as $widget ) {
		$results[] = array(
			'label' => sprintf(
				/* translators: 1: widget name, 2: widget SKU */
				__( 'My Plugin Widget: %1$s — %2$s', 'my-plugin' ),
				$widget->name,
				$widget->sku
			),
			'url'   => edd_get_admin_url(
				array(
					'page' => 'my-plugin-widgets',
					'view' => 'edit',
					'id'   => $widget->id,
				)
			),
		);
	}

	return $results;
}
```

Return an empty array when there are no matches. Results are capped at
`EDD\REST\Controllers\CommandPalette::MAX_RESULTS_PER_SOURCE` per source, so
let your query do the limiting.

## Numeric searches

A numeric term is usually a record ID (an order number, a subscription ID, a
license), so the palette treats numbers specially:

- Sources flagged `'numeric' => true` are shown **first** for numeric searches.
- For non-numeric searches, those same sources are shown **last**, since a name
  or email is unlikely to be looking for them.

If your records are commonly found by a numeric ID, set the flag and make your
search callback resolve that ID directly — a generic text search often will not
match an ID column.

## Full example

Software Licensing surfacing license keys:

```php
add_action(
	'edd/command_palette/register_sources',
	function () {
		EDD\CommandPalette\Registry::register(
			'edd-software-licensing/search-licenses',
			array(
				'label'           => __( 'Licenses', 'edd-software-licensing' ),
				'capability'      => 'manage_licenses',
				'numeric'         => true,
				'search_callback' => function ( $search ) {
					$results = array();

					// Replace with your own data access.
					foreach ( edd_software_licensing()->get_licenses_by_search( $search, 10 ) as $license ) {
						$results[] = array(
							'label' => sprintf(
								/* translators: %s: license key */
								__( 'EDD License: %s', 'edd-software-licensing' ),
								$license->key
							),
							'url'   => edd_get_admin_url(
								array(
									'page'    => 'edd-licenses',
									'view'    => 'overview',
									'license' => $license->id,
								)
							),
						);
					}

					return $results;
				},
			)
		);
	}
);
```

## Notes

- **Capabilities are per-source.** The aggregated request checks each source's
  capability independently; unauthorized sources are silently omitted rather
  than failing the request.
- **Labels are global.** There is no reliable per-screen context in the palette,
  so a label carries its own attribution: `EDD Order: 1842 — jane@example.com`,
  not `Order 1842` and not `1842`. WordPress prefixes its own menu rows the same
  way (`Go to: Settings > Permalinks`), so a bare record name has nothing next to
  it to place it.
- **Ranking follows the front of the label.** The palette scores a match at the
  start of the string highest, so a label leading with the brand ranks below
  other plugins' rows for a query like `licenses`. EDD's sub-view commands pass a
  separate `searchLabel` that leads with the sub-view and carries the brand last,
  keeping the branded label on screen. Sources cannot set one yet; open an issue
  if your records need it.
- **The palette does not group results.** WordPress renders one flat `Results`
  list and its own category badge, so a source's `label` cannot become a
  heading. It reaches the palette as a search keyword instead.
- **Keep callbacks fast.** Loaders run on every keystroke (EDD debounces on the
  JavaScript side, ~250ms). One slow source slows the whole aggregated request,
  so bound your query and avoid expensive work.
- **No JavaScript needed.** Registering the PHP source is enough; the palette
  script and REST aggregation are handled for you.

[abilities]: https://developer.wordpress.org/reference/functions/wp_register_ability/
