/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { newAuthedPage } from './helpers/stream-plugin';

/**
 * Admin UI smoke test for the Stream plugin.
 *
 * Loads the main Stream admin screens and asserts that native selects,
 * the jQuery UI datepicker, and relative timestamps render, and that no
 * uncaught JS errors are emitted. Intended to catch regressions from
 * upstream jQuery or jQuery UI version bumps that the unit / integration
 * suites do not exercise.
 */

test.describe.configure( { mode: 'serial' } );

let page;
const consoleErrors = [];
const pageErrors = [];

test.beforeAll( async ( { browser } ) => {
	page = await newAuthedPage( browser );

	page.on( 'console', ( msg ) => {
		if ( msg.type() === 'error' ) {
			consoleErrors.push( msg.text() );
		}
	} );
	page.on( 'pageerror', ( err ) => {
		pageErrors.push( err.message );
	} );
} );

test.afterAll( async () => {
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

	await page.context().close();
} );

test.describe( 'Admin UI smoke', () => {
	test( 'exposes window.jQuery on the Stream records page', async () => {
		await page.goto( '/wp-admin/admin.php?page=wp_stream' );
		const version = await page.evaluate(
			() => window.jQuery && window.jQuery.fn && window.jQuery.fn.jquery,
		);
		// eslint-disable-next-line no-console
		console.log( `window.jQuery.fn.jquery = ${ version }` );
		expect( version ).toBeTruthy();
	} );

	test( 'renders the records list table', async () => {
		await page.goto( '/wp-admin/admin.php?page=wp_stream' );
		await expect( page.locator( 'table.wp-list-table' ) ).toBeVisible();
	} );

	test( 'opens the jQuery UI date range picker', async () => {
		await page.goto( '/wp-admin/admin.php?page=wp_stream' );

		// The date inputs are revealed only when the "Custom" range is selected.
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

	test( 'uses native selects on the records filters', async () => {
		await page.goto( '/wp-admin/admin.php?page=wp_stream' );
		await expect(
			page.locator( '#record-filter-form select[name="context"]' ),
		).toBeVisible();
		await expect( page.locator( '.select2-dropdown' ) ).toHaveCount( 0 );
		await expect( page.locator( '.select2-container' ) ).toHaveCount( 0 );
	} );

	test( 'renders relative timestamps next to the absolute date', async () => {
		await page.goto( '/wp-admin/admin.php?page=wp_stream' );
		const time = page
			.locator( 'table.wp-list-table .column-date time.timeago' )
			.first();
		await expect( time ).toBeVisible();
		await expect( time ).toHaveAttribute( 'datetime', /./ );
		await expect( time ).toHaveText(
			/ago|now|yesterday|today|tomorrow|last\s|next\s|in\s|this\s/i,
		);
		// Previous presentation: the bold relative string renders alongside the
		// original absolute date, which stays visible in the cell.
		await expect( time.locator( 'xpath=ancestor::td[1]' ) ).toHaveText(
			/\d{4}\/\d{2}\/\d{2}/,
		);
	} );

	test( 'uses native selects on Settings exclude rules', async () => {
		await page.goto(
			'/wp-admin/admin.php?page=wp_stream_settings&tab=exclude',
		);
		await page.locator( '#exclude_rules_new_rule' ).click();
		const contextSelect = page
			.locator( '.stream-exclude-list tbody tr:not(.hidden) select.connector_or_context' )
			.last();
		await expect( contextSelect ).toBeVisible();
		await expect( page.locator( '.select2-dropdown' ) ).toHaveCount( 0 );
		await expect( page.locator( '.select2-container' ) ).toHaveCount( 0 );
	} );

	test( 'loads the Settings tab', async () => {
		await page.goto( '/wp-admin/admin.php?page=wp_stream_settings' );
		await expect( page.locator( 'form' ).first() ).toBeVisible();
	} );

	test( 'loads the Alerts tab', async () => {
		await page.goto( '/wp-admin/edit.php?post_type=wp_stream_alerts' );
		const list = page.locator( '.wp-list-table' );
		const empty = page.locator( '.no-items, .post-state' );
		await expect( list.or( empty ).first() ).toBeVisible();
		await expect( page.locator( '.select2-dropdown' ) ).toHaveCount( 0 );
		const bulkWidth = await page
			.locator( '#bulk-action-selector-top' )
			.evaluate( ( el ) => parseFloat( window.getComputedStyle( el ).width ) );
		expect( bulkWidth ).toBeLessThan( 250 );
	} );

	test( 'new alert type list includes webhook and omits IFTTT', async () => {
		await page.goto( '/wp-admin/edit.php?post_type=wp_stream_alerts' );
		await page.locator( 'a.page-title-action' ).click();
		const form = page.locator( '#add-new-alert' );
		await expect( form ).toBeVisible();
		const typeSelect = form.locator( '#wp_stream_alert_type' );
		await expect( typeSelect.locator( 'option[value="webhook"]' ) ).toHaveCount( 1 );
		await expect( typeSelect.locator( 'option[value="ifttt"]' ) ).toHaveCount( 0 );
	} );
} );
