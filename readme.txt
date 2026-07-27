=== MSC Post Expiry ===
Contributors: djm56
Tags: expire posts, post expiration, unpublish, content expiration, redirect expired posts
Requires at least: 5.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.7.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically expire posts and pages on a schedule — trash, delete, draft, make private, recategorise or redirect. With notifications and analytics.

== Description ==

**Set an expiration date on any post or page and MSC Post Expiry handles the rest — automatically.**

When the date arrives, choose what happens: move to trash, permanently delete, revert to draft, make private, move to a different category, or keep the post live and redirect visitors. Perfect for limited-time offers, event announcements, job listings, seasonal content and legal notices that must come down on time.

**Features:**

* Schedule post expiration dates and times from the block editor sidebar or classic editor metabox
* Six expiration actions: move to trash, permanently delete, convert to draft, change to private, move to category, or redirect only
* Custom redirect URLs for expired posts
* Smart Expiry Rules: conditional actions by category, tag, author, post age, comment count, view count, or custom field — with priorities
* Bulk expiry scheduling from the Posts list
* Email notifications before posts expire (1–30 days ahead, to author, admin or both)
* SEO handling for expired posts (noindex/nofollow, canonical URL, 410 Gone status)
* Expiry analytics dashboard with charts (trends, action breakdown, top categories and authors) — Chart.js bundled locally, no CDN
* Action history log
* Works with any public post type, include or exclude mode
* Automatic processing via WordPress cron
* No external services, no tracking — GDPR-friendly
* Translated into 12 languages
* Developer-friendly with helper functions and hooks

**Use Cases:**

* Temporary promotional content that should disappear automatically
* Time-sensitive announcements
* Seasonal content management
* Event posts that should be archived
* Automatic content cleanup
* Redirect expired offers to current landing pages
* Notify authors before their content expires

The plugin adds a "Post Expiry" panel to the block editor sidebar and a metabox in the classic editor where you can set the expiration date and time. Once the scheduled time passes, the plugin automatically processes the post according to your configured settings.

== Frequently Asked Questions ==

= How do I make a WordPress post expire automatically? =

When editing a post or page, look for the "Post Expiry" panel in the block editor sidebar (or the metabox in the classic editor). Enter the date and time when you want the post to expire — the plugin takes care of the rest via WordPress cron.

= How do I automatically unpublish a post on a specific date? =

Set an expiry date and choose the "Change to Draft" or "Change to Private" action — the post is unpublished automatically when the date passes, without deleting it. This is the simplest way to handle time-limited content expiration and scheduled unpublishing in WordPress.

= What happens when a post expires? =

The plugin performs one of six actions based on your settings:
* **Move to Trash** - The post is moved to trash and no longer visible to visitors
* **Permanently Delete** - The post is permanently deleted from your site
* **Change to Draft** - The post is changed to draft status and hidden from visitors
* **Change to Private** - The post is changed to private status
* **Move to Category** - The post is moved to a selected category
* **Redirect Only** - The post stays published but visitors are redirected to a specified URL

= Can I redirect an expired post instead of deleting it? =

Yes. Choose the "Redirect Only" action (or a Smart Rule with a redirect action), enable redirects in Settings, and expired posts will send visitors to the URL you set — great for pointing old offers at your current landing page. The SEO tab can additionally set a canonical URL for expired content.

= Does it work with the block editor (Gutenberg) and the classic editor? =

Yes, both. The block editor gets a "Post Expiry" sidebar panel; the classic editor gets a metabox with date and time fields.

= Can I get an email before a post expires? =

Yes. Enable email notifications in Settings. You can notify the post author, the site admin, or both, between 1 and 30 days before expiry. Each post triggers one reminder.

= What are Smart Expiry Rules? =

Smart Rules let expiry behaviour depend on the post itself. For example: "If a post is in the News category, move it to draft when it expires" or "If a post is older than 90 days, delete it permanently." Conditions include category, tag, author, post age, comment count, view count and custom fields. Rules run in priority order (lower number wins) and the first matching rule overrides the default action.

= Can each post have its own expiry action? =

Yes. In the "Post Expiry" panel (block editor sidebar or classic metabox) you can override the action for that single post — including a redirect URL or a target category — which takes precedence over the global default. You can also vary the action automatically with Smart Rules (by category, tag, author, age and more).

= How does the SEO handling work? =

When a post expires, the plugin can automatically add noindex/nofollow meta tags, set a canonical URL (to home or category), and return a 410 Gone status code so search engines drop the page cleanly. Configure these in the SEO tab.

= How often are expired posts checked? Is WP-Cron reliable? =

