=== Stream ===
Contributors:     lukecarbis, fjarrett, shadyvb, westonruter, stream, X-team
Tags:              actions, activity, admin, analytics, dashboard, log, notification, stream, users
Requires at least: 3.7
Tested up to:      4.0
Stable tag:        trunk
License:           GPLv2 or later
License URI:       http://www.gnu.org/licenses/gpl-2.0.html

Stream is the easiest and safest way to track content changes happening to your WordPress site and then view them in beautifully organized detail.

== Description ==

[youtube http://www.youtube.com/watch?v=t_qD4Sp4E70]

**Note: This plugin requires PHP 5.3 or higher to be activated.**

Never be in the dark about WP Admin activity again.

Stream allows you to know exactly when changes to your site have been made, and more importantly, who did them.

Every logged-in user action is logged in an activity stream and organized for easy filtering by user, role, context, action and IP address.

Built with performance in mind, Stream won't slow down content querying on your site.

**Built-In Tracking For Popular Plugins:**

 * [Advanced Custom Fields](https://wp-stream.com/connectors/acf/)
 * [bbPress](https://wp-stream.com/connectors/bbpress/)
 * [BuddyPress](https://wp-stream.com/connectors/buddypress/)
 * [Easy Digital Downloads](https://wp-stream.com/connectors/edd/)
 * [Gravity Forms](https://wp-stream.com/connectors/gravity-forms/)
 * [Jetpack](https://wp-stream.com/connectors/jetpack/)
 * [WooCommerce](https://wp-stream.com/connectors/woocommerce/)
 * [WordPress SEO by Yoast](https://wp-stream.com/connectors/wordpress-seo/)

**Plus Core Tracking For:**

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

**Other Noteworthy Features:**

 * Dashboard widget of most recent user activity
 * Limit who can view user activity records by user role
 * Set exclude rules to ignore certain kinds of user activity
 * Live update of user activity records in the Stream
 * Private RSS and JSON feeds of user activity records
 * Support for IPv6 addresses

**Coming Soon:**

 * Multisite view of all activity records on a network

**Languages Supported:**

 * English
 * French (France)
 * German
 * Indonesian
 * Polish
 * Portuguese (Brazil)
 * Spanish (Spain)

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

= 2.0.1 - September 30, 2014 =

* Tweak: Improved localisation strings throughout the plugin ([#644](https://github.com/wp-stream/stream/pull/644))
* Tweak: Improved tooltip text explaining WP.com sign in
* Fix: ACF Pro doesn't save custom field values when Stream enabled ([#642](https://github.com/wp-stream/stream/issues/642))

Props [lukecarbis](http://profiles.wordpress.org/lukecarbis/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 2.0.0 - September 27, 2014 =

* All activity is now stored only in the cloud over SSL, local MySQL storage dependence is over!
* Connector and Context have merged in the UI, now just called Contexts
* The Exclude Rules UI has been completely revamped
* Notifications and Reports are now conveniently built into Stream for Pro subscribers
* Connectors for tracking other popular plugins are now built into Stream, like BuddyPress, Jetpack, Gravity Forms, and more...
* You create an account for Stream simply by signing in with your WordPress.com ID

**NOTE:** Multisite view of all activity records in the Network Admin has been removed in this release. If you require this feature, please do not update Stream until version 2.1.0 is released.

= 1.4.9 - July 23, 2014 =

* Fix: Revert delayed log mechanism for post transition ([#585](https://github.com/x-team/wp-stream/issues/585))
* Fix: Revert usage of get_taxonomy() ([#586](https://github.com/x-team/wp-stream/pull/586))
* Fix: Notices not firing on correct action ([#589](https://github.com/x-team/wp-stream/issues/589))

Props [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.4.8 - July 18, 2014 =

* New: Greatly improved widget tracking, including changes performed in Customizer ([#391](https://github.com/x-team/wp-stream/pull/391))
* New: Now tracking when Akismet automatically marks comments as spam ([#587](https://github.com/x-team/wp-stream/pull/587))
* Tweak: Log WP-CLI details to Stream author meta ([#470](https://github.com/x-team/wp-stream/issues/470))
* Tweak: Track changes to options more deeply ([#573](https://github.com/x-team/wp-stream/pull/573))
* Fix: Labels not seen for CPT registered on init with default priority ([#565](https://github.com/x-team/wp-stream/issues/565))
* Fix: Stream menu appearing in Network menu when not network activated ([#582](https://github.com/x-team/wp-stream/issues/582))
* Fix: Post Revision ID associated to record is not the most recent one ([#585](https://github.com/x-team/wp-stream/issues/585))
* Fix: Incorrect action label for comment throttling ([#591](https://github.com/x-team/wp-stream/issues/591))

Props [westonruter](http://profiles.wordpress.org/westonruter/), [fjarrett](http://profiles.wordpress.org/fjarrett/), [shadyvb](http://profiles.wordpress.org/shadyvb/), [lukecarbis](http://profiles.wordpress.org/lukecarbis/), [chacha](https://github.com/chacha/)

= 1.4.7 - June 27, 2014 =

* New: Comment Type support added to the Comments connector ([#558](https://github.com/x-team/wp-stream/issues/558))
* Fix: Datepicker opens again with each paged view ([#568](https://github.com/x-team/wp-stream/issues/568))
* Fix: PHP warning when deleting network users ([#579](https://github.com/x-team/wp-stream/issues/579))
* Fix: Track user count setting changes ([#583](https://github.com/x-team/wp-stream/issues/583))
* Fix: .po and .pot files out-of-date for translators ([#584](https://github.com/x-team/wp-stream/issues/584))

Props [lukecarbis](http://profiles.wordpress.org/lukecarbis/), [fjarrett](http://profiles.wordpress.org/fjarrett/), [bordoni](http://profiles.wordpress.org/bordoni/), [shadyvb](http://profiles.wordpress.org/shadyvb/)

= 1.4.6 - May 30, 2014 =

* Tweak: Actions provided for trashed posts are irrelevant ([#523](https://github.com/x-team/wp-stream/issues/523))
* Tweak: Use core language pack translations where possible ([#534](https://github.com/x-team/wp-stream/issues/534))
* Tweak: Consolidate show filter and show column screen options ([#542](https://github.com/x-team/wp-stream/issues/542))
* Tweak: Stop tracking failed login attempts ([#547](https://github.com/x-team/wp-stream/issues/547))
* Tweak: Remove all uses of extract() from Stream ([#556](https://github.com/x-team/wp-stream/issues/556))
* Fix: Excluding roles is not handled properly ([#527](https://github.com/x-team/wp-stream/issues/527))
* Fix: Stream runs install routine twice ([#528](https://github.com/x-team/wp-stream/issues/528))
* Fix: Widget records show sidebar slug instead of label ([#531](https://github.com/x-team/wp-stream/issues/531))
* Fix: Fatal error when PHP version is less than 5.3 ([#538](https://github.com/x-team/wp-stream/issues/538))
* Fix: Cannot exclude Custom Background context ([#543](https://github.com/x-team/wp-stream/issues/543))
* Fix: Conflict with Jetpack body class in WP Admin ([#545](https://github.com/x-team/wp-stream/issues/545))
* Fix: Stream settings exclude error for big wp_users table ([#551](https://github.com/x-team/wp-stream/issues/551))

Props [fjarrett](http://profiles.wordpress.org/fjarrett/), [lukecarbis](http://profiles.wordpress.org/lukecarbis/), [shadyvb](http://profiles.wordpress.org/shadyvb/), [barryceelen](http://profiles.wordpress.org/barryceelen/), [japh](http://profiles.wordpress.org/japh/)

= 1.4.5 - May 15, 2014 =

* New: Lightweight frontend indicator for sites using Stream ([#507](https://github.com/x-team/wp-stream/issues/507))
* Tweak: Add filterable method for excluded comment types ([#487](https://github.com/x-team/wp-stream/issues/487))
* Tweak: Rename "ID" column label to "Record ID" ([#490](https://github.com/x-team/wp-stream/issues/490))
* Tweak: One admin notice for any missing DB tables ([#506](https://github.com/x-team/wp-stream/pull/506))
* Fix: Custom authentication schemes not tracking user logins correctly ([#434](https://github.com/x-team/wp-stream/issues/434))
* Fix: Taxonomy connector conflicts with Edit Flow plugin ([#498](https://github.com/x-team/wp-stream/issues/498))
* Fix: Switching user is incorrectly tracked ([#501](https://github.com/x-team/wp-stream/issues/501))
* Fix: Extension activation links broken when plugin folders are renamed ([#502](https://github.com/x-team/wp-stream/issues/502))
* Fix: Author info showing up incorrectly ([#505](https://github.com/x-team/wp-stream/issues/505))
* Fix: Incompatibility with multi-server environments ([#517](https://github.com/x-team/wp-stream/issues/517))
* Fix: Warnings seen when Show Avatars is disabled ([#518](https://github.com/x-team/wp-stream/issues/518))
* Fix: Notices for non-existent extension data after timeout ([#529](https://github.com/x-team/wp-stream/pull/529))

Props [fjarrett](http://profiles.wordpress.org/fjarrett/), [shadyvb](http://profiles.wordpress.org/shadyvb/), [lukecarbis](http://profiles.wordpress.org/lukecarbis/), [japh](http://profiles.wordpress.org/japh/)

= 1.4.4 - May 6, 2014 =

* New: Admin pointers to highlight when new admin screens are introduced ([#466](https://github.com/x-team/wp-stream/issues/466))
* Tweak: Filter introduced to allow the Stream admin menu position to be changed ([#99](https://github.com/x-team/wp-stream/issues/99))
* Tweak: Provide option label for records that show when the Stream database has updated ([#444](https://github.com/x-team/wp-stream/pull/444))
* Tweak: Better handling of authors in the list table ([#448](https://github.com/x-team/wp-stream/pull/448))
* Tweak: Way for developers to set their Stream Extensions affiliate ID on links from the Extensions screen ([#482](https://github.com/x-team/wp-stream/issues/482))
* Fix: Extensions screen CSS bug in Firefox ([#464](https://github.com/x-team/wp-stream/issues/464))
* Fix: Error when installing extensions from the Network Admin ([#491](https://github.com/x-team/wp-stream/issues/491))
* Fix: Undefined notice in admin.php ([#468](https://github.com/x-team/wp-stream/issues/468))

Props [westonruter](http://profiles.wordpress.org/westonruter/), [fjarrett](http://profiles.wordpress.org/fjarrett/), [japh](http://profiles.wordpress.org/japh/), [lukecarbis](http://profiles.wordpress.org/lukecarbis/), [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/), [bordoni](http://profiles.wordpress.org/bordoni/)

= 1.4.3 - April 26, 2014 =

* New: Introducing the Stream Extensions screen! ([#396](https://github.com/x-team/wp-stream/issues/396))

Props [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/), [lukecarbis](http://profiles.wordpress.org/lukecarbis/), [shadyvb](http://profiles.wordpress.org/shadyvb/), [c3mdigital](http://profiles.wordpress.org/c3mdigital/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.4.2 - April 24, 2014 =

* Fix: Update Database button redirecting to previous screen ([#443](https://github.com/x-team/wp-stream/issues/443))
* Fix: Update routine hotfix that was causing records to disappear ([#447](https://github.com/x-team/wp-stream/issues/447))

Props [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/), [lukecarbis](http://profiles.wordpress.org/lukecarbis/), [westonruter](http://profiles.wordpress.org/westonruter/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.4.1 - April 24, 2014 =

* Fix: Scripts and styles not using Stream version number ([#440](https://github.com/x-team/wp-stream/issues/440))
* Fix: WP-CLI incorrectly referenced in records ([#441](https://github.com/x-team/wp-stream/issues/441))

Props [westonruter](http://profiles.wordpress.org/westonruter/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.4.0 - April 24, 2014 =

* New: Multisite is now fully supported, activate Stream network-wide ([#65](https://github.com/x-team/wp-stream/issues/65))
* New: Separate API for handling DB update routines ([#379](https://github.com/x-team/wp-stream/issues/379))
* New: WP-CLI compatibility, Stream now tracks changes made via WP-CLI ([#423](https://github.com/x-team/wp-stream/issues/423))
* Tweak: Deprecate functions and hooks in favor of consistent naming conventions ([#267](https://github.com/x-team/wp-stream/issues/267))
* Tweak: Use icon link instead of clicking the summary to filter by object ID ([#380](https://github.com/x-team/wp-stream/issues/380))
* Tweak: Save additional author meta for better records ([#389](https://github.com/x-team/wp-stream/issues/389))
* Tweak: More compact search filters for smaller screens ([#403](https://github.com/x-team/wp-stream/issues/403))
* Fix: Fix AJAX loading of authors in dropdown filters ([#49](https://github.com/x-team/wp-stream/issues/49))
* Fix: Custom capability conflict with W3 Total Cache plugin ([#296](https://github.com/x-team/wp-stream/issues/296))
* Fix: Live updates remove last item in activity table ([#386](https://github.com/x-team/wp-stream/issues/386))
* Fix: Live updates screen option checkbox not persisting ([#392](https://github.com/x-team/wp-stream/issues/392))
* Fix: IP validator not respecting zero ([#394](https://github.com/x-team/wp-stream/issues/394))
* Fix: Non-Administrator users seeing errors in Settings records ([#406](https://github.com/x-team/wp-stream/issues/406))
* Fix: Uninstall confirmation message doesn't display ([#411](https://github.com/x-team/wp-stream/issues/411))
* Fix: TTL purge schedule is never setup ([#412](https://github.com/x-team/wp-stream/issues/412))
* Fix: NextGen compability issue ([#416](https://github.com/x-team/wp-stream/issues/416))
* Fix: Stream Feeds Key not being automatically generated ([#420](https://github.com/x-team/wp-stream/issues/420))

Props [fjarrett](http://profiles.wordpress.org/fjarrett/), [lukecarbis](http://profiles.wordpress.org/lukecarbis/), [c3mdigital](http://profiles.wordpress.org/c3mdigital/), [westonruter](http://profiles.wordpress.org/westonruter/), [shadyvb](http://profiles.wordpress.org/shadyvb/), [powelski](http://profiles.wordpress.org/powelski/), [johnregan3](http://profiles.wordpress.org/johnregan3/), [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/), [desaiuditd](http://profiles.wordpress.org/desaiuditd/)

= 1.3.1 - April 3, 2014 =

* New: Theme Editor connector for tracking changes made to theme files ([#313](https://github.com/x-team/wp-stream/issues/313))
* New: Additional screen options to show/hide only the filters you care about ([#329](https://github.com/x-team/wp-stream/issues/329))
* New: Visibility option in Exclude settings to hide past records from view ([#355](https://github.com/x-team/wp-stream/issues/355))
* New: Stream Activity dashboard widget now supports live updates ([#356](https://github.com/x-team/wp-stream/issues/356))
* New: Hover authors to reveal a tooltip with helpful user meta ([#338](https://github.com/x-team/wp-stream/issues/338))
* New: Hover roles to reveal a tooltip with the number of authors assigned to that role ([#377](https://github.com/x-team/wp-stream/issues/377))
* Tweak: Future dates now disabled in Start date field datepicker ([#334](https://github.com/x-team/wp-stream/issues/334))
* Tweak: Now showing user Gravatars in Exclude Authors & Roles settings field ([#333](https://github.com/x-team/wp-stream/issues/333))
* Tweak: ID column is now hidden by default in Screen Options ([#348](https://github.com/x-team/wp-stream/issues/348))
* Tweak: Widget updated summary message improvement ([8818976](https://github.com/x-team/wp-stream/commit/88189761d4a8836038e8d9ec348096a0aab3072d))
* Fix: Autocomplete not working correctly in Exclude IP Addressees settings field ([#335](https://github.com/x-team/wp-stream/issues/335))
* Fix: Reset Stream Database link not clearing everything in all cases ([#347](https://github.com/x-team/wp-stream/issues/347))
* Fix: PHP 5.3.3 compatibility issue with filter constant ([#351](https://github.com/x-team/wp-stream/issues/351))
* Fix: Predefined date range intervals not honoring the site timezone setting ([#353](https://github.com/x-team/wp-stream/issues/353))
* Fix: wpdb::prepare() notice appearing in WordPress 3.9 ([#354](https://github.com/x-team/wp-stream/issues/354))
* Fix: Invalid argument warning thrown on fresh installations of WordPress ([#358](https://github.com/x-team/wp-stream/issues/358))
* Fix: Record TTL purge not functioning correctly ([#371](https://github.com/x-team/wp-stream/issues/371))
* Fix: Small CSS bug in jQuery UI datepicker skins ([04c80af](https://github.com/x-team/wp-stream/commit/04c80afa99486086612be9f6ad83148dfbbe533a))

Props [powelski](http://profiles.wordpress.org/powelski/), [fjarrett](http://profiles.wordpress.org/fjarrett/), [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/), [faishal](http://profiles.wordpress.org/faishal/), [desaiuditd](http://profiles.wordpress.org/desaiuditd/), [lukecarbis](http://profiles.wordpress.org/lukecarbis/), [johnregan3](http://profiles.wordpress.org/johnregan3/), [Powdered-Toast-Man](https://github.com/Powdered-Toast-Man)

= 1.3.0 - March 12, 2014 =

* New: Exclude tab in Settings to prevent specific types of activity from being tracked ([#251](https://github.com/x-team/wp-stream/issues/251))
* New: Now logging Custom Background and Custom Header changes ([#309](https://github.com/x-team/wp-stream/issues/309))
* New: Predefined date intervals now available when filtering records ([#320](https://github.com/x-team/wp-stream/issues/320))
* Tweak: Action links are now available for Stream Settings records ([#305](https://github.com/x-team/wp-stream/issues/305))
* Tweak: User avatars now displayed in Authors dropdown filter ([#311](https://github.com/x-team/wp-stream/issues/311))
* Tweak: Live updates are enabled by default for new installs ([#312](https://github.com/x-team/wp-stream/issues/312))
* Fix: Fallback to the term slug if a label does not exist in list-table ([#214](https://github.com/x-team/wp-stream/issues/214))
* Fix: Widget sorting is now being tracked properly as well as Inactive widgets ([#283](https://github.com/x-team/wp-stream/issues/283))
* Fix: Superfluous auto-draft posts are now prevented from being logged ([#293](https://github.com/x-team/wp-stream/issues/293))

Props [powelski](http://profiles.wordpress.org/powelski/), [faishal](http://profiles.wordpress.org/faishal/), [fjarrett](http://profiles.wordpress.org/fjarrett/), [desaiuditd](http://profiles.wordpress.org/desaiuditd/), [lukecarbis](http://profiles.wordpress.org/lukecarbis/), [shadyvb](http://profiles.wordpress.org/shadyvb/)

= 1.2.9 - March 8, 2014 =
Fixes bug that caused media uploads to fail on new posts. Props [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.2.8 - March 7, 2014 =
Use attachment type as context in Media connector. Bug fixes. Props [lukecarbis](http://profiles.wordpress.org/lukecarbis/), [powelski](http://profiles.wordpress.org/powelski/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.2.7 - March 4, 2014 =
Pagination added to Stream Activity dashboard widget. Bug fixes. Props [chacha](https://github.com/chacha/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.2.6 - February 28, 2014 =
Improved context names in Users connector. Props [powelski](http://profiles.wordpress.org/powelski/)

= 1.2.5 - February 27, 2014 =
Use sidebar area names as context in Widgets connector. Bug fixes. Props [desaiuditd](http://profiles.wordpress.org/desaiuditd/), [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/), [bordoni](http://profiles.wordpress.org/bordoni/)

= 1.2.4 - February 25, 2014 =
Use post type names as context in Comments connector. German translation update. Bug fixes. Props [powelski](http://profiles.wordpress.org/powelski/), [kucrut](http://profiles.wordpress.org/kucrut/), [pascalklaeres](http://profiles.wordpress.org/pascalklaeres/), [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.2.3 - February 21, 2014 =
Replacement function for filter_input family to avoid PHP bug. Filter added to main Stream query. Bug fixes. Props [shadyvb](http://profiles.wordpress.org/shadyvb/), [powelski](http://profiles.wordpress.org/powelski/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.2.2 - February 19, 2014 =
Prevent records of disabled connectors from appearing in the Stream. Bug fixes. Props [kucrut](http://profiles.wordpress.org/kucrut/), [johnregan3](http://profiles.wordpress.org/johnregan3/)

= 1.2.1 - February 17, 2014 =
Translation updates. Langage packs for pt_BR and id_ID. Bug fixes. Props [kucrut](http://profiles.wordpress.org/kucrut/), [shadyvb](http://profiles.wordpress.org/shadyvb/), [bordoni](http://profiles.wordpress.org/webord/), [powelski](http://profiles.wordpress.org/powelski/), [omniwired](https://github.com/omniwired), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.2.0 - February 12, 2014 =
Awesome datepicker styles. Performance optimizations. Bug fixes. Props [johnregan3](http://profiles.wordpress.org/johnregan3/), [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/), [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/)

= 1.1.9 - February 10, 2014 =
Load authors filter using AJAX if there are more than 50. Props [powelski](http://profiles.wordpress.org/powelski/)

= 1.1.8 - February 9, 2014 =
Bug fixes. Props [shadyvb](http://profiles.wordpress.org/shadyvb/)

= 1.1.7 - February 6, 2014 =
Upgrade routine for IPv6 support. Persist tab selection after saving Stream Settings. Props [shadyvb](http://profiles.wordpress.org/shadyvb/), [dero](https://github.com/dero)

= 1.1.6 - February 6, 2014 =
Sortable columns bug fix on the records screen. Props [powelski](http://profiles.wordpress.org/powelski/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.1.5 - February 5, 2014 =
Fixed a class scope bug [reported in the support forum](http://wordpress.org/support/topic/temporary-fatal-error-after-upgrade-113) that was causing a fatal error on some installs. Props [shadyvb](http://profiles.wordpress.org/shadyvb/)

= 1.1.4 - February 5, 2014 =
Highlight changed settings field feature. DB upgrade routine for proper utf-8 charset. Various bug fixes. Props [powelski](http://profiles.wordpress.org/powelski/), [johnregan3](http://profiles.wordpress.org/johnregan3/), [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.1.3 - February 4, 2014 =
Upgrade routine for IP column in DB. Serialized option parsing for Stream Settings records. Purge records immediately when TTL is set backwards in Stream Settings. Various bug fixes. Props [shadyvb](http://profiles.wordpress.org/shadyvb/), [powelski](http://profiles.wordpress.org/powelski/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.1.2 - February 2, 2014 =
Bug fix for list table notice on new installations. Props [shadyvb](http://profiles.wordpress.org/shadyvb/)

= 1.1.0 - January 31, 2014 =
Disable terms in dropdown filters for which records do not exist. Props [johnregan3](http://profiles.wordpress.org/johnregan3/)

= 1.0.9 - January 31, 2014 =
Several important bug fixes. Props [shadyvb](http://profiles.wordpress.org/shadyvb/)

= 1.0.8 - January 30, 2014 =
Bug fix for sites using BuddyPress. Props [johnregan3](http://profiles.wordpress.org/johnregan3/)

= 1.0.7 - January 29, 2014 =
Code efficiency improvements when fetching admin area URLs. Props [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.0.6 - January 28, 2014 =
Query improvements, default connector interface, hook added for general settings fields. Bug fixes. Props [dero](https://github.com/dero), [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/), [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.0.5 - January 27, 2014 =
Bug fix for live updates breaking columns when some are hidden via Screen Options. Props [johnregan3](http://profiles.wordpress.org/johnregan3/)

= 1.0.4 - January 23, 2014 =
Language pack for Polish. Bug fixes. Props [powelski](http://profiles.wordpress.org/powelski/), [fjarrett](http://profiles.wordpress.org/fjarrett/), [johnregan3](http://profiles.wordpress.org/johnregan3/), [kucrut](http://profiles.wordpress.org/kucrut/)

= 1.0.3 - January 19, 2014 =
Language pack for Spanish. Bug fixes. Props [omniwired](https://github.com/omniwired), [shadyvb](http://profiles.wordpress.org/shadyvb/)

= 1.0.2 - January 15, 2014 =
Ensure the dashboard widget repects the Role Access setting. Props [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 1.0.1 - January 15, 2014 =
Require nonce for generating a new user feed key. Props [johnregan3](http://profiles.wordpress.org/johnregan3/)

= 1.0.0 - January 13, 2014 =
Allow list table to be exensible. Hook added to prevent tables from being created, if desired. Props [johnregan3](http://profiles.wordpress.org/johnregan3/), [fjarrett](http://profiles.wordpress.org/fjarrett/), [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/)

= 0.9.9 - January 8, 2014 =
Updated screenshot assets and descriptions. Props [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.9.8 - January 1, 2014 =
Support for live updates in the Stream. Bug fixes. Props [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/), [johnregan3](http://profiles.wordpress.org/johnregan3/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.9.7 - December 29, 2013 =
Plugin version available as a constant. Bug fixes. Props [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.9.6 - December 29, 2013 =
Use menu name as context in Menus connector. Warning if required DB tables are missing. Bug fixes. Props [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/), [fjarrett](http://profiles.wordpress.org/fjarrett/), [topher1kenobe](http://profiles.wordpress.org/topher1kenobe/)

= 0.9.5 - December 22, 2013 =
WordPress context added to Installer connector for core updates. Props [shadyvb](http://profiles.wordpress.org/shadyvb/)

= 0.9.3 - December 22, 2013 =
Replacing Chosen library with Select2. Bug fixes. Props [kucrut](http://profiles.wordpress.org/kucrut/), [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.9.2 - December 22, 2013 =
Added support for private feeds in JSON format. Flush rewrite rules automatically for feeds when enabled/disabled. Bug fixes. Props [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.9.1 - December 21, 2013 =
Specify which roles should have their activity logged. Delete all options on uninstall. Bug fixes. Props [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.9.0 - December 20, 2013 =
Added connector for Comments. Stream activity dashboard widget. UI enhancements. Bug fixes. Props [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/), [fjarrett](http://profiles.wordpress.org/fjarrett/), [shadyvb](http://profiles.wordpress.org/shadyvb/), [topher1kenobe](http://profiles.wordpress.org/topher1kenobe/)

= 0.8.2 - December 19, 2013 =
Language packs for French and German. Option to uninstall database tables. Bug fixes. Props [jonathanbardo](http://profiles.wordpress.org/jonathanbardo/), [fjarrett](http://profiles.wordpress.org/fjarrett/), [topher1kenobe](http://profiles.wordpress.org/topher1kenobe/), [pascalklaeres](http://profiles.wordpress.org/pascalklaeres/)

= 0.8.1 - December 18, 2013 =
Setting to enable/disable private feeds functionality. Additional record logged when a user's role is changed. Bug fixes. Props [fjarrett](http://profiles.wordpress.org/fjarrett/), [kucrut](http://profiles.wordpress.org/kucrut/), [topher1kenobe](http://profiles.wordpress.org/topher1kenobe/), [justinsainton](http://profiles.wordpress.org/justinsainton/)

= 0.8.0 - December 16, 2013 =
Ability to query Stream records in a private RSS feed. Bug fixes. Props [fjarrett](http://profiles.wordpress.org/fjarrett/), [shadyvb](http://profiles.wordpress.org/shadyvb/)

= 0.7.3 - December 13, 2013 =
Bug fix for Role Access option. Props [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.7.2 - December 12, 2013 =
Bug fixes for the Installer connector. Props [shadyvb](http://profiles.wordpress.org/shadyvb/)

= 0.7.1 - December 12, 2013 =
Hotfix to remove PHP 5.4-only syntax. Role Access option added to Settings. Props [kucrut](http://profiles.wordpress.org/kucrut/)

= 0.7.0 - December 11, 2013 =
Added connectors for Taxonomies and Settings. Bug fixes. Props [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.6.0 - December 9, 2013 =
UX improvements to manual DB purge. Cron event for user-defined TTL of records. Bug fixes. Props [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.5.0 - December 8, 2013 =
Require PHP 5.3 to activate plugin. Provide action links for records when applicable. Bug fixes. Props [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.4.0 - December 8, 2013 =
Improved support for pages and custom post types. Chosen for filter dropdowns. Pagination support in screen options. Bug fixes. Props [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.3.0 - December 7, 2013 =
Improved actions for Users context. Action for edited images in Media context. Bug fixes in Menus context. Props [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/), [akeda](http://profiles.wordpress.org/akeda/)

= 0.2.0 - December 6, 2013 =
Second iteration build using custom tables data model. First public release. Props [shadyvb](http://profiles.wordpress.org/shadyvb/), [fjarrett](http://profiles.wordpress.org/fjarrett/)

= 0.1.0 =
Initial concept built using custom post type/taxonomies as the data model. Props [shadyvb](http://profiles.wordpress.org/shadyvb/)
