# Contribute to Stream


## Development Environment

Stream uses [npm](https://npmjs.com) for JavaScript dependencies, [Composer](https://getcomposer.org) for PHP dependencies, and [@wordpress/scripts](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/) to build assets.

Local WordPress — daily development, PHPUnit, and Playwright — runs on [@wordpress/env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) (`wp-env`). You need **Node.js** and **Docker**. Keep using Composer for PHP packages (`composer install`).

### Requirements

- [Node.js](https://nodejs.org) (see `.nvmrc`)
- [Composer](https://getcomposer.org)
- [Docker](https://www.docker.com) Desktop or Engine, running
- **PHP 8.2+** on the host for `composer test-unit` and other Composer scripts (PHPUnit 11). wp-env defaults to PHP 8.2; use the `switch-to:php*` scripts to test other supported versions locally.

We suggest using the [Homebrew package manager](https://brew.sh) on macOS:

	brew install node composer
	brew install --cask docker

### Environment Setup

1. See the [Git Flow](#git-flow) section below for how to fork the repository.
2. Run `npm install` and `composer install` to set up project dependencies. PHPUnit helper plugins (ACF, EDD, Jetpack, …) are Composer dev dependencies installed under `local/public/wp-content/` and mapped into the wp-env tests environment via `env.tests.mappings` in `.wp-env.json`.
3. Run `npm run build` to build the assets.
4. Run `npm start` to start wp-env. The repo is mapped in via `"plugins": ["."]` in [`.wp-env.json`](.wp-env.json); multisite is enabled there too. Stream and Email Logger are network-activated automatically via the `lifecycleScripts.afterStart` hook in `.wp-env.json`.
5. Visit [http://localhost:8888](http://localhost:8888) and log in with `admin` / `password`.

Override the development port with `WP_ENV_PORT`. Playwright and the E2E helpers read `PLAYWRIGHT_BASE_URL` or `WP_ENV_PORT` (default `http://localhost:8888`).

The testing site (used by PHPUnit via `wp-env run tests-cli`) is [http://localhost:8889](http://localhost:8889). DB settings for that container are in [`tests/wp-tests-config-wp-env.php`](tests/wp-tests-config-wp-env.php).

### HTTPS, Application Passwords, and MCP

The default wp-env stack is **HTTP** on localhost. WordPress Application Passwords work on `http://localhost` (core treats local environments as an exception to the HTTPS requirement). Production MCP clients that insist on HTTPS need a reverse proxy or another TLS terminator; that is not part of this environment.

### MCP (Model Context Protocol) integration

Stream exposes its abilities as MCP-discoverable tools by tagging each registered ability with `meta.mcp.public = true`. The [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) does the actual MCP server work; Stream does not load or initialize the adapter itself.

The MCP Adapter is a `require-dev` Composer dependency declared as `"type": "wordpress-plugin"`, so `composer install` drops it into `local/public/wp-content/plugins/mcp-adapter/`. Map it into wp-env with a gitignored `.wp-env.override.json`:

```json
{
  "env": {
    "development": {
      "mappings": {
        "wp-content/plugins/mcp-adapter": "./local/public/wp-content/plugins/mcp-adapter"
      }
    }
  }
}
```

Then restart wp-env (`npm start`) and network-activate the adapter:

```sh
npm run cli -- wp plugin activate mcp-adapter --network
```

Enable the "Enable Abilities API and MCP" toggle in Stream → Settings → Advanced (network admin on network-activated multisite). Verify the default server route:

```sh
curl http://localhost:8888/wp-json/mcp/mcp-adapter-default-server
```

To use MCP from Claude Desktop or another MCP client, follow the [mcp-adapter README's MCP client configuration section](https://github.com/WordPress/mcp-adapter#mcp-client-configuration). On this HTTP localhost stack you can create an Application Password in the user profile and point the client at `http://localhost:8888`.

### PHP Xdebug

Start the environment with Xdebug enabled:

```sh
npm run start-xdebug
```

That runs `wp-env start --xdebug`. [Step Debugging](https://xdebug.org/docs/step_debug) should work in VS Code via [`.vscode/launch.json`](.vscode/launch.json). The container path for this plugin is `/var/www/html/wp-content/plugins/stream`.

For PhpStorm, follow the [official guide](https://www.jetbrains.com/help/phpstorm/configuring-xdebug.html) and use the same mapping: `${workspaceRoot}` → `/var/www/html/wp-content/plugins/stream`.

### Mail

The wp-env stack includes the [Email Logger](https://make.wordpress.org/test/handbook/get-setup-for-testing/email-testing/) plugin from the WordPress Test Handbook. It hooks into `wp_mail` and stores the last 50 outgoing emails in the database.

- **Plugin slug:** `wp-email-logger` (installed from the handbook zip, not WordPress.org)
- **View logs:** In wp-admin, open **Email Log** in the admin menu
- **Multisite:** Network-activated alongside Stream via `lifecycleScripts.afterStart` in `.wp-env.json`

Playwright specs that need a notifier can use Stream's Highlight alert type instead of email when that is simpler for the test.

### phpMyAdmin

[phpMyAdmin](https://www.phpmyadmin.net/) is available at [http://localhost:9001](http://localhost:9001/). Login: `root` / `password`.

### Scripts and Commands

We use npm as the canonical task runner for the project. The following commands are available:

- `npm run start` / `npx wp-env start` to start the wp-env development and tests containers (network-activates Stream and Email Logger via `lifecycleScripts.afterStart`).
- `npm run stop` / `npx wp-env stop` to stop them.
- `npm run stop-all` to stop _all_ Docker containers.
- `npm run build` to build the plugin JS and CSS files.
- `npm run dev` to watch and build the plugin assets continuously.
- `npm run lint` to check JS and PHP files for syntax and style issues.
- `npm run deploy` to deploy the plugin to the WordPress.org repository.
- `npm run cli -- wp info` runs WP-CLI inside the wp-env CLI container. For example, `npm run cli -- wp plugin list`.
- `npm run test` to run PHPUnit (single-site + multisite) inside `wp-env run tests-cli`.
- `composer test-unit` to run the fast PHP unit suite on the host (no Docker, no WordPress bootstrap). Requires PHP 8.2+ (plugin minimum; PHPUnit 11).
- `npm run test:php-unit` to run the fast PHP unit suite in the wp-env tests container.
- `npm run test:php` / `npm run test:php-multisite` to run one integration PHPUnit suite only.
- `npm run test-xdebug` will run PHPUnit after starting wp-env with Xdebug.
- `npm run test-e2e` will run the Playwright E2E tests against `http://localhost:8888`.
- `npm run test-e2e-debug` will run the Playwright E2E tests in debug mode (Chromium + dev tools).
- `npm run switch-to:php8.2`, `npm run switch-to:php8.3`, and `npm run switch-to:php8.4` restart wp-env with `WP_ENV_PHP_VERSION` set (supported runtime versions).
- `npm run document:connectors` generates [connectors.md](connectors.md). This runs via your local PHP.
- `npm run large-records-generate` inserts ~1.6M rows to `wp_stream` and ~8.4M rows to `wp_streammeta` for testing
- `npm run large-records-remove` removes the test data only
- `npm run large-records-show` shows how much test data is in the tables, this does not include non-test entries

By default, tests have `WP_DEBUG` as false. To enable it, prefix the PHPUnit command with `WP_STREAM_TEST_DEBUG=yes`, for example:

```sh
WP_STREAM_TEST_DEBUG=yes npm run test:php
```

### wp-env issues

If the environment is stale, try `npx wp-env start --update`. To reset databases, `npx wp-env clean all` (this deletes local WordPress data). `npx wp-env destroy` removes containers and downloaded sources — only use it when you intend to wipe the environment.

## Issues Tracker

Support issues or usage questions should be posted on the [Plugin Support Forum](https://wordpress.org/support/plugin/stream).

The [issue tracker on GitHub](https://github.com/xwp/stream/issues) is the preferred channel for [bug reports](#bugs), [features requests](#features) and [submitting pull requests](#pull-requests).


<a name="bugs"></a>

## Reporting Bugs

A bug is a _demonstrable problem_ that is caused by the code in the repository. Good bug reports with complete error messages, environment details and screenshots are extremely helpful &mdash; thank you!

Guidelines for bug reports:

1. **Check if the bug has already been fixed** &mdash; Someone may already be on top of it, so try to reproduce it using the latest from the `master` branch.

2. **Use the [GitHub issue search](https://github.com/xwp/stream/search?type=Issues)** &mdash; Someone might already know about it, so please check if the issue has already been reported.

3. **Isolate the problem** &mdash; The better you can determine exactly what behavior(s) cause the issue, the faster and more effectively it can be resolved. “I’m getting an error message.” is not a good bug report. A good bug report shouldn't leave others needing to contact you for more information.

Please try to be as detailed as possible in your report. What is your environment? What steps will reproduce the issue? What browser(s) experience the problem? What outcome did you expect, and how did it differ from what you actually saw? All these details will help people to fix any potential bugs.

Example:

> Short and descriptive example bug report title
>
> A summary of the issue and the environment/browser in which it occurs. If
> suitable, include the steps required to reproduce the bug.
>
> 1. This is the first step
> 2. This is the second step
> 3. Further steps, etc.
>
> Any other information you want to share that is relevant to the issue being reported. This might include the lines of code that you have identified as causing the bug, and potential solutions (and your opinions on their merits).

**Note:** In an effort to keep open issues to a manageable number, we will close any issues that do not provide enough information for us to be able to work on a solution. You will be encouraged to provide the necessary details, after which we will reopen the issue.


<a name="features"></a>

## Feature Requests

Feature requests are very welcome! But take a moment to find out whether your idea fits with the scope and aims of the project. It's up to *you* to make a strong case to convince the project's developers of the merits of this feature. Please provide as much detail and context as possible.

Building something great means choosing features carefully especially because it is much, much easier to add features than it is to take them away. Additions to Stream will be evaluated on a combination of scope (how well it fits into the project), maintenance burden and general usefulness to users.


<a name="pull-requests"></a>

## Pull Requests

Good pull requests &mdash; patches, improvements, new features &mdash; are a fantastic help.
They should remain focused in scope and avoid containing unrelated commits.

**Please ask first** before embarking on any significant pull request (e.g. implementing features, refactoring code), otherwise you risk spending a lot of time working on something that the project's developers might not want to merge into the project. You can solicit feedback and opinions in an open enhancement issue, or [create a new one](https://github.com/xwp/stream/issues/new).

Please use the [git flow for pull requests](#git-flow) and follow [WordPress Coding Standards](https://make.wordpress.org/core/handbook/coding-standards/) before submitting your work.


<a name="git-flow"></a>

### Git Flow for Pull Requests

1. [Fork](https://help.github.com/fork-a-repo/) the project, clone your fork, and configure the remotes:

   ```bash
   # Clone your fork of the repo into the current directory
   git clone git@github.com:<YOUR_USERNAME>/stream.git
   # Navigate to the newly cloned directory
   cd stream
   # Assign the original repo to a remote called "upstream"
   git remote add upstream https://github.com/xwp/stream
   ```

2. If you cloned a while ago, get the latest changes from upstream:

   ```bash
   git checkout master
   git pull upstream master
   ```

3. Create a new topic branch (off the `master` branch) to contain your feature, change, or fix:

   ```bash
   git checkout -b <topic-branch-name>
   ```

4. Commit your changes in logical chunks. Please adhere to these [git commit message guidelines](https://tbaggery.com/2008/04/19/a-note-about-git-commit-messages.html). Use Git's [interactive rebase](https://help.github.com/articles/interactive-rebase) feature to tidy up your commits before making them public.

5. Locally merge (or rebase) the upstream development branch into your topic branch:

   ```bash
   git pull [--rebase] upstream master
   ```

6. Push your topic branch up to your fork:

   ```bash
   git push origin <topic-branch-name>
   ```

7. [Open a Pull Request](https://help.github.com/articles/using-pull-requests/) (with a clear title and description) to the `develop` branch.

**IMPORTANT**: By submitting a patch, you agree to allow the project owner to license your work under the [GPL v2 license](https://www.gnu.org/licenses/gpl-2.0.html).

## Release Cycle

The plugin versioning follows [semantic versioning](https://semver.org).

### Pre-release

Features, bug fixes, and other changes are assigned to a milestone. Once all issues in a milestone are closed:

1. **Create Release Branch:**
   - Branch off from `develop`.
   - Name it `release/vX.Y.Z`, where `X.Y.Z` is the version number.

2. **Update Metadata:**
   - Update the plugin version, changelog and other relevant information.

3. **Create Pre-release in GitHub:**
   - Name the release like `X.Y.Z-rc.N`, e.g. `4.0.1-rc.1`.
   - The tag name should be prefixed with `v`, e.g. `v4.0.1-rc.1`.

4. **Review and Test:**
   - Publishing a pre-release will trigger a GitHub action.
   - A dry-run of WP.org deployment will occur (no files are committed).
      - Review the SVN changes log in the action output.
   - A ZIP archive with the plugin is created and uploaded as a release asset.
      - Use that ZIP file for final testing.

5. **Fix Issues:**
   - If any issues are found, fix them in the release branch.
   - Repeat the process from step 3.

### Release

Once ready, follow these steps:

1. **Create Release in GitHub:**
   - Name the release like `X.Y.Z`, e.g. `4.0.1`.
   - The tag name should be prefixed with `v`, e.g. `v4.0.1`.

2. **Confirm Deployment:**
   - The GitHub action deploys the plugin to WP.org.
      - Confirm the changes have been deployed to SVN in the [plugin trac](https://plugins.trac.wordpress.org/browser/stream/).
   - A ZIP archive is created and uploaded to GitHub release assets.

3. **Merge Branches:**
   - Merge the release branch into `master`.
   - Merge `master` into `develop`.

By following this process, you ensure a smooth and consistent release cycle.
