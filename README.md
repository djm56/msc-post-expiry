# MSC Post Expiry

![Version](https://img.shields.io/badge/version-1.7.0-blue)
![License](https://img.shields.io/badge/license-GPL--2.0%2B-green)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)
![WordPress](https://img.shields.io/badge/WordPress-5.9%2B-blue)
![Tested up to](https://img.shields.io/badge/tested%20up%20to-7.0-blue)

Automatically expire posts and pages on a scheduled date. Set expiration dates in the post editor and let the plugin handle the rest.

**All features are free. There is no premium version.**

## Index

- [Features](#features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Setting Expiration Dates](#setting-expiration-dates)
- [Developer Reference](#developer-reference)
- [Development](#development)
- [Changelog](#changelog)
- [License](#license)

## Features

- **Scheduled Expiry** — Set an expiry date and time per post via the block editor sidebar or the classic editor metabox
- **Six Expiry Actions** — Move to Trash, Permanently Delete, Change to Draft, Change to Private, Move to Category, or Redirect Only (keep published)
- **Per-Post Overrides** — Override the action, redirect URL, or target category on an individual post
- **Smart Rules** — Apply different actions based on category, tag, author, post age, or custom fields
- **Bulk Scheduling** — Set expiry for many posts at once from the Posts list, with a configurable default window
- **Quick Edit** — Adjust expiry without opening the editor
- **Email Notifications** — Notify the author, the admin, or both a configurable number of days before expiry
- **SEO Handling** — Add `noindex`/`nofollow`, set a canonical target, and return a chosen HTTP status for expired posts
- **Analytics Dashboard** — Expiry trends, action breakdowns, and top categories/authors rendered with Chart.js
- **Action History** — The most recent expiry actions, with 30-day log retention
- **Post Type Control** — Include selected post types, or exclude selected types from all public types
- **Automatic Processing** — WordPress cron checks for expired posts every 5 minutes (date/time) and 15 minutes (timestamp)
- **Developer-Friendly** — Helper functions, actions, and filters for every stage of the expiry flow
- **12 Languages** — German (DE/CH), Spanish (ES/MX), French (FR/CA), Italian, Japanese, Dutch (NL/BE), Portuguese (BR/PT)

## Installation

### From WordPress Admin

1. Download the plugin zip file
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload the zip and click **Install Now**
4. Click **Activate**

### Manual Installation

1. Upload the `msc-post-expiry` folder to `/wp-content/plugins/`
2. Activate via the **Plugins** menu in WordPress

### Post-Activation

1. Go to **Settings → MSC Post Expiry**
2. Choose the post types that support expiry and the default expiry action
3. Confirm WordPress cron is running — expiry processing depends on it

## Configuration

The plugin has 6 settings tabs:

### Settings Tab

| Option | Description | Default |
|--------|-------------|---------|
| Enable Post Expiry | Master toggle for the plugin | Enabled |
| Post Type Mode | Include selected post types, or exclude selected types from all public types | Include selected |
| Post Types | The post types that support expiry | `post`, `page` |
| Expiry Action | Default action when a post expires | Move to Trash |
| Expiry Category | Target category for the Move to Category action | None |
| Enable Redirects | Allow per-post redirect URLs | Disabled |
| Bulk Default Days | Default expiry window for bulk scheduling | 30 |
| Enable Notifications | Send an email before a post expires | Disabled |
| Notify Days Before | How many days ahead of expiry to send the email | 3 |
| Notify Recipients | Author, admin, or both | Author |
| Enable Logging | Record expiry actions to the history log | Enabled |

### SEO Tab

| Option | Description | Default |
|--------|-------------|---------|
| Add noindex | Send `noindex` for expired posts that stay published | Enabled |
| Add nofollow | Send `nofollow` for expired posts that stay published | Disabled |
| Canonical Strategy | Where the canonical URL points: none, homepage, or the redirect target | None |
| HTTP Status Code | Status returned for an expired post (e.g. `200`, `410`) | `200` |

### Smart Rules Tab

Define rules that pick an expiry action from post properties — category, tag, author, post age, or a custom field. Rules are evaluated before the default action applies.

### Analytics Tab

Charts for expiry volume over time, a breakdown by action, and the top categories and authors by expiry count.

### History Tab

The most recent expiry actions with post, action, and timestamp.

### Support Tab

Setup instructions, feature explanations, FAQ, and contact info.

## Setting Expiration Dates

**Block editor**

1. Open the post
2. Find the **Post Expiry** panel in the document sidebar
3. Set the expiry date and time
4. Save the post

**Classic editor**

1. Find the **Post Expiry** metabox in the sidebar
2. Enter the expiry date and time
3. Update the post

**Bulk / Quick Edit** — select posts in the Posts list and use the bulk action, or set the date inline through Quick Edit.

## Developer Reference

### Constants

| Constant | Value | Description |
|----------|-------|-------------|
| `MSCPE_PLUGIN_VERSION` | `'1.7.0'` | Current plugin version |
| `MSCPE_PLUGIN_FILE` | `__FILE__` | Absolute path to main plugin file |
| `MSCPE_PLUGIN_DIR` | Plugin directory path | Absolute path to plugin directory |
| `MSCPE_PLUGIN_URL` | Plugin directory URL | URL to plugin directory |

### Plugin Options

Core options are stored as a single serialised array under `mscpe_options`. Access via:

```php
$options = get_option( 'mscpe_options' );
```

Or via the plugin API:

```php
$plugin = MSCPE\Plugin::instance();
$value  = $plugin->get_option( 'expiry_action', 'trash' );
```

| Option Key | Type | Default | Description |
|------------|------|---------|-------------|
| `module_enabled` | `int` | `1` | Enable/disable post expiry (1/0) |
| `post_types` | `array` | `['post','page']` | Post types that support expiry |
| `post_type_mode` | `string` | `'include'` | `include` or `exclude` |
| `expiry_action` | `string` | `'trash'` | `trash`, `delete`, `draft`, `private`, `category`, `redirect_only` |
| `expiry_category` | `int` | `0` | Target category for the `category` action |
| `redirect_enabled` | `int` | `0` | Allow per-post redirect URLs (1/0) |
| `bulk_default_days` | `int` | `30` | Default expiry window for bulk scheduling |
| `notify_enabled` | `int` | `0` | Send pre-expiry email notifications (1/0) |
| `notify_days_before` | `int` | `3` | Days before expiry to send the email |
| `notify_recipients` | `string` | `'author'` | `author`, `admin`, or `both` |
| `log_enabled` | `int` | `1` | Record expiry actions to the history log (1/0) |

SEO options live separately under `mscpe_seo_options`:

| Option Key | Type | Default | Description |
|------------|------|---------|-------------|
| `noindex_enabled` | `int` | `1` | Add `noindex` for expired posts (1/0) |
| `nofollow_enabled` | `int` | `0` | Add `nofollow` for expired posts (1/0) |
| `canonical_strategy` | `string` | `'none'` | `none`, `homepage`, or the redirect target |
| `status_code` | `string` | `'200'` | HTTP status returned for an expired post |

Smart rules are stored under `mscpe_rules`, and the action history under `mscpe_action_log`.

### Helper Functions

#### `mscpe_get_expiry_datetime( $post_id = 0 )`

Get the expiry date and time for a post.

```php
$expiry = mscpe_get_expiry_datetime( $post_id );
if ( $expiry ) {
    echo 'Expires on: ' . $expiry['date'] . ' at ' . $expiry['time'];
}
```

#### `mscpe_is_post_expired( $post_id = 0 )`

Check whether a post has passed its expiry time.

```php
if ( mscpe_is_post_expired( $post_id ) ) {
    echo 'This post has expired.';
}
```

#### `mscpe_get_expiry_status( $post_id = 0 )`

Get a human-readable expiry status.

```php
echo mscpe_get_expiry_status( $post_id );
// "5 days remaining", "Expired", or "No expiry set"
```

#### `mscpe_format_expiry_datetime( $date, $time = '00:00' )`

Format an expiry date and time for display.

```php
$formatted = mscpe_format_expiry_datetime( '2026-04-15', '14:30' );
```

### Custom Actions

| Action | Parameters | Description |
|--------|------------|-------------|
| `mscpe_before_expire_post` | `int $post_id`, `string $action` | Fires before a single post is expired |
| `mscpe_after_expire_post` | `int $post_id`, `string $action` | Fires after a single post has been expired |
| `mscpe_before_process_expired_posts` | — | Fires before a cron batch of expired posts is processed |
| `mscpe_after_process_expired_posts` | — | Fires after a cron batch has been processed |
| `mscpe_settings_sections` | — | Fires inside the settings form. Use to add custom fields |
| `mscpe_settings_save` | `array $posted` | Fires when settings are saved. Use to persist custom fields |
| `mscpe_settings_before_extensions` | — | Fires before the extensions area of the settings page |
| `mscpe_tab_content` | `string $current_tab` | Fires on custom tabs. Use to render custom tab content |

```php
add_action( 'mscpe_after_expire_post', function( $post_id, $action ) {
    // Post has been expired.
}, 10, 2 );
```

### Plugin Filters

| Filter | Parameters | Description |
|--------|------------|-------------|
| `mscpe_expiry_actions` | `array $actions` | The available expiry actions shown in the editor and settings |
| `mscpe_tabs` | `array $tabs` | The settings page tabs |
| `mscpe_enable_logging` | `bool $enabled` | Whether expiry actions are logged |
| `mscpe_cron_log_retention_days` | `int $days` | Log retention in days. Default `30` |

```php
add_filter( 'mscpe_expiry_actions', function( $actions ) {
    $actions['custom'] = __( 'My Custom Action', 'my-plugin' );
    return $actions;
} );
```

### Cron Events

| Hook | Recurrence | Purpose |
|------|------------|---------|
| `mscpe_process_expired_posts` | `mscpe_5min` | Process posts with a date/time expiry |
| `mscpe_process_expiry_advanced` | `mscpe_15min` | Process posts with a timestamp expiry |

If your host has unreliable WP-Cron, drive it from a real cron job:

```bash
*/5 * * * * curl https://yoursite.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1
```

### Database Tables

| Table | Purpose |
|-------|---------|
| `{prefix}mscpe_analytics` | Expiry events used by the Analytics tab |
| `{prefix}mscpe_rules` | Smart rules definitions |

### Uninstall Behaviour

On plugin deletion (not deactivation), the plugin removes:

- `mscpe_options`, `mscpe_seo_options`, `mscpe_rules`, `mscpe_action_log` from `wp_options`
- `mscpe_db_version`, `mscpe_activated_time`, `mscpe_review_dismissed` from `wp_options`
- All `mscpe_` and `_mscpe_` post meta
- The `{prefix}mscpe_analytics` and `{prefix}mscpe_rules` tables
- Scheduled cron events

## Development

### Requirements

- PHP 7.4+
- Composer
- MySQL/MariaDB (for tests)
- WP-CLI (for .pot generation)

### Setup

```bash
cd msc-post-expiry
composer install
```

### Linting

```bash
# Check coding standards (WordPress-Core, WordPress-Docs, WordPress-Extra)
composer lint

# Auto-fix fixable issues
composer lint-fix
```

### Testing

The plugin includes 99 PHPUnit tests covering the plugin core, expiry processing, cron, smart rules, SEO, settings, analytics, and uninstall.

```bash
# Run all tests
composer test

# Run with readable output
vendor/bin/phpunit --testdox

# Run a specific test file
vendor/bin/phpunit tests/Test_Rules.php

# Run a specific test
vendor/bin/phpunit --filter test_rule_matches_category
```

**Test files:**

| File | Tests | Covers |
|------|-------|--------|
| `Test_Plugin.php` | 16 | Singleton, activation, options, cron registration |
| `Test_Module.php` | 9 | Expiry processing, redirects, bulk, notifications |
| `Test_Cron.php` | 8 | Cron scheduling and legacy date/time processing |
| `Test_Settings.php` | 13 | Options, validation, tab saves |
| `Test_Rules.php` | 18 | Smart rule matching and action resolution |
| `Test_SEO.php` | 10 | noindex/nofollow, canonical, status codes |
| `Test_Helper_Functions.php` | 9 | Public helper functions |
| `Test_Migrations.php` | 5 | Database migrations |
| `Test_Analytics.php` | 3 | Analytics recording and aggregation |
| `Test_Uninstall.php` | 8 | Data removal on deletion |

### Translations

The plugin ships with 12 translations. To update:

```bash
# Regenerate the .pot template from source PHP files (requires WP-CLI)
composer run i18n:pot

# Compile all .po files in the languages directory
wp i18n make-mo languages/
```

**Supported locales:** de_DE, de_CH, es_ES, es_MX, fr_FR, fr_CA, it_IT, ja, nl_NL, nl_BE, pt_BR, pt_PT

### Composer Scripts

| Script | Command | Description |
|--------|---------|-------------|
| `lint` | `composer lint` | Run PHP_CodeSniffer |
| `lint-fix` | `composer lint-fix` | Auto-fix coding standard issues |
| `i18n:pot` | `composer run i18n:pot` | Regenerate .pot file from source |
| `test` | `composer test` | Run PHPUnit tests |

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## License

GPL-2.0+ — see [LICENSE](LICENSE) for details.
