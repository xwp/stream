=== Stream ===
Contributors: xwp
Tags: wp stream, stream, activity, logs, track
Requires at least: 4.6
Tested up to: 6.6
Stable tag: 4.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

With Stream, you’re never left in the dark about changes to your WordPress site.

== Description ==

With real-time notifications and third-party integrations, Stream can proactively alert you when something goes wrong with your WordPress site.

Designed for debugging and compliance purposes, Stream is useful for keeping tabs on your WordPress users: From activating plugins to deleting posts, to login attempts and new user creation, you can see what’s changed, who changed it and when.

The plugin records WordPress user and system action to the Stream logs.  Every logged-in user action is displayed in an activity stream and organized for easy filtering by User, Role, Context, Action or IP address. Admins can highlight entries in the Stream log—such as suspicious user activity—to investigate what’s happening in real time. Stream also allows you to configure email alerts and webhooks for integrations like Slack and IFTTT to notify you and your team when something has gone wrong.

For advanced users, Stream also supports a network view of all activity records on your Multisite, the ability to set exclude rules to ignore certain kinds of user activity, and a WP‑CLI command for querying records.

With Stream’s powerful logging, you’ll have the valuable information you need to responsibly manage your WordPress sites.


= Built-In Tracking Integrations For Popular Plugins: =

 * Advanced Custom Fields
 * bbPress
 * BuddyPress
 * Easy Digital Downloads
 * Gravity Forms
 * Jetpack
 * User Switching
 * WooCommerce
 * Yoast SEO

= Built-In Tracking For Core Actions: =

 * Posts
 * Pages
 * Custom Post Types
 * Users
 * Themes
 * Plugins
 * Tags
 * Categories
 * Custom Taxonomies
 * Settings
 * Custom Backgrounds
 * Custom Headers
 * Menus
 * Media Library
 * Widgets
 * Comments
 * Theme Editor
 * WordPress Core Updates

= Other Noteworthy Features: =

 * Multisite view of all activity records on a network
 * Limit who can view user activity records by user role
 * Set exclude rules to ignore certain kinds of user activity
 * Live updates of user activity records in the Stream
 * Export your Activity Stream as a CSV or JSON file
 * WP-CLI command for querying records


== Configuration ==

Most of the plugin configuration is available under the "Stream" → "Settings" page in the WordPress dashboard.


= Request IP Address =

The plugin expects the `$_SERVER['REMOTE_ADDR']` variable to contain the verified IP address of the current request. On hosting environments with PHP processing behind reverse proxies or CDNs the actual client IP is passed to PHP through request HTTP headers such as `X-Forwarded-For` and `True-Client-IP` which can't be trusted without an additional layer of validation. Update your server configuration to set the `$_SERVER['REMOTE_ADDR']` variable to the verified client IP address.

As a workaround, you can use the `wp_stream_client_ip_address` filter to adapt the IP address:

`add_filter(
	'wp_stream_client_ip_address',
	function( $client_ip ) {
		// Trust the first IP in the X-Forwarded-For header.
		// ⚠️ Note: This is inherently insecure and can easily be spoofed!
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded_ips = explode( ',' $_SERVER['HTTP_X_FORWARDED_FOR'] );

			if ( filter_var( $forwarded_ips[0], FILTER_VALIDATE_IP ) ) {
				return $forwarded_ips[0];
			}
		}

		return $client_ip;
	}
);`

⚠️ **WARNING:** The above is an insecure workaround that you should only use when you fully understand what this implies. Relying on any variable with the `HTTP_*` prefix is prone to spoofing and cannot be trusted!


== Known Issues ==

 * We have temporarily disabled the data removal feature through plugin uninstallation, starting with version 3.9.3. We identified a few edge cases that did not behave as expected and we decided that a temporary removal is preferable at this time for such an impactful and irreversible operation. Our team is actively working on refining this feature to ensure it performs optimally and securely. We plan to reintroduce it in a future update with enhanced safeguards.


== Contribute ==

There are several ways you can get involved to help make Stream better:

