# How to add a connector

Connectors are Stream's event adapters. Each one listens to WordPress (or plugin) actions and writes activity records. This guide is enough to scaffold a working connector without reading every builtin file.

Related: [ARCHITECTURE.md](../ARCHITECTURE.md) (lifecycle) · [connectors.md](../connectors.md) (generated inventory of builtins)

## Where connectors live

| Kind | Location |
|------|----------|
| Abstract contract | [`classes/class-connector.php`](../classes/class-connector.php) (`WP_Stream\Connector`) |
| Registry | [`classes/class-connectors.php`](../classes/class-connectors.php) (`WP_Stream\Connectors`) |
| Builtin + integrated connectors | [`connectors/class-connector-{slug}.php`](../connectors/) |

Builtins are listed in `Connectors::BUILTIN_CONNECTOR_SLUGS`. On `init` priority 9, Stream includes each `connectors/class-connector-{slug}.php`, instantiates the class, then applies the `wp_stream_connectors` filter so other plugins can add more.

Stream does **not** autoload files under `docs/` or `connectors/` except the slugs in that constant. A third-party connector lives in *your* plugin.

## Minimum a connector class needs

Extend `WP_Stream\Connector` and set:

1. **`$name`** — slug stored on each record (lowercase, no spaces). Example: `noop`.
2. **`$actions`** — WordPress hook names this connector listens to. `Connector::register()` attaches `callback()` to each of them.
3. **`get_label()`** — translated connector name shown in Stream's UI.
4. **`get_context_labels()`** — map of context slug → translated label (the "what kind of object" column).
5. **`get_action_labels()`** — map of action slug → translated label (created, updated, clicked, …).
6. **`callback_{action}()`** — one method per hook in `$actions`.

Optional flags:

- `$register_admin` (default `true`) — run in wp-admin.
- `$register_frontend` (default `true`) — run on the front end (also cron, REST, and WP-CLI, because those use `is_admin() === false`).
- `is_dependency_satisfied()` — return `false` to skip registration when a required plugin is missing.

## How a hook becomes a record

```
WP action in $actions
  → Connector::callback()
  → callback_{sanitized_action}()
  → $this->log( $message, $args, $object_id, $context, $action )
```

The callback method name is the hook name with anything except `a-z`, `0-9`, and `_` replaced by `_`. So `stream_noop_demo_clicked` maps to `callback_stream_noop_demo_clicked`. A hyphenated hook such as `hyphenated-action` maps to `callback_hyphenated_action`.

`log()` arguments:

| Argument | Role |
|----------|------|
| `$message` | sprintf-ready string shown in the Stream table. Extra `%` characters in user data must be escaped (`Connector::escape_percentages()`). |
| `$args` | Values for sprintf **and** stored as record meta. |
| `$object_id` | Related object ID, or `null`. |
| `$context` | Must be a key from `get_context_labels()`. |
| `$action` | Must be a key from `get_action_labels()`. |
| `$user_id` | Optional. Defaults to the current user. |

`Connector::register()` is what attaches the hooks. Call `parent::register()` if you override it.

Do not pass class-name strings to the registry. `wp_stream_connectors` receives **instances** keyed by `$name`.

## Register from another plugin

Add the filter on `plugins_loaded` (or earlier). Connectors are constructed on `init` priority 9, so `plugins_loaded` is early enough. Registering the filter on `init` priority 10 is too late.

```php
add_action( 'plugins_loaded', 'my_plugin_stream_connectors' );

function my_plugin_stream_connectors() {
	add_filter( 'wp_stream_connectors', 'my_plugin_register_stream_connector' );
}

function my_plugin_register_stream_connector( $instances ) {
	if ( ! class_exists( \WP_Stream\Connector::class ) ) {
		return $instances;
	}

	require_once __DIR__ . '/class-connector-example.php';

	$connector                     = new \WP_Stream\Connector_Example();
	$instances[ $connector->name ] = $connector;

	return $instances;
}
```

Guard on `class_exists( \WP_Stream\Connector::class )` so the site does not fatal when Stream is inactive.

### Stale examples

The [wiki Filter Reference](https://github.com/xwp/stream/wiki/Filter-Reference) and the 2014 [x-team/wp-stream-connector-example](https://github.com/x-team/wp-stream-connector-example) plugin document `wp_stream_connectors` as an array of **class names**. That is wrong for current Stream. Pass `Connector` objects keyed by slug, as above.

## Register as a builtin (this repo)

Use this path only when the connector belongs in Stream itself (core WordPress or a bundled integration).

1. Add the slug to `Connectors::BUILTIN_CONNECTOR_SLUGS` in [`classes/class-connectors.php`](../classes/class-connectors.php). Hyphens in the slug become underscores in the class name (`two-factor` → `Connector_Two_Factor`).
2. Create `connectors/class-connector-{slug}.php` with `class Connector_{Slug} extends Connector` in the `WP_Stream` namespace.
3. Set `$name` / `$actions`, implement the three label methods, and add `callback_{action}()` methods that call `$this->log()`. Template: [`connectors/class-connector-posts.php`](../connectors/class-connector-posts.php).
4. Add PHPUnit coverage under `tests/phpunit/connectors/`.
5. Regenerate the inventory with `npm run document:connectors`.

Do not copy the noop sample into `connectors/` or into `BUILTIN_CONNECTOR_SLUGS`. That sample is a third-party plugin, not a Stream builtin.

## Worked example: noop connector

A complete sample plugin lives at [`docs/examples/stream-noop-connector/`](examples/stream-noop-connector/). Stream does not load it. WordPress will not auto-activate it from inside this directory (plugins are discovered one level under `wp-content/plugins/`).

### Try it (about 10 minutes)

1. Copy the folder into `wp-content/plugins/` (WordPress only discovers plugins one level under that directory, not from inside Stream):

   ```sh
   cp -R docs/examples/stream-noop-connector wp-content/plugins/
   ```

   On the wp-env stack from [contributing.md](../contributing.md):

   ```sh
   npm run cli -- cp -R /var/www/html/wp-content/plugins/stream/docs/examples/stream-noop-connector /var/www/html/wp-content/plugins/
   npm run cli -- wp plugin activate stream-noop-connector --network
   ```

2. Otherwise activate it from **Plugins** in wp-admin.
3. In wp-admin open **Tools → Stream Noop Demo**.
4. Click **Log a noop event**.
5. Open **Stream**. You should see one record: connector **Noop (example)**, context **Demo**, action **Clicked**, message `Noop demo button clicked.`

### What the sample does

- [`stream-noop-connector.php`](examples/stream-noop-connector/stream-noop-connector.php) registers `stream_noop_connector_register()` on `wp_stream_connectors` from `plugins_loaded`, adds a Tools page, and handles `admin-post.php` with a nonce and `manage_options`.
- [`class-connector-noop.php`](examples/stream-noop-connector/class-connector-noop.php) is the connector: `$name = 'noop'`, one action `stream_noop_demo_clicked`, `$register_frontend = false`.
- Submitting the button runs `do_action( 'stream_noop_demo_clicked' )` → `callback_stream_noop_demo_clicked()` → `$this->log( …, 'demo', 'clicked' )`.

Copy those two files into your own plugin, rename the slug / hooks / text domain, and replace the demo button with real events.
