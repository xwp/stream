// @ts-check
/**
 * External dependencies
 */
const { defineConfig, devices } = require( '@playwright/test' );

/**
 * Read environment variables from file.
 * https://github.com/motdotla/dotenv
 */
// require('dotenv').config({ path: path.resolve(__dirname, '.env') });

/**
 * Logged-in Chromium context shared by every spec project.
 */
const desktopChromeAuth = {
	...devices[ 'Desktop Chrome' ],
	storageState: 'playwright/.auth/user.json',
};

/**
 * Specs that save Settings → General (options-general.php) to seed a Stream
 * record. Concurrent saves race on the success notice, so they run in the
 * serial `settings-ui` project instead of `chromium`.
 */
const settingsUiSpecs = [
	'**/alert-create.spec.js',
	'**/export-download.spec.js',
	'**/records-filter.spec.js',
];

/**
 * Toggles Stream network-activation. Must not overlap any other spec.
 */
const networkActivatedSpec = '**/network-activated.spec.js';

/**
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig( {
	testDir: './tests/e2e',
	/* Run tests in files in parallel */
	fullyParallel: true,
	/* Fail the build on CI if you accidentally left test.only in the source code. */
	forbidOnly: !! process.env.CI,
	timeout: 60_000,
	/* Retry on CI only */
	retries: process.env.CI ? 2 : 0,
	/* Global worker pool. Project `workers` only caps that project. */
	workers: process.env.CI ? 2 : 4,
	/* Reporter to use. See https://playwright.dev/docs/test-reporters */
	reporter: 'html',
	/* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
	use: {
		/* wp-env development site. Override with PLAYWRIGHT_BASE_URL or WP_ENV_PORT. */
		baseURL:
			process.env.PLAYWRIGHT_BASE_URL ||
			`http://localhost:${ process.env.WP_ENV_PORT || '8888' }`,

		/* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
		trace: 'on-first-retry',
	},
	/* setup → (chromium ∥ settings-ui) → network-activated */
	projects: [
		{ name: 'setup', testMatch: /setup\.js/ },
		{
			name: 'chromium',
			testIgnore: [ networkActivatedSpec, ...settingsUiSpecs ],
			fullyParallel: true,
			use: desktopChromeAuth,
			dependencies: [ 'setup' ],
		},
		{
			name: 'settings-ui',
			testMatch: settingsUiSpecs,
			fullyParallel: false,
			workers: 1,
			use: desktopChromeAuth,
			dependencies: [ 'setup' ],
		},
		{
			name: 'network-activated',
			testMatch: networkActivatedSpec,
			fullyParallel: false,
			workers: 1,
			use: desktopChromeAuth,
			dependencies: [ 'chromium', 'settings-ui' ],
		},
	],
} );
