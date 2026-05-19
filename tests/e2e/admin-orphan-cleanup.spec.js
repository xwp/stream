/**
 * WordPress dependencies
 */
/**
 * External dependencies
 */
import { execSync } from 'node:child_process';
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Node dependencies
 */

/**
 * Settings → Advanced manual "Clean Orphaned Meta" link.
 *
 * Asserts that the link is rendered, that its href points at admin-ajax.php
 * with the expected action + nonce parameters, and that following the link
 * redirects back to the Stream settings page with a confirmation marker in
 * the URL.
 *
 * Also covers the running-state UX: when the auto-purge chain is active the
 * link is hidden and the field description is swapped to explain the reaper
 * will run as part of the active cycle.
 */
const ADMIN = 'http://stream.wpenv.net/wp-admin';

/**
 * Run a PHP snippet inside the wordpress container.
 *
 * @param {string} php The body of the eval call (no `<?php`).
 * @return {string} Trimmed stdout.
 */
function wpEval( php ) {
	// Single-quote the PHP, escape any embedded single quotes.
	const escaped = php.replace( /'/g, "'\\''" );
	return execSync(
		`docker compose run --rm --user $(id -u) wordpress -- wp eval '${ escaped }'`,
		{ encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] },
	).trim();
}

/**
 * Clear all AS state for the auto-purge group.
 */
function clearAutoPurgeState() {
	wpEval(
		`foreach (["stream_auto_purge_batch_action","stream_auto_purge_reaper_action"] as $h) { if (function_exists("as_unschedule_all_actions")) as_unschedule_all_actions($h); }`,
	);
}

/**
 * Seed a single pending reaper action so is_running_auto_purge() returns true.
 */
function seedRunningAutoPurge() {
	wpEval(
		`as_enqueue_async_action("stream_auto_purge_reaper_action", array(), "stream-auto-purge");`,
	);
}

test.describe.configure( { mode: 'serial' } );

let page;

test.beforeAll( async ( { browser } ) => {
	page = await browser.newPage();

	// The setup fixture deactivates Stream network-wide before the suite.
	// Reactivate it so the Stream admin pages are reachable.
	await page.goto( `${ ADMIN }/network/plugins.php` );
	const activate = page.getByLabel( 'Network Activate Stream' );
	if ( await activate.isVisible() ) {
		await activate.click();
	}
} );

test.afterAll( async () => {
	// Deactivate Stream again so other suites start from the same state
	// as the shared setup fixture.
	await page.goto( `${ ADMIN }/network/plugins.php` );
	const deactivate = page.getByLabel( 'Network Deactivate Stream' );
	if ( await deactivate.isVisible() ) {
		await deactivate.click();
	}
} );

const ADVANCED_TAB_URL = `${ ADMIN }/network/admin.php?page=wp_stream_network_settings&tab=advanced`;

test.describe( 'Manual orphan-meta cleanup link', () => {
	test.beforeEach( () => {
		// Idle state for the common-case assertions.
		clearAutoPurgeState();
	} );

	test( 'is visible on the Advanced tab (idle state)', async () => {
		await page.goto( ADVANCED_TAB_URL );

		const link = page.getByRole( 'link', { name: /Clean Orphaned Meta/i } );
		await expect( link ).toBeVisible();
	} );

	test( 'links to admin-ajax with the expected action and nonce', async () => {
		await page.goto( ADVANCED_TAB_URL );

		const link = page.getByRole( 'link', { name: /Clean Orphaned Meta/i } );
		const href = await link.getAttribute( 'href' );

		expect( href ).toContain( 'admin-ajax.php' );
		expect( href ).toContain( 'action=wp_stream_clean_orphan_meta' );
		expect( href ).toMatch(
			/wp_stream_nonce_clean_orphan_meta=[a-f0-9]+/,
		);
	} );

	test( 'redirects back to settings with confirmation marker', async () => {
		await page.goto( ADVANCED_TAB_URL );

		const link = page.getByRole( 'link', { name: /Clean Orphaned Meta/i } );
		await Promise.all( [
			page.waitForURL(
				/wp_stream_message=orphan_meta_cleanup_scheduled/,
			),
			link.click(),
		] );
	} );

	test( 'hides the link while the auto-purge chain is running', async () => {
		// Simulate an active chain by enqueuing a reaper action.
		seedRunningAutoPurge();

		try {
			await page.goto( ADVANCED_TAB_URL );

			// The link MUST NOT be rendered.
			const link = page.getByRole( 'link', {
				name: /Clean Orphaned Meta/i,
			} );
			await expect( link ).toHaveCount( 0 );

			// The replacement description text MUST be visible somewhere
			// in the settings form. We assert on a stable substring rather
			// than the full sentence.
			await expect(
				page.getByText( /Auto-purge is currently running/i ),
			).toBeVisible();
		} finally {
			clearAutoPurgeState();
		}
	} );

	test( 'restores the link after the chain drains (idle state again)', async () => {
		// Seed running, drain, confirm idle UX restored.
		seedRunningAutoPurge();
		clearAutoPurgeState();

		await page.goto( ADVANCED_TAB_URL );
		const link = page.getByRole( 'link', {
			name: /Clean Orphaned Meta/i,
		} );
		await expect( link ).toBeVisible();
	} );
} );
