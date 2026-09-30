# Page patterns — section recipes (v1)

Composition recipes: which widgets build which section, in what order. All **design** judgment — layout limits, color, type, spacing, motion — comes from the site-served guide (`premium-addons-get-design-guide`); these recipes never override it. Build the hero first as the quality bar, judge the rendered result, then continue.

Every standard section below has a Premium Templates catalog category (in brackets). Check it first — `premium-addons-list-premium-templates` by category, shortlist with previews, user picks — and fall back to the widget recipe when nothing fits (`premium-addons-get-design-guide` with `part: ["premium-templates"]`).

## Landing page (default arc)

Hero → value/features → proof → offer → action. One message per section; a layout family appears once per page (guide rule — never a row of three identical cards). When several sections come from the catalog, pick them from one `style` family; the `landing-page` category holds full-page compositions when the user wants the whole arc at once.

**Hero** [`hero-scenes`] — container + Heading (or Dual Heading / Animated Text for one emphasized element) + short subtext + Button. Optional: Video Box instead of image; Liquid Glass on the content card; Lottie accent. Guide limits: fits first viewport, ≤2-line headline, ~4 text elements, primary action visible.

**Features / services** [`blurbs`, `brief`] — vary the family: uneven split (large feature + stacked smaller), Bullet List beside media, or Advanced Carousel — not identical icon boxes. Equal Height when cards must align.

**Social proof** [`testimonials-and-reviews`, `facts-figures`, `team-members`] — Testimonial (single, strong) or Advanced Carousel of testimonials; Counter row for numbers (real numbers only); Team Members where people are the proof.

**Pricing** [`pricing-tables`] — Pricing Table per plan in one container; declare mobile collapse; badge the recommended plan via the table's own options.

**Conversion close** [`call-to-actions`] — Banner or a container with Heading + Button; Countdown only for a real deadline.

## Named recipes

**Exit-intent sale popup (docs example: "summer sale popup on exit intent, hidden on touch devices")** — Modal Box: build content (Heading + Countdown + Button), set exit-intent trigger from its schema, hide on touch via responsive visibility, template content by **title** if using a saved template (`premium_modal_box_content_temp`).

**Horizontal-scroll storytelling (docs example)** — PRO territory (Multi Scroll / Magic Scroll). Free fallback: Vertical Scroll for full-screen section storytelling; state the difference, one CTA max.

**Blog/news section** [`magazine-news`] — Blog widget (grid or carousel skin); News Ticker for headlines strip.

**Portfolio/gallery** [`image-galleries-accordions`, `carousel`] — Media Grid with filters; Image Scroll for long screenshots; Modal Box for case-study popups.

**Contact section** [`contact-us`, `contacts-forms-maps`] — Contact Form 7 widget + Google Maps; Global Tooltips for field hints. Catalog templates carrying the `form` notice need Contact Form 7 active or the insert is blocked.

**Coming-soon page** [`coming-soon`] — Countdown + Animated Text + subscribe form (site's form plugin) + Floating Effects on one background element.

**404 page** [`404-pages`] — catalog first; by hand: Heading + short copy + Button back home, Lottie or SVG Draw accent.

**Shop sections** [`woocommerce`] — catalog templates need WooCommerce active (the `woocommerce` notice hard-blocks the insert otherwise); by hand, the `modules/woocommerce` widgets from the live element list.

## Composition rules

1. Sections are sibling containers, top-level; never nest a full section inside another.
2. Every multi-column container declares its mobile collapse (guide rule).
3. Existing pages: new sections are inserted at a stated position — confirm placement in the Plan (`position` on `premium-addons-insert-premium-template` and `premium-addons-add-container` is zero-based among top-level elements).
4. Reuse before rebuild, in this order: the Premium Templates catalog (`premium-addons-list-premium-templates`, by category — user picks from previews), then the site's own saved templates (`premium-addons-list-templates`, inserted by **title** into a picker control), then a scratch build.
5. An inserted catalog section is customized, never rebuilt; ask the styling question (keep its look vs align to the kit) once per page.
