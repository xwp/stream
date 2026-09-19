# How to add an alert type

Alert types are Stream's notifiers. Each one decides what happens when a record matches an alert's triggers (email, Slack, highlight, …). This guide is enough to scaffold a working type without reading every builtin file.

Related: [ARCHITECTURE.md](../ARCHITECTURE.md) (lifecycle) · [adding-a-connector.md](adding-a-connector.md) (how records get into Stream)

## Where alert types live

| Kind | Location |
|------|----------|
| Abstract contract | [`classes/class-alert-type.php`](../classes/class-alert-type.php) (`WP_Stream\Alert_Type`) |
| Registry | [`classes/class-alerts.php`](../classes/class-alerts.php) (`WP_Stream\Alerts::load_alert_types()`) |
| Builtin types | [`alerts/class-alert-type-{slug}.php`](../alerts/) |

Builtins are listed in the `$alert_types` array inside `Alerts::load_alert_types()`. On `init` priority 9, Stream includes each `alerts/class-alert-type-{slug}.php`, instantiates the class, then applies the `wp_stream_alert_types` filter so other plugins can add more.

Stream does **not** autoload files under `docs/` or `alerts/` except the slugs in that array. A third-party type lives in *your* plugin.

`alerts/class-alert-type-die.php` and `alerts/class-alert-type-menu-alert.php` are on disk but not default-loaded. They only appear if something adds them through `wp_stream_alert_types`. Do not copy them as a template.

## Minimum an alert type class needs

Extend `WP_Stream\Alert_Type` and set:

1. **`$name`** — translated label in the Stream → Alerts dropdown. Example: `Error Log (example)`.
2. **`$slug`** — unique key stored on the alert post (lowercase, hyphens ok). Example: `error-log`.
3. **`alert( $record_id, $recordarr, $alert )`** — dispatch. Stream passes the `Alert` object as the third argument (not a plain options array).
4. **`display_fields( $alert )`** — configuration UI for that type. Optional, but this is how users set destinations.

Optional:

- `$creatable` (default `true`) — return `false` to hide the type from the new-alert UI. Existing alerts still fire and remain editable (IFTTT uses this).
- `is_dependency_satisfied()` — return `false` to drop the type from the registry when a required plugin is missing.

### Configuration is not `save_fields()`

`Alert_Type::save_fields()` exists on the abstract class and **is never called**. Triggers persist through `Alert_Trigger::save_fields()` (a different class); alert types persist through the `wp_stream_alerts_save_meta` filter, applied from [`Alerts_Admin_UI::save_new_alert()`](../classes/class-alerts-admin-ui.php) and [`Alerts_List`](../classes/class-alerts-list.php).

Hook that filter with a **static** callable (`[ Alert_Type_Example::class, 'add_alert_meta' ]`) or a named function. Do not use a closure or `[$this, 'method']`. Template for fields: [`alerts/class-alert-type-email.php`](../alerts/class-alert-type-email.php).

If the type stores credentials, add those meta keys to `wp_stream_secret_alert_meta_keys` so Abilities/MCP output redacts them. The error-log sample does not store secrets.

Do not pass class-name strings to the registry. `wp_stream_alert_types` receives **instances** keyed by `$slug`.

## How the trigger pipeline calls the type

```
DB insert
  → wp_stream_record_inserted
  → Alerts_Trigger_Engine::check_records()
  → WP_Query enabled wp_stream_alerts posts
  → Alert::check_record()
  → wp_stream_alert_trigger_check (AND of author / context / action)
  → Alert::send_alert()
  → Alert_Type::alert()
```

Matching is **synchronous** on each record insert (one `WP_Query` per insert). Empty trigger meta means “any”. All configured triggers must pass.

The `Alert` model is a CPT (`wp_stream_alerts`) with statuses `wp_stream_enabled` / `wp_stream_disabled`. Disabled alerts are not queried.

## Register from another plugin

Call `add_filter( 'wp_stream_alert_types', … )` from the plugin file. WordPress includes plugin files before `plugins_loaded`, and Alerts are constructed on `init` priority 9, so a top-level `add_filter` is early enough. Registering the filter on `init` priority 10 is too late.

