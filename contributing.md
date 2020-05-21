# Contribute to Stream


## Development Environment

Stream uses [npm](https://npmjs.com) for javascript dependencies, [Composer](https://getcomposer.org) for PHP dependencies and the [Grunt](https://gruntjs.com) task runner to minimize and compile scripts and styles and to deploy to the WordPress.org plugin repository.

Included is a local development environment built with [Docker](https://www.docker.com) which can be optionally run inside [Vagrant](https://www.vagrantup.com) for network isolation and better performance.

### Requirements

- [VirtualBox](https://www.virtualbox.org)
- [Vagrant](https://www.vagrantup.com)
- [Node.js](https://nodejs.org)
- [Composer](https://getcomposer.org)

We suggest using the [Homebrew package manager](https://brew.sh) on macOS to install the dependencies:

	brew install node composer
	brew cask install virtualbox vagrant

For setups with local Docker environment you don't need Vagrant and VirtualBox.

### Environment Setup

1. See the [Git Flow](#git-flow) section below for how to fork the repository.
2. Run `npm install` to install all project dependencies.
3. Run `vagrant up` to start the development environment.
4. Visit [stream.local](http://stream.local) and login using `admin` / `password`.
5. Activate the Stream plugin.

### PHP Xdebug

The WordPress container includes the [Xdebug PHP extension](https://xdebug.org). It is configured to [autostart](https://xdebug.org/docs/remote#remote_autostart) and to [automatically detect the IP address of the connecting client](https://xdebug.org/docs/remote#remote_connect_back) running in your code editor. See [`.vscode/launch.json`](.vscode/launch.json) for the directory mapping from the WordPress container to the project directory in your code editor.

### Mail Catcher

We use a [MailHog](https://github.com/mailhog/MailHog) container to capture all emails sent by the WordPress container, available at [stream.local:8025](https://stream.local:8025).

### Scripts and Commands

We use npm as the canonical task runner for the project. The following commands are available:

- `npm run build` to build the plugin JS and CSS files.

- `npm run lint` to check JS and PHP files for syntax and style issues.

- `npm run deploy` to deploy the plugin to the WordPress.org repository.

- `npm run cli -- wp info` where `wp info` is the CLI command to run inside the WordPress container. For example, use `npm run cli -- ls -lah` to list all files in the root of the WordPress installation.

- `npm run compose -- up -d` where `up -d` is the `docker-compose` command for the WordPress container. For example, use `npm run compose -- down` and `npm run compose -- up -d` to restart the WordPres container.

- `npm run phpunit` to run PHPunit tests inside the WordPress container.

All `npm` commands running inside Vagrant are prefixed with `v`, for example, `npm run vcli` and `npm run vcompose`.


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
