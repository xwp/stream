# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Stream — WordPress plugin. Activity log and audit trail. Track user/system actions for debugging, security, compliance.

- Plugin: `stream.php` (root)
- Namespace: `WP_Stream`
- Min PHP: 7.2 (CI runs 8.1; containerized dev supports 7.4 / 8.2 via `npm run switch-to:php7.4` / `switch-to:php8.2`)
- WordPress: 6.9+
- Custom capability: `view_stream` (in addition to default `manage_options` for settings)
- Settings capability is overridable via `WP_STREAM_SETTINGS_CAPABILITY` constant in `wp-config.php`

## Dev environment (Docker)

`npm` is canonical task runner. All commands run inside Docker containers.

- `npm install` — JS deps
- `composer install` — PHP deps (also installs mcp-adapter plugin to `local/public/wp-content/plugins/`)
- `npm run build` — compile JS/CSS to `build/`
- `npm start` — start containers (mkcert auto-generates cert for `https://stream.wpenv.net`)
- `npm run install-wordpress` — multisite install (admin/password)
- `npm run cli -- <cmd>` — run shell command inside WordPress container
- `npm run stop` / `npm run stop-all`

Dev URL: `https://stream.wpenv.net` (HTTPS required for App Passwords + Abilities/MCP).
MailHog: `stream.wpenv.net:8025`. phpMyAdmin: `stream.wpenv.net:8080`.

Xdebug step-debugging works out of the box in VSCode via `.vscode/launch.json`. Use `npm run start-xdebug` to enable.

## Build / lint / test

All via npm scripts (chain to composer inside container):

- `npm run build` / `npm run dev` — webpack build / watch
- `npm run lint` — runs both `lint:js` (`wp-scripts lint-js`) and `lint:php` (composer `phpcs .`)
- `npm run lint:php-tests` — phpcs for `tests/` (uses `tests/phpcs.xml.dist`)
- `npm run format` / `npm run format:js` / `npm run format:php` — autofix
- `npm test` — PHPUnit + multisite (with coverage)
- `npm run test-one` — single test pass (no coverage); append `-- --filter=TestClass::test_method` to target a test
- `npm run test:php-multisite` — multisite only
- `npm run test-xdebug` — PHPUnit with Xdebug
- `npm run test-e2e` / `npm run test-e2e-debug` — Playwright (uses `playwright/.auth/user.json` storage state; HTTPS errors ignored for mkcert)
- `npm run test-report` — Coveralls upload
- `npm run document:connectors` — regenerates `connectors.md` from `connectors/` headers (runs on host php, not container)

Override `WP_STREAM_TEST_DEBUG=yes` to enable `WP_DEBUG` during PHPUnit runs.

PHPUnit coverage config (`phpunit.xml`):
- Coverage `<include>` walks: `abilities/`, `alerts/`, `classes/`, `connectors/`, `exporters/`, `includes/` (all `class-*.php`)
- Excludes: `classes/class-cli.php`, `includes/lib`
- Multisite config: `phpunit-multisite.xml`
- `processUncoveredFiles="true"` walks coverage include dirs via `include_once`, bypassing the autoloader — so any class hit by coverage that `use`s a trait must have that trait available at include time. The shared `Trait_View_Stream_Permission` is loaded in `tests/bootstrap.php` for this reason.

## Code architecture

### Entry point
`stream.php` → requires `classes/class-plugin.php` → instantiates `WP_Stream\Plugin`. Min-PHP check gates plugin load. Global accessor: `wp_stream_get_instance()`.

### `Plugin` (singleton in `$GLOBALS['wp_stream']`)
- Constructor: registers `spl_autoload_register` for `WP_Stream\*` (file: `class-{lowercase-underscores}.php` in `classes/`), selects scheduler backend (Action Scheduler vs WP-Cron via `wp_stream_use_action_scheduler` filter; `__construct` runs BEFORE `plugins_loaded`, so filter callbacks must be registered from mu-plugins/wp-config/earlier plugins), instantiates DB driver (`DB_Driver_WPDB` by default, filterable via `wp_stream_db_driver`).
- `init` hook (priority 9): instantiates `Settings`, `Connectors`, `Alerts`, `Alerts_List`, `Abilities`.
- `Plugin::enqueue_asset()` loads `build/{handle}.asset.php` for dependency arrays + version; throws `RuntimeException` if built assets missing.
- `is_network_activated()`, `is_mustuse()`, `get_site_type()` distinguish `single` / `multisite-network` / `multisite-not-network` — used widely by connectors, settings, and scheduler.

### Connectors (`classes/class-connector.php` + `connectors/`)
Abstract `Connector` base. Subclasses declare `$actions = ['hook_name', ...]`; each is wired via `callback()` which dispatches to `callback_{action_name}` (sanitized). Test-only: under `WP_STREAM_TESTS` constant, fires `wp_stream_test_{callback_name}` action before the real handler — connectors are tested through this hook.

