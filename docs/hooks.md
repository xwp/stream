# Stream hook reference

Auto-generated from PHPDoc at hook call sites. Do not edit by hand; run `npm run docs:hooks`.

## Stream hooks

### `stream_records_per_page`

- **Type:** filter
- **Location:** `classes/class-list-table-query-builder.php:96`
- **Parameters:** `$records_per_page` — Number of records per page.

Filter records per page for the Stream list table query.

### `wp_stream_abilities_connectors`

- **Type:** filter
- **Location:** `abilities/class-ability-get-connectors.php:109`
- **Parameters:** `$connectors` — Registered connector instances.

Filter the connector list returned by the get-connectors ability.

### `wp_stream_acf_enable_value_logging`

- **Type:** filter
- **Location:** `connectors/class-connector-acf.php:120`
- **Parameters:** `$enable_value_logging`

Allow devs to disable logging values of rendered forms

### `wp_stream_action_link_url`

- **Type:** filter
- **Location:** `connectors/class-connector-settings.php:583`
- **Parameters:** `$url`; `$record`

Filter the action link URL for Settings connector records.

### `wp_stream_action_links_{connector}`

- **Type:** filter
- **Location:** `classes/class-list-table-column-renderer.php:167`
- **Parameters:** `$action_links` — Action links.; `$record` — Record.

Filter allows modification of action links for a specific connector

### `wp_stream_admin_body_classes`

- **Type:** filter
- **Location:** `classes/class-admin-assets.php:196`
- **Parameters:** `$stream_classes`

Filter the Stream admin body classes

### `wp_stream_admin_menu`

- **Type:** action
- **Location:** `classes/class-admin-menu.php:106`

Fires before submenu items are added to the Stream menu allowing plugins to add menu items before Settings

### `wp_stream_admin_menu_screens`

- **Type:** action
- **Location:** `classes/class-admin-menu.php:128`

Fires just before the Stream list table is registered.

### `wp_stream_admin_menu_title`

- **Type:** filter
- **Location:** `classes/class-admin-menu.php:74`
- **Parameters:** `$main_menu_title`

Filter the main admin menu title

### `wp_stream_admin_page_title`

- **Type:** filter
- **Location:** `classes/class-admin-menu.php:90`
- **Parameters:** `$main_page_title`

Filter the main admin page title

### `wp_stream_after_connectors_registration`

- **Type:** action
- **Location:** `classes/class-connectors.php:229`
- **Parameters:** `$labels` — All register connectors labels array; `$connector_classes` — The Connectors object

Fires after all connectors have been registered.

### `wp_stream_after_list_table`

- **Type:** action
- **Location:** `classes/class-list-table.php:904`

Fires after the list table is displayed.

### `wp_stream_agent_label`

- **Type:** filter
- **Location:** `classes/class-author.php:307`
- **Parameters:** `$agent` — Key representing agent.

Filter agent labels

### `wp_stream_alert_ifttt_date_format`

- **Type:** filter
- **Location:** `alerts/class-alert-type-ifttt.php:250`
- **Parameters:** `$date_format` — Date format string.; `$alert` — The Alert.; `$recordarr` — The Record&#039;s data.

Filter the date format string Defaults to 'Y-m-d H:i:s'.

### `wp_stream_alert_ifttt_user_data_value`

- **Type:** filter
- **Location:** `alerts/class-alert-type-ifttt.php:236`
- **Parameters:** `$user_field` — User data field name.; `$alert` — The Alert object.; `$recordarr` — Array of Record data.

Filter User data field. Defaults to 'user_login'.

### `wp_stream_alert_ifttt_value_one`

- **Type:** filter
- **Location:** `alerts/class-alert-type-ifttt.php:283`
- **Parameters:** `$summary` — The Record&#039;s summary.; `$alert` — The Alert.; `$recordarr` — Array of Record data.

Filter the first IFTTT alert value

### `wp_stream_alert_ifttt_value_three`