1. **Report Bugs:** If you find a bug, error or other problem, please report it! You can do this by [creating a new topic](https://wordpress.org/support/plugin/stream) in the plugin forum. Once a developer can verify the bug by reproducing it, they will create an official bug report in GitHub where the bug will be worked on.

2. **Translate into Your Language:** Use the official plugin translation tool to [translate Stream into your language](https://translate.wordpress.org/projects/wp-plugins/stream/).

3. **Suggest New Features:** Have an awesome idea? Please share it! Simply [create a new topic](https://wordpress.org/support/plugin/stream) in the plugin forum to express your thoughts on why the feature should be included and get a discussion going around your idea.

4. **Issue Pull Requests:** If you're a developer, the easiest way to get involved is to help out on [issues already reported](https://github.com/x-team/wp-stream/issues) in GitHub. Be sure to check out the [contributing guide](https://github.com/x-team/wp-stream/blob/master/contributing.md) for developers.

Thank you for wanting to make Stream better for everyone!

[View contributors here.](https://github.com/xwp/stream/graphs/contributors)


== Screenshots ==

1. Every logged-in user action is displayed in an activity stream and organized for easy filtering and searching.
2. Enable live updates in Screen Options to watch your site activity appear in near real-time.
3. Create rules for excluding certain kinds of records from appearing in Stream.


== Upgrade Notice ==

= 4.0.0 =

Use only `$_SERVER['REMOTE_ADDR']` as the client IP address for event logs without additional support for `X-Forwarded-For` HTTP request header value which could be spoofed. See the changelog for additional details.


== Changelog ==

= 4.0.2 - August 22, 2024 =

**Security update**

- Fix vulnerability which allowed logged in users to update some site options in certain configurations. Props to [@sybrew](https://github.com/sybrew) for responsibly disclosing this issue.

= 4.0.1 - July 30, 2024 =

**Bug fixes**

- Fix PHP Type error in CLI (in [#1475](https://github.com/xwp/stream/pull/1475)) props [@Soean](https://github.com/Soean)
- Fix Uncaught ValueError in Gravity Forms and WordPress SEO connectors (in [#1508](https://github.com/xwp/stream/pull/1508)) props [@krokodok](https://github.com/krokodok)
- Fix dynamic callback method detection for custom connectors (in [#1469](https://github.com/xwp/stream/pull/1469)) props [@shadyvb](https://github.com/shadyvb)
- Fix PHP warning in PHP 8 by adjusting exclude rules filtering to avoid passing null to `strlen()` (in [#1513](https://github.com/xwp/stream/pull/1513)) props [@ocean90](https://github.com/ocean90)
- Fix adding multiple columns to the stream table using filters only displays the last column correctly (in [#1519](https://github.com/xwp/stream/pull/1519)) props [@thefrosty](https://github.com/thefrosty)
- Fix offset warning in Slack alert when there is no custom logo (in [#1522](https://github.com/xwp/stream/pull/1522)) props [@benerd](https://github.com/benerd)
- Fix BuddyPress Connector, check for BuddyPress dependencies before using (in [#1517](https://github.com/xwp/stream/pull/1517)) props [@dd32](https://github.com/dd32)
- Fix [Security] Update `select2` to `4.0.13` (in [#1495](https://github.com/xwp/stream/pull/1495))

**Development**

- Update local development environment to use Docker (in [#1423](https://github.com/xwp/stream/pull/1423))
- Update `wp-coding-standards/wpcs` and fix all linting issues
- Require PHP ≥ 7.0 and WordPress ≥ 4.6
- Allow switching between PHP 7.4 and PHP 8.2
- Document Connectors (in [#1518](https://github.com/xwp/stream/pull/1518))
- Update dependencies
  - `eslint` to `^8.57.0` (in [#1480](https://github.com/xwp/stream/pull/1480))
  - `@babel/traverse` from `7.20.10` to `7.23.2` (in [#1463](https://github.com/xwp/stream/pull/1463))
  - `braces` from `3.0.2` to `3.0.3` (in [#1487](https://github.com/xwp/stream/pull/1487))
  - `composer/composer` from `2.2.21` to `2.2.24` (in [#1488](https://github.com/xwp/stream/pull/1488))
  - `@wordpress/eslint-plugin` to `v19` (in [#1452](https://github.com/xwp/stream/pull/1452))
  - `@wordpress/eslint-plugin` to `^19.2.0` (in [#1490](https://github.com/xwp/stream/pull/1490))

**Deprecations**

- Deprecate PHP 5.6 (in [#1499](https://github.com/xwp/stream/issues/1499))
- Deprecate `wp_stream_register_column_defaults` filter (in [#1519](https://github.com/xwp/stream/pull/1519))

= 4.0.0 - January 9, 2024 =

- Fix: Use only `$_SERVER['REMOTE_ADDR']` as the reliable client IP address for event logs. This might cause incorrectly reported event log IP addresses on environments where PHP is behind a proxy server or CDN. Use the `wp_stream_client_ip_address` filter to set the correct client IP address (see `readme.txt` for instructions) or configure the hosting environment to report the correct IP address in `$_SERVER['REMOTE_ADDR']` (issue [#1456](https://github.com/xwp/stream/issues/1456), props [@calvinalkan](https://github.com/calvinalkan)).
- Tweak: fix typos in message strings and code comments (fixed in [#1461](https://github.com/xwp/stream/pull/1461) by [@szepeviktor](https://github.com/szepeviktor)).
- Development: use Composer v2 during CI runs (fixed in [#1465](https://github.com/xwp/stream/pull/1465) by [@szepeviktor](https://github.com/szepeviktor)).

= 3.10.0 - October 9, 2023 =

- Fix: Improve PHP 8.1 compatibility by updating `filter_*()` calls referencing `FILTER_SANITIZE_STRING` (issue [#1422](https://github.com/xwp/stream/pull/1422)).
- Fix: prevent PHP deprecation warning when checking for the Stream settings page requests (issue [#1440](https://github.com/xwp/stream/pull/1440)).
- Fix: Add the associated post title to comment events (issue [#1430](https://github.com/xwp/stream/pull/1430)).
- Fix: Use the user associated with a comment instead of the current logged-in user when logging comments (issue [#1429](https://github.com/xwp/stream/pull/1429)).
- Fix: Prevent PHP warnings when no Lead ID present for a Gravity Forms submission (issue [#1447](https://github.com/xwp/stream/pull/1447)).
- Fix: Remove support for legacy WordPress VIP user attribute helpers `get_user_attributes()`, `delete_user_attributes()` and `update_user_attributes()` (issue [#1425](https://github.com/xwp/stream/pull/1425)).
- Development: Document the process for reporting security vulnerabilities (issue [#1433](https://github.com/xwp/stream/pull/1433)).
- Development: Mark as tested with WordPress version 6.3.

= 3.9.3 - April 25, 2023 =

- Fix: [Security] CVE-2022-43490: Temporarily remove uninstall flow to avoid inadvertent uninstallation of the plugin, props [@Lucisu](https://github.com/Lucisu) via [Patchstack](https://patchstack.com/).
- Fix: [Security] CVE-2022-43450: Check for capabilities in 'wp_ajax_load_alerts_settings' AJAX action before loading alert settings, props [@Lucisu](https://github.com/Lucisu) via [Patchstack](https://patchstack.com/).
- Development: Mark as tested with the latest version 6.2 of WordPress.

= 3.9.2 - January 10, 2023 =

- Fix: [Security] Check authorization on 'save_new_alert' AJAX action [#1391](https://github.com/xwp/stream/pull/1391), props [marcS0H](https://github.com/marcS0H) (WPScan)
- Development: Mark as tested with the latest version 6.1 of WordPress.
- Development: Update development dependencies.

= 3.9.1 - August 23, 2022 =

- Fix: PHP 8 compatibility for widget connector [#1294](https://github.com/xwp/stream/pull/1355), props [@ParhamG](https://github.com/ParhamG)
- Development: Mark as tested with the latest version 6.0 of WordPress.
- Development: Update development dependencies.

= 3.9.0 - March 8, 2022 =

- Fix: Track changes to posts when using the block editor by making the Posts connector to run on both frontend and backend requests since block editor changes happen over the REST API [#1264](https://github.com/xwp/stream/pull/1264), props [@coreymckrill](https://github.com/coreymckrill).
- Fix: Don't store empty log event parameters [#1307](https://github.com/xwp/stream/pull/1307), props [@lkraav](https://github.com/lkraav).
- Development: Adjust the local development environment to use MariaDB containers for ARM processor compatibility.

[See the full changelog here.](https://github.com/xwp/stream/blob/master/changelog.md)