`log()` filters via `wp_stream_log_data`; returning `false` suppresses the record. Connectors may override `is_dependency_satisfied()` (e.g. ACF connector only registers when ACF is active). Delayed logging: `delayed_log($handle, ...args)` buffers until `shutdown`, then `delayed_log_commit()` flushes.

Existing connectors cover: ACF, bbPress, Blogs, BuddyPress, Comments, EDD, Editor, GravityForms, Installer, Jetpack, Media, Menus, Mercator, Posts, Settings, Taxonomies, Two-Factor, User Switching, Users, Widgets, WooCommerce, WordPress SEO. See `connectors.md` for the auto-generated list.

### Records / DB (`classes/class-log.php`, `class-db.php`, `class-db-driver*.php`, `class-record.php`)
- `Log::log($connector, $message, $args, $object_id, $context, $action, $user_id)` — entry point for all records.
- `DB` wraps a swappable `DB_Driver` (default `DB_Driver_WPDB`). Driver handles table creation, record insert, meta, queries.
- `Record` is the value object. `Query` builds filtered queries for the list table.
- `wp_stream` + `wp_streammeta` tables. `is_large_records_table($n)` filter (default: `>1_000_000` is "large") — gates behavior in admin / scheduler.

### Admin UI (`classes/class-admin.php`, `class-list-table.php`, `class-settings.php`, `class-network.php`)
- `Admin` only constructed in admin context (`is_admin()`) or with `WP_STREAM_DEV_DEBUG` / `WP_CLI` / `DOING_CRON`. REST/AJAX contexts get a lighter `Admin` without UI bootstrap.
- `Settings` registers settings + sections (general, advanced, etc.). `enable_abilities_api` advanced toggle gates the Abilities API.
- `List_Table` — main records screen. Filter by connector, context, action, author, IP, date range.
- `Network` — multisite-only admin (network settings page).
- JS entry points in `src/js/`: `admin.js`, `alerts.js`, `settings.js`, `live-updates.js`, `alerts-list.js`, `admin-exclude.js`, `alert-type-highlight.js`, `global.js`, `wpseo-admin.js`. Webpack config in `webpack.config.js`. Select2 + timeago copied from `node_modules` into `build/`.

### Alerts (`classes/class-alerts.php`, `class-alert.php`, `class-alert-trigger*`, `class-alert-type*`)
- `Alert` — single rule: trigger + type.
- Triggers (`alerts/`): `action`, `author`, `context`.
- Types (`alerts/`): `none`, `highlight` (admin menu), `email`, `ifttt`, `slack`, `die` (hard stop), `menu-alert`.
- `Alerts_List` renders the admin list.
- `wp_stream_test_*` hooks also used here for tests.

### Abilities / MCP (`classes/class-abilities.php`, `class-ability.php`, `abilities/`)
- New (4.2+) integration with WordPress Abilities API. Gated on WP 6.9+ AND the `enable_abilities_api` advanced setting.
- Each ability = subclass of `Ability` in `abilities/class-ability-*.php`. Abstract methods: `get_name()`, `get_label()`, `get_description()`, `get_input_schema()`, `get_output_schema()`, `execute($input = null)`. Optional: `permission_callback()` (default: `WP_STREAM_SETTINGS_CAPABILITY` — read abilities should override with `view_stream`).
- `Ability::get_meta()` sets `show_in_rest: true` AND `mcp.public: true` so the [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) auto-exposes them when installed.
- MCP Adapter is a `require-dev` Composer dep; `composer install` drops it into `local/public/wp-content/plugins/mcp-adapter/`. Activate with `wp plugin activate mcp-adapter --network`. Verify route: `curl -sk https://stream.wpenv.net/wp-json/mcp/mcp-adapter-default-server`.
- Existing abilities: `get-records`, `get-record`, `get-connectors`, `get-settings`, `update-settings`, `get-alerts`, `create-alert`, `delete-alert`, `get-exclusion-rules`, `create-exclusion-rule`, `purge-records`.
- `Trait_View_Stream_Permission` in `abilities/trait-view-stream-permission.php` — shared by all read abilities. Loaded in `tests/bootstrap.php` for PHPUnit coverage (see PHPUnit config note above).

### Scheduler (`classes/class-scheduler.php`, `class-as-scheduler.php`, `class-cron-scheduler.php`)
- `Plugin::create_scheduler()` picks `AS_Scheduler` (Action Scheduler, bundled in `vendor/woocommerce/action-scheduler/`) or `Cron_Scheduler` (WP-Cron fallback) based on `wp_stream_use_action_scheduler` filter. Default: AS when its file exists.
- Used for deferred work: record purge, reset, scheduled re-checks. AS is hard requirement for the WP.org build.
- `AS_Scheduler_Versions` arbitration handles a host that already provides its own AS.

### Exporters (`exporters/class-exporter-csv.php`, `class-exporter-json.php`)
`Export` admin page. Format swappable via filter.

### Other
- `class-cli.php` — `wp stream` WP-CLI command. Excluded from PHPUnit coverage.
- `class-installer.php` — DB migrations / `db-updates.php`. Always loaded in admin/CLI/cron contexts.
- `class-live-update.php` — heartbeat-driven list table refresh.
- `class-form-generator.php` — settings form helper.
- `class-filter-input.php` — `wp_stream_filter_input()` wrapper.
- `class-date-interval.php` — interval math for "records older than X days" queries.
- `class-author.php` — author-role context for records.
- `class-network.php` — multisite admin.