- **Type:** filter
- **Location:** `alerts/class-alert-type-ifttt.php:302`
- **Parameters:** `$date` — The Record&#039;s date.; `$alert` — The Alert.; `$recordarr` — Array of Record data.

Filter the third IFTTT alert value

### `wp_stream_alert_ifttt_value_two`

- **Type:** filter
- **Location:** `alerts/class-alert-type-ifttt.php:293`
- **Parameters:** `$user_value` — The user meta value requested above.; `$user_id` — The user ID who fired the Alert.; `$alert` — The Alert.; `$recordarr` — Array of Record data.

Filter the second IFTTT alert value

### `wp_stream_alert_trigger_check`

- **Type:** filter
- **Location:** `classes/class-alert.php:253`
- **Parameters:** `$match`; `$record_id`; `$recordarr`; `$alert`

Filter whether an alert trigger matches a record.

### `wp_stream_alert_trigger_form_display`

- **Type:** action
- **Location:** `classes/class-alerts-admin-ui.php:311`
- **Parameters:** `$form`; `$alert`

Fires when displaying the alert trigger form.

### `wp_stream_alert_triggers`

- **Type:** filter
- **Location:** `classes/class-alerts.php:178`
- **Parameters:** `$classes` — An array of Notifier objects. In the format alert_trigger_slug =&gt; Notifier_Class()

Allows for adding additional alert_triggers via classes that extend Notifier.

### `wp_stream_alert_types`

- **Type:** filter
- **Location:** `classes/class-alerts.php:134`
- **Parameters:** `$classes` — An array of Notifier objects. In the format alert_type_slug =&gt; Notifier_Class()

Allows for adding additional alert_types via classes that extend Notifier.

### `wp_stream_alerts_save_meta`

- **Type:** filter
- **Location:** `classes/class-alerts-admin-ui.php:557`
- **Location:** `classes/class-alerts-list.php:428`
- **Parameters:** `$alert_meta`; `$alert_type`

Filter alert trigger meta before it is saved.

### `wp_stream_auto_purge`

- **Type:** action
- **Location:** `classes/class-admin-purge.php:551`
- **Location:** `classes/class-settings.php:323`

Fires once per auto-purge cycle, after all bail-out checks pass and immediately before deletion work is enqueued. Preserved for backward compatibility with consumers that hooked the legacy WP-Cron event of the same name in Stream <= 4.1.x. Note that since 4.2.0 this fires only when a purge is actually about to run — it no longer fires on every cron tick regardless of whether work happens. Hook into the recurring AS action (Admin::AUTO_PURGE_ACTION) directly if you need the older "every tick" semantics.

### `wp_stream_batch_size`

- **Type:** filter
- **Location:** `classes/class-admin-purge.php:318`
- **Location:** `classes/class-admin-purge.php:691`
- **Parameters:** `$batch_size` — The batch size, default 250000.

Filters the number of records in the {$wpdb->stream} table to do at a time.

### `wp_stream_blog_id_logged`

- **Type:** filter
- **Location:** `classes/class-log.php:124`

### `wp_stream_bulk_actions_threshold`

- **Type:** filter
- **Location:** `classes/class-admin-assets.php:129`
- **Parameters:** `$bulk_actions_threshold`

The maximum number of items that can be updated in bulk without receiving a warning. Stream watches for bulk actions performed in the WordPress Admin (such as updating many posts at once) and warns the user before proceeding if the number of items they are attempting to update exceeds this threshold value. Since Stream will try to save a log for each item, it will take longer than usual to complete the operation. The default threshold is 100 items.

### `wp_stream_check_connector_is_excluded`

- **Type:** filter
- **Location:** `classes/class-connectors.php:187`
- **Parameters:** `$is_excluded` — True if excluded, otherwise false.; `$connector` — The current connector&#039;s slug.; `$excluded_connectors` — An array of all excluded connector slugs.

Allows excluded connectors to be overridden and registered.

### `wp_stream_client_ip_address`

