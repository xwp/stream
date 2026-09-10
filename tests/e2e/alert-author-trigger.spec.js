/**
 * Alert author trigger end-to-end: picking, titling, firing, and quick edit.
 *
 * Covers the user-picker migration's core promise — an alert bound to one
 * author fires only for that author — plus the title regeneration and
 * quick-edit seeding fixes. Runs in both picker modes.
 */

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	forceUserComboboxViaWpCli,
	newAuthedPage,
	restorePreloadUsersCapViaWpCli,
	seedAuthorRecordViaWpCli,
	selectUserCombobox,
} from './helpers/stream-plugin';

test.describe.configure( { mode: 'serial' } );

const ALICE_ID = '3';
const ALICE_NAME = 'Alice Editor';

/** Row ids created by this spec, trashed in afterAll. */
const createdRowIds = [];

test.afterAll( async ( { browser } ) => {
	const page = await newAuthedPage( browser );
	await page.goto( '/wp-admin/edit.php?post_type=wp_stream_alerts' );
	for ( const rowId of createdRowIds ) {
		await page.evaluate( async ( id ) => {
			await fetch( `/wp-admin/post.php?post=${ id }&action=trash`, {
				credentials: 'same-origin',
			} ).catch( () => {} );
		}, rowId );
	}
	await page.context().close();
} );

/**
 * Create an alert via the Add New row with the given author.
 *
 * @param {import('@playwright/test').Page} page      Authed page.
 * @param {string}                          author    Author user id ('' = any).
 * @param {Function}                        setAuthor Mode-specific picker callback.
 * @return {Promise<string>} New row id (`post-{ID}`).
 */
async function createAlertWithAuthor( page, author, setAuthor ) {
	await page.goto( '/wp-admin/edit.php?post_type=wp_stream_alerts' );
	const existingIds = await listAlertRowIds( page );
	await page.locator( 'a.page-title-action' ).click();
	const form = page.locator( '#add-new-alert' );
	await expect( form ).toBeVisible();
	await page.waitForTimeout( 400 );

	await setAuthor();

	await form.locator( '#wp_stream_alert_type' ).selectOption( 'highlight' );
	await form.locator( '#wp_stream_highlight_color' ).selectOption( 'blue' );
	await form.locator( 'button.button-primary.save' ).click( { noWaitAfter: true } );
	await expect( page.locator( '#add-new-alert' ) ).toHaveCount( 0, {
		timeout: 20_000,
	} );

	const afterIds = await listAlertRowIds( page );
	const newRowId = afterIds.find( ( id ) => ! existingIds.includes( id ) ) || '';
	expect( newRowId, 'Alert row should appear after save' ).toBeTruthy();
	createdRowIds.push( newRowId );
	return newRowId;
}

/**
 * Row ids currently listed on the Alerts table.
 *
 * @param {import('@playwright/test').Page} wpPage Page.
 * @return {Promise<string[]>} `post-{ID}` values.
 */
async function listAlertRowIds( wpPage ) {
	return wpPage
		.locator( '#the-list tr.type-wp_stream_alerts' )
		.evaluateAll( ( rows ) => rows.map( ( row ) => row.id ).filter( Boolean ) );
}

