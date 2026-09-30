# Premium Addons — Page Design Guide

The job is a page that fits its site and looks deliberately made. The floor is avoiding the machine-built look — an ad-hoc color and size in every section, a centered mega-hero, three identical icon boxes, vague copy, filler images. Clearing it is the start, not the finish.

## Read before you build

Every build, call first:

- `premium-addons/get-global-settings` — the kit's colors, typography, content width, spacing.
- `premium-addons/get-theme-styles` — theme palette and fonts, when the theme holds them instead of the kit.
- `premium-addons/get-page-structure` — the element tree and ids.
- `premium-addons/detect-atomic-support` and `premium-addons/list-pa-addons` — what this install has: whether atomic (v4) is available, Premium Addons Pro, enabled widgets.

Then, before placing anything:

- **Ground it.** Name the business, its audience, and this page's one job. Design for that.
- **Match, don't reinvent.** Derive the direction from the globals and pages you just read. Propose a fresh look only when nothing coherent exists to match, or the user asks.
- **Stay scoped.** A request for a section yields a section. Never restyle the rest of the site as a side effect — offer it, don't do it.
- **Plan, critique, then build.** Draft a small token set (palette, type scale, spacing, one signature idea) and a layout. Re-read it against the brief; cut anything you'd produce for any page. Then build to the plan.

## The token contract

The Elementor kit is the token layer. Take a small palette, type scale, and spacing scale from what `get-global-settings` and `get-theme-styles` returned, and reuse those exact values across the page. Scattered one-off values are the largest reason these pages look unfinished.

The abilities read the kit but cannot yet write it, and controls take raw values — so reuse the kit's own hex, font, and size values verbatim, never a new palette mid-page, and take spacing from one scale. Tell the user which values to register in Site Settings so the page stays editable. When kit-write and global references land, reference globals instead and add any missing token to the kit first.

## The dials

Infer these from the subject and state them:

- **variance** — low is symmetric and composed; high is asymmetric, offset, split. Low is a finished result, not a shortfall.
- **density** — low opens whitespace and leading; high tightens both.
- **motion** — low is static and complete; high earns entrance and scroll movement.
- **match vs new** — default match; go new only when asked.

## Build with native Elementor only

- **Containers, not sections or columns.** Default to `premium-addons/add-container` (v3) with classic widgets via `premium-addons/insert-widget`. Read a widget's keys with `get-widget-schema` (and extension keys with `get-addon-schema`) before writing them.
- **Declare the mobile collapse** for every multi-column container.
- **Saved templates go in by title, never by id.** Premium Addons template pickers (`Premium_Post_Filter` with `source: elementor_library`) store the template's **post title**, and the widget looks the title back up at render time. A numeric id renders nothing. Read the `title` field from `premium-addons/list-templates` and write that string verbatim — including any dashes, spaces, or casing. Controls affected: carousel `premium_carousel_repeater_item`, modal box `premium_modal_box_content_temp`, vertical scroll and media wheel `section_template`, nav menu `submenu_item`, notifications `content_temp`.
- **Native only.** No injected `<script>`, no external CSS or animation libraries — nothing the editor cannot see and own.
- **Fit the tier.** Build with Elementor core and Premium Addons widgets by default. Reach for a Pro or third-party widget only when it is clearly the better tool, and only if the install has it.

## Confirm by building

Build the first real section — usually the hero — as the visual check and the finished quality bar, not a rough draft. Judge the front-end rendering, not the stored settings.

## Layout (hard rules)

- Hero fits the first viewport: headline ≤2 lines, short subtext, primary action visible without scrolling. A four-line headline is a size error.
- The hero is one moment, ~4 text elements at most. Logo walls, stats, and bullets go in sections below.
- A layout family appears once. Never a row of three identical cards — use an uneven split, a varied grid, or a carousel. At most two image-and-text splits in a row.
- Eyebrows rationed: at most one small uppercase label per three sections.
- One message per section. Navigation stays one line at desktop.

## Color, type, shape

- One accent, held across the page. Neutrals biased slightly toward the accent's hue, one temperature. Never pure black for text or grounds.
- A cream-and-clay palette, or a purple one, is a choice to declare — not a default to fall into.
- Display leading 1.0–1.15; body measure 60–70 characters; leading tracks the density dial. Build hierarchy with weight and color, not size alone.
- Elevation must mean something: use a card for real hierarchy, else a divider or space. Tint shadows to the ground — no pure-black shadow. Lock one corner-radius scale.

## Motion

Elementor owns entrance and hover effects. Use them only when motivated — hierarchy, feedback, state. A static page is complete. Honor reduced-motion.

## Images

Prefer the site's own media; reading the library is best. Generating or uploading needs explicit consent first. Until a source is chosen, never leave an empty slot — use an on-palette gradient or block in the page's colors, composed like the final image. Never pass a stock photo off as a real product shot.

## Copy

Words are design material. Active voice; name things by what the person controls, not the system. One label per action, kept through the flow. An error says what happened and how to fix it; an empty state shows how to fill it. One register per page. Real numbers only — no invented precision.

## Self-audit before finishing

Re-check the rendered page and fix: any color, font, or spacing off the token set; more than two font families; contrast below AA; an over-long H1; three identical cards; critical controls left unset. Then cut what a text scan cannot catch:

- numbered eyebrows or step labels (01 ·, Phase 1); version or status badges; locale, time, or weather strips; scroll cues.
- fake product UI built from plain elements; decorative craft labels; anything laid over an image as decoration; overused middle dots.
- broken, referent-less, or mock-poetic strings; mixed copy registers.
