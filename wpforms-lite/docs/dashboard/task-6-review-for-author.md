Hey @Zain-Balkhi!

Thank you for this one. It is the most tier-variable widget in the whole epic, and overall you did a really nice job with it. I tested on Elite and the re-scope, the reload persistence, the site-wide fallback on an empty range, the graph toggle and the zero-filled hand-picked rows all behave exactly as the spec describes. Console and debug.log stay clean, `npm run cs` is green. The `range_start` / `range_end` stamping plus the pre-stamp transient guard in `get_range_dates()`, and re-validating the saved scope against the displayed rows, are both careful details that are easy to miss.

I have one blocker and a list of smaller things. Everything below stays inside the dashboard, apart from the migration question in point 1.

## Blocker

1. [ ] **The new index on the Form Analytics table has no migration of its own.** There are two mechanisms in the PR and neither belongs to the thing whose schema changed:

   * `Fields.php:56` adds `KEY form_period` to `CREATE TABLE`, but that only fires for a fresh table. `Helpers\DB::create_custom_tables():270` does `continue` on every table that already exists, so dbDelta is not re-run on upgrade. `wpforms_analytics_fields` shipped in 2.0.0, so for every existing site this branch is dead code.
   * What actually creates the index is `DashboardBackfillTask::build_indexes():279-288`, called from `Upgrade2_0_1::build_indexes_inline():134`.

   For a client arriving on the new version the index does get built, so this is not "the index will be missing". The problem is ownership: `wpforms_analytics_fields` is a Form Analytics table (`Pro\Db\Analytics\Fields`), and its schema change now depends on a Dashboard task, a Dashboard flag (`wpforms_dashboard_rollup_indexes_built`) and a Dashboard failure counter. Your own comment says the index "also serves Form Analytics' own date-ranged field reads", which makes that coupling harder to justify. The two mechanisms also disagree: `create_table()` claims the index is part of the schema, while on an upgraded site its existence depends on whether a Dashboard task ran.

   Could we keep the `CREATE TABLE` key for fresh installs and move the existing-site path into an explicit migration step for the version the epic ships in? `Upgrade2_0_1` is already the epic's migration, so the smallest correct change is a step there rather than a line in `build_indexes()`. That also keeps us out of new Form Analytics files.

   Side note, not about clients: the flag is a plain boolean with no version awareness, so on a site where it is already set the index is never added. I checked on mine: `wpforms_versions` has `2.0.1`, `backfill_complete` is `1`, and after that no path reaches `build_indexes()` at all. We reset those by hand internally, so it does not block anything, but a revision counter instead of a boolean would save us from repeating this the next time we add an index to a released version.

## Should fix

2. [ ] **The Display Graph group is missing its section label and panel.** Figma shows a "Display Options" heading and the checkbox inside a light grey rounded container, and Payments does exactly that (`Payments::get_settings_schema():196-201`). Two array keys in `Entries::get_settings_schema():606` cover it, and no SCSS work is needed because `_framework.scss:160-164` already styles `&-group.wpforms-dashboard-widget-settings-panel`:

   ```php
   [
       'type'    => 'checkboxes',
       'label'   => __( 'Display Options', 'wpforms-lite' ), // add
       'panel'   => true,                                    // add
       'options' => [ 'graph' => __( 'Display Graph', 'wpforms-lite' ) ],
       'value'   => [ 'graph' => $settings['graph'] ],
   ],
   ```

   The unlabeled form checklist below it is correct as built, Figma has no heading there, so nothing to change in that field. Same for your `widget-settings.php` edit: the `checklist` branch does need that `! empty( $field['label'] )` guard, otherwise the base template renders an empty `<p class="…-label">` above the list. Please keep it.

3. [ ] **The form checklist is not the scroll container.** `max-height` and `overflow-y` are unset on the `<ul>`, so the whole popover scrolls instead. On my site with 96 forms that is 2955px of scroll in a 539px popover, so `Display Graph`, `Number of Forms` and `Save Changes` all scroll out of view. Figma shows a bounded list with the button fixed under it. Since Payments and Locations only have 6 and 10 items, the cap belongs in `_framework.scss` next to the existing `-panel` rule rather than as a widget override.

