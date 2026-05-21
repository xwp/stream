/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Admin UI smoke test for the Stream plugin.
 *
 * Loads the main Stream admin screens and asserts that core
 * jQuery-dependent widgets render and that no uncaught JS errors
 * are emitted. Intended to catch regressions from upstream jQuery,
 * jQuery UI, or select2 version bumps that the unit / integration
 * suites do not exercise.
 */

const ADMIN = 'https://stream.wpenv.net/wp-admin';

test.describe.configure( { mode: 'serial' } );

let page;
const consoleErrors = [];
const pageErrors = [];

test.beforeAll( async ( { browser } ) => {
	page = await browser.newPage();

	page.on( 'console', ( msg ) => {
		if ( msg.type() === 'error' ) {
			consoleErrors.push( msg.text() );
		}
	} );
	page.on( 'pageerror', ( err ) => {
		pageErrors.push( err.message );
	} );

	// The setup fixture deactivates Stream network-wide before the suite.
	// Reactivate it so the Stream admin pages are reachable.
	await page.goto( `${ ADMIN }/network/plugins.php` );
	const activate = page.getByLabel( 'Network Activate Stream' );
	if ( await activate.isVisible() ) {
		// eslint-disable-next-line no-console
		console.log( 'Activating Stream for admin UI smoke tests.' );
		await activate.click();
	}
} );

test.afterAll( async () => {
	// Deactivate Stream again so other suites start from the same state
	// as the shared setup fixture.
	await page.goto( `${ ADMIN }/network/plugins.php` );
	const deactivate = page.getByLabel( 'Network Deactivate Stream' );
	if ( await deactivate.isVisible() ) {
		// eslint-disable-next-line no-console
		console.log( 'Deactivating Stream after admin UI smoke tests.' );
		await deactivate.click();
	}

	// eslint-disable-next-line no-console
	console.log( `Console errors captured: ${ consoleErrors.length }` );
	consoleErrors.forEach( ( e, i ) => {
		// eslint-disable-next-line no-console
		console.log( `  ${ i + 1 }. ${ e }` );
	} );

	expect(
		pageErrors,
		`No uncaught JS errors expected on Stream admin UI, got: ${ pageErrors.join(
			' | ',
		) }`,
	).toEqual( [] );
} );

test.describe( 'Admin UI smoke', () => {
	test( 'exposes window.jQuery on the Stream records page', async () => {
		await page.goto( `${ ADMIN }/admin.php?page=wp_stream` );
		const version = await page.evaluate(
			() => window.jQuery && window.jQuery.fn && window.jQuery.fn.jquery,
		);
		// eslint-disable-next-line no-console
		console.log( `window.jQuery.fn.jquery = ${ version }` );
		expect( version ).toBeTruthy();
	} );

	test( 'renders the records list table', async () => {
		await page.goto( `${ ADMIN }/admin.php?page=wp_stream` );
		await expect( page.locator( 'table.wp-list-table' ) ).toBeVisible();
	} );

	test( 'opens the jQuery UI date range picker', async () => {
		await page.goto( `${ ADMIN }/admin.php?page=wp_stream` );

		// The date inputs are revealed only when the "Custom" range is selected.
		// The visible UI is a select2 widget on top of the real <select>, so set
		// the value on the native element and dispatch the change event jQuery
		// listens for.
		await page.evaluate( () => {
			const select = document.querySelector(
				'select[name="date_predefined"]',
			);
			select.value = 'custom';
			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );

		const dateField = page.locator( 'input.date-picker.field-from' );
		await expect( dateField ).toBeVisible();
		await dateField.click();
		await expect( page.locator( '#ui-datepicker-div' ) ).toBeVisible();
	} );

	test( 'opens a select2 dropdown', async () => {
		await page.goto( `${ ADMIN }/admin.php?page=wp_stream` );
		const select2 = page.locator( '.select2-selection' ).first();
		await expect( select2 ).toBeVisible();
		await select2.click();
		await expect( page.locator( '.select2-dropdown' ) ).toBeVisible();
		await page.keyboard.press( 'Escape' );
	} );

	test( 'loads the Settings tab', async () => {
		await page.goto( `${ ADMIN }/admin.php?page=wp_stream_settings` );
		await expect( page.locator( 'form' ).first() ).toBeVisible();
	} );

	test( 'loads the Alerts tab', async () => {
		await page.goto( `${ ADMIN }/edit.php?post_type=wp_stream_alerts` );
		const list = page.locator( '.wp-list-table' );
		const empty = page.locator( '.no-items, .post-state' );
		await expect( list.or( empty ).first() ).toBeVisible();
	} );
} );
