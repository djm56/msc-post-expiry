# MSC Post Expiry

Automatically expire posts and pages on a scheduled date. Set expiration dates in the post editor and let the plugin handle the rest.

## Overview

MSC Post Expiry allows you to schedule automatic expiration for your posts and pages. Set an expiration date and time, and the plugin will automatically process the post when it expires — with full control over what happens next.

### Features

- **Schedule Post Expiration**: Set expiration dates via block editor sidebar panel or classic editor metabox
- **Flexible Expiration Actions**: Choose what happens when a post expires:
  - Move to Trash
  - Permanently Delete
  - Convert to Draft
  - Change to Private
  - Move to Category
  - Redirect Only (keep published, redirect visitors)
- **Per-Post Overrides**: Set a different action, redirect URL, or target category on individual posts
- **Conditional Rules**: Define rules to apply different actions based on category, tag, author, post age, or custom fields
- **Bulk Scheduling**: Set expiry for multiple posts at once from the Posts list
- **Email Notifications**: Get notified before posts expire (author, admin, or both)
- **SEO Handling**: Automatically add noindex/nofollow, set canonical URLs, and return 410 status codes for expired posts
- **Analytics Dashboard**: Track expiry trends, action breakdowns, top categories/authors with Chart.js charts
- **Action History**: View the last 50 expiry actions in the History tab
- **Post Type Configuration**: Enable expiry on specific post types or all public post types
- **Automatic Processing**: WordPress cron checks for expired posts every 5–15 minutes
- **Comprehensive Logging**: Detailed logs of all expiry actions with 30-day retention
- **Block Editor Support**: Native sidebar panel for the block editor
- **Developer-Friendly**: Helper functions, hooks, and filters
- **Internationalization Ready**: Full translation support with 12 languages included

### Use Cases

- Temporary promotional content that should disappear automatically
- Time-sensitive announcements and news
- Seasonal content management
- Event posts that should be archived
- Automatic content cleanup
- Redirect expired offers to current landing pages
- Notify authors before their posts expire
- SEO-safe content expiration

## Installation

### From WordPress.org (Recommended)

1. Go to **Plugins > Add New** in your WordPress admin
2. Search for "MSC Post Expiry"
3. Click **Install Now**
4. Click **Activate**
5. Go to **Settings > MSC Post Expiry** to configure

### Manual Installation

1. Download the plugin ZIP file
2. Upload the `msc-post-expiry` folder to `/wp-content/plugins/`
3. Activate the plugin through the **Plugins** screen
4. Go to **Settings > MSC Post Expiry** to configure

## Configuration

### Settings Page

Navigate to **Settings > MSC Post Expiry** to configure:

1. **Enable Post Expiry**: Toggle to enable/disable the plugin
2. **Post Type Mode**: Choose to enable expiry on specific post types or all except selected
3. **Post Types**: Select which post types support expiry
4. **Expiry Action**: Default action when posts expire
5. **Redirect Settings**: Enable per-post redirect URLs
6. **Bulk Scheduling**: Default expiry window (days) for bulk actions
7. **Notifications**: Email notification settings (recipients, timing)
8. **Logging**: Enable/disable action history

### Settings Tabs

- **Settings** — Core expiry configuration
- **SEO** — Configure noindex, nofollow, canonical, and HTTP status codes
- **Smart Rules** — Define smart rules based on post properties
- **Analytics** — Dashboard with charts showing expiry trends and breakdowns
- **History** — View recent expiry action log
- **Support** — Help documentation and FAQ

### Setting Expiration Dates

**Block Editor:**
1. Open the post in the block editor
2. Look for the **Post Expiry** panel in the document sidebar
3. Set the expiration date and time
4. Save the post

**Classic Editor:**
1. Look for the **Post Expiry** metabox in the sidebar
2. Enter the expiration date and time
3. Update the post

## Developer Guide

### Helper Functions

#### `mscpe_get_expiry_datetime( $post_id )`

Get the expiry date and time for a post.

```php
$expiry = mscpe_get_expiry_datetime( $post_id );
if ( $expiry ) {
    echo 'Expires on: ' . $expiry['date'] . ' at ' . $expiry['time'];
}
```

