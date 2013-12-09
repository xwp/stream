=== Stream ===
Contributors:      X-team, shadyvb, fjarrett, akeda
Tags:              stream, activity, analytics, log, users, notification, actions
Requires at least: 3.6
Tested up to:      3.7.1
Stable tag:        trunk
License:           GPLv2 or later
License URI:       http://www.gnu.org/licenses/gpl-2.0.html

Track and monitor every change made on your WordPress site in beautifully organized detail.

== Description ==

**Note: This plugin requires PHP 5.3 or higher to be activated.**

Never be in the dark about WP Admin activity again. Stream allows you to know exactly when changes to your site have been made, and more importantly, who did them.

Every logged-in user action is logged in an activity stream and organized for easy filtering by context, action, and even IP address.

Built with performance in mind, you can determine how long records should live before being purged (depending on the amount of user activity your site can expect). Stream also won’t pollute your default posts table with records or slow down content querying on your site.

Stream is built to extend, allowing developers to easily build their own connectors to track any type of action in the activity stream (developer documentation coming soon).

**Development of this plugin is done [on GitHub](https://github.com/x-team/wp-stream). Pull requests welcome. Please see [issues](https://github.com/x-team/wp-stream/issues) reported there before going to the plugin forum.**

== Screenshots ==

1. Every logged-in user action is logged in the activity stream and organized for easy filtering and searching.
2. Determine how long records should live before being purged, or purge them from the database manually at any time.

== Changelog ==

= 0.6 =
UX improvements to manual DB purge. Cron event for user-defined TTL of records. Bug fixes. Props [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.5 =
Require PHP 5.3 to activate plugin. Provide action links for records when applicable. Bug fixes. Props [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.4 =
Improved support for pages and custom post types. Chosen for filter dropdowns. Pagination support in screen options. Bug fixes. Props [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.3 =
Improved actions for Users context. Action for edited images in Media context. Bug fixes in Menus context. Props [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/), [akeda](http://profiles.wordpress.org/akeda/)

= 0.2 =
Second iteration build using custom tables data model. First public release. Props [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.1 =
Initial concept build using custom post type/taxonomies as the data model. Props [shadyvb](http://profiles.wordpress.org/shadyvb/)