- **Type:** filter
- **Location:** `classes/class-plugin.php:466`
- **Parameters:** `$client_ip_address`

Filter the client IP address stored on Stream records.

### `wp_stream_comments_comment_type_labels`

- **Type:** filter
- **Location:** `connectors/class-connector-comments.php:97`
- **Parameters:** `$comment_type_labels`

Filter comment type labels for the Comments connector.

### `wp_stream_comments_exclude_comment_types`

- **Type:** filter
- **Location:** `connectors/class-connector-comments.php:669`
- **Parameters:** `$ignored_comment_types`

Filter excluded comment types for the Comments connector.

### `wp_stream_connectors`

- **Type:** filter
- **Location:** `classes/class-connectors.php:244`
- **Parameters:** `$instances` — An array of Connector objects.

Allows for adding additional connectors via classes that extend Connector.

### `wp_stream_current_agent`

- **Type:** filter
- **Location:** `classes/class-author.php:281`
- **Parameters:** `$agent`

Filter the current agent string

### `wp_stream_custom_action_links_{connector}`

- **Type:** filter
- **Location:** `classes/class-list-table-column-renderer.php:175`
- **Parameters:** `$custom_links` — Custom links.; `$record` — Record.

Filter allows addition of custom links for a specific connector

### `wp_stream_db_count_query`

- **Type:** filter
- **Location:** `classes/class-query.php:111`
- **Parameters:** `$query`; `$args`

Filter allows the result count query to be modified before execution.

### `wp_stream_db_driver`

- **Type:** filter
- **Location:** `classes/class-plugin.php:193`
- **Location:** `classes/class-plugin.php:405`
- **Parameters:** `$driver_class` — Database driver class name.

Filter the database driver class name used by Stream.

### `wp_stream_db_query`

- **Type:** filter
- **Location:** `classes/class-query.php:97`
- **Parameters:** `$query`; `$args`

Filter allows the final query to be modified before execution

### `wp_stream_db_query_where`

- **Type:** filter
- **Location:** `classes/class-query.php:78`
- **Parameters:** `$where` — WHERE statement.

Filters query WHERE statement as an alternative to filtering the $query using the hook below.

### `wp_stream_db_tables_prefix`

- **Type:** filter
- **Location:** `classes/class-db-driver-wpdb.php:41`
- **Parameters:** `$prefix`

Filter the database table prefix used for Stream tables.

### `wp_stream_db_update_versions`

- **Type:** filter
- **Location:** `classes/class-install.php:309`
- **Parameters:** `$db_update_versions`

Filter to alter the DB update versions array

### `wp_stream_editor_context`

- **Type:** filter
- **Location:** `connectors/class-connector-editor.php:111`
- **Parameters:** `$context` — Context slug; `$location` — The URL of the redirect

Filter available contexts for the Editor connector

### `wp_stream_editor_context_labels`

- **Type:** filter
- **Location:** `connectors/class-connector-editor.php:78`
- **Parameters:** `$context_labels` — Array of context slugs and their translated labels

Filter available context labels for the Editor connector

### `wp_stream_enable_auto_purge`

- **Type:** filter
- **Location:** `classes/class-admin-purge.php:377`
- **Location:** `classes/class-admin-purge.php:485`
- **Parameters:** `$enabled` — Whether auto-purge scheduling is enabled.

Filter whether Stream schedules its TTL record auto-purge at all. Custom storage drivers that manage retention externally (TTL indexes, partition rotation, a warehouse job, etc.) can return false to disable all TTL purge scheduling regardless of the scheduler backend. Any already-registered recurring purge is unscheduled from both backends so it cannot keep firing.

### `wp_stream_export_limit`

- **Type:** filter
- **Location:** `classes/class-export.php:169`
- **Parameters:** `$limit` — The number of records to export.

Filter to change how many records are exported. Increasing this too much could cause your export to time out.

### `wp_stream_exporters`

- **Type:** filter
- **Location:** `classes/class-export.php:228`
- **Parameters:** `$classes` — An array of Exporter objects. In the format exporter_slug =&gt; Exporter_Class()