Expired posts are checked every 5 minutes via WordPress cron. Note that WP-Cron runs on site traffic — on very low-traffic sites a scheduled task can run late. For exact timing, point a real cron job at `wp-cron.php` (your host's docs explain how) — the plugin then processes precisely on schedule.

= Can I expire many posts at once? =

Yes. Select posts in the Posts list and choose the "Set expiry using default window" bulk action — each selected post gets an expiry date of now plus your configured default window (Settings → Bulk Scheduling, 1–3650 days).

= Does this plugin work with custom post types? =

Yes. Configure any public post types in Settings, in include or exclude mode.

= Is there a way to check if a post is expired in my theme? =

Yes, developers can use the helper functions:
* `mscpe_is_post_expired( $post_id )` - Check if a post is expired
* `mscpe_get_expiry_datetime( $post_id )` - Get the expiry date and time
* `mscpe_get_expiry_status( $post_id )` - Get human-readable expiry status
* `mscpe_format_expiry_datetime( $date, $time )` - Format expiry date for display

= Does it send data anywhere? Is it GDPR-friendly? =

No data leaves your site. There are no external services, no CDN assets (Chart.js is bundled locally), and no tracking. Optional expiry-reminder emails are sent through your own WordPress mail.

= What data is removed on uninstall? =

Uninstalling deletes all plugin options, rules, the action log, the analytics table, and all expiry post meta. Log files under `wp-content/uploads/msc-post-expiry-logs/` can be removed manually.

= What languages are supported? =

The plugin includes translations for 12 languages: German (Germany and Switzerland), Spanish (Spain and Mexico), French (France and Canada), Italian, Japanese, Dutch (Netherlands and Belgium), and Portuguese (Portugal and Brazil).

== Installation ==

1. Upload the plugin to `/wp-content/plugins/`.
2. Activate in the WordPress plugins screen.
3. Go to Settings > MSC Post Expiry to configure the plugin.
4. When editing a post or page, use the "Post Expiry" panel to set expiration dates.

== Screenshots ==

1. Post expiry settings — choose the default expiry action (trash, delete, draft, private, move to category, or redirect), target post types, and set the bulk scheduling window.
2. SEO handling for expired posts — add noindex/nofollow, set a canonical URL, and return a 410 Gone status so search engines drop expired content cleanly.
3. Smart Expiry Rules — build conditional rules by category, tag, author, post age, comment count, view count or custom field, evaluated in priority order.
4. Expiry action history — a log of recent automatic expiry actions with the post, action taken, and date.

== Changelog ==

= 1.7.0 =
* Changed: Support now links to the plugin's WordPress.org support forum instead of the old contact button.

= 1.6.0 =
* New: Per-post override UI — set a different expiry action, redirect URL or target category for a single post, in both the block editor sidebar and the classic metabox.
* Fixed: Smart Rules created through the admin UI never fired — the form now saves rules in the format the rules engine reads, sets the enabled flag, and stores the priority.
* Fixed: Rule priority is now honoured — rules evaluate in priority order (lower number = higher priority).
* Added: Comment Count and Post Views conditions to the Smart Rules form (previously engine-only).
* Added: Rule name field, enabled checkbox, and a clearer rules table showing resolved conditions and status.
* Fixed: Consolidated the two expiry pipelines into one — expired posts are now processed every 5 minutes with the full feature set (Smart Rules, per-post overrides, SEO handling, notifications, analytics). Previously, posts scheduled via the classic editor could be expired by a legacy pipeline that skipped these features and could double-log actions.
* Fixed: Rescheduling an already-expired post from the block editor now re-arms processing.
* Changed: Legacy per-post date/time meta is migrated automatically to the unified timestamp format.
* Changed: Removed the unused rules database table (rules are stored in options; dropped on reactivation).
* Improved: WordPress.org listing rewritten — clearer title, searchable tags, expanded FAQ, and captioned screenshots.

= 1.5.2 =
* Confirmed compatibility with WordPress 7.0.2 — no functional changes.
* Updated "Tested up to" to 7.0.2.

= 1.5.1 =
* Refactored all analytics SQL queries to use fully-static SQL templates with toggle-pattern conditions, eliminating dynamic SQL construction that triggered WordPress.org review warnings
* Replaced `build_where_clause()` SQL fragment assembly with flat associative array returning sanitized scalar values for direct `$wpdb->prepare()` parameterization
* Removed all `phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared` and `WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare` comments (no longer needed)
* All database queries now use `$wpdb->prepare()` with static SQL containing only `%d`/`%s` placeholders — no interpolated SQL fragments

= 1.5.0 =
* Fixed duplicate "Post Expiry" metabox appearing in block editor — classic metabox now only shows in classic editor via `use_block_editor_for_post()` per-post check
* Fixed data inconsistency: classic editor metabox now saves `_mscpe_expiry_timestamp` alongside date/time fields, ensuring cron processing works correctly
* Fixed timezone display: metabox and template functions now use `wp_date()` instead of `gmdate()` for local time
* Added autosave and revision guards to metabox save handler
* Added date/time format validation with regex before processing
* Added `strtotime()` false guards in `mscpe_is_post_expired()` and `mscpe_get_expiry_status()` to prevent incorrect expiry detection
* Replaced deprecated `current_time('timestamp')` with `time()` in expiry check functions
* Added timestamp validation (`is_numeric()` && `>0`) to prevent 1970-01-01 display from non-numeric meta values

= 1.4.1 =
* Hardened input sanitization in settings flows and extension save hook payload.
* Moved settings inline JavaScript to an external admin asset loaded only on the plugin settings page.
* Moved analytics inline CSS to an external admin stylesheet loaded only on the Analytics tab.
* Bumped version to 1.4.1.

= 1.4.0 =
* Renamed class files for consistent MSCPE prefix (class-mscpe-plugin.php, class-mscpe-settings.php, class-mscpe-module.php)
* Bumped version to 1.4.0

= 1.3.0 =
* Complete translations for all 189 strings across 12 locales (de_DE, de_CH, es_ES, es_MX, fr_FR, fr_CA, it_IT, ja, nl_NL, nl_BE, pt_BR, pt_PT)
* Regenerated POT template with all current translatable strings
* Updated translation dictionaries from 59 to 188 entries per language
* Fixed Plugin Check SQL interpolation warnings in analytics

= 1.2.1 =
* Renamed "Conditional Expiry Rules" to "Smart Expiry Rules" with improved descriptions
* Updated Support tab with comprehensive feature documentation
* Added PHPUnit test suite
* Removed multi-step expiry workflows feature
* Fixed package.json and README.md version mismatches

= 1.2.0 =
* Added per-post expiry action override
* Added custom redirect URLs for expired posts
* Added "Redirect Only" expiry action
* Added conditional expiry rules engine (by category, tag, author, age, custom field)
* Added bulk expiry scheduling from Posts list
* Added email notifications before posts expire
* Added SEO handling (noindex, nofollow, canonical, HTTP status codes)
* Added analytics dashboard with Chart.js charts
* Added action history log
* Added block editor sidebar panel for setting expiry
* Added SEO, Rules, Analytics, and History tabs to settings
* Added redirect, notification, and logging settings
* Removed upgrade prompts (all features included)

= 1.1.0 =
* Rebranded to MSC Post Expiry
* Redesigned settings page with clean tab-based layout
* Added "Change to Private" expiry action
* Added "Move to Category" expiry action with category selector
* Fixed time-based expiry (posts now expire at exact scheduled times)
* Fixed log file append issue
* Fixed WP_Filesystem usage for WordPress.org Plugin Check compliance
* Added comprehensive debug logging for cron processing

= 1.0.0 =
* Initial release
* Post expiry scheduling with date and time
* Three expiration actions: trash, delete, draft
* Post type configuration (include/exclude modes)
* Automatic cron-based processing
* Comprehensive logging
* Developer helper functions
* Full internationalization support

== Upgrade Notice ==

= 1.6.0 =
Important fix: Smart Rules created in the admin now actually run, with working priorities. Single consolidated expiry pipeline — all features apply on the 5-minute check. Recommended for all users.

= 1.5.2 =
Compatibility update: confirmed tested against WordPress 7.0.2. No functional changes — safe update.

= 1.5.1 =
SQL security update: All analytics database queries refactored to use fully-static SQL templates with toggle-pattern conditions. Recommended update for WordPress.org compliance.

= 1.5.0 =
Important bug fixes: duplicate metabox in block editor, data consistency between classic and block editor, timezone display, and deprecated function replacements. Recommended update for all users.

= 1.4.1 =
Security and maintainability update: improved settings input sanitization and moved inline admin assets to scoped external files.

= 1.4.0 =
Class files renamed for consistent naming. No functional changes — safe update.

= 1.3.0 =
Complete translation update: all 189 translatable strings now translated across 12 locales. POT template regenerated.

= 1.2.1 =
Removed workflows feature, renamed rules to Smart Expiry Rules, added PHPUnit test suite, updated documentation.

= 1.2.0 =
Major feature release: per-post actions, redirects, smart expiry rules, bulk scheduling, email notifications, SEO handling, analytics dashboard, and block editor support. All features included free.

= 1.1.0 =
New features: "Change to Private" and "Move to Category" expiry actions. Critical bug fixes for time-based expiry and logging.

= 1.0.0 =
Initial release of MSC Post Expiry. Schedule automatic expiration for your posts and pages.