#### `mscpe_is_post_expired( $post_id )`

Check if a post is expired.

```php
if ( mscpe_is_post_expired( $post_id ) ) {
    echo 'This post has expired!';
}
```

#### `mscpe_get_expiry_status( $post_id )`

Get human-readable expiry status.

```php
echo mscpe_get_expiry_status( $post_id );
// Output: "5 days remaining" or "Expired" or "No expiry set"
```

#### `mscpe_format_expiry_datetime( $date, $time )`

Format expiry date and time for display.

```php
$formatted = mscpe_format_expiry_datetime( '2026-04-15', '14:30' );
```

### Hooks and Filters

#### `mscpe_before_expire_post`

Fires before expiring a single post.

```php
add_action( 'mscpe_before_expire_post', function( $post_id, $action ) {
    // $post_id = post ID, $action = expiry action
}, 10, 2 );
```

#### `mscpe_after_expire_post`

Fires after expiring a single post.

```php
add_action( 'mscpe_after_expire_post', function( $post_id, $action ) {
    // Post has been expired
}, 10, 2 );
```

#### `mscpe_expiry_actions`

Filter available expiry actions.

```php
add_filter( 'mscpe_expiry_actions', function( $actions ) {
    $actions['custom'] = __( 'My Custom Action', 'my-plugin' );
    return $actions;
} );
```

#### `mscpe_tabs`

Filter settings page tabs.

```php
add_filter( 'mscpe_tabs', function( $tabs ) {
    $tabs[] = array( 'slug' => 'custom', 'label' => 'Custom Tab' );
    return $tabs;
} );
```

### Logging

The plugin logs all expiry actions to:

```
/wp-content/uploads/msc-post-expiry-logs/
```

Log files are automatically rotated after 30 days.

## Requirements

- **WordPress**: 5.9 or higher
- **PHP**: 7.4 or higher
- **License**: GPL-2.0+

## Frequently Asked Questions

### How often does the plugin check for expired posts?

Every 5 minutes (date/time-based expiry) and every 15 minutes (timestamp-based expiry) using WordPress cron.

### What if my site doesn't have reliable cron?

Set up a real cron job:

```bash
*/5 * * * * curl https://yoursite.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1
```

### Does this work with custom post types?

Yes! Configure in Settings which post types support expiry.

### What languages are supported?

12 languages: German (DE/CH), Spanish (ES/MX), French (FR/CA), Italian, Japanese, Dutch (NL/BE), and Portuguese (PT/BR).

## File Structure

```
msc-post-expiry/
├── msc-post-expiry.php              # Main plugin file
├── uninstall.php                    # Cleanup on uninstall
├── readme.txt                       # WordPress.org readme
├── README.md                        # Development readme
├── CHANGELOG.md                     # Version history
├── LICENSE                          # GPL-2.0+ license
├── includes/
│   ├── class-mscpe-plugin.php              # Main plugin class
│   ├── class-mscpe-settings.php            # Settings, metabox, tabs
│   ├── class-mscpe-module.php              # Expiry processing, redirects, bulk, notifications
│   ├── class-mscpe-cron.php               # Legacy cron processing (date/time)
│   ├── class-mscpe-migrations.php         # Database migrations
│   ├── class-mscpe-seo.php               # SEO handling
│   ├── class-mscpe-rules.php             # Smart expiry rules engine
│   └── class-mscpe-analytics.php         # Analytics tracking and dashboard
├── assets/
│   ├── css/
│   │   ├── admin-components.css
│   │   └── admin-tokens.css
│   └── js/
│       └── expiry-sidebar.js        # Block editor sidebar panel
├── languages/                       # Translation files (.po/.mo)
├── tests/                           # PHPUnit test suite
└── vendor/                          # Composer dependencies (dev only)
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for full version history.

## License

This plugin is licensed under the [GPL-2.0+ License](LICENSE).

## Credits

**Developed by**: [Anomalous Developers](https://anomalous.co.za)

## Support

For support, questions, or feature requests, please visit:

- **Website**: https://anomalous.co.za

**Status**: Production Ready  
**Version**: 1.4.1  
**Last Updated**: June 1, 2026