The constructor needs a `Plugin` instance (unlike connectors). Read it from `wp_stream_get_instance()` when the filter runs — that helper is defined in Stream’s plugin file, so it is present whenever this filter fires. Guard on `class_exists( \WP_Stream\Alert_Type::class )` so a direct call to the register function does not fatal if Stream is inactive.

```php
add_filter( 'wp_stream_alert_types', 'my_plugin_register_stream_alert_type' );

function my_plugin_register_stream_alert_type( $types ) {
	if ( ! class_exists( \WP_Stream\Alert_Type::class ) ) {
		return $types;
	}

	$plugin = wp_stream_get_instance();
	if ( ! $plugin instanceof \WP_Stream\Plugin ) {
		return $types;
	}

	require_once __DIR__ . '/class-alert-type-example.php';

	$type                 = new \WP_Stream\Alert_Type_Example( $plugin );
	$types[ $type->slug ] = $type;

	return $types;
}
```

## Register as a builtin (this repo)

Use this path only when the type belongs in Stream itself.

1. Add the slug to the `$alert_types` array in `Alerts::load_alert_types()` in [`classes/class-alerts.php`](../classes/class-alerts.php). Hyphens in the slug become underscores in the class name (`error-log` → `Alert_Type_Error_Log`).
2. Create `alerts/class-alert-type-{slug}.php` with `class Alert_Type_{Slug} extends Alert_Type` in the `WP_Stream` namespace.
3. Set `$name` / `$slug`, implement `alert()`, and add `display_fields()` plus a `wp_stream_alerts_save_meta` callback if the type has settings. Template: [`alerts/class-alert-type-email.php`](../alerts/class-alert-type-email.php).
4. Add PHPUnit coverage under `tests/phpunit/`.

Do not copy the error-log sample into `alerts/` or into `load_alert_types()`. That sample is a third-party plugin, not a Stream builtin. `Alert_Type_None` (`none`) is the silent builtin; it is not this example.

## Worked example: error-log alert

A complete sample plugin lives at [`docs/examples/stream-error-log-alert/`](examples/stream-error-log-alert/). Stream does not load it. WordPress will not auto-activate it from inside this directory (plugins are discovered one level under `wp-content/plugins/`).

### Try it (about 10 minutes)

1. Copy the folder into `wp-content/plugins/` (WordPress only discovers plugins one level under that directory, not from inside Stream):

   ```sh
   cp -R docs/examples/stream-error-log-alert wp-content/plugins/
   ```

   On the wp-env stack from [contributing.md](../contributing.md):

   ```sh
   npm run cli -- cp -R /var/www/html/wp-content/plugins/stream/docs/examples/stream-error-log-alert /var/www/html/wp-content/plugins/
   npm run cli -- wp plugin activate stream-error-log-alert --network
   ```

2. Otherwise activate it from **Plugins** in wp-admin.
3. In wp-admin open **Stream → Alerts → Add New**.
4. Choose **Error Log (example)** as the alert type. Leave the author / connector / action triggers empty (match any record). Enable the alert and save.
5. Cause a Stream record: update a post, or (if you also installed the [noop connector sample](adding-a-connector.md)) click **Log a noop event**.
6. Check the PHP error log. On wp-env: `npm run logs`. You should see a line like `Stream alert: record 123 …`.

### What the sample does

- [`stream-error-log-alert.php`](examples/stream-error-log-alert/stream-error-log-alert.php) registers `stream_error_log_alert_register()` on `wp_stream_alert_types` from the plugin file, and skips registration when Stream is inactive.
- [`class-alert-type-error-log.php`](examples/stream-error-log-alert/class-alert-type-error-log.php) is the type: `$slug = 'error-log'`, `$name = 'Error Log (example)'`, optional prefix field, `alert()` calls `error_log()`.

Copy those two files into your own plugin, rename the slug / text domain, and replace `error_log()` with the real notifier (HTTP, email, …).