test.describe( 'Alert author trigger (native picker)', () => {
	test( 'pick saves, titles the alert, and fires only for that author', async ( {
		page,
	} ) => {
		const setAuthor = () =>
			page.evaluate(
				( author ) => {
					const select = document.querySelector(
						'#add-new-alert select[name="wp_stream_trigger_author"]',
					);
					select.value = author;
					select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				},
				ALICE_ID,
			);

		const rowId = await createAlertWithAuthor( page, ALICE_ID, setAuthor );

		// The list title regenerates from the saved trigger.
		await expect( page.locator( `#${ rowId }` ) ).toContainText( ALICE_NAME );

		// Fire uniquely-titled records as two different authors and judge the
		// alert only by rows carrying our marker (and our highlight color, so
		// pre-existing alerts on the install cannot skew the result).
		const marker = `E2E fires ${ Date.now() } `;
		seedAuthorRecordViaWpCli( 1, marker + 'by-admin' );
		seedAuthorRecordViaWpCli( ALICE_ID, marker + 'by-alice' );

		await page.goto( '/wp-admin/admin.php?page=wp_stream' );
		const adminRow = page
			.locator( '#the-list tr:not(.no-items)' )
			.filter( { hasText: marker + 'by-admin' } );
		const aliceRow = page
			.locator( '#the-list tr:not(.no-items)' )
			.filter( { hasText: marker + 'by-alice' } );

		await expect( adminRow ).toHaveCount( 1 );
		await expect( adminRow ).not.toHaveClass( /highlight-blue/ );
		await expect( aliceRow ).toHaveCount( 1 );
		await expect( aliceRow ).toHaveClass( /highlight-blue/ );
		await expect( aliceRow.locator( '.column-user_id' ) ).toContainText(
			ALICE_NAME,
		);
	} );

	test( 'WP-CLI author titles the alert as WP-CLI', async ( { page } ) => {
		const setAuthor = () =>
			page.evaluate( () => {
				const select = document.querySelector(
					'#add-new-alert select[name="wp_stream_trigger_author"]',
				);
				select.value = '0';
				select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} );

		const rowId = await createAlertWithAuthor( page, '0', setAuthor );

		await expect( page.locator( `#${ rowId }` ) ).toContainText( 'WP-CLI' );
	} );

	test( 'quick edit seeds the saved author and editing updates the title', async ( {
		page,
	} ) => {
		const setAuthor = () =>
			page.evaluate(
				( author ) => {
					const select = document.querySelector(
						'#add-new-alert select[name="wp_stream_trigger_author"]',
					);
					select.value = author;
					select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				},
				ALICE_ID,
			);
		const rowId = await createAlertWithAuthor( page, ALICE_ID, setAuthor );

		// Open Quick Edit on the new row.
		await page.evaluate( ( id ) => {
			document
				.querySelector( `#the-list #${ id } .row-actions .editinline` )
				?.click();
		}, rowId );
		const editRow = page.locator( `#edit-${ rowId.replace( 'post-', '' ) }` );
		await expect( editRow ).toBeVisible();

		// The saved author is preselected (the previously-dropped value).
		await expect(
			editRow.locator( 'select[name="wp_stream_trigger_author"]' ),
		).toHaveValue( ALICE_ID );

		// Change it to admin and save; the title follows (regeneration fix).
		await editRow.locator( 'select[name="wp_stream_trigger_author"]' ).selectOption( '1' );
		await editRow.locator( 'button.save' ).click( { noWaitAfter: true } );
		await page.waitForTimeout( 2500 );
		await expect( page.locator( `#${ rowId }` ) ).toContainText( /(^|\s)admin/ );
	} );
} );

test.describe( 'Alert author trigger (combobox picker)', () => {
	test.beforeAll( () => {
		forceUserComboboxViaWpCli();
	} );

	test.afterAll( () => {
		restorePreloadUsersCapViaWpCli();
	} );

	test( 'combobox pick saves, titles, and quick edit seeds the combobox', async ( {
		page,
	} ) => {
		const setAuthor = async () => {
			const combobox = page.locator( '#add-new-alert .stream-user-combobox' );
			await selectUserCombobox( combobox, 'alice' );
			await expect(
				combobox.locator( '.stream-user-combobox__value' ),
			).not.toHaveValue( '' );
		};

		const rowId = await createAlertWithAuthor( page, '', setAuthor );
		await expect( page.locator( `#${ rowId }` ) ).toContainText( ALICE_NAME );

		// Quick Edit seeds the combobox with the saved author's label.
		await page.evaluate( ( id ) => {
			document
				.querySelector( `#the-list #${ id } .row-actions .editinline` )
				?.click();
		}, rowId );
		const editRow = page.locator( `#edit-${ rowId.replace( 'post-', '' ) }` );
		await expect( editRow ).toBeVisible();
		await expect(
			editRow.locator( '.stream-user-combobox__input' ),
		).toHaveValue( ALICE_NAME );
	} );
} );
