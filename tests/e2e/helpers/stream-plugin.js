/**
 * External dependencies
 */
import { execFileSync } from 'child_process';

/** @type {string} wp-env development origin (HTTP on localhost). */
export const E2E_SITE_URL =
	process.env.PLAYWRIGHT_BASE_URL ||
	process.env.E2E_SITE_URL ||
	`http://localhost:${ process.env.WP_ENV_PORT || '8888' }`;

/**
 * Shared helpers for Stream Playwright specs.
 *
 * Setup ensures Stream is network-activated. Specs should assume the
 * plugin is already active and must not activate or deactivate it,
 * except `network-activated.spec.js` which owns that toggle in its
 * own serial project and restores activation in afterAll.
 *
 * Use newAuthedPage() instead of browser.newPage() so the page gets
 * storageState (Playwright's Browser.newPage() does not).
 */

/**
 * Log in as the local e2e admin (`admin` / `password`).
 *
 * Use the login form IDs, not getByLabel('Password'). In headed Chromium
 * (and `test-e2e-debug`) that label can land on the username field, so
 * submit stays on wp-login.php and waitForURL('/wp-admin/') times out.
 *
 * @param {import('@playwright/test').Page} page Page.
 */
export async function loginAsAdmin( page ) {
	await page.goto( '/wp-login.php' );
	const user = page.locator( '#user_login' );
	const pass = page.locator( '#user_pass' );
	await user.waitFor( { state: 'visible' } );
	await user.fill( 'admin' );
	await pass.fill( 'password' );
	// Headed Chromium password-manager autofill can overwrite the username
	// with the password value after fill(). Force the posted fields.
	await page.evaluate( () => {
		const login = document.getElementById( 'user_login' );
		const pwd = document.getElementById( 'user_pass' );
		if ( login ) {
			login.value = 'admin';
		}
		if ( pwd ) {
			pwd.value = 'password';
		}
	} );
	await Promise.all( [
		page.waitForURL( /\/wp-admin(\/|$|\?)/ ),
		page.locator( '#wp-submit' ).click(),
	] );
}

/**
 * Open a logged-in page with the same origin and auth settings as the project.
 *
 * @param {import('@playwright/test').Browser} browser Browser.
 * @return {Promise<import('@playwright/test').Page>} Page.
 */
export async function newAuthedPage( browser ) {
	const context = await browser.newContext( {
		storageState: 'playwright/.auth/user.json',
		baseURL: E2E_SITE_URL,
	} );
	return context.newPage();
}

/**
 * Navigate to a wp-admin link using path + query so Playwright's baseURL
 * (host/port) is kept. WordPress often emits absolute URLs without a port,
 * and plugin-row actions use path-relative hrefs like `plugins.php?action=`.
 * Resolve against the current page URL so those stay under /wp-admin/.
 *
 * @param {import('@playwright/test').Page}    page Page.
 * @param {import('@playwright/test').Locator} link Locator with an href.
 */
export async function followAdminLink( page, link ) {
	const href = await link.getAttribute( 'href' );
	if ( ! href ) {
		await link.click();
		return;
	}
	const current = page.url();
	const base =
		current && 'about:blank' !== current ? current : E2E_SITE_URL;
	const { pathname, search } = new URL( href, base );
	await page.goto( `${ pathname }${ search }` );
}

/**
 * Network-activate Stream if it is currently inactive.
 *
 * @param {import('@playwright/test').Page} page Page.
 */
export async function networkActivateStream( page ) {
	await page.goto( '/wp-admin/network/plugins.php' );
	const activate = page.locator( '#activate-stream' );
	if ( await activate.isVisible() ) {
		await followAdminLink( page, activate );
	}
}

/**
 * Network-activate Stream via wp-cli (`stream` in wp-env).
 *
 * Use when the spec must wait until connectors are loaded (e.g. before a
 * wp-cli seed). The plugins-row click can finish before Stream is actually
 * active; this call is synchronous.
 */
export function networkActivateStreamViaWpCli() {
	const slugs = [ 'stream', 'stream-src' ];
	let lastError;
	for ( const slug of slugs ) {
		try {
			runWpCli( [ 'plugin', 'activate', slug, '--network' ] );
			return;
		} catch ( error ) {
			lastError = error;
		}
	}
	throw lastError;
}

/**
 * Network-deactivate Stream if it is currently active.
 *
 * @param {import('@playwright/test').Page} page Page.
 */
export async function networkDeactivateStream( page ) {
	await page.goto( '/wp-admin/network/plugins.php' );
	const deactivate = page.locator( '#deactivate-stream' );
	if ( await deactivate.isVisible() ) {
		await followAdminLink( page, deactivate );
	}
}

