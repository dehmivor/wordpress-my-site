# Premium Templates — catalog-first sections

The Premium Templates catalog (premiumtemplates.io) holds hundreds of ready-made, mobile-friendly Elementor sections built with Premium Addons widgets. Two abilities expose it: `premium-addons-list-premium-templates` browses it, `premium-addons-insert-premium-template` drops one section into a page. Both need the **Premium Templates** switch on in the PA dashboard (**Premium Addons → Features → Premium Templates**) and a site that can reach premiumtemplates.io. Verified against PA v4.11.100.

These are _catalog_ templates, distinct from the site's own saved templates (`premium-addons-list-templates`, inserted by title into picker controls). The catalog's `template_id` is a numeric id that belongs only to `premium-addons-insert-premium-template` — it never goes into a widget control.

## When to reach for the catalog

**Yes** — the user asks for a standard section: hero, team, testimonials/reviews, pricing, contact, CTA, facts and figures, feature blurbs, galleries, 404, coming-soon, news/magazine, tables, Woo sections. A designed section beats a scratch build in speed and quality; customize it afterward.

**No** — the section is site-specific (navigation, dynamic post queries wired to this site's taxonomy, a form that must match an existing one), the user explicitly wants a bespoke build, or the user declined the shortlist. Then build from the `widget-selection` part as usual.

**Always** — list before insert, in the same session. `premium-addons-insert-premium-template` reads requirement notes and inner-template dependencies from metadata that only the list call caches; an insert without a prior list fails with `premium_addons_template_data_unavailable`.

## Querying

`category` (OR across values), `keyword` (OR; values are **PA widget slugs** used inside the template), `pro` (`false` on sites without a valid PRO license; omit when PRO is active), `per_page` ≤ 50, `page`. The live schema carries the valid slugs as enums — read them there. If the enums are absent, the catalog was unreachable when the tools registered: pass plain slugs from the table below; a filtered query that matches nothing returns `valid_categories` / `valid_keywords` to correct against whenever the term lists are reachable (they may be missing too, for up to 15 minutes after a failed fetch).

Intent → `category`:

| The user wants…                                | Category slug                            |
| ---------------------------------------------- | ---------------------------------------- |
| Hero / opening section                         | `hero-scenes`                            |
| Feature / service boxes, icon + text grids     | `blurbs`                                 |
| Short intro / about / statement section        | `brief`                                  |
| CTA band, conversion close                     | `call-to-actions`                        |
| Team                                           | `team-members`                           |
| Testimonials, reviews, quotes                  | `testimonials-and-reviews`               |
| Stats, counters, numbers                       | `facts-figures`                          |
| Pricing / plans                                | `pricing-tables`                         |
| Contact section                                | `contact-us` · `contacts-forms-maps`     |
| Gallery, image accordion, portfolio            | `image-galleries-accordions`             |
| Slider / carousel of anything                  | `carousel`                               |
| Blog / news / magazine layout                  | `magazine-news`                          |
| Comparison or data table                       | `tables`                                 |
| Social feeds / icons                           | `social-media`                           |
| Shop sections (needs WooCommerce)              | `woocommerce`                            |
| 404 page                                       | `404-pages`                              |
| Coming-soon / launch                           | `coming-soon`                            |
| Full landing page composition                  | `landing-page`                           |
| Frosted-glass look                             | `liquid-glass`                           |
| Scroll-driven / pinned animation (PRO effects) | `magic-scroll` · `vertical-multi-scroll` |
| Asymmetric, broken-grid layouts                | `off-grid`                               |
| Dividers, separators, decorative pieces        | `design-elements`                        |

Use `keyword` when the user names a widget or a specific mechanic ("a testimonials _carousel_" → `keyword: ["testimonials", "advanced-carousel"]`). Combine a category with `pro: false` on free sites so the shortlist is insertable.

## Reading a result row

- `title`, `description` — written for humans; quote them.
- `style[]` — closed vocabulary: `light`, `dark`, `minimal`, `bold`, `colorful`, `corporate`, `playful`, `elegant`, `gradient`, `liquid-glass`. Match it to the committed design direction; prefer candidates whose tags agree with the page's existing sections.
- `pro` + `requires_pro_upgrade` — `requires_pro_upgrade: true` means this site cannot insert it (no valid PRO license). Do not shortlist it as the primary option; it may be named once under the PRO boundary rules.
- `preview_url` — a live demo page. Always show it; it is how the user chooses.
- `notice[]` — requirement notes. `container` means "built with Elementor flexbox containers" and is on nearly every template — routine, never alarming. `woocommerce` / `form` mean the section renders through that plugin (WooCommerce / Contact Form 7); the insert hard-blocks when it is missing.
- `keywords[]` — which PA widgets are inside. Cross-check against `premium-addons-list-available-elements`: a widget this site can't render still inserts, and comes back as a `missing_widget` / `pro_gated` warning — better to know before the user picks.

## Proposing a shortlist

Two to four candidates, each as: **title** — one line from `description` — `style` tags — preview link — free/PRO flag when it matters. Say which one you'd pick and why (fit to the design direction, widget availability). The user chooses; nothing is inserted before that.

## Inserting

`premium-addons-insert-premium-template` with `template_id`, `post_id` (page, post, or template), and an optional zero-based `position` among the page's top-level elements (omit to append). Existing content is never removed. What the ability does server-side: fetches the section, regenerates element ids, **sideloads every image into this site's media library**, creates the inner Elementor library templates the section renders (or reuses one with the same title), inserts at the position, and saves the post on **whatever status it already has** — a draft stays a draft.

One exception to drafts-by-default: the inner library templates it creates are **published** `elementor_library` posts, because that is how Elementor resolves templates at render time; an existing one is reused only when it is published with exactly that title. Mention it when it happens (`templates[]` in the result lists them with `created` / `reused`). They are created before the page is saved, so they can exist even if the page save then fails.

Read the whole result before the next call:

| Field                    | Use                                                                         |
| ------------------------ | --------------------------------------------------------------------------- |
| `inserted_element_ids[]` | Your edit targets and your Verify targets. Top-level ids, in catalog order. |
| `edit_url`               | Give it to the user with the QA verdict.                                    |
| `templates[]`            | Inner templates `created` or `reused` on this site, with their `post_id`.   |
| `warnings[]`             | QA items — never silently dropped. See below.                               |

| Warning `type`    | Meaning                                                                                                                                                                                      | Your action                                                                                                                                                                                 |
| ----------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `notice`          | A catalog requirement note (`detail` = the note, e.g. `container`)                                                                                                                           | Relay only when it changes something for this site.                                                                                                                                         |
| `missing_widget`  | A widget in the section isn't registered here — disabled in the PA dashboard, its plugin not installed, or a PA PRO widget on a site without PRO — inserted but won't render                 | Widget availability states in the workflow: Disabled → name it, offer to enable, wait for a yes; not installed / PRO absent → free substitute via Editing discipline, PRO boundary applies. |
| `pro_gated`       | A third-party widget whose source is locked here: free site (`premium_addons_widget_source_locked`), or PRO active with the third-party switch off (`premium_addons_widget_source_disabled`) | The matching availability state: PRO-locked → PRO boundary and a free substitute; Switch off → point to the third-party toggle, nothing else until it is on.                                |
| `failed_media`    | An image couldn't be copied; the element still points at the catalog URL — a hotlink                                                                                                         | Replace it via Media policy (site library or a consented upload) before QA passes.                                                                                                          |
| `template_failed` | An inner template wasn't created; whatever renders it (carousel, modal, scroll section) stays empty                                                                                          | Say so; offer to build that inner content by hand.                                                                                                                                          |

## After the insert

1. **Verify** — `premium-addons-get-page-structure`: every `inserted_element_ids` entry exists at the intended position.
2. **Ask the styling question once:** keep the template's own look (its colors, fonts, spacing come with it), or align it to the site kit's values? Only on "align" do you restyle — through Editing discipline, per the token contract, kit values verbatim. Never restyle uninvited; never rebuild.
3. **Customize content** — text, images, links, alt text — element by element with `premium-addons-get-element-settings` → `premium-addons-update-element-settings`. Placeholder images from the catalog are not the user's product shots; follow Media policy.
4. **QA** as in the workflow's Verify phase, plus every warning resolved or reported.

## Composing several templates

Pick them from one `style` family so the page reads as one system; the design guide's "one layout family per page" rule still applies across templates. Insert in page order using `position`, verify after each. Ask the styling question once per page, not per section.

## Error codes

| Code                                       | Meaning                                                                                    | Path                                                                                                                  |
| ------------------------------------------ | ------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------- |
| `premium_addons_templates_disabled`        | Premium Templates is off in the PA dashboard                                               | Point to Premium Addons → Features → Premium Templates; offer to enable via `premium-addons-update-setting` on a yes. |
| `premium_addons_catalog_data_unavailable`  | The site can't reach premiumtemplates.io, or got something other than a valid listing back | Retry shortly; if persistent, the host likely blocks outbound HTTP or the catalog is down — say so, scratch-build.    |
| `premium_addons_template_data_unavailable` | No cached metadata for this id (no list call yet), or the catalog returned no content      | Call `premium-addons-list-premium-templates` so the id appears in a result, then retry.                               |
| `premium_addons_invalid_template_id`       | Id isn't in the catalog                                                                    | Re-list; never guess ids.                                                                                             |
| `premium_addons_missing_pro_license`       | Pro template on a site without a valid PRO license; nothing was written                    | PRO boundary. Offer a free template from the same category.                                                           |
| `premium_addons_missing_plugin`            | Section needs WooCommerce or Contact Form 7 and it isn't active                            | Say which plugin; don't insert until it is.                                                                           |
| `premium_addons_missing_template_id`       | Call made without `template_id`                                                            | Fix the call.                                                                                                         |

## Rules

1. List, shortlist with previews, user picks, then insert — in that order, every time.
2. Free sites: query with `pro: false`; a `requires_pro_upgrade` template is never the primary proposal.
3. Never insert "to see if it works"; the result is a real edit to the user's page.
4. Customize an inserted section; never rebuild it. Restyle only after the user answers the styling question.
5. `premium-addons-remove-element` removes an inserted section, but not the library templates the insert created — those stay in Templates → Saved Templates until the user deletes them.
6. The dashboard switch is **Premium Addons → Features → Premium Templates** (the ability's own error text calls it "Global Features" — same switch).
