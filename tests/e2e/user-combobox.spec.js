/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	clearNetworkExcludeRulesViaWpCli,
	clearUserCombobox,
	forceUserComboboxViaWpCli,
	newAuthedPage,
	restorePreloadUsersCapViaWpCli,
	seedSettingsRecord,
	selectUserCombobox,
} from './helpers/stream-plugin';

test.describe.configure( { mode: 'serial' } );

let seeded = false;
let createdAlertRowId = '';

test.beforeAll( () => {
	forceUserComboboxViaWpCli();
} );

test.beforeEach( async ( { page } ) => {
	if ( ! seeded ) {
		await seedSettingsRecord( page );
		seeded = true;
	}
} );

test.afterAll( async ( { browser } ) => {
	try {
		const page = await newAuthedPage( browser );
		clearNetworkExcludeRulesViaWpCli();
		if ( createdAlertRowId ) {
			await page.goto( '/wp-admin/edit.php?post_type=wp_stream_alerts' );
			const row = page.locator( `#the-list tr#${ createdAlertRowId }` );
			if ( await row.isVisible().catch( () => false ) ) {
				await row.hover();
				const trash = row.getByRole( 'link', { name: /Trash/i } );
				if ( await trash.isVisible().catch( () => false ) ) {
					await trash.click();
				}
			}
		}
		await page.context().close();
	} finally {
		restorePreloadUsersCapViaWpCli();
	}
} );

/**
 * Ensure the user filter control is visible (a persisted screen-options
 * preference can hide it between runs).
 *
 * @param {import('@playwright/test').Page} page Authed page on the records screen.
 */
async function ensureUserFilterVisible( page ) {
	const combobox = page.locator( '#record-filter-form .stream-user-combobox' );
	if ( await combobox.isVisible().catch( () => false ) ) {
		return;
	}
	await page.locator( '#show-settings-link' ).click();
	const toggle = page.locator( '#user_id-hide' );
	if ( await toggle.isVisible().catch( () => false ) ) {
		await toggle.check();
	}
	await expect( combobox ).toBeVisible();
}

