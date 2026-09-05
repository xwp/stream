/**
 * External dependencies
 */
import { test as setup } from '@playwright/test';

/**
 * Internal dependencies
 */
import {
	clearAutoPurgeQueueViaWpCli,
	clearNetworkExcludeRulesViaWpCli,
	followAdminLink,
	loginAsAdmin,
} from '../helpers/stream-plugin';

const authFile = 'playwright/.auth/user.json';

/**
 * Log in and ensure Stream is network-activated before all tests.
 * Specs assume the plugin stays active and must not toggle it.
 * @see https://playwright.dev/docs/auth
 */
setup( 'authenticate', async ( { page } ) => {
	await loginAsAdmin( page );

	await page.goto( '/wp-admin/network/plugins.php' );
	const isActive = await page.locator( '#deactivate-stream' ).isVisible();

	// eslint-disable-next-line no-console
	console.log( `Stream is currently active: ${ isActive }` );

	if ( ! isActive ) {
		// eslint-disable-next-line no-console
		console.log( 'Activating Stream before tests.' );
		await followAdminLink( page, page.locator( '#activate-stream' ) );
	}

	// Interrupted headed/debug runs can leave a reaper or empty exclude
	// rows that hide the orphan-cleanup link or swallow later records.
	clearAutoPurgeQueueViaWpCli();
	clearNetworkExcludeRulesViaWpCli();

	// End of authentication steps.
	await page.context().storageState( { path: authFile } );

	// eslint-disable-next-line no-console
	console.log( 'Done with network setup.' );
} );
