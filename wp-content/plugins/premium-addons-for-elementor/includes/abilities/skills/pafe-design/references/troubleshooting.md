# Troubleshooting

Diagnose in this order: connection → auth → permissions → ability availability → content.

## Connection failures

- **Endpoint unreachable** — the MCP lives at `https://site.com/wp-json/mcp` only if permalinks are pretty; the canonical path is `https://site.com/wp-json/premium-addons/mcp`. Plain permalinks or HTTP-only sites can't use OAuth — Application Passwords are the fallback there.
- **CDN / firewall / security plugin filtering AI clients** — Cloudflare, WAFs, and security plugins can block MCP clients while the site works fine in a browser. Symptom: timeouts or HTML error pages instead of MCP responses. Fix: allowlist the AI client / the `/wp-json/premium-addons/` path in the CDN or security plugin.
- **Worked yesterday, dead today** — most often an expired refresh token (14 days) or a revoked client. Reconnect via OAuth.

## Auth failures

- **Access token expiry (1 hour)** — clients refresh in the background; a mid-session 401 usually self-heals on retry. If not: reconnect.
- **Client registration fails** — registration only works within ~30 minutes of opening the AI Abilities tab. Have the user reload **Premium Addons → AI Abilities**, then connect promptly.
- **Registration refused at 50 clients** — the site hit its registered-client cap. Clients with no live token are pruned automatically once they are a day old, so have the user retry later; the dashboard has no control to remove clients.

## Permission failures

Tool calls succeed but writes fail → the connected WordPress user lacks capability (needs to edit pages / manage options for dashboard settings). Say which operation failed and that the site login used for OAuth needs a role with those rights.

## Ability / widget availability

- **A tool this skill names isn't in context** → it's toggled off. Point to **Premium Addons → AI Abilities**, stop that path (SKILL.md: Toolset states).
- **A widget won't insert / isn't offered** → check `premium-addons-list-available-elements` + `premium-addons-get-settings`: Disabled → offer to enable (confirmed); PRO-locked → boundary path; genuinely absent (e.g. Woo widgets on a non-Woo site) → say so.
- **Elementor missing/inactive** → the abilities have nothing to build on; the user must activate Elementor.

**Known error codes (verified against source):**

- `premium_addons_invalid_widget_type` — the widget isn't registered; a PA widget disabled in the dashboard is not registered (enable it, or check the name).
- `premium_addons_atomic_widget_unsupported` — v4 atomic widgets can't be inserted; use the classic widget equivalent.
- `premium_addons_widget_source_locked` — third-party widget on a free site; the error includes an upgrade link — relay it (once, per the PRO boundary rules).
- `premium_addons_widget_source_disabled` — PRO is active but third-party building is off: Premium Addons → AI Abilities → Build → third-party toggle.

## Premium Templates (catalog)

- **Both template tools missing from context** → toggled off in AI Abilities (Toolset states). **Present but erroring:**
- `premium_addons_templates_disabled` — the Premium Templates feature is off: **Premium Addons → Features → Premium Templates**; offer to enable it via `premium-addons-update-setting` (key `premium-templates`), on a yes.
- `premium_addons_catalog_data_unavailable` — the _site_ can't reach premiumtemplates.io (outbound HTTP blocked by host/firewall, or the catalog is down). Retry once later; if it persists, say so and scratch-build.
- `premium_addons_template_data_unavailable` — two causes, read the message: no cached metadata for that id (`premium-addons-list-premium-templates` hasn't returned it — list so the id appears in a result, then retry), or the catalog returned a template with no content (retry later; re-listing won't help).
- `premium_addons_invalid_template_id` / `premium_addons_missing_template_id` — the id isn't in the catalog or wasn't sent. Never guess ids; re-list.
- `premium_addons_missing_pro_license` — Pro template on a site without a valid PRO license; nothing was written. PRO boundary path; offer a free template from the same category.
- `premium_addons_missing_plugin` — the section needs WooCommerce or Contact Form 7 (its `notice`); the insert refused. Name the plugin; don't work around it.
- **`category` / `keyword` show no enum values in the tool schema** → the catalog was unreachable when the tools registered. Pass plain slugs from the `premium-templates` category table; a filtered query with zero results returns `valid_categories` / `valid_keywords` when the term lists are reachable.

## "It renders nothing"

- **Template-picker set by id** — the #1 cause. Pickers store the template's post **title**; rewrite the control with the exact `title` string from `premium-addons-list-templates`.
- **An inserted Premium Template's carousel / modal / scroll section is empty** — check the insert result: a `template_failed` warning means the inner library template wasn't created; `templates[]` shows what was. Offer to build that inner content by hand.
- **An inserted Premium Template shows a blank spot where a widget should be** — a `missing_widget` (not registered here: disabled, not installed, or PRO absent) or `pro_gated` (third-party widget locked: free site, or PRO's third-party switch off) warning; follow the matching widget availability state.
- **An inserted section still loads images from premiumtemplates.io** — `failed_media` warning; the sideload failed, so the element hotlinks. Replace via Media policy.
- **Element built but invisible** — check Display Conditions on it, responsive visibility settings, and whether it landed inside an unexpected parent (`premium-addons-get-page-structure`).
- **Stale styling after big changes** — `premium-addons-clear-dynamic-assets`, then hard-refresh.

## Rollback

Duplicate-then-edit is the safety net (`premium-addons-duplicate-post` before risky work). WordPress revisions restore a page's content history (wp-admin → the page → Revisions). `premium-addons-remove-element` surgically removes one mistake — including a whole inserted Premium Template section (remove each of its `inserted_element_ids`); the library templates that insert created stay under Templates → Saved Templates until the user deletes them. Nothing publishes without approval, so the live site is safe by default.