Allows for adding additional exporters via classes that extend Exporter.

### `wp_stream_form_render_field`

- **Type:** filter
- **Location:** `classes/class-form-generator.php:219`
- **Parameters:** `$output`; `$field_type`; `$args`

Filter HTML for an unrecognized form field type.

### `wp_stream_frontend_indicator`

- **Type:** filter
- **Location:** `classes/class-plugin.php:368`
- **Parameters:** `$comment` — The content of the HTML comment

Filter allows the HTML output of the frontend indicator comment to be altered or removed, if desired.

### `wp_stream_get_role_list_separator`

- **Type:** filter
- **Location:** `classes/class-author.php:220`
- **Parameters:** `$separator`

Filter the separator between role names in the author display.

### `wp_stream_hidden_option_fields`

- **Type:** filter
- **Location:** `classes/class-network.php:274`
- **Parameters:** `$stream_hidden_options` — Stream hidden options.

Filter hidden option fields on per-site settings when network activated.

### `wp_stream_insert_column_default_{column_name}`

- **Type:** filter
- **Location:** `classes/class-list-table-column-renderer.php:145`
- **Parameters:** `$out` — Column content.; `$record` — Record with row content.; `$column_name` — Column name.

Allows for the addition of content under a specified column.

### `wp_stream_is_large_records_table`

- **Type:** filter
- **Location:** `classes/class-plugin.php:502`
- **Parameters:** `$is_large_table` — Whether or not the number of records should be considered large.; `$record_number` — The number of records being checked.

Filters whether or not the number of records should be considered a large table.

### `wp_stream_is_network_activated`

- **Type:** filter
- **Location:** `classes/class-plugin.php:437`
- **Parameters:** `$is_network_activated` — Whether the plugin is network activated.; `$plugin` — The stream plugin object.

Filter allows the network activated detection to be overridden.

### `wp_stream_is_option_ignored`

- **Type:** filter
- **Location:** `connectors/class-connector-settings.php:359`
- **Parameters:** `$is_ignored` — True if ignored, otherwise false.; `$option_name` — Current option name.; `$default_ignored` — Default options for Stream to ignore.

Filters the boolean output for is_option_ignored().

### `wp_stream_is_record_excluded`

- **Type:** filter
- **Location:** `classes/class-log.php:219`
- **Parameters:** `$exclude_record` — Whether the record should excluded.; `$recordarr` — The record to log.

Filters whether or not a record should be excluded from the log. If true, the record is not logged.

### `wp_stream_list_table_columns`

- **Type:** filter
- **Location:** `classes/class-list-table.php:135`
- **Parameters:** `$columns` — Columns.

Allows devs to add new columns to table

### `wp_stream_list_table_filters`

- **Type:** filter
- **Location:** `classes/class-list-table.php:487`
- **Parameters:** `$filters` — Filters.

Filter allows additional filters in the list table dropdowns Note the format of the filters above, with they key and array containing a title and array of items.

### `wp_stream_list_table_screen_id`

- **Type:** filter
- **Location:** `classes/class-list-table.php:50`
- **Parameters:** `$screen_id` — Screen ID.

Filter the list table screen ID

### `wp_stream_log_data`

- **Type:** filter
- **Location:** `classes/class-connector.php:181`
- **Parameters:** `$data` — An array of the data to be logged or false if it should not be logged.

Override the data logged. Returning false to this filter will stop the data from being logged. Examples of this filter in use can be found in some of the custom connectors.

### `wp_stream_log_handler`

- **Type:** filter
- **Location:** `classes/class-plugin.php:221`
- **Parameters:** `$log` — Log handler instance.

Filter the log handler instance used to persist Stream records.

### `wp_stream_menu_position`

- **Type:** filter
- **Location:** `classes/class-admin-menu.php:83`
- **Parameters:** `$main_menu_position`

Filter the main admin menu position Note: Using longtail decimal string to reduce the chance of position conflicts, see Codex

