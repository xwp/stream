/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	ensureSubdirectorySite,
	clearNetworkExcludeRulesViaWpCli,
	seedSubsitePostViaWpCli,
} from './helpers/stream-plugin';

/**
 * Records while Stream is network-activated: network admin and a real
 * subdirectory sub-site. `network-activated.spec.js` covers the plugin
 * menu / settings link when Stream is off vs on (its own serial project).
 *
 * The stack is subdirectory multisite (`SUBDOMAIN_INSTALL` false). Paths are
 * resolved against Playwright's baseURL (`http://localhost:8888` by default),
 * e.g. `http://localhost:8888/<slug>/wp-admin/`.
 */

const SUBSITE_SLUG = 'e2e-records';
const SUBSITE_TITLE = 'Stream E2E Records';

test.describe.configure( { mode: 'serial' } );

let seeded = false;
let subsitePostTitle = '';

test.beforeEach( async ( { page } ) => {
	if ( ! seeded ) {
		await ensureSubdirectorySite( page, SUBSITE_SLUG );
		clearNetworkExcludeRulesViaWpCli();
		subsitePostTitle = seedSubsitePostViaWpCli( SUBSITE_SLUG );
		seeded = true;
	}
} );

test.describe( 'Network-activated records', () => {
	test( 'shows the sub-site record in network admin', async ( { page } ) => {
		await page.goto( '/wp-admin/network/admin.php?page=wp_stream' );
		await filterNetworkListBySiteTitle( page, SUBSITE_TITLE );
		await expect( page.locator( 'table.wp-list-table' ) ).toBeVisible();
		await expect(
			page.locator( '#the-list tr:not(.no-items)' ).first(),
		).toBeVisible();
		await expect(
			page.getByText( subsitePostTitle ).first(),
		).toBeVisible();
		await expect(
			page.locator( '#the-list .column-blog_id' ).first(),
		).toContainText( SUBSITE_TITLE );
	} );

	test( 'shows records on the subdirectory sub-site admin', async ( {
		page,
	} ) => {
		await page.goto(
			`/${ SUBSITE_SLUG }/wp-admin/admin.php?page=wp_stream`,
		);
		await expect( page ).toHaveURL(
			new RegExp( `/${ SUBSITE_SLUG }/wp-admin/` ),
		);
		await expect( page.locator( 'table.wp-list-table' ) ).toBeVisible();
		await expect(
			page.locator( '#the-list tr:not(.no-items)' ).first(),
		).toBeVisible();
		await expect(
			page.getByText( subsitePostTitle ).first(),
		).toBeVisible();
	} );

	test( 'does not show the sub-site record on the primary site', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/admin.php?page=wp_stream' );
		await expect( page ).not.toHaveURL(
			new RegExp( `/${ SUBSITE_SLUG }/wp-admin/` ),
		);
		await expect( page.locator( 'table.wp-list-table' ) ).toBeVisible();
		await expect( page.locator( '#the-list' ) ).not.toContainText(
			subsitePostTitle,
		);
	} );
} );

/**
 * Restrict the network records list to one site via the Site filter.
 *
 * @param {import('@playwright/test').Page} wpPage    Page.
 * @param {string}                          siteTitle Blog name.
 */
async function filterNetworkListBySiteTitle( wpPage, siteTitle ) {
	const blogId = await wpPage.evaluate( ( title ) => {
		const options = document.querySelectorAll(
			'select[name="blog_id"] option',
		);
		for ( const option of options ) {
			if ( option.textContent.trim() === title ) {
				return option.value;
			}
		}
		return '';
	}, siteTitle );
	expect( blogId, `Site filter should list ${ siteTitle }` ).toBeTruthy();
	await wpPage.goto(
		`/wp-admin/network/admin.php?page=wp_stream&blog_id=${ blogId }`,
	);
}
