=== MSC Post Expiry ===
Contributors: anomalous
Tags: post-expiry,content,scheduling,automation
Requires at least: 5.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically expire posts and pages on a scheduled date. Set expiration dates in the post editor and let the plugin handle the rest.

== Description ==

MSC Post Expiry allows you to schedule automatic expiration for your posts and pages. Set an expiration date and time, and the plugin will automatically process the post when it expires.

**Features:**

* Schedule post expiration dates and times
* Choose expiration action: move to trash, permanently delete, convert to draft, change to private, move to category, or redirect only
* Per-post expiry action override
* Custom redirect URLs for expired posts
* Conditional expiry rules (by category, tag, author, age, custom field)
* Bulk expiry scheduling from the Posts list
* Email notifications before posts expire
* SEO handling for expired posts (noindex, canonical, status codes)
* Expiry analytics dashboard with charts
* Action history log
* Configure which post types support expiry
* Block editor sidebar panel
* Automatic processing via WordPress cron
* Full internationalization support with 12 languages
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

= How do I set an expiration date for a post? =

When editing a post or page, look for the "Post Expiry" panel in the block editor sidebar (or the metabox in the classic editor). Enter the date and time when you want the post to expire.

= What happens when a post expires? =

The plugin will perform one of several actions based on your settings:
* **Move to Trash** - The post is moved to trash and no longer visible to visitors
* **Permanently Delete** - The post is permanently deleted from your site
* **Change to Draft** - The post is changed to draft status and hidden from visitors
* **Change to Private** - The post is changed to private status
* **Move to Category** - The post is moved to a selected category
* **Redirect Only** - The post stays published but visitors are redirected to a specified URL

= Can I set a different expiry action per post? =

Yes! Each post can have its own expiry action, redirect URL, and target category. These override the global default.

= Can I get notified before a post expires? =

Yes. Enable email notifications in Settings. You can choose to notify the post author, site admin, or both, and configure how many days before expiry the notification is sent.

= What are Smart Expiry Rules? =

Smart Rules let you define automatic actions based on post properties. For example: "If a post is in the News category, move it to draft when it expires" or "If a post is older than 90 days, delete it permanently." Rules are checked in priority order when a post expires, and the first matching rule overrides the default action.

= How does the SEO feature work? =

When a post expires, the plugin can automatically add noindex/nofollow meta tags, set a canonical URL (to home or category), and return a 410 Gone status code. Configure these in the SEO tab.

= How often does the plugin check for expired posts? =

The plugin checks for expired posts every 5 minutes (legacy date/time system) and every 15 minutes (timestamp system) using WordPress cron.

= Can I choose which post types support expiry? =

Yes! Go to Settings > MSC Post Expiry to configure which post types support expiration.

= Is there a way to check if a post is expired? =

Yes, developers can use the helper functions:
* `mscpe_is_post_expired( $post_id )` - Check if a post is expired
* `mscpe_get_expiry_datetime( $post_id )` - Get the expiry date and time
* `mscpe_get_expiry_status( $post_id )` - Get human-readable expiry status
* `mscpe_format_expiry_datetime( $date, $time )` - Format expiry date for display

= Does this plugin work with custom post types? =

Yes! You can configure the plugin to work with any public post type, including custom post types.

= What languages are supported? =

The plugin includes translations for 12 languages: German (Germany and Switzerland), Spanish (Spain and Mexico), French (France and Canada), Italian, Japanese, Dutch (Netherlands and Belgium), and Portuguese (Portugal and Brazil).

== Installation ==

1. Upload the plugin to `/wp-content/plugins/`.
2. Activate in the WordPress plugins screen.
3. Go to Settings > MSC Post Expiry to configure the plugin.
4. When editing a post or page, use the "Post Expiry" panel to set expiration dates.

== Changelog ==

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
