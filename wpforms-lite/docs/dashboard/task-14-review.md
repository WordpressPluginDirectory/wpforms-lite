# Task 14 review consolidated: PR #18343 "Dashboard: Spam + Security Checkup"

> **PR:** https://github.com/awesomemotive/wpforms-plugin/pull/18343
> **Author:** Zain-Balkhi
> **Branch:** `core/17787-dashboard/17968-task-14-spam-security` -> `core/17787-dashboard/main`
> **Diff:** 10 files, +775 / -16
> **Reviewed:** 2026-07-28

Merges two independent review passes into one numbered list. Every point below was
re-verified against the live repo and, where behavioural, against a running site on this
branch (`https://wpforms.test`).

**How it was verified**

- Read the full diff plus `docs/dashboard/widgets/widget-spam-security-checkup.md`,
  `task-14-spam-security-checkup-spec.md`, `task-14-spam-security-checkup-plan.md`.
- Compared the rendered widget against `figma_exports/Dashboard - Lite/ No Data.png` and
  `Full Data.png`, including pixel sampling of the glyphs.
- Exercised both states live: toggled `wpforms_settings['gdpr']` on, confirmed the met and
  superseded branches, then restored the option to boolean `false`.
- Opened the WPConsent education modal to confirm the wiring, then cancelled. Nothing was
  installed (`wp plugin list` confirms WPConsent is still absent).
- `phpcs --standard=phpcs.xml` on both new PHP files: clean.

---

## Action items

### 1. [P2] Met-row glyph is the wrong Font Awesome variant

`templates/admin/dashboard/sidebar/spam-security.php:36`

```php
$icon_class = $row['is_met'] ? 'fa-solid fa-circle-check' : 'fa-regular fa-circle';
```

`fa-solid fa-circle-check` renders a **filled green disc with a white check**. The Figma
Full Data export shows a green **outlined ring with a green check inside**.

Verified by sampling the 24x24 glyph region of `Full Data.png`: 460 of 576 pixels are
white (`#FDFEFD`), with the green (`#0E902C`) confined to the ring and the check stroke.
A filled disc would have almost no white. Confirmed again on the live render, which shows
a solid disc.

**Fix:** `fa-regular fa-circle-check`. This also pairs correctly with the unmet
`fa-regular fa-circle`, which the author already got right.

### 2. [P2] The doc-list wrapper is duplicated instead of shared

The shared **row** partial `admin/dashboard/doc-link` is reused unchanged, which is
correct. The **list wrapper** around it is not.

`templates/admin/dashboard/sidebar/spam-security.php:94-109` is a byte-for-byte copy of
`templates/admin/dashboard/sidebar/getting-started.php:15-30`: the same `<ul>`, the same
`empty()` skip guard, the same `// Filtered data:` comment, the same `phpcs:ignore`, the
same `wpforms_render()` call. Only the `<ul>` class and the loop variable name differ.

The SCSS is duplicated too. `_widget-spam-security.scss:20-31`

```scss
.wpforms-dashboard-spam-security-rows,
.wpforms-dashboard-spam-security-docs {
	display: flex;
	flex-direction: column;
	gap: $spacing_ms;
	margin: 0;
	padding: 0;
	list-style: none;

	li { margin: 0; }
}
```

is identical, declaration for declaration, to `.wpforms-dashboard-getting-started-list` in
`_widget-getting-started.scss:7-18`.

**Fix:** promote a `admin/dashboard/doc-links.php` list partial (taking `links` plus an
optional extra class) and a shared `.wpforms-dashboard-doc-list` SCSS class, then point
both widgets at them. Both widgets are still unreleased on `core/17787-dashboard/main`, so
touching Getting Started costs nothing now and gets more expensive after release.

### 3. [P2] The "same definition as CompletionDetector" claim is false for WPConsent Premium

`src/Admin/Dashboard/Widgets/SpamSecurity.php:277` states:

> Same definition as `SetupChecklist\CompletionDetector::is_privacy_compliance_configured()`.