## Tests

- PHPUnit: `tests/phpunit/test-*.php` (one class per `classes/class-*.php`). Mirrored `tests/phpunit/connectors/`, `tests/phpunit/abilities/`, `tests/phpunit/alerts/`.
- Base class: `WP_StreamTestCase` (in `tests/testcase.php`) extends `WP_Ajax_UnitTestCase`. `$this->plugin` is pre-populated in `setUp()`. Helpers: `do_action_validation()`, `do_filter_validation()`.
- Abilities: `tests/phpunit/abilities/abilities-testcase.php` provides a base. `fake-ability.php` is a stub used by unit tests.
- Fixtures / test data: `tests/data/`.
- Bootstrap (`tests/bootstrap.php`): loads `WP_TESTS_DIR` from env, defines `WP_STREAM_TESTS`, `WP_STREAM_DEV_DEBUG`. Forces plugin activation via `WP_TEST_ACTIVATED_PLUGINS` constant. Activates EDD + WordPress SEO. Jetpack in offline mode.
- E2E (Playwright): `tests/e2e/`. Setup auth state in `tests/e2e/setup/`. `playwright.config.js` uses `https://stream.wpenv.net` base URL with `ignoreHTTPSErrors: true`.

## Coding conventions

- WordPress Coding Standards (WPCS) + VIP coding standards. `phpcs.xml.dist` enforces; runs via `composer lint` / `composer lint-tests` for tests. `WordPress.Security.EscapeOutput.DeprecatedWhitelistCommentFound` excluded. `view_stream` registered as known custom cap.
- PHPCompatibilityWP targets PHP 7.0+.
- PHP files use `namespace WP_Stream;`. Class file naming: `class-{lowercase-underscored-name}.php` in `classes/`, `connectors/`, `alerts/`, `abilities/`, `exporters/`.
- All new connectors follow the abstract pattern in `classes/class-connector.php`. Register `$actions`, dispatch via `callback_{sanitized_action_name}`. Use `log()` / `delayed_log()` only.
- All new abilities extend `WP_Stream\Ability`, set namespaced name (e.g. `stream/foo-bar`), JSON Schema for I/O, and default `permission_callback` to the most restrictive cap. `get_meta()` should keep `mcp.public: true` if the ability should be MCP-discoverable.
- Text domain: `stream`. Load via `load_plugin_textdomain('stream', ...)` in `Plugin::i18n()`.
- i18n strings: use the translation functions; never echo HTML without `esc_html` / `wp_kses_*`.

## Release flow

- Version constant in `stream.php` header + `Plugin::VERSION` in `classes/class-plugin.php`. Keep in sync.
- Branch from `develop`: `release/vX.Y.Z`. PR target: `develop`. Master receives merges from release branches only.
- GitHub workflow `.github/workflows/lint-and-test.yml` runs on push.
- `.github/workflows/deploy-to-wp-org.yml` and `deploy-to-stream-dist.yml` handle WP.org + plugin-dist deploys from GitHub releases.
- Semver. Pre-release tags: `X.Y.Z-rc.N`. Release tags: `vX.Y.Z`.
- WordPress.org plugin ZIP is generated by the action; no manual packaging.

## Common gotchas

- `Plugin::__construct` runs at plugin-file include time, BEFORE `plugins_loaded`. The `wp_stream_use_action_scheduler` filter must be registered from mu-plugins, `wp-config.php`, or an earlier-loading plugin — not from a normal plugin's `plugins_loaded` callback (too late).
- `Plugin::enqueue_asset()` requires `npm run build` to have produced `build/{handle}.asset.php`. Missing assets → `RuntimeException` (visible in admin).
- Coverage analysis with `processUncoveredFiles="true"` will `include_once` files in the include list directly, bypassing the autoloader. Any class hit that `use`s a trait must have the trait available at include time. The shared `Trait_View_Stream_Permission` is loaded in `tests/bootstrap.php` for this reason.
- `is_admin()` check in the constructor means REST/AJAX contexts get a leaner `Plugin` (no `Admin` UI bootstrap). If a feature is needed in REST/AJAX, gate the load differently or check `$this->plugin->admin` (e.g. `Abilities::__construct` does this for the `user_has_cap` filter).
- `Connector::callback()` fires `wp_stream_test_*` only when `WP_STREAM_TESTS` is defined — that constant is set by the PHPUnit bootstrap, not in production.
- `Connector::is_dependency_satisfied()` is the canonical way to skip a connector's registration when its host plugin (ACF, EDD, etc.) isn't active. Don't add module-load guards inline.
- `Settings` changes usually need a fresh settings registration in `classes/class-settings.php` + a section registration; UI lives in `src/js/settings.js` for client-side bits.
- `connectors.md` is generated, not hand-edited. Run `npm run document:connectors` after adding/changing connectors.
