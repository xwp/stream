<?php
/**
 * Composer post-install / post-update helper that recursively installs
 * the WordPress MCP Adapter plugin's own Composer dependencies.
 *
 * The adapter ships as type=wordpress-plugin so Stream's `installer-paths`
 * config drops it into local/public/wp-content/plugins/mcp-adapter/. The
 * adapter's bootstrap then calls its own Autoloader, which expects
 * <plugin>/vendor/autoload.php to exist. That file only exists if the
 * adapter's own `composer install` has been run inside its plugin
 * directory, which Composer does NOT do automatically for sub-packages.
 *
 * This script bridges the gap. It runs after every `composer install`
 * and `composer update` on the host (production releases use --no-dev,
 * so the adapter directory simply doesn't exist and we silently no-op).
 * Dev installs land here, see the directory, find no vendor/, and run
 * `composer install --no-dev` inside it.
 *
 * Shipping safety: the adapter directory lives under /local/, which is
 * already excluded from release bundles by .distignore, so the artifact
 * shipped to WordPress.org never carries the adapter or its vendor
 * regardless of how this script behaves.
 *
 * @package WP_Stream
 */

$plugin_dir = __DIR__ . '/../public/wp-content/plugins/mcp-adapter';

// Adapter not installed (production release, or fresh checkout without
// dev deps). Nothing to do, no log noise.
if ( ! file_exists( $plugin_dir . '/composer.json' ) ) {
	return;
}

// Sub-dependencies already populated. Nothing to do.
if ( is_dir( $plugin_dir . '/vendor' ) ) {
	return;
}

echo "Installing mcp-adapter sub-dependencies...\n";

// Run inside the adapter directory; --no-dev because we never need its
// dev tooling for runtime use.
passthru(
	sprintf(
		'composer install --no-dev --no-interaction --working-dir=%s',
		escapeshellarg( $plugin_dir )
	),
	$exit_code
);

exit( (int) $exit_code );