### `wp_stream_network_option_fields`

- **Type:** filter
- **Location:** `classes/class-network.php:291`
- **Parameters:** `$network_hidden_options` — Network hidden options.

Filter hidden option fields on network settings when network activated.

### `wp_stream_posts_exclude_post_types`

- **Type:** filter
- **Location:** `connectors/class-connector-posts.php:351`
- **Parameters:** `$excluded_post_types`

Filter excluded post types for the Posts connector.

### `wp_stream_predefined_date_intervals`

- **Type:** filter
- **Location:** `classes/class-date-interval.php:65`
- **Parameters:** `$date_intervals` — Date intervals array.; `$timezone` — Timezone.

Allow other plugins to filter the predefined date intervals.

### `wp_stream_preload_users_max`

- **Type:** filter
- **Location:** `classes/class-admin.php:146`
- **Parameters:** `$preload_users_max` — Default cap (50).

Filters the maximum number of users preloaded into a picker. Above this cap the picker switches to Ajax search. Zero forces Ajax.

### `wp_stream_query_args`

- **Type:** filter
- **Location:** `classes/class-db.php:232`
- **Parameters:** `$args` — Array of query arguments

Filter allows additional arguments to query $args

### `wp_stream_query_properties`

- **Type:** filter
- **Location:** `classes/class-db.php:213`
- **Parameters:** `$properties` — Array of query properties

Filter allows additional query properties to be added

### `wp_stream_record_actions_menu`

- **Type:** filter
- **Location:** `classes/class-list-table.php:811`
- **Parameters:** `$actions` — Should be in the format of action_slug =&gt; &#039;Action Name&#039;

Filter the records screen actions dropdown menu

### `wp_stream_record_array`

- **Type:** filter
- **Location:** `classes/class-db.php:46`
- **Parameters:** `$record`

Filter allows modification of record information

### `wp_stream_record_classes`

- **Type:** filter
- **Location:** `classes/class-list-table.php:870`
- **Parameters:** `$classes`; `$item`

Filter CSS classes on a Stream list table row.

### `wp_stream_record_insert_error`

- **Type:** action
- **Location:** `classes/class-db.php:62`
- **Parameters:** `$record`; `$result`

Fires on a record insertion error

### `wp_stream_record_inserted`

- **Type:** action
- **Location:** `classes/class-db.php:73`
- **Parameters:** `$record_id`; `$record`

Fires after a record has been inserted

### `wp_stream_register_column_defaults`

- **Type:** filter
- **Deprecated:** via `apply_filters_deprecated`
- **Location:** `classes/class-list-table-column-renderer.php:129`
- **Parameters:** `$new_columns` — Columns injected in the table.

Registers new Columns to be inserted into the table. The cell contents of this column is set below with 'wp_stream_insert_column_default_'

### `wp_stream_secret_alert_meta_keys`

- **Type:** filter
- **Location:** `classes/class-ability.php:174`
- **Parameters:** `$keys` — Meta keys to redact.; `$alert_meta` — The alert meta being redacted.

Filters the alert_meta keys treated as credentials and withheld from ability output. Third-party alert types registered via `wp_stream_alert_types` may store their own destination secrets under names Stream cannot know about; add them here so they are redacted too.

### `wp_stream_serialized_labels`

- **Type:** filter
- **Location:** `connectors/class-connector-settings.php:450`
- **Parameters:** `$labels` — Serialized labels

Filter allows for insertion of serialized labels

### `wp_stream_settings_form_action`

- **Type:** filter
- **Location:** `classes/class-admin-screen-settings.php:63`
- **Parameters:** `$form_action`

Filter the settings form action URL.

### `wp_stream_settings_form_description`

- **Type:** filter
- **Location:** `classes/class-admin-screen-settings.php:70`
- **Parameters:** `$page_description`

Filter the settings page description.

### `wp_stream_settings_form_title`