The PR description makes the same promise ("Row conditions adopt `SetupChecklist\CompletionDetector`'s
definitions so the surfaces agree"). For WPConsent Premium it is not true:

| Surface | Activation check | Sees `wpconsent-premium/wpconsent-premium.php`? |
|---|---|---|
| `SpamSecurity::is_wpconsent_met()` | `WPConsentHelper::is_activated()` | **Yes** (`Helper.php:83` matches `SLUG` or `wpconsent-premium`) |
| `CompletionDetector::is_privacy_compliance_configured()` | `PluginDetector::status( 'wpconsent-cookies-banner-privacy-suite/wpconsent.php' )['active']` | **No** |

`PluginDetector::status()` (`SetupWizard/Service/PluginDetector.php:78-81`) passes a
single-element array to `inspect_plugin()`, so despite that method supporting Lite/Pro
variants, only the one Lite basename is inspected. `PluginCatalog::CROSS_PLUGINS` has a
`pro` key mechanism (used for Uncanny Automator and WP Mail SMTP) but the WPConsent entry
does not use it (`PluginCatalog.php:53-55`).

Premium is standalone, not an add-on on top of Lite. The established pattern elsewhere in
the codebase is `is_plugin_active( lite ) || is_plugin_active( pro )`, see
`Admin/Pages/PrivacyCompliance.php:313-314` and `Admin/Pages/SugarCalendar.php:327-328`.
`class-menu.php:519-520` also maps both basenames independently to the `wpconsent` page.

So with WPConsent Premium active and onboarded, the Dashboard row shows green while the
Setup Checklist item stays incomplete.

**Important: the fix direction is not to narrow this widget.** The PR is on the correct
side. `Education\WPConsent\Helper` is the canonical detector and matches the rest of the
codebase; `CompletionDetector` is the outlier. Narrowing `SpamSecurity` to match would
propagate the bug into a second surface.

In scope for this PR: correct the docblock at `:277` and the PR description to say
"same onboarding condition, with broader activation detection covering Lite and Premium",
so the file stops asserting something untrue.

Out of scope, worth a separate issue: `CompletionDetector:280` (and
`SetupChecklist/Page.php:322`, and the missing `pro` key in `PluginCatalog`) do not detect
WPConsent Premium. That is a pre-existing defect in shipped Setup Checklist code, not
something this PR introduced. The spec already lists `CompletionDetector` under
"Unchanged, deliberately".

### 4. [P3] A superseded first row strikes its trailing "or..."

`src/Admin/Dashboard/Widgets/SpamSecurity.php:173` / `216` always set `or_text` on the
first row of each group, and the template wraps the whole composed label in `<s>` when the
row is superseded. Result, verified live with `gdpr` enabled:

```
  (o) WPConsent installed and enabled, or...     <- entire line struck, "or..." included
  (v) GDPR Enhancements enabled
```

Figma only ever shows the **second** row of a group struck, and the second row has no
"or...", so this state is unspecified by the design. As built the struck connector dangles
into the row that is actually satisfied.

**Fix:** drop `or_text` when `is_superseded` is true.

### 5. [P3] `.row-name { font-weight: 500 }` persists on superseded rows

`_widget-spam-security.scss:63-65`. In the Figma Full Data export the struck rows render at
uniform weight; here the entity name stays at 500 while the surrounding sentence is 400
(confirmed live: `getComputedStyle(...).fontWeight === "500"`).

The SCSS comment says this is deliberate, so this is for design to confirm rather than a
defect. Flagging only so the decision is explicit.

### 6. [P3] WPConsent state is recomputed two or three times per render

`src/Admin/Dashboard/Widgets/SpamSecurity.php:204` computes `$wpconsent_met`, then
`get_wpconsent_link()` recomputes it from scratch at `:349` and `:352`.

Exact call counts per render:

| Site state | `is_activated()` | `settings->get_option( 'onboarding_completed' )` |
|---|---|---|
| WPConsent active and onboarded | 3x | 2x |
| WPConsent not active | 2x | 0x |

Not a real performance problem. `Helper::$basename` is statically memoised, `get_plugins()`
is request-cached (and already warm on this page because GrowthTools renders
Install/Installed states earlier), `is_plugin_active()` reads the cached `active_plugins`
option, and `get_option()` is object-cached.

**Fix:** pass the already computed `$wpconsent_met` into `get_wpconsent_link()`.

### 7. [P3] `is_education_available()` duplicated from StatCards

`src/Admin/Dashboard/Widgets/SpamSecurity.php:473` reproduces
`src/Admin/Dashboard/StatCards.php:431` verbatim. The docblock admits the mirroring. It is
`private` in StatCards, so a real fix needs a shared home. Low priority, but it is the
second copy, so a third will make it a pattern.

### 8. [P3] Redundant `line-through` rule and over-qualified selector

`_widget-spam-security.scss:81-85`

```scss
.wpforms-dashboard-spam-security-row-superseded {

	.wpforms-dashboard-spam-security-row-label s {
		text-decoration: line-through;
	}
}
```

`<s>` already gets `line-through` from the UA stylesheet, and nothing in wp-admin resets
it. Verified live: an `<s>` injected into wp-admin with no matching rule computes
`text-decoration-line: line-through`.

The `<s>` element is also only ever emitted inside a superseded row
(`spam-security.php:62-64`), so the outer `.row-superseded` wrapper is redundant as well.
The whole block can go.

---

## Notes, no action needed

### 9. No tests

The PR ships none. Neither do the sibling sidebar widgets (`GettingStarted`, `GrowthTools`
have no test coverage on the base branch). Consistent, so not a blocker for this PR.

### 10. No changelog line in the PR body

Sub-task PRs merging into the dashboard feature branch appear not to carry one, and per
project convention changelog text belongs in the PR description rather than
`CHANGELOG.md`. Team lead's call.

---

## Verified correct

### Security: nothing found

- No user input, no superglobals, no DB access, no AJAX. Read-only render on a page gated
  by `manage_options`.
- Escaping is correct throughout: `esc_html()`, `esc_attr()`, `esc_url()`. The single
  `WordPress.Security.EscapeOutput` suppression (`spam-security.php:70`) is justified: the
  format string goes through `esc_html()` and both arguments are pre-escaped HTML.
- `wpforms_html_attributes()` sanitises attribute names via `sanitize_html_class()` and
  values via `esc_attr()` (`includes/functions/escape-sanitize.php:326-365`). The `data-*`
  install payload survives intact, confirmed in the rendered DOM.
- Install nonces come from the shipped helpers (`wp_create_nonce( 'wpforms-admin' )`), and
  `wpforms_can_install()` is enforced server-side by the education AJAX handlers.
- `esc_url( '#' )` returns `#` (WP skips the scheme prefix when the URL starts with `#`),
  so the modal trigger href is not mangled.

### Performance: fine

Zero queries, zero HTTP calls, no transients. Deliberate: the spec wants live detection
rather than a cached flag, and point 6 above is the only redundancy.

### Docs conformance

| Spec requirement | Status |
|---|---|
| Sidebar, order 40, between Growth Tools and Getting Started | Verified live |
| Three group headings, no gear menu, not dismissible, no footer, no JS | Verified |
| Predicates match `CompletionDetector`, including provider-first short-circuit in `is_captcha_met()` | Matches (except point 3) |
| Doc-link anchors `#spam-protection-and-security-settings` and `#minimum-time` | Match corrected spec 3.7 |
| `view=captcha` | Canonical, same as `Admin\Education\Builder\Captcha:169` |
| `#wpforms-setting-row-gdpr` | Real id, verified live on Settings -> General |
| WPConsent dashboard slug `wpconsent` | Matches `class-menu.php:519-520` |
| `@since {VERSION}`, FQCN filter name, `private` visibility, tabs, comments end with a period, `wpforms-lite` domain | All correct, PHPCS clean |

### Behaviour verified live

- No-Data state matches Figma apart from point 1.
- `gdpr` enabled: GDPR row goes green with SR text "Done."; WPConsent row gains
  `-superseded`, wraps in `<s>`, drops its link, and carries SR text
  "Not needed, GDPR Enhancements covers this." Option restored afterwards.
- WPConsent link opens the standard education modal reading "The WPConsent plugin is not
  installed. Would you like to install and activate it?" The `data-name` workaround
  described in the docblock at `:336-341` works; no "undefined" leaks into the copy.
  Cancelled, nothing installed.
- ActiveLayer is active-without-API-key on the test site, and the row correctly stays unmet
  while linking to the ActiveLayer dashboard.
- `wpforms-admin-education-core` is enqueued on the Dashboard page and binds
  `.education-modal` delegated on `document` (`education/core.js:99`), so the widget needs
  no enqueue of its own, as claimed.

---

## Corrected during review

Two things that looked like findings and are not. Recording them so they are not raised
again.

### Widget title is not double-escaped

`get_title()` returns `esc_html__( 'Spam & Security Checkup', ... )` and `widget.php:45`
runs `esc_html( $title )` a second time. This looked like it would render
`Spam &amp; Security Checkup` literally. It does not: `esc_html()` calls
`_wp_specialchars()` with `$double_encode = false`, so the existing entity is left alone.
Confirmed in the browser, `titleText === 'Spam & Security Checkup'`.

Same reasoning clears `WhatsNew::get_title()`, which has the identical shape with an
apostrophe.

### "Move SpamSecurity onto CompletionDetector's WPConsent check"

The divergence in point 3 is real, but this fix direction is backwards. `CompletionDetector`
is the narrower and incorrect one. See point 3.
