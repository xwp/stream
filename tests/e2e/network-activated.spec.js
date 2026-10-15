/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	followAdminLink,
	networkActivateStream,
	networkDeactivateStream,
	newAuthedPage,
} from './helpers/stream-plugin';

let page;

/**
 * Off-vs-on network menu. Runs alone in the `network-activated` project
 * (after chromium and settings-ui) so deactivating Stream cannot race
 * other workers. afterAll restores activation for later reruns.
 */
test.describe( 'Network: shows network activated states', () => {
	test.describe.configure( { mode: 'serial' } );

	test.beforeAll( async ( { browser } ) => {
		page = await newAuthedPage( browser );
		await networkDeactivateStream( page );
	} );

	test.afterAll( async () => {
		await networkActivateStream( page );
		await page.context().close();
	} );

	// Do we have a published row?
	test( 'does not show stream in network admin when deactivated', async () => {
		await page.goto( '/wp-admin/network/index.php' );
		// Expects Stream log to not have the Network Settings.
		await expect(
			page.locator( '[href*="admin.php?page=wp_stream"]' ),
		).not.toBeVisible();
	} );

	// We should not have an updated row. This times out which makes it fail.
	test( 'does not have updated row', async () => {
		await page.goto( '/wp-admin/network/plugins.php' );
		const isInactive = await page
			.locator( '#activate-stream' )
			.isVisible();

		if ( isInactive ) {
			// eslint-disable-next-line no-console
			console.log( 'Activating Stream for next tests.' );
			await followAdminLink( page, page.locator( '#activate-stream' ) );
		}

		await page.goto( '/wp-admin/network/index.php' );
		// Expects Stream log to not have the Network Settings.
		await expect(
			page.locator( '[href*="admin.php?page=wp_stream_network_settings"]' ),
		).toBeVisible();
	} );
} );
