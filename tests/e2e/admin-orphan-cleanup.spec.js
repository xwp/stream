/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Settings → Advanced manual "Clean Orphaned Meta" link.
 *
 * Asserts that the link is rendered, that its href points at admin-ajax.php
 * with the expected action + nonce parameters, and that following the link
 * redirects back to the Stream settings page with a confirmation marker in
 * the URL.
 */
const ADMIN = 'http://stream.wpenv.net/wp-admin';

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
	test( 'is visible on the Advanced tab', async () => {
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
} );
