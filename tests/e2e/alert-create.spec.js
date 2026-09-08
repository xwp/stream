/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { newAuthedPage, seedSettingsRecord } from './helpers/stream-plugin';

/**
 * Create a Highlight alert (smallest reliable notifier — paints the matching
 * records row). Empty triggers mean "any" event. Then fire a real Settings
 * update and assert the records list shows the highlight class.
 *
 * Email alerts go through wp_mail. Highlight is asserted here instead
 * of querying Email Logger, which keeps the E2E flow simpler.
 */

test.describe.configure( { mode: 'serial' } );

/** List-table row id (`post-{ID}`) of the alert this spec created. */
let createdAlertRowId = '';
let createdWebhookRowId = '';

test.afterAll( async ( { browser } ) => {
	const page = await newAuthedPage( browser );
	await trashCreatedAlert( page, createdAlertRowId );
	await trashCreatedAlert( page, createdWebhookRowId );
	await page.context().close();
} );

test.describe( 'Alert create', () => {
	test( 'creates a highlight alert, fires on a real event, and marks the record', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/edit.php?post_type=wp_stream_alerts' );
		const existingIds = await listAlertRowIds( page );

		await page.locator( 'a.page-title-action' ).click();
		const form = page.locator( '#add-new-alert' );
		await expect( form ).toBeVisible();

		await form.locator( '#wp_stream_alert_type' ).selectOption( 'highlight' );
		await expect( form.locator( '#wp_stream_highlight_color' ) ).toBeVisible();

		await form.locator( 'button.button-primary.save' ).click( {
			noWaitAfter: true,
		} );
		await expect( page.locator( '#add-new-alert' ) ).toHaveCount( 0, {
			timeout: 20_000,
		} );
		await expect(
			page.locator( '#the-list tr.type-wp_stream_alerts' ).first(),
		).toBeVisible();

		const afterIds = await listAlertRowIds( page );
		createdAlertRowId = afterIds.find( ( id ) => ! existingIds.includes( id ) ) || '';
		expect( createdAlertRowId, 'New alert row should appear after save' ).toBeTruthy();

		await seedSettingsRecord( page );

		await page.goto( '/wp-admin/admin.php?page=wp_stream' );
		await expect(
			page.locator( '#the-list tr.alert-highlight' ).first(),
		).toBeVisible();
		await expect(
			page.locator( '#the-list tr.highlight-yellow' ).first(),
		).toBeVisible();
	} );

	test( 'creates an outgoing webhook alert', async ( { page } ) => {
		await page.goto( '/wp-admin/edit.php?post_type=wp_stream_alerts' );
		const existingIds = await listAlertRowIds( page );

		await page.locator( 'a.page-title-action' ).click();
		const form = page.locator( '#add-new-alert' );
		await expect( form ).toBeVisible();
		await expect( form.locator( '#wp_stream_alert_type option[value="ifttt"]' ) ).toHaveCount(
			0,
		);

		await form.locator( '#wp_stream_alert_type' ).selectOption( 'webhook' );
		await expect( form.locator( '#wp_stream_webhook_url' ) ).toBeVisible();
		await form.locator( '#wp_stream_webhook_url' ).fill( 'https://example.com/stream-hook' );
		await form.locator( '#wp_stream_webhook_method' ).selectOption( 'POST' );

		await form.locator( 'button.button-primary.save' ).click( {
			noWaitAfter: true,
		} );
		await expect( page.locator( '#add-new-alert' ) ).toHaveCount( 0, {
			timeout: 20_000,
		} );

		const afterIds = await listAlertRowIds( page );
		createdWebhookRowId =
			afterIds.find( ( id ) => ! existingIds.includes( id ) ) || '';
		expect( createdWebhookRowId, 'Webhook alert row should appear after save' ).toBeTruthy();
		await expect(
			page.locator( `#the-list tr#${ createdWebhookRowId }` ),
		).toContainText( /Outgoing Webhook/ );
	} );
} );

/**
 * Row ids currently listed on the Stream Alerts table.
 *
 * @param {import('@playwright/test').Page} wpPage Page.
 * @return {Promise<string[]>} `post-{ID}` values.
 */
async function listAlertRowIds( wpPage ) {
	return wpPage
		.locator( '#the-list tr.type-wp_stream_alerts' )
		.evaluateAll( ( rows ) => rows.map( ( row ) => row.id ).filter( Boolean ) );
}

/**
 * Trash an alert created by this spec so leftover rules do not affect later tests.
 *
 * @param {import('@playwright/test').Page} wpPage Page.
 * @param {string}                          rowId  `post-{ID}` value.
 */
async function trashCreatedAlert( wpPage, rowId ) {
	if ( ! rowId ) {
		return;
	}
	await wpPage.goto( '/wp-admin/edit.php?post_type=wp_stream_alerts' );
	const row = wpPage.locator( `#the-list tr#${ rowId }` );
	if ( ! ( await row.isVisible().catch( () => false ) ) ) {
		return;
	}
	await row.hover();
	const trash = row.getByRole( 'link', { name: /Trash/i } );
	if ( await trash.isVisible().catch( () => false ) ) {
		await trash.click();
		await wpPage
			.locator( '.notice-success, #message' )
			.first()
			.waitFor( { state: 'visible', timeout: 10_000 } )
			.catch( () => {} );
	}
}