4. [ ] **`build_indexes()` touches the analytics table without a guard.** `RollupRepository::tables_exist():139` only covers the three rollup tables, and `wpforms_analytics_fields` can legitimately be missing. Then `index_exists()` runs `SHOW INDEX` before `suppress_errors( true )`, so `print_error()` can echo into an AJAX response, and the built flag never lands. Could we wrap the new index in `AnalyticsDB::tables_exist()`? This is the other side of point 1: there a Form Analytics index depends on a Dashboard flag, here a Dashboard flag depends on a Form Analytics table.

5. [ ] **Trashed or deleted forms come back through the saved selection.** `get_resolved_settings():665` only runs the stored IDs through `absint()`. A selected form that is later trashed keeps rendering with a live title and working links, and a permanently deleted one gives an empty-titled row. Could we intersect the selection with published form IDs? `Cache::get_published_form_ids()` already exists. The Graph AJAX endpoint is fine as it is, `EntriesCount::get_allowed_forms():449` already requires `publish`.

6. [ ] **The cached per-form superset is unbounded.** `top_forms( $start, $end, 0 )` at `Pro\Cache:296`, and `get_by_form_sql()` at `:321` whose `limit` default is 0. On a site with hundreds of forms that stores hundreds of rows in the transient and builds one placeholder per form in both analytics queries, while the widget shows at most 10 rows. I understand why the cap was lifted for the gear selection. Could we put a ceiling on it anyway?

7. [ ] **Race condition in the graph AJAX.** `applyScope()` at `widget-entries.js:601` and `resetScope()` at `:634` apply every response unconditionally, and `swapWidget():195` replaces the DOM and the current range underneath them. Changing the date range while a scope request is in flight draws stale data on the new canvas. The server also writes the selection inside each request (`Pro\Ajax:122`), so ignoring stale responses on the client alone is not enough. Aborting the previous request would cover it.

8. [ ] **The locked cell's upgrade link has no dashboard attribution.** `$render_locked_cell` in `entries-table.php:28` does not pass `data-utm-medium` or `data-utm-content`, so `getUTMContentValue()` in `education/core.js:274` falls back to `data-name` and `utm_content` becomes "Form Analytics" instead of `analytics-upgrade`, while `utm_medium` stays at the base value. Forms Overview sets `forms-overview` for the same badge, so right now an upgrade from the dashboard is indistinguishable from one there. Two attributes in the template. Could you check the medium value with the content team, since "The URLs introduced in PR have been reviewed by the content team" is still unchecked?

9. [ ] **`get_views_by_form()` and `get_interactions_by_form()` repeat `get_total_views()`.** All three build the same placeholder and prepare block (`Cache:305`, `:347`, `Pro\Cache:359`). One private helper taking the select clause would cover them, and all three are dashboard files.

10. [ ] **`has_published_forms():143` queries `$wpdb->posts` directly** on the grounds that this runs before `init`. Every path to `get_state()` actually runs after `init`: `admin_enqueue_scripts`, `wpforms_admin_page` and `wp_ajax_*`. It also disagrees with `get_form_choices():640`, which deliberately goes through the form handler for the access and multilingual filters. Could we use the handler here too?

11. [ ] **Pro-only methods are called on the base type.** `get_entries_widget()` returns `?Entries` (Lite base), but `Pro\Ajax:120` and `:122` call `build_form_graph()` and `save_active_form_id()`, which only exist on the Pro subclass. It works through FQCN resolution, but static analysis cannot see it. An override with the Pro return type in `Pro\Ajax` would fix it.