test.describe( 'User combobox', () => {
	test( 'records filter can search, select, and clear a user', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/admin.php?page=wp_stream' );
		await ensureUserFilterVisible( page );
		const combobox = page.locator( '#record-filter-form .stream-user-combobox' );
		await expect( combobox ).toHaveCount( 1 );

		await combobox.locator( '.stream-user-combobox__input' ).fill( 'adm' );
		const option = combobox.locator( '[role="option"]' ).first();
		await expect( option ).toBeVisible( { timeout: 10_000 } );
		await expect( option.locator( 'img.stream-user-combobox__avatar' ) ).toHaveCount(
			1,
		);
		await selectUserCombobox( combobox, 'adm' );
		await expect( combobox.locator( '.stream-user-combobox__value' ) ).not.toHaveValue(
			'',
		);

		await clearUserCombobox( combobox );
		await expect( combobox.locator( '.stream-user-combobox__value' ) ).toHaveValue(
			'',
		);
	} );

	test( 'records filter keeps the committed user when Filter races a keystroke', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/admin.php?page=wp_stream' );
		await ensureUserFilterVisible( page );
		const combobox = page.locator( '#record-filter-form .stream-user-combobox' );
		await selectUserCombobox( combobox, 'adm' );
		const userId = await combobox
			.locator( '.stream-user-combobox__value' )
			.inputValue();
		expect( userId ).toBeTruthy();

		await combobox.locator( '.stream-user-combobox__input' ).pressSequentially( 'x' );
		await Promise.all( [
			page.waitForURL( new RegExp( `user_id=${ userId }` ) ),
			page.locator( '#record-query-submit' ).click( { noWaitAfter: true } ),
		] );
	} );

	test( 'records filter reopens the listbox with ArrowDown after Escape', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/admin.php?page=wp_stream' );
		await ensureUserFilterVisible( page );
		const combobox = page.locator( '#record-filter-form .stream-user-combobox' );
		const input = combobox.locator( '.stream-user-combobox__input' );
		await input.fill( 'adm' );
		await expect( combobox.locator( '[role="option"]' ).first() ).toBeVisible( {
			timeout: 10_000,
		} );
		await input.press( 'Escape' );
		await expect( combobox.locator( '[role="option"]' ) ).toHaveCount( 0 );
		await input.press( 'ArrowDown' );
		await expect( combobox.locator( '[role="option"]' ).first() ).toBeVisible();
	} );

	test( 'settings exclude rule can search and select a user', async ( {
		page,
	} ) => {
		await page.goto(
			'/wp-admin/network/admin.php?page=wp_stream_network_settings&tab=exclude',
		);
		await page.locator( '#exclude_rules_new_rule' ).click();
		const combobox = page.locator(
			'.stream-exclude-list tbody tr:not(.hidden):not(.helper) .stream-user-combobox',
		).last();
		await selectUserCombobox( combobox, 'adm' );
		await expect( combobox.locator( '.stream-user-combobox__value' ) ).not.toHaveValue(
			'',
		);
	} );

	test( 'settings exclude picks roles and users from the single combobox', async ( {
		page,
	} ) => {
		await page.goto(
			'/wp-admin/network/admin.php?page=wp_stream_network_settings&tab=exclude',
		);
		await page.locator( '#exclude_rules_new_rule' ).click();
		const combobox = page.locator(
			'.stream-exclude-list tbody tr:not(.hidden):not(.helper) .stream-user-combobox',
		).last();

		// One control: opening it with an empty query offers the Roles group.
		await combobox.locator( '.stream-user-combobox__input' ).click();
		await expect(
			combobox.locator( '.stream-user-combobox__group', { hasText: 'Roles' } ),
		).toBeVisible();
		await expect(
			combobox.locator( '.stream-user-combobox__hint' ),
		).toBeVisible();

		// Pick a role from the listbox.
		const roleOption = combobox
			.locator( '.stream-user-combobox__option', { hasText: 'Administrator' } )
			.first();
		await roleOption.evaluate( ( node ) => {
			node.dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true } ) );
		} );
		await expect( combobox.locator( '.stream-user-combobox__value' ) ).toHaveValue(
			'administrator',
		);
		await expect( combobox.locator( '.stream-user-combobox__input' ) ).toHaveValue(
			'Administrator',
		);

		// Searching keeps matching roles visible next to user results.
		await combobox.locator( '.stream-user-combobox__input' ).fill( 'admin' );
		const list = combobox.locator( '.stream-user-combobox__option' );
		await list.first().waitFor( { state: 'visible', timeout: 10_000 } );
		await expect(
			combobox.locator( '.stream-user-combobox__option', { hasText: 'Administrator' } ),
		).toBeVisible();

		// Picking a user replaces the role (users carry avatars; roles do not).
		const userOption = combobox
			.locator( '.stream-user-combobox__option:has(.stream-user-combobox__avatar)' )
			.first();
		await userOption.waitFor( { state: 'visible', timeout: 10_000 } );
		await userOption.evaluate( ( node ) => {
			node.dispatchEvent( new MouseEvent( 'mousedown', { bubbles: true } ) );
		} );
		await expect( combobox.locator( '.stream-user-combobox__value' ) ).not.toHaveValue(
			'administrator',
		);
		await expect(
			combobox.locator( '.stream-user-combobox__value' ),
		).not.toHaveValue( '' );
	} );

	test( 'records filter combobox is hidden and shown by screen options', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/admin.php?page=wp_stream' );
		const combobox = page.locator( '#record-filter-form .stream-user-combobox' );
		await expect( combobox ).toBeVisible();

		await page.locator( '#show-settings-link' ).click();
		const toggle = page.locator( '#user_id-hide' );
		try {
			await toggle.uncheck();
			await expect( combobox ).toBeHidden();
		} finally {
			// Always restore visibility: the preference persists across runs
			// and would hide the combobox for every later test.
			await toggle.check();
		}
		await expect( combobox ).toBeVisible();
	} );

	test( 'combobox announces results through wp.a11y', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=wp_stream' );
		await ensureUserFilterVisible( page );

		// wp.a11y's live regions are not observable in the DOM in this WP
		// version, so assert the speak() contract instead.
		await page.evaluate( () => {
			window.__spoken = [];
			// wp.a11y.speak is a read-only property, but window.wp.a11y itself
			// is writable — swap the whole object for a spying shim.
			const real = window.wp.a11y;
			window.wp.a11y = Object.assign( Object.create( Object.getPrototypeOf( real ) ), real, {
				speak( message, context ) {
					window.__spoken.push( message );
					real.speak( message, context );
				},
			} );
		} );

		const combobox = page.locator( '#record-filter-form .stream-user-combobox' );
		await selectUserCombobox( combobox, 'adm' );

		await expect
			.poll( async () =>
				page.evaluate( () => ( window.__spoken || [] ).join( ' | ' ) ),
			)
			.toMatch( /user/i );
	} );

	test( 'keyboard navigation moves aria-activedescendant and commits roles', async ( {
		page,
	} ) => {
		await page.goto(
			'/wp-admin/network/admin.php?page=wp_stream_network_settings&tab=exclude',
		);
		await page.locator( '#exclude_rules_new_rule' ).click();
		const combobox = page
			.locator( '.stream-exclude-list tbody tr:not(.hidden):not(.helper) .stream-user-combobox' )
			.last();

		// Drive the keyboard flow inside one page tick so the assertion reads
		// the DOM in the exact state the widget produced.
		const step1 = await combobox.evaluate( ( root ) => {
			const input = root.querySelector( '.stream-user-combobox__input' );
			input.focus();
			return {
				optionCount: root.querySelectorAll( '.stream-user-combobox__option' ).length,
				firstId: root.querySelector( '.stream-user-combobox__option' )?.id || '',
				active: input.getAttribute( 'aria-activedescendant' ),
			};
		} );
		expect( step1.optionCount ).toBeGreaterThan( 1 );
		expect( step1.active ).toBe( step1.firstId );

		const step2 = await combobox.evaluate( ( root ) => {
			const input = root.querySelector( '.stream-user-combobox__input' );
			input.dispatchEvent(
				new KeyboardEvent( 'keydown', { key: 'ArrowDown', bubbles: true } ),
			);
			const second = root.querySelectorAll( '.stream-user-combobox__option' )[ 1 ];
			return {
				secondId: second?.id || '',
				secondSlug: second?.getAttribute( 'data-id' ) || '',
				active: input.getAttribute( 'aria-activedescendant' ),
			};
		} );
		expect( step2.active ).toBe( step2.secondId );
		expect( step2.secondSlug ).toBeTruthy();

		const step3 = await combobox.evaluate( ( root ) => {
			const input = root.querySelector( '.stream-user-combobox__input' );
			input.dispatchEvent(
				new KeyboardEvent( 'keydown', { key: 'Enter', bubbles: true } ),
			);
			return {
				value: root.querySelector( '.stream-user-combobox__value' ).value,
				label: input.value,
				closed: root
					.querySelector( '.stream-user-combobox__listbox' )
					.hasAttribute( 'hidden' ),
			};
		} );
		expect( step3.value ).toBe( step2.secondSlug );
		expect( step3.label ).toBeTruthy();
		expect( step3.closed ).toBe( true );
	} );

	test( 'exclude user rule round trips through save and reload', async ( { page } ) => {
		await page.goto(
			'/wp-admin/network/admin.php?page=wp_stream_network_settings&tab=exclude',
		);
		await page.locator( '#exclude_rules_new_rule' ).click();
		const combobox = page
			.locator( '.stream-exclude-list tbody tr:not(.hidden):not(.helper) .stream-user-combobox' )
			.last();
		await selectUserCombobox( combobox, 'alice' );
		const picked = await combobox
			.locator( '.stream-user-combobox__value' )
			.inputValue();
		expect( picked ).toBeTruthy();

		await Promise.all( [
			page.waitForURL( /settings-updated=true/ ),
			page.getByRole( 'button', { name: 'Save Changes' } ).click( {
				noWaitAfter: true,
			} ),
		] );
		await page.waitForLoadState( 'load' );
		await page.waitForTimeout( 500 );

		const reloaded = page
			.locator( '.stream-exclude-list .stream-user-combobox' )
			.first();
		await expect( reloaded.locator( '.stream-user-combobox__value' ) ).toHaveValue(
			picked,
		);
		await expect( reloaded.locator( '.stream-user-combobox__input' ) ).toHaveValue(
			/Alice|admin|Editor|Author|Contributor|Shopper|Beta|Alpha|Gamma|Charlie|Bob|Eve|Frank|Grace|Dana/i,
		);
	} );

	test( 'new alert author can search, select, and clear', async ( { page } ) => {
		await page.goto( '/wp-admin/edit.php?post_type=wp_stream_alerts' );
		await page.locator( 'a.page-title-action' ).click();
		const form = page.locator( '#add-new-alert' );
		await expect( form ).toBeVisible();

		const combobox = form.locator( '.stream-user-combobox' );
		await expect( combobox ).toHaveCount( 1 );
		await selectUserCombobox( combobox, 'adm' );
		await expect( combobox.locator( '.stream-user-combobox__value' ) ).not.toHaveValue(
			'',
		);

		await clearUserCombobox( combobox );
		await expect( combobox.locator( '.stream-user-combobox__value' ) ).toHaveValue(
			'',
		);

		await form.locator( '#wp_stream_alert_type' ).selectOption( 'highlight' );
		const existingIds = await page
			.locator( '#the-list tr.type-wp_stream_alerts' )
			.evaluateAll( ( rows ) => rows.map( ( row ) => row.id ).filter( Boolean ) );
		await form.locator( 'button.button-primary.save' ).click( { noWaitAfter: true } );
		await expect( page.locator( '#add-new-alert' ) ).toHaveCount( 0, {
			timeout: 20_000,
		} );
		const afterIds = await page
			.locator( '#the-list tr.type-wp_stream_alerts' )
			.evaluateAll( ( rows ) => rows.map( ( row ) => row.id ).filter( Boolean ) );
		createdAlertRowId =
			afterIds.find( ( id ) => ! existingIds.includes( id ) ) || '';
	} );
} );
