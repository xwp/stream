/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	followAdminLink,
	seedSettingsRecord,
	setJQuerySelect,
} from './helpers/stream-plugin';

/**
 * Records list filters: each dimension must update the URL query string and
 * leave matching rows visible.
 *
 * Connector and context share one dropdown (parents = connectors, children =
 * contexts). IP has no filter control; the IP column link is the UI for that
 * dimension.
 */

test.describe.configure( { mode: 'serial' } );

let seeded = false;

test.beforeEach( async ( { page } ) => {
	if ( ! seeded ) {
		await seedSettingsRecord( page );
		seeded = true;
	}
} );

test.describe( 'Records filters', () => {
	test( 'filters by date (predefined range)', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=wp_stream' );
		await setJQuerySelect(
			page,
			'select[name="date_predefined"]',
			'last-30-days',
		);
		await submitFilters( page, /date_predefined=last-30-days/ );
		await expectVisibleRows( page );
	} );

	test( 'filters by user', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=wp_stream' );
		const userId = await firstEnabledOptionValue(
			page,
			'select[name="user_id"]',
		);
		expect(
			userId,
			'User filter should list at least one recorded user',
		).toBeTruthy();
		await setJQuerySelect( page, 'select[name="user_id"]', userId );
		await submitFilters( page, new RegExp( `user_id=${ userId }` ) );
		await expectVisibleRows( page );
	} );

	test( 'filters by connector (parent context option)', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=wp_stream' );
		const connector = await page.evaluate( () => {
			const settings = document.querySelector(
				'select[name="context"] option.level-1[data-group="settings"]:not([disabled])',
			);
			if ( settings ) {
				return {
					value: settings.value,
					group: settings.getAttribute( 'data-group' ),
				};
			}
			const child = document.querySelector(
				'select[name="context"] option.level-2:not([disabled])',
			);
			if ( ! child ) {
				return null;
			}
			let parent = child.previousElementSibling;
			while ( parent && ! parent.classList.contains( 'level-1' ) ) {
				parent = parent.previousElementSibling;
			}
			return parent
				? {
					value: parent.value,
					group: parent.getAttribute( 'data-group' ),
				}
				: null;
		} );
		expect(
			connector,
			'Connector parent options should exist after seeding',
		).toBeTruthy();
		await setJQuerySelect( page, 'select[name="context"]', connector.value );
		await submitFilters(
			page,
			new RegExp( `connector=${ connector.group }` ),
		);
		await expectVisibleRows( page );
	} );

	test( 'filters by context (child context option)', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=wp_stream' );
		// Prefer settings/general (tagline seed). Child values like `general`
		// are reused across connectors; the data-group disambiguates.
		const context = await page.evaluate( () => {
			const preferred = document.querySelector(
				'select[name="context"] option.level-2[data-group="settings"][value="general"]:not([disabled])',
			);
			const option =
				preferred ||
				document.querySelector(
					'select[name="context"] option.level-2[data-group="settings"]:not([disabled])',
				);
			return option
				? {
					value: option.value,
					group: option.getAttribute( 'data-group' ),
				}
				: null;
		} );
		expect(
			context,
			'Context child options should exist after seeding',
		).toBeTruthy();
		await setJQuerySelect(
			page,
			'select[name="context"]',
			context.value,
			`option.level-2[data-group="${ context.group }"][value="${ context.value }"]`,
		);
		await submitFilters( page, new RegExp( `context=${ context.value }` ) );
		await expect( page ).toHaveURL(
			new RegExp( `connector=${ context.group }` ),
		);
		await expectVisibleRows( page );
		await expect(
			page.locator( '#the-list tr:not(.no-items) .column-context' ).first(),
		).toBeVisible();
	} );

	test( 'filters by action', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=wp_stream' );
		const action = await firstEnabledOptionValue(
			page,
			'select[name="action"]',
		);
		expect(
			action,
			'Action filter should list at least one recorded action',
		).toBeTruthy();
		await setJQuerySelect( page, 'select[name="action"]', action );
		await submitFilters( page, new RegExp( `action=${ action }` ) );
		await expectVisibleRows( page );
	} );

	test( 'filters by IP via the column link', async ( { page } ) => {
		// There is no IP dropdown in extra_tablenav; List_Table::column_default()
		// renders each IP as a filter link (the only UI for this dimension).
		await page.goto( '/wp-admin/admin.php?page=wp_stream' );
		await expectVisibleRows( page );

		const ipLink = page
			.locator( '#the-list tr:not(.no-items) .column-ip a' )
			.first();
		await expect( ipLink ).toBeVisible();
		const ip = ( await ipLink.innerText() ).trim();
		expect( ip ).toBeTruthy();

		await followAdminLink( page, ipLink );
		await page.waitForURL( /[?&]ip=/ );
		const filteredIp = new URL( page.url() ).searchParams.get( 'ip' );
		expect( filteredIp ).toBe( ip );
		await expectVisibleRows( page );
		await expect(
			page.locator( '#the-list tr:not(.no-items) .column-ip' ).first(),
		).toContainText( ip );
	} );
} );

/**
 * Submit the records filter form and wait for the query string to match.
 *
 * @param {import('@playwright/test').Page} wpPage Page.
 * @param {RegExp}                          urlRe  Expected URL fragment.
 */
async function submitFilters( wpPage, urlRe ) {
	await Promise.all( [
		wpPage.waitForURL( urlRe ),
		wpPage
			.locator( '#record-query-submit' )
			.click( { noWaitAfter: true } ),
	] );
	await expect( wpPage ).toHaveURL( urlRe );
}

/**
 * Assert the records table has at least one data row.
 *
 * @param {import('@playwright/test').Page} wpPage Page.
 */
async function expectVisibleRows( wpPage ) {
	await expect( wpPage.locator( 'table.wp-list-table' ) ).toBeVisible();
	await expect(
		wpPage.locator( '#the-list tr:not(.no-items)' ).first(),
	).toBeVisible();
}

/**
 * First non-empty, non-disabled option value from a <select>.
 *
 * @param {import('@playwright/test').Page} wpPage   Page.
 * @param {string}                          selector CSS selector.
 * @return {Promise<string>} Option value or empty string.
 */
async function firstEnabledOptionValue( wpPage, selector ) {
	return wpPage.evaluate( ( sel ) => {
		const option = document.querySelector(
			`${ sel } option:not([disabled]):not([value=""])`,
		);
		return option ? option.value : '';
	}, selector );
}