/**
 * Create a subdirectory site if it is not already on the network.
 *
 * Prefers `wp-env run cli wp site create` (same stack as `wp site list`).
 * Falls back to Network Admin → Add New, posting the form to the current
 * origin when WordPress emits a portless absolute URL.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @param {string}                          slug Path slug (no slashes).
 */
export async function ensureSubdirectorySite( page, slug ) {
	if ( ensureSubdirectorySiteWithWpCli( slug ) ) {
		return;
	}
	await ensureSubdirectorySiteWithUi( page, slug );
}

/**
 * Log a Settings record by changing the site tagline.
 *
 * Faster than Gutenberg and produces user / connector / context / action / IP
 * values the records filters can target. Snapshots the current tagline and
 * restores it after the marker save so the persistent install is unchanged.
 *
 * @param {import('@playwright/test').Page} page      Page.
 * @param {string}                          adminPath Site wp-admin path, e.g. `/wp-admin`.
 * @return {Promise<string>} Tagline written to the record.
 */
export async function seedSettingsRecord( page, adminPath = '/wp-admin' ) {
	const base = adminPath.replace( /\/$/, '' );
	const settingsUrl = `${ base }/options-general.php`;
	await page.goto( settingsUrl );
	if ( '/wp-admin' !== base ) {
		const escaped = base.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
		await page.waitForURL( new RegExp( escaped ) );
	}
	const field = page.locator( '#blogdescription' );
	const originalTagline = await field.inputValue();
	const tagline = `stream-e2e-${ Date.now() }`;
	await field.fill( tagline );
	await saveOptionsGeneral( page );
	await page.goto( settingsUrl );
	await page.locator( '#blogdescription' ).fill( originalTagline );
	await saveOptionsGeneral( page );
	return tagline;
}

/**
 * Submit Settings → General and wait for the saved notice.
 *
 * @param {import('@playwright/test').Page} page Page.
 */
async function saveOptionsGeneral( page ) {
	await page.locator( '#submit' ).click();
	await page
		.locator( '.notice-success, #setting-error-settings_updated' )
		.first()
		.waitFor( { state: 'visible', timeout: 20_000 } );
}

/**
 * Cancel leftover auto-purge batch/reaper actions.
 *
 * Settings → Advanced hides "Clean Orphaned Meta" while
 * `is_running_auto_purge()` is true. A prior spec run that clicked the
 * link leaves a pending reaper, so the next suite would see the running
 * copy instead of the idle-state link.
 */
export function clearAutoPurgeQueueViaWpCli() {
	runWpCli( [
		'db',
		'query',
		"UPDATE wp_actionscheduler_actions SET status = 'canceled' WHERE hook IN ('stream_auto_purge_batch_action','stream_auto_purge_reaper_action') AND status IN ('pending','in-progress');",
	] );
}

/**
 * Drop leftover network Exclude rows so site-level connectors can log.
 *
 * Empty `exclude_row` entries match every record (`0 === 0` in
 * `record_matches_rules()`), which is what settings-save restore can leave
 * behind. Removes only empty rows and the optional IP this run added —
 * never deletes the entire `exclude_rules` key. Network-admin events still
 * log (they skip these rules).
 *
 * @param {string} [ipAddress] IP of the row this run added (TEST-NET-3).
 */
export function clearNetworkExcludeRulesViaWpCli( ipAddress = '203.0.113.44' ) {
	const dropIp = JSON.stringify( ipAddress || '' );
	const php = `$opt = (array) get_site_option( "wp_stream_network", array() ); if ( empty( $opt["exclude_rules"] ) || ! is_array( $opt["exclude_rules"] ) ) { return; } $rules = $opt["exclude_rules"]; $rows = isset( $rules["exclude_row"] ) && is_array( $rules["exclude_row"] ) ? $rules["exclude_row"] : array(); $drop_ip = ${ dropIp }; $keys = array( "exclude_row", "author_or_role", "connector", "context", "action", "ip_address" ); $keep = array(); foreach ( $rows as $row_id => $marker ) { $ip = isset( $rules["ip_address"][ $row_id ] ) ? (string) $rules["ip_address"][ $row_id ] : ""; $is_empty = true; foreach ( $keys as $key ) { if ( ! empty( $rules[ $key ][ $row_id ] ) ) { $is_empty = false; break; } } if ( $is_empty || ( "" !== $drop_ip && $ip === $drop_ip ) ) { continue; } $keep[] = $row_id; } if ( empty( $keep ) ) { unset( $opt["exclude_rules"] ); } else { $filtered = array(); foreach ( $keys as $key ) { if ( ! isset( $rules[ $key ] ) || ! is_array( $rules[ $key ] ) ) { continue; } foreach ( $keep as $row_id ) { if ( array_key_exists( $row_id, $rules[ $key ] ) ) { $filtered[ $key ][ $row_id ] = $rules[ $key ][ $row_id ]; } } } $opt["exclude_rules"] = $filtered; } update_site_option( "wp_stream_network", $opt );`;
	runWpCli( [ 'eval', php ] );
}

