/**
 * External dependencies
 */
import { readFile } from 'fs/promises';

/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

/**
 * Internal dependencies
 */
import { seedSettingsRecord } from './helpers/stream-plugin';

/**
 * CSV and JSON export from the records screen. Export::expand_columns() always
 * includes Blog ID on network-activated multisite, even from site admin.
 */

const CSV_HEADERS = [
	'Date',
	'Summary',
	'User',
	'User ID',
	'Connector',
	'Context',
	'Object ID',
	'Action',
	'IP Address',
	'Blog ID',
];

const JSON_KEYS = [
	'date',
	'summary',
	'user',
	'user_id',
	'connector',
	'context',
	'object_id',
	'action',
	'ip',
	'blog_id',
];

test.describe.configure( { mode: 'serial' } );

let seeded = false;

test.beforeEach( async ( { page } ) => {
	if ( ! seeded ) {
		await seedSettingsRecord( page );
		seeded = true;
	}
} );

test.describe( 'Records export', () => {
	test( 'downloads CSV with the expanded column headers', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/admin.php?page=wp_stream' );
		const text = await downloadExport( page, 'export-csv' );
		const headerLine = text.split( /\r?\n/ )[ 0 ];
		expect(
			parseCsvRow( headerLine ),
			'CSV header cells must match expand_columns() exactly (User ≠ User ID)',
		).toEqual( CSV_HEADERS );
		const csvLines = text.split( /\r?\n/ ).filter( ( line ) => '' !== line );
		expect( csvLines.length ).toBeGreaterThan( 1 );
		expect( parseCsvRow( csvLines[ 1 ] ) ).toHaveLength( CSV_HEADERS.length );
	} );

	test( 'downloads JSON with the expanded record shape', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/admin.php?page=wp_stream' );
		const text = await downloadExport( page, 'export-json' );
		const records = JSON.parse( text );
		expect( Array.isArray( records ) ).toBe( true );
		expect( records.length ).toBeGreaterThan( 0 );
		for ( const key of JSON_KEYS ) {
			expect(
				records[ 0 ],
				`JSON row should include ${ key }`,
			).toHaveProperty( key );
		}
	} );
} );

/**
 * Trigger a records export and return the downloaded file contents.
 *
 * @param {import('@playwright/test').Page} wpPage Page.
 * @param {string}                          action export-csv or export-json.
 * @return {Promise<string>} File body.
 */
async function downloadExport( wpPage, action ) {
	await wpPage
		.locator( '#record-actions-form select[name="record-actions"]' )
		.selectOption( action );

	const downloadPromise = wpPage.waitForEvent( 'download' );
	await wpPage
		.locator( '#record-actions-submit' )
		.click( { noWaitAfter: true } );
	const download = await downloadPromise;
	const filePath = await download.path();
	expect( filePath ).toBeTruthy();
	return readFile( filePath, 'utf8' );
}

/**
 * Split one CSV record on commas, honoring fputcsv double-quoted fields.
 *
 * @param {string} line Header or data row.
 * @return {string[]} Cells with quotes unescaped.
 */
function parseCsvRow( line ) {
	const cells = [];
	let cell = '';
	let quoted = false;

	for ( let i = 0; i < line.length; i++ ) {
		const char = line[ i ];
		if ( char === '"' ) {
			if ( quoted && line[ i + 1 ] === '"' ) {
				cell += '"';
				i++;
				continue;
			}
			quoted = ! quoted;
			continue;
		}
		if ( char === ',' && ! quoted ) {
			cells.push( cell );
			cell = '';
			continue;
		}
		cell += char;
	}
	cells.push( cell );
	return cells;
}
