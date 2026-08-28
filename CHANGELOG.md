# Changelog

All notable changes to MSC Post Expiry are documented in this file.

## [1.7.3] - 2026-08-28

### Removed

- Deprecated admin CSS files (`admin-tokens.css`, `admin-components.css`) that were shipping to WordPress.org but never loaded by any PHP code. Removes a dead teal palette and non-standard class names that collided conceptually with sibling-plugin class names.

### Improved

- Accessibility — added `scope="col"` to all admin table headers (Smart Rules table, Action History table, Analytics recent-activity table), added `aria-label` attributes to all four Chart.js `<canvas>` elements on the Analytics tab, added `aria-describedby` wiring on form controls paired with their description paragraphs, and added a `<legend>` to the Post types fieldset. No new translatable strings introduced.

## [1.7.2] - 2026-08-27

### Changed

- Updated translations: added 8 new languages (Russian, Simplified Chinese, Turkish, Polish, Indonesian, Swedish, Ukrainian, Arabic) and refreshed all 20 bundled locales to 100% string coverage.

## [1.7.1] - 2026-08-27

### Changed

- Confirmed compatibility with WordPress 7.1 — "Tested up to" header updated to 7.1. No functional changes.

## [1.7.0] - 2026-07-25

### Changed

- Support now links to the plugin's WordPress.org support forum instead of the old contact button.

## [1.6.0] - 2026-07-24

### Added

- Per-post override UI: the "Post Expiry" panel (block editor sidebar and classic metabox) now lets you set a per-post expiry action, redirect URL and target category, backed by REST-registered meta. Previously the override meta was read at processing time but only settable by developers.
- One-time, dismissible in-plugin review request on the settings page (7+ days after activation).

### Fixed

- **Smart Rules created through the admin UI never fired.** The Rules form posted `condition_value`/`action_value`/`priority` fields while `Rules::save_rule()` expected `condition_config`/`action_config` arrays and an `enabled` flag — UI-created rules were stored with empty configs and disabled. The form handler now resolves values (category/tag slug or ID, author login or ID, day counts, min/max ranges, `field=value` custom fields) into the engine's config format and sets the enabled flag.
- **Rule priority is now stored and honoured** — `evaluate_rules()` sorts by priority (lower = higher) before evaluating; previously the priority field was collected but discarded and rules ran in insertion order.
- **Consolidated the dual expiry pipelines.** The legacy 5-minute cron applied only the global action (skipping Smart Rules, per-post overrides, SEO, notifications, analytics) and did not set `_mscpe_expiry_processed`, so classic-editor posts could be processed by both pipelines and double-logged. The 5-minute cron now migrates legacy `mscpe_expiry_date`/`mscpe_expiry_time` meta to `_mscpe_expiry_timestamp` and delegates to the single full-featured pipeline.
- **Rescheduling an expired post now re-arms processing** — setting a new future expiry date (block editor, classic metabox, or bulk action) resets the processed flag.

### Added

- Comment Count and Post Views conditions in the Smart Rules form (previously engine-only); rule name field; enabled checkbox; rules table now shows resolved condition summaries and enabled/disabled status.
- `mscpe_before_expire_post` / `mscpe_after_expire_post` / `mscpe_before_process_expired_posts` / `mscpe_after_process_expired_posts` action hooks now fire from the consolidated pipeline (previously legacy-cron only).

### Changed

- Classic editor metabox now stores only the unified `_mscpe_expiry_timestamp` (legacy date/time meta removed on save).
- Removed creation of the unused `wp_mscpe_rules` database table (rules live in the `mscpe_rules` option); the table is dropped on reactivation and on uninstall.
- WordPress.org listing rewritten: searchable title and tags, keyword-rich description, expanded 15-question FAQ.

## [1.5.2] - 2026-07-24

### Changed

- Confirmed compatibility with WordPress 7.0.2 — updated "Tested up to" header to 7.0.2. No functional changes.

## [1.5.1] - 2026-06-03

### Fixed

- Refactored all analytics SQL queries to use fully-static SQL templates with toggle-pattern conditions (`(0 = %d OR column = %d)`) instead of dynamic SQL construction — eliminates `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` and `WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare` warnings flagged in WordPress.org review
- `build_where_clause()` now returns a flat associative array of sanitized scalar values (`range_cutoff`, `category_id`, `author_id`, `action`) for direct parameter passing to `$wpdb->prepare()` — no longer builds or returns SQL fragments
- All `$wpdb->prepare()` calls in `class-mscpe-analytics.php` now use static SQL strings with only `%d`/`%s` placeholders — no variable SQL fragments are injected into query templates
- Removed all `phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared` and `WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare` inline suppression comments (no longer needed)
- Removed all `phpcs:ignore WordPress.DB.DirectDatabaseQuery.UnescapedDBParameter` inline suppression comments (no longer needed)

## [1.5.0] - 2026-06-02

### Fixed