- **Type:** filter
- **Location:** `classes/class-admin-menu.php:113`
- **Parameters:** `$settings_page_title`

Filter the Settings admin page title

### `wp_stream_settings_option_fields`

- **Type:** filter
- **Location:** `classes/class-settings-registry.php:152`
- **Parameters:** `$fields` — Option fields.

Filter allows for modification of options fields

### `wp_stream_settings_option_key`

- **Type:** filter
- **Location:** `classes/class-settings.php:123`
- **Parameters:** `$option_key`

Filter the settings option key.

### `wp_stream_settings_options`

- **Type:** filter
- **Location:** `classes/class-settings.php:245`
- **Parameters:** `$options` — Options.; `$option_key` — Option key.

Filter allows for modification of options

### `wp_stream_taxonomies_exclude_taxonomies`

- **Type:** filter
- **Location:** `connectors/class-connector-taxonomies.php:269`
- **Parameters:** `$excluded_taxonomies`

Filter excluded taxonomies for the Taxonomies connector.

### `wp_stream_test_{callback}[1]`

- **Type:** action
- **Location:** `classes/class-connector.php:134`
- **Parameters:** `$callback` — Callback name

Action fires during testing to test the current callback

### `wp_stream_use_action_scheduler`

- **Type:** filter
- **Location:** `classes/class-plugin.php:281`
- **Parameters:** `$use_action_scheduler` — Whether to use Action Scheduler.

Filter whether Stream uses Action Scheduler for its deferred work. IMPORTANT — timing: this filter is applied in Plugin::boot(), which runs from the constructor when the Stream plugin file is included, i.e. BEFORE the `plugins_loaded` action. Callbacks must therefore be registered from code that loads before Stream: an mu-plugin, wp-config.php, or a plugin guaranteed to load earlier. Registering it from a regular plugin's `plugins_loaded` hook is too late and will be ignored.                                   Defaults to true when the bundled                                   AS library is present. Return a                                   real boolean: the value is cast                                   with (bool), so PHP string                                   truthiness applies to strings                                   (e.g. 'false' is truthy).

### `wp_stream_woocommerce_contexts`

- **Type:** filter
- **Location:** `connectors/class-connector-woocommerce.php:172`
- **Parameters:** `$context_labels`

Filter WooCommerce connector context labels.

### `wp_stream_woocommerce_custom_settings`

- **Type:** filter
- **Location:** `connectors/class-connector-woocommerce.php:245`
- **Parameters:** `$custom_settings`

Filter WooCommerce custom settings tracked by the connector.

## External hooks invoked

### `atom_head`

- **Type:** action
- **Location:** `includes/feeds/atom.php:29`

Action fires during RSS head

### `atom_item`

- **Type:** action
- **Location:** `includes/feeds/atom.php:59`

Action fires during Atom item

### `atom_ns`

- **Type:** action
- **Location:** `includes/feeds/atom.php:16`

### `edd_api_log_requests`

- **Type:** filter
- **Location:** `connectors/class-connector-edd.php:197`

This filter is documented in Easy Digital Downloads.

### `jetpack_module_configurable_{slug}`

- **Type:** filter
- **Location:** `connectors/class-connector-jetpack.php:163`
- **Location:** `connectors/class-connector-jetpack.php:193`

This filter is documented in Jetpack.

### `rss2_head`

- **Type:** action
- **Location:** `includes/feeds/rss-2.0.php:43`

Action fires during RSS head

### `rss2_item`

- **Type:** action
- **Location:** `includes/feeds/rss-2.0.php:70`

Action fires during RSS item

### `rss2_ns`

- **Type:** action
- **Location:** `includes/feeds/rss-2.0.php:27`

Action fires during RSS xmls printing

### `sidebars_widgets`

- **Type:** filter
- **Location:** `connectors/class-connector-widgets.php:847`
- **Parameters:** `$sidebars_widgets` — Sidebar Widgets in Options table; `$inserted` — Inserted Sidebar Widgets

Filter allows for insertion of sidebar widgets
