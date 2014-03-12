=== Stream ===
Contributors:      X-team, shadyvb, fjarrett, jonathanbardo, johnregan3, akeda, kucrut, topher1kenobe, powelski
Tags:              actions, activity, admin, analytics, dashboard, log, notification, stream, users
Requires at least: 3.7
Tested up to:      3.8.1
Stable tag:        trunk
License:           GPLv2 or later
License URI:       http://www.gnu.org/licenses/gpl-2.0.html

Stream tracks logged-in user activity so you can monitor every change made on your WordPress site in beautifully organized detail.

== Description ==

[youtube http://www.youtube.com/watch?v=H9TnZMUE_Y8]

**Note: This plugin requires PHP 5.3 or higher to be activated.**

Never be in the dark about WP Admin activity again. Stream allows you to know exactly when changes to your site have been made, and more importantly, who did them.

Every logged-in user action is logged in a user activity stream and organized for easy filtering by connector, context, action and IP address.

Built with performance in mind, Stream won't pollute your default posts table with records or slow down content querying on your site.

Stream is built to extend, allowing developers to easily build their own connectors to track any type of action in the activity stream (developer documentation coming soon).

**Recorded activity:**

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
 * WordPress Core Updates

**Noteworthy features:**

 * Dashboard widget of most recent user activity
 * Limit who can view user activity records by user role
 * UI to exclude specific types of user activity from being tracked
 * Live update of user activity records in the Stream
 * Private RSS and JSON feeds of user activity records
 * Set how long records should live before being purged automatically
 * Option to manually purge all user activity records from the database
 * Disable connectors where you don't want user activity tracked
 * Support for IPv6 addresses

**Languages:**

 * English
 * French (France)
 * German
 * Indonesian
 * Polish
 * Portuguese (Brazil)
 * Spanish (Spain)

**Coming soon:**

 * Limit who can view user activity records by user, not just by role
 * Multisite view of all activity records on a network
 * Language support for Arabic (RTL), Czech and Slovak

**See room for improvement?**

Great! There are several ways you can get involved to help make Stream better:

1. **Report Bugs:** If you find a bug, error or other problem, please report it! You can do this by [creating a new topic](http://wordpress.org/support/plugin/stream) in the plugin forum. Once a developer can verify the bug by reproducing it, they will create an official bug report in GitHub where the bug will be worked on.
2. **Suggest New Features:** Have an awesome idea? Please share it! Simply [create a new topic](http://wordpress.org/support/plugin/stream) in the plugin forum to express your thoughts on why the feature should be included and get a discussion going around your idea.
3. **Issue Pull Requests:** If you're a developer, the easiest way to get involved is to help out on [issues already reported](https://github.com/x-team/wp-stream/issues) in GitHub. Be sure to check out the [contributing guide](https://github.com/x-team/wp-stream/blob/master/contributing.md) for developers.

Thank you for wanting to make Stream better for everyone! We salute you.

== Screenshots ==

1. Every logged-in user action is logged in the activity stream and organized for easy filtering and searching.
2. Enable live updates in Screen Options to watch your site activity flow into the Stream in real-time.
3. Control which user roles have their activity tracked and which user roles can access Stream.
4. Enable private feed access for your activity Stream, determine how long records should live before being purged, or purge them from the database manually at any time.

== Changelog ==

= 1.3.0 =
**2014/03/11** - New "Exclude" tab to prevent specific activity from being tracked. Now logging Custom Background and Custom Header changes. User avatars now displayed in dropdown filter. Bug fixes and UI improvements. Props [powelski](http://profiles.wordpress.org/powelski/), [faishal](http://profiles.wordpress.org/faishal/), [fjarrett](http://profiles.wordpress.org/fjarrett/), [desaiuditd](http://profiles.wordpress.org/desaiuditd/), [shadyvb](http://profiles.wordpress.org/shadyvb/)

= 1.2.9 =
**2014/03/08** - Fixes bug that caused media uploads to fail on new posts. Props [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.2.8 =
**2014/03/07** - Use attachment type as context in Media connector. Bug fixes. Props [lukecarbis](http://profiles.wordpress.org/lukecarbis/), [powelski](http://profiles.wordpress.org/powelski/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.2.7 =
**2014/03/04** - Pagination added to Stream Activity dashboard widget. Bug fixes. Props [chacha](https://github.com/chacha/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.2.6 =
**2014/02/28** - Improved context names in Users connector. Props [powelski](http://profiles.wordpress.org/powelski/)

= 1.2.5 =
**2014/02/27** - Use sidebar area names as context in Widgets connector. Bug fixes. Props [desaiuditd](http://profiles.wordpress.org/desaiuditd/), [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/), [bordoni](http://profiles.wordpress.org/bordoni/)

= 1.2.4 =
**2014/02/25** - Use post type names as context in Comments connector. German translation update. Bug fixes. Props [powelski](http://profiles.wordpress.org/powelski/), [kucrut](http://profiles.wordpress.org/kucrut/), [pascalklaeres](http://profiles.wordpress.org/pascalklaeres/), [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.2.3 =
**2014/02/21** - Replacement function for filter_input family to avoid PHP bug. Filter added to main Stream query. Bug fixes. Props [shadyvb](http://profiles.wordpress.org/shadyvb/), [powelski](http://profiles.wordpress.org/powelski/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.2.2 =
**2014/02/19** - Prevent records of disabled connectors from appearing in the Stream. Bug fixes. Props [kucrut](http://profiles.wordpress.org/kucrut/), [johnregan3](http://profiles.wordpress.org/johnregan3/)

= 1.2.1 =
**2014/02/17** - Translation updates. Langage packs for pt_BR and id_ID. Bug fixes. Props [kucrut](http://profiles.wordpress.org/kucrut/), [shadyvb](http://profiles.wordpress.org/shadyvb/), [bordoni](http://profiles.wordpress.org/webord/), [powelski](http://profiles.wordpress.org/powelski/), [omniwired](https://github.com/omniwired), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.2.0 =
**2014/02/12** - Awesome datepicker styles. Performance optimizations. Bug fixes. Props [johnregan3](http://profiles.wordpress.org/johnregan3/), [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/), [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/)

= 1.1.9 =
**2014/02/10** - Load authors filter using AJAX if there are more than 50. Props [powelski](http://profiles.wordpress.org/powelski/)

= 1.1.8 =
**2014/02/09** - Bug fixes. Props [shadyvb](http://profiles.wordpress.org/shadyvb/)

= 1.1.7 =
**2014/02/06** - Upgrade routine for IPv6 support. Persist tab selection after saving Stream Settings. Props [shadyvb](http://profiles.wordpress.org/shadyvb/), [dero](https://github.com/dero)

= 1.1.6 =
**2014/02/06** - Sortable columns bug fix on the records screen. Props [powelski](http://profiles.wordpress.org/powelski/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.1.5 =
**2014/02/05** - Fixed a class scope bug [reported in the support forum](http://wordpress.org/support/topic/temporary-fatal-error-after-upgrade-113) that was causing a fatal error on some installs. Props [shadyvb](http://profiles.wordpress.org/shadyvb/)

= 1.1.4 =
**2014/02/05** - Highlight changed settings field feature. DB upgrade routine for proper utf-8 charset. Various bug fixes. Props [powelski](http://profiles.wordpress.org/powelski/), [johnregan3](http://profiles.wordpress.org/johnregan3/), [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.1.3 =
**2014/02/04** - Upgrade routine for IP column in DB. Serialized option parsing for Stream Settings records. Purge records immediately when TTL is set backwards in Stream Settings. Various bug fixes. Props [shadyvb](http://profiles.wordpress.org/shadyvb/), [powelski](http://profiles.wordpress.org/powelski/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.1.2 =
**2014/02/02** - Bug fix for list table notice on new installations. Props [shadyvb](http://profiles.wordpress.org/shadyvb/)

= 1.1.0 =
**2014/01/31** - Disable terms in dropdown filters for which records do not exist. Props [johnregan3](http://profiles.wordpress.org/johnregan3/)

= 1.0.9 =
**2014/01/31** - Several important bug fixes. Props [shadyvb](http://profiles.wordpress.org/shadyvb/)

= 1.0.8 =
**2014/01/30** - Bug fix for sites using BuddyPress. Props [johnregan3](http://profiles.wordpress.org/johnregan3/)

= 1.0.7 =
**2014/01/29** - Code efficiency improvements when fetching admin area URLs. Props [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.0.6 =
**2014/01/28** - Query improvements, default connector interface, hook added for general settings fields. Bug fixes. Props [dero](https://github.com/dero), [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/), [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.0.5 =
**2014/01/27** - Bug fix for live updates breaking columns when some are hidden via Screen Options. Props [johnregan3](http://profiles.wordpress.org/johnregan3/)

= 1.0.4 =
**2014/01/23** - Language pack for Polish. Bug fixes. Props [powelski](http://profiles.wordpress.org/powelski/), [fjarrett](http://profiles.wordpress.org/fjarrett/), [johnregan3](http://profiles.wordpress.org/johnregan3/), [kucrut](http://profiles.wordpress.org/kucrut/)

= 1.0.3 =
**2014/01/19** - Language pack for Spanish. Bug fixes. Props [omniwired](https://github.com/omniwired), [shadyvb](http://profiles.wordpress.org/shadyvb/)

= 1.0.2 =
**2014/01/15** - Ensure the dashboard widget repects the Role Access setting. Props [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.0.1 =
**2014/01/15** - Require nonce for generating a new user feed key. Props [johnregan3](http://profiles.wordpress.org/johnregan3/)

= 1.0.0 =
**2014/01/13** - Allow list table to be exensible. Hook added to prevent tables from being created, if desired. Props [johnregan3](http://profiles.wordpress.org/johnregan3/), [fjarrett](http://profiles.wordpress.org/fjarrett/), [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/)

= 0.9.9 =
**2014/01/08** - Updated screenshot assets and descriptions. Props [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.9.8 =
**2014/01/01** - Support for live updates in the Stream. Bug fixes. Props [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/), [johnregan3](http://profiles.wordpress.org/johnregan3/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.9.7 =
**2013/12/29** - Plugin version available as a constant. Bug fixes. Props [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.9.6 =
**2013/12/29** - Use menu name as context in Menus connector. Warning if required DB tables are missing. Bug fixes. Props [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/), [fjarrett](http://profiles.wordpress.org/fjarrett/), [topher1kenobe](http://profiles.wordpress.org/topher1kenobe/)

= 0.9.5 =
**2013/12/22** - WordPress context added to Installer connector for core updates. Props [shadyvb](http://profiles.wordpress.org/shadyvb/)

= 0.9.3 =
**2013/12/22** - Replacing Chosen library with Select2. Bug fixes. Props [kucrut](http://profiles.wordpress.org/kucrut/), [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.9.2 =
**2013/12/22** - Added support for private feeds in JSON format. Flush rewrite rules automatically for feeds when enabled/disabled. Bug fixes. Props [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.9.1 =
**2013/12/21** - Specify which roles should have their activity logged. Delete all options on uninstall. Bug fixes. Props [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.9.0 =
**2013/12/20** - Added connector for Comments. Stream activity dashboard widget. UI enhancements. Bug fixes. Props [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/), [fjarrett](http://profiles.wordpress.org/fjarrett/), [shadyvb](http://profiles.wordpress.org/shadyvb/), [topher1kenobe](http://profiles.wordpress.org/topher1kenobe/)

= 0.8.2 =
**2013/12/19** - Language packs for French and German. Option to uninstall database tables. Bug fixes. Props [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/), [fjarrett](http://profiles.wordpress.org/fjarrett/), [topher1kenobe](http://profiles.wordpress.org/topher1kenobe/), [pascalklaeres](http://profiles.wordpress.org/pascalklaeres/)

= 0.8.1 =
**2013/12/18** - Setting to enable/disable private feeds functionality. Additional record logged when a user's role is changed. Bug fixes. Props [fjarrett](http://profiles.wordpress.org/fjarrett/), [kucrut](http://profiles.wordpress.org/kucrut/), [topher1kenobe](http://profiles.wordpress.org/topher1kenobe/), [justinsainton](http://profiles.wordpress.org/justinsainton/)

= 0.8.0 =
**2013/12/16** - Ability to query Stream records in a private RSS feed. Bug fixes. Props [fjarrett](http://profiles.wordpress.org/fjarrett/), [shadyvb](http://profiles.wordpress.org/shadyvb/)

= 0.7.3 =
**2013/12/13** - Bug fix for Role Access option. Props [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.7.2 =
**2013/12/12** - Bug fixes for the Installer connector. Props [shadyvb](http://profiles.wordpress.org/shadyvb/)

= 0.7.1 =
**2013/12/12** - Hotfix to remove PHP 5.4-only syntax. Role Access option added to Settings. Props [kucrut](http://profiles.wordpress.org/kucrut/)

= 0.7.0 =
**2013/12/11** - Added connectors for Taxonomies and Settings. Bug fixes. Props [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.6.0 =
**2013/12/09** - UX improvements to manual DB purge. Cron event for user-defined TTL of records. Bug fixes. Props [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.5.0 =
**2013/12/08** - Require PHP 5.3 to activate plugin. Provide action links for records when applicable. Bug fixes. Props [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.4.0 =
**2013/12/08** - Improved support for pages and custom post types. Chosen for filter dropdowns. Pagination support in screen options. Bug fixes. Props [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.3.0 =
**2013/12/07** - Improved actions for Users context. Action for edited images in Media context. Bug fixes in Menus context. Props [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/), [akeda](http://profiles.wordpress.org/akeda/)

= 0.2.0 =
**2013/12/06** - Second iteration build using custom tables data model. First public release. Props [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.1.0 =
Initial concept built using custom post type/taxonomies as the data model. Props [shadyvb](http://profiles.wordpress.org/shadyvb/)
