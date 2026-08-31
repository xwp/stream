/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import {
	newAuthedPage,
	clearNetworkExcludeRulesViaWpCli,
} from './helpers/stream-plugin';

/**
 * Change one setting on each Stream settings tab, save once, and assert the
 * values persist after reload. Uses network settings because Stream is
 * network-activated by setup.
 */

const SETTINGS_URL =
	'/wp-admin/network/admin.php?page=wp_stream_network_settings';
const EXCLUDE_IP = '203.0.113.44';

/** Captured before mutation so afterAll can restore the live install. */
let originalTtl = null;
let cronWasChecked = null;

test.describe.configure( { mode: 'serial' } );

test.afterAll( async ( { browser } ) => {
	const page = await newAuthedPage( browser );
	await restoreSettings( page );
	await page.context().close();
} );

test.describe( 'Settings save', () => {
	test( 'persists one change per tab after save and reload', async ( {
		page,
	} ) => {
		await page.goto( SETTINGS_URL );

		const ttlInput = page.locator( '#wp_stream_network_general_records_ttl' );
		await expect( ttlInput ).toBeVisible();
		originalTtl = await ttlInput.inputValue();
		const nextTtl = String( Number( originalTtl ) === 31 ? 32 : 31 );
		await ttlInput.fill( nextTtl );

		await page.getByRole( 'link', { name: 'Exclude' } ).click();
		await page.locator( '#exclude_rules_new_rule' ).click();
		await page.evaluate( ( ip ) => {
			const select = document.querySelector(
				'.stream-exclude-list tbody tr:not(.hidden):not(.helper) select.ip_address',
			);
			const option = new Option( ip, ip, true, true );
			select.appendChild( option );
			window.jQuery( select ).trigger( 'change' );
		}, EXCLUDE_IP );

		await page.getByRole( 'link', { name: 'Advanced' } ).click();
		const cron = page.locator(
			'input[name="wp_stream_network[advanced_wp_cron_tracking]"]',
		);
		await expect( cron ).toBeVisible();
		cronWasChecked = await cron.isChecked();
		if ( cronWasChecked ) {
			await cron.uncheck();
		} else {
			await cron.check();
		}

		await Promise.all( [
			page.waitForURL( /settings-updated=true/ ),
			page.getByRole( 'button', { name: 'Save Changes' } ).click( {
				noWaitAfter: true,
			} ),
		] );

		await page.goto( `${ SETTINGS_URL }&tab=general` );
		await expect(
			page.locator( '#wp_stream_network_general_records_ttl' ),
		).toHaveValue( nextTtl );

		await page.getByRole( 'link', { name: 'Exclude' } ).click();
		await expect(
			page
				.locator(
					`.stream-exclude-list select.ip_address option[value="${ EXCLUDE_IP }"]`,
				)
				.first(),
		).toBeAttached();

		await page.getByRole( 'link', { name: 'Advanced' } ).click();
		if ( cronWasChecked ) {
			await expect(
				page.locator(
					'input[name="wp_stream_network[advanced_wp_cron_tracking]"]',
				),
			).not.toBeChecked();
		} else {
			await expect(
				page.locator(
					'input[name="wp_stream_network[advanced_wp_cron_tracking]"]',
				),
			).toBeChecked();
		}
	} );
} );

/**
 * Restore TTL, exclude rule, and cron tracking so other suites keep defaults.
 *
 * @param {import('@playwright/test').Page} wpPage Page.
 */
async function restoreSettings( wpPage ) {
	await wpPage.goto( SETTINGS_URL );

	const ttlInput = wpPage.locator( '#wp_stream_network_general_records_ttl' );
	if ( null !== originalTtl && ( await ttlInput.isVisible().catch( () => false ) ) ) {
		await ttlInput.fill( originalTtl );
	}

	await wpPage.getByRole( 'link', { name: 'Exclude' } ).click();
	const ipRows = wpPage.locator( '.stream-exclude-list tbody tr' ).filter( {
		hasText: EXCLUDE_IP,
	} );
	const ipRowCount = await ipRows.count();
	for ( let i = 0; i < ipRowCount; i++ ) {
		await ipRows
			.nth( 0 )
			.locator( 'a.exclude_rules_remove_rule_row' )
			.evaluate( ( el ) => el.click() );
	}

	await wpPage.getByRole( 'link', { name: 'Advanced' } ).click();
	const cron = wpPage.locator(
		'input[name="wp_stream_network[advanced_wp_cron_tracking]"]',
	);
	if ( null !== cronWasChecked && ( await cron.isVisible().catch( () => false ) ) ) {
		if ( cronWasChecked ) {
			await cron.check();
		} else {
			await cron.uncheck();
		}
	}

	const save = wpPage.getByRole( 'button', { name: 'Save Changes' } );
	if ( await save.isVisible().catch( () => false ) ) {
		await save.click( { noWaitAfter: true } );
		await wpPage.waitForURL( /settings-updated=true/ ).catch( () => {} );
	}

	// Removing the IP row in the UI can still persist empty exclude_row
	// entries that match every site-level record. Strip only those leftovers
	// and this run's IP — keep any pre-existing exclude_rules.
	clearNetworkExcludeRulesViaWpCli( EXCLUDE_IP );
}
