/**
 * Functional exclude-rule coverage: a rule saved through the UI must actually
 * suppress records. Covers both author-or-role kinds in whatever picker mode
 * the environment renders (native select or combobox).
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	dropAllNetworkExcludeRulesViaWpCli,
	newAuthedPage,
	seedAuthorRecordViaWpCli,
} from './helpers/stream-plugin';

const NETWORK_EXCLUDE_URL =
	'/wp-admin/network/admin.php?page=wp_stream_network_settings&tab=exclude';
/** Alice Editor (id 3) has the editor role on this install. */
const EDITOR_ID = 3;

test.describe.configure( { mode: 'serial' } );

test.afterAll( () => {
	dropAllNetworkExcludeRulesViaWpCli();
} );

/**
 * Count Stream records for a fresh, unique title marker.
 *
 * @param {import('@playwright/test').Page} page   Authed page.
 * @param {string}                          marker Unique substring of the seeded titles.
 * @return {Promise<number>} Rows shown for the marker.
 */
async function countRecordsFor( page, marker ) {
	await page.goto( '/wp-admin/admin.php?page=wp_stream' );
	await page.waitForSelector( 'table.wp-list-table' );
	return page
		.locator( '#the-list tr:not(.no-items)' )
		.filter( { hasText: marker } )
		.count();
}

/**
 * Add an exclude rule with the given author-or-role value via the UI.
 *
 * @param {import('@playwright/test').Page} page         Authed page on the exclude tab.
 * @param {string}                          desiredValue 'editor' (role) or '3' (user id).
 * @param {string}                          desiredLabel Label to pick in the control.
 */
async function addAuthorOrRoleRule( page, desiredValue, desiredLabel ) {
	await page.goto( NETWORK_EXCLUDE_URL );
	await page.locator( '#exclude_rules_new_rule' ).click();
	await page.waitForTimeout( 400 );

	const row = page
		.locator( '.stream-exclude-list tbody tr:not(.hidden):not(.helper)' )
		.last();
	const nativeSelect = row.locator( 'select.author_or_role' );

	if ( await nativeSelect.count() ) {
		await nativeSelect.selectOption( desiredValue );
	} else {
		const combobox = row.locator( '.stream-user-combobox' );
		await combobox.locator( '.stream-user-combobox__input' ).click();
		const option = combobox
			.locator( '.stream-user-combobox__option', { hasText: desiredLabel } )
			.first();
		await option.waitFor( { state: 'visible', timeout: 10_000 } );
		await option.evaluate( ( node ) => {
			node.dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true } ) );
		} );
	}

	await Promise.all( [
		page.waitForURL( /settings-updated=true/ ),
		page.getByRole( 'button', { name: 'Save Changes' } ).click( {
			noWaitAfter: true,
		} ),
	] );
	await page.waitForLoadState( 'load' );
	await page.waitForTimeout( 500 );
}

test.describe( 'Exclude rules suppress records', () => {
	test( 'role rule excludes records by that role', async ( { browser } ) => {
		const page = await newAuthedPage( browser );
		const marker = 'E2E role rule ' + Date.now();

		await addAuthorOrRoleRule( page, 'editor', 'Editor' );

		seedAuthorRecordViaWpCli( EDITOR_ID, marker );
		const visible = await countRecordsFor( page, marker );
		expect( visible ).toBe( 0 );

		// Removing the rule lets the (already fired) record show up.
		dropAllNetworkExcludeRulesViaWpCli();
		seedAuthorRecordViaWpCli( EDITOR_ID, marker );
		await expect
			.poll( () => countRecordsFor( page, marker ) )
			.toBeGreaterThan( 0 );

		await page.close();
	} );

	test( 'user rule excludes records by that user', async ( { browser } ) => {
		const page = await newAuthedPage( browser );
		const marker = 'E2E user rule ' + Date.now();

		await addAuthorOrRoleRule( page, '3', 'Alice Editor' );

		seedAuthorRecordViaWpCli( EDITOR_ID, marker );
		await expect
			.poll( () => countRecordsFor( page, marker ) )
			.toBe( 0 );

		dropAllNetworkExcludeRulesViaWpCli();
		seedAuthorRecordViaWpCli( EDITOR_ID, marker );
		await expect
			.poll( () => countRecordsFor( page, marker ) )
			.toBeGreaterThan( 0 );

		await page.close();
	} );
} );
