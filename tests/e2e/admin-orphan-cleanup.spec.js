/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	clearAutoPurgeQueueViaWpCli,
	followAdminLink,
	newAuthedPage,
} from './helpers/stream-plugin';

/**
 * Settings → Advanced manual "Clean Orphaned Meta" link.
 *
 * Asserts the idle-state UX: the link renders, its href points at admin-ajax.php
 * with the expected action + nonce, and following it redirects back to the
 * settings page with a confirmation marker in the URL.
 *
 * The running-state branch (`is_running_auto_purge() === true` hides the link
 * and swaps the description) is covered by the PHPUnit suite. Reproducing it
 * here would require shelling out of Playwright to seed Action Scheduler state,
 * which no other e2e spec in this project does.
 */
const ADVANCED_TAB_URL =
	'/wp-admin/network/admin.php?page=wp_stream_network_settings&tab=advanced';

let page;

test.describe.configure( { mode: 'serial' } );

test.beforeAll( async ( { browser } ) => {
	page = await newAuthedPage( browser );

	// Idle-state UX is what this spec asserts. A leftover reaper from a
	// previous run hides the link (`is_running_auto_purge()`).
	clearAutoPurgeQueueViaWpCli();
} );

test.afterAll( async () => {
	clearAutoPurgeQueueViaWpCli();
	await page.context().close();
} );

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
		await followAdminLink( page, link );
		await page.waitForURL(
			/wp_stream_message=orphan_meta_cleanup_scheduled/,
		);
	} );
} );