/**
 * Publish a post on a subdirectory site via wp-cli.
 *
 * Call after Stream is network-active. The posts connector logs this against
 * that blog; Settings UI from `/<slug>/wp-admin/` still attaches to blog 1.
 *
 * @param {string} slug Path slug.
 * @return {string} Post title.
 */
export function seedSubsitePostViaWpCli( slug ) {
	const title = `e2e-sub-${ Date.now() }`;
	const siteUrl = `${ playwrightSiteUrl().replace( /\/$/, '' ) }/${ slug }/`;
	runWpCli(
		[
			'post',
			'create',
			`--post_title=${ title }`,
			'--post_status=publish',
			'--user=admin',
		],
		siteUrl,
	);
	return title;
}

/**
 * Set a chosen-select / select2 native <select> and fire the jQuery change
 * event Stream's admin scripts listen for.
 *
 * Context children reuse the same option value across connectors (e.g.
 * `general`). Pass optionSelector to select that specific <option> instead
 * of jQuery.val(), which always picks the first match.
 *
 * @param {import('@playwright/test').Page} page             Page.
 * @param {string}                          selector         CSS selector for the select.
 * @param {string}                          value            Option value.
 * @param {string}                          [optionSelector] Optional option CSS inside the select.
 */
export async function setJQuerySelect( page, selector, value, optionSelector ) {
	await page.evaluate(
		( { selector: sel, value: val, optionSelector: optSel } ) => {
			const $el = window.jQuery( sel );
			if ( optSel ) {
				$el.find( 'option' ).prop( 'selected', false );
				$el.find( optSel ).prop( 'selected', true );
			} else {
				$el.val( val );
			}
			$el.trigger( 'change' );
		},
		{ selector, value, optionSelector: optionSelector || '' },
	);
}

/**
 * Origin used for wp-cli `--url`, matching Playwright's baseURL.
 *
 * @return {string} Site origin.
 */
function playwrightSiteUrl() {
	return E2E_SITE_URL;
}

/**
 * Run a WP-CLI command in the wp-env development CLI container.
 *
 * @param {string[]} args  Arguments after `wp`.
 * @param {string}   [url] `--url` value. Defaults to the Playwright origin.
 * @return {string}        Command stdout.
 */
function runWpCli( args, url = playwrightSiteUrl() ) {
	return execFileSync(
		'npx',
		[ 'wp-env', 'run', 'cli', '--', 'wp', ...args, `--url=${ url }` ],
		{ encoding: 'utf8', timeout: 60_000 },
	);
}

/**
 * Grant the e2e `admin` user a role on a subdirectory site.
 *
 * @param {string} slug Path slug.
 */
function addAdminToSite( slug ) {
	const siteUrl = `${ playwrightSiteUrl().replace( /\/$/, '' ) }/${ slug }/`;
	runWpCli(
		[ 'user', 'set-role', 'admin', 'administrator' ],
		siteUrl,
	);
}

/**
 * Create or reuse a subdirectory site via wp-cli in the wp-env CLI container.
 *
 * @param {string} slug Path slug.
 * @return {boolean} True when the site exists or was created.
 */
function ensureSubdirectorySiteWithWpCli( slug ) {
	try {
		const listed = runWpCli( [ 'site', 'list', '--field=url' ] );
		if ( listed.includes( `/${ slug }/` ) ) {
			addAdminToSite( slug );
			return true;
		}
		runWpCli( [
			'site',
			'create',
			`--slug=${ slug }`,
			'--title=Stream E2E Records',
			'--email=admin@example.com',
		] );
		addAdminToSite( slug );
		return true;
	} catch {
		return false;
	}
}

/**
 * Create a subdirectory site from Network Admin → Add New.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @param {string}                          slug Path slug.
 */
async function ensureSubdirectorySiteWithUi( page, slug ) {
	await page.goto( '/wp-admin/network/sites.php' );
	if ( ( await page.locator( `a[href*="/${ slug }/"]` ).count() ) > 0 ) {
		return;
	}

	await page.goto( '/wp-admin/network/site-new.php' );
	await page.locator( '#site-address' ).fill( slug );
	await page.locator( '#site-title' ).fill( 'Stream E2E Records' );
	await page.locator( '#admin-email' ).fill( 'admin@example.com' );
	await page.evaluate( () => {
		const form = document.querySelector( 'form' );
		if ( ! form ) {
			return;
		}
		const action = new URL(
			form.getAttribute( 'action' ) || '',
			window.location.href,
		);
		action.protocol = window.location.protocol;
		action.host = window.location.host;
		form.setAttribute( 'action', action.href );
		form.submit();
	} );
	await page.waitForURL( /[?&]update=added/, { timeout: 20_000 } );
}