12. [ ] **Could you check the Lite, Lite-Connect-off state?** Reading the code, forms with Form Analytics views but no submissions never reach the table: the row set comes from `Lite\Reports\EntriesCount::get_by_form():46`, which skips any form with an empty lifetime `wpforms_entries_count`, and `zero_form_counts()` then zeroes the rest. Per `widget-entries.md` §4.4 Views should still be populated there. You already built this exact fallback for the LC-on path in `map_lc_forms()`, with the comment about what the parent excludes, so the LC-off branch may just need the same treatment. Please keep the fix in `Lite\Admin\Dashboard\Cache` rather than in `EntriesCount`, since the weekly summary email uses `get_by_form()` too (`Lite/Emails/Summaries.php:99`). I could not verify this one in the browser, so it may not reproduce.

13. [ ] **The Lite path enriches twice.** The base `get_aggregates()` now calls `enrich_forms_with_analytics()`, and the Lite override calls it again on the LC-mapped rows (`Lite\Cache:53`). Two extra queries per cache compute.

14. [ ] **Test silently skips half of itself.** `CacheTest.php:381` uses `return` instead of `markTestSkipped()` when the analytics tables are missing, so it passes green with nothing asserted. Also `interactions` is only checked as `0`, so `get_interactions_by_form()` has no non-zero coverage.

## Nice to have

15. [ ] `_framework.scss:181` still says we let WP core paint the checked mark, but the new mask exists because that glyph is invisible on the painted box. Worth updating the comment. Note for QA: the mask repaints the checkbox in every gear popover, so Payments and Locations need a spot-check.

16. [ ] `get_views_by_form():347` hardcodes `$wpdb->prefix . 'wpforms_analytics_forms'` while `AnalyticsDB::forms_table()` exists. The Pro sibling in the same PR does use `fields_table()`.

17. [ ] `Entries:930` references `DateTimeImmutable` in the PHPDoc without a `use` import, so tooling resolves it inside the widget namespace. The Pro subclass imports it correctly.

18. [ ] `Page.php:330` passes the chart label as `esc_html__( 'Entries', 'wpforms-lite' )`. On Pro the widget title comes from the `wpforms` domain, so the tooltip and the card title can diverge in a translated build, and the entities go straight into Chart.js.

19. [ ] `is_entries_widget_visible():444` can never be false, since `get_state()` hardcodes visible. The memoized `has_published_forms()` query runs only to be discarded. Either simplify it or note that it is a forward-looking seam.

20. [ ] The gear checklist shows duplicate form titles with nothing to tell them apart. My test site has two "Simple Contact Form" and three "Job Application", and I picked the wrong one while testing. Maybe append the ID when a title repeats?

21. [ ] `get_form_choices():640` has no null guard on `wpforms()->obj( 'form' )`, unlike `Payments::get_recent_payments()`. And `entries-empty.php:47` could run the classes through `wpforms_sanitize_classes()` the way `widget.php:34` does.

22. [ ] `placeholderSeries()` at `widget-entries.js:392` is `getPlaceholderData()` from `widget-payments.js:409`, same three constants and the same random line. It belongs in the shared dashboard app, but since it touches the Payments module it is fine as a separate follow-up rather than in this PR.

## Two questions

23. [ ] **Per-user or site-wide for the abandonment clock?** The code uses a site-wide option, but `task-6-entries-widget-spec.md` §2.7 in this PR says per-user meta, and `widget-entries.md` §3.5 says "the user's first visit". With the option, a second admin who opens the dashboard later sees the promo immediately. Whichever we pick is fine, but could we align the code and the two spec files?

24. [ ] **Changelog.** You added the entry to `CHANGELOG.md` and the same text is in the PR description. I thought the leads merge those at release time. Could you check with them which way we want it here?

One thing the AI review got wrong, so you can ignore it: the dismissal key is correct. `data-section="dashboard-abandonment-promo"` becomes `edu-dashboard-abandonment-promo` in `Education\Core::ajax_dismiss():116`, which is exactly what the widget reads.

I did not check the Lite build, the no-forms empty state or the abandonment promo in the browser, so points 12 and 23 come from reading the code.

Thanks again, and let me know what you think about any of these.
