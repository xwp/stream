# Stream for WordPress

[![Lint and Test](https://github.com/xwp/stream/actions/workflows/lint-and-test.yml/badge.svg)](https://github.com/xwp/stream/actions/workflows/lint-and-test.yml)
[![Coverage Status](https://coveralls.io/repos/github/xwp/stream/badge.svg?branch=develop)](https://coveralls.io/github/xwp/stream?branch=develop)

**Activity log and audit trail for WordPress.** Track every user and system action for debugging, security, and compliance.

- [Product Website](https://xwp.co/work/stream/)
- [Plugin on WordPress.org](https://wordpress.org/plugins/stream/)

## Documentation

View the [plugin description on WordPress.org](https://wordpress.org/plugins/stream/) for the list of features and screenshots.

Hook and filter reference: [docs/hooks.md](docs/hooks.md) (regenerate with `npm run docs:hooks`).

### Connectors

A list of the connectors is in [connectors.md](connectors.md). To add one, follow [How to add a connector](docs/adding-a-connector.md).

### Alerts

To add an alert type, follow [How to add an alert type](docs/adding-an-alert-type.md).

### Configuration

To customize who can manage Stream settings, you can define the `WP_STREAM_SETTINGS_CAPABILITY` constant in your `wp-config.php` file. By default, capability will be set to `manage_options`.

```php
define('WP_STREAM_SETTINGS_CAPABILITY', 'wp_stream_manage_settings');
```

## Known Issues

- We have temporarily disabled the data removal feature through plugin uninstallation, starting with version 3.9.3. We identified a few edge cases that did not behave as expected and we decided that a temporary removal is preferable at this time for such an impactful and irreversible operation. Our team is actively working on refining this feature to ensure it performs optimally and securely. We plan to reintroduce it in a future update with enhanced safeguards.

## Changelog

[View the changelog here.](changelog.md)

## Contribute

All suggestions and contributions are welcome! View the [contributor documentation](contributing.md) for how to report issues and setup the local development environment. For a map of how the plugin boots and how connectors, alerts, and exports flow, read [ARCHITECTURE.md](ARCHITECTURE.md).

## Credits

The plugin is owned and maintained by [XWP](https://xwp.co). View [all contributors](https://github.com/xwp/stream/graphs/contributors).