- Fixed duplicate "Post Expiry" metabox appearing in the block editor — classic metabox now only registers in the classic editor via `use_block_editor_for_post()` / `use_block_editor_for_post_type()` guard
- Classic metabox render now reads `_mscpe_expiry_timestamp` first (with `is_numeric()` + `> 0` validation), falling back to legacy `mscpe_expiry_date`/`mscpe_expiry_time` fields
- Classic metabox save now writes `_mscpe_expiry_timestamp` alongside `mscpe_expiry_date`/`mscpe_expiry_time`, ensuring cron processing works for classic-editor saves; defaults empty time to `00:00`; includes autosave and revision guards, plus date/time regex validation
- `register_metabox()` replaced `foreach` loop with `in_array()` for post-type eligibility check and accepts second `$post` parameter from the `add_meta_boxes` hook
- `mscpe_get_expiry_datetime()` reads `_mscpe_expiry_timestamp` first (with `is_numeric()` + `> 0` validation), falls back to date/time fields, and uses `wp_date()` for timezone-aware formatting
- `mscpe_is_post_expired()` added `strtotime()` false guard and replaced deprecated `current_time('timestamp')` with `time()`
- `mscpe_get_expiry_status()` added `strtotime()` false guard and replaced deprecated `current_time('timestamp')` with `time()`

## [1.4.1] - 2026-06-01

### Changed

- Bumped plugin version to 1.4.1
- Moved settings inline JavaScript into a dedicated admin asset file and load it only on the MSC Post Expiry settings page
- Moved analytics inline CSS into a dedicated admin stylesheet and load it only on the Analytics tab

### Fixed

- Sanitized success-notice and rule-delete input handling in settings page flows
- Sanitized the payload passed to `mscpe_settings_save` so extensions receive cleaned input values

## [1.4.0] - 2026-05-28

### Changed

- Renamed class files for consistent MSCPE prefix: `class-msc-post-expiry.php` → `class-mscpe-plugin.php`, `class-msc-post-expiry-settings.php` → `class-mscpe-settings.php`, `class-msc-post-expiry-module.php` → `class-mscpe-module.php`
- Bumped plugin version to 1.4.0

## [1.3.0] - 2026-05-03

### Added

- Complete translations for all 189 translatable strings across 12 locales
- New translation entries for Settings, SEO, Smart Rules, Analytics, History, and Support tabs
- Redirect Only action translations for all languages

### Changed

- Regenerated POT template with all current translatable strings (was severely outdated at ~60 strings, now 189)
- Updated translation dictionaries from 59 entries to 188 entries per language
- Regenerated all .po and .mo files for de_DE, de_CH, es_ES, es_MX, fr_FR, fr_CA, it_IT, ja, nl_NL, nl_BE, pt_BR, pt_PT
- Bumped plugin version to 1.3.0

### Fixed

- Fixed Plugin Check SQL interpolation warnings in analytics class (phpcs:ignore comments)
- Fixed duplicate msgid in POT file (merged Plugin Name header entry)

## [1.2.1] - 2026-05-03

### Added

- PHPUnit test suite with comprehensive tests for all plugin components

### Changed

- Renamed "Conditional Expiry Rules" to "Smart Expiry Rules" with improved descriptions
- Updated Support tab with comprehensive feature documentation (Smart Rules, SEO, Analytics, per-post overrides)
- Updated plugin version to 1.2.1

### Removed

- Removed multi-step expiry workflows feature and associated database tables
- Removed Workflows settings tab

### Fixed

- Fixed package.json version mismatch (was 1.0.0, now 1.2.1)
- Fixed README.md version (was showing 1.1.0)

## [1.2.0] - 2026-05-01

### Added

- Per-post expiry action override (set a different action for individual posts)
- Custom redirect URLs for expired posts
- "Redirect Only" expiry action (keep post published, redirect visitors)
- Conditional expiry rules engine (trigger actions by category, tag, author, post age, or custom field)
- Multi-step expiry workflows with delayed actions
- Bulk expiry scheduling from the Posts list
- Email notifications before posts expire (configurable recipients and timing)
- SEO handling for expired posts (noindex, nofollow, canonical URL, HTTP status codes)
- Analytics dashboard with Chart.js charts (trends, action breakdown, top categories/authors)
- Action history log (last 50 expiry actions)
- Block editor sidebar panel for setting expiry dates
- SEO, Rules, Workflows, Analytics, and History tabs in settings
- Redirect, bulk scheduling, notification, and logging settings sections
- 15-minute cron schedule for timestamp-based expiry processing
- Database tables for workflows, workflow steps, rules, and analytics

### Changed

- Bumped version to 1.2.0
- All features now included in the single plugin (no separate add-on needed)
- Removed upgrade prompts from Support tab

### Removed

- Removed `is_pro_active()` and `has_feature()` methods (no longer needed)
- Removed upgrade CTA from settings page

## [1.1.0] - 2026-04-13

### Added

- Redesigned settings page with clean tab-based layout
- Added "Change to Private" expiry action
- Added "Move to Category" expiry action with category selector
- Added expiry category option to settings
- Added index.php security file for log directory
- Added comprehensive debug logging for cron processing

### Changed

- Improved cache behavior when WP_DEBUG is enabled

### Fixed

- Fixed time-based expiry (posts now expire at exact scheduled times)
- Fixed log file append issue (now properly appends to existing logs)
- Fixed log file permissions (log files are now readable by web server)
- Fixed WP_Filesystem usage for WordPress.org Plugin Check compliance

## [1.0.0] - 2026-03-26

### Added

- Initial public release
- Post expiry scheduling with date and time
- Three expiration actions: trash, delete, draft
- Post type configuration (include/exclude modes)
- Automatic cron-based processing (every 5 minutes)
- Comprehensive logging with 30-day retention
- Developer helper functions
- Full internationalization support
