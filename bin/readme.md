wp-plugin-dev-lib
=================

**Common code used during development of WordPress plugins**

It is intended that this repo be included in plugin repo via git-subtree/submodule in a `bin/` directory.

To **add** it to your repo, do:

```bash
git subtree add --prefix bin \
  git@github.com:x-team/wp-plugin-dev-lib.git master --squash
```

To **update** the library with the latest changes:

```bash
git subtree pull --prefix bin \
  git@github.com:x-team/wp-plugin-dev-lib.git master --squash
```

And if you have changes you want to **upstream**, do:

```bash
git subtree push --prefix bin \
  git@github.com:x-team/wp-plugin-dev-lib.git master
```

Symlink to the `.travis.yml`, `.jshintrc`, and `phpcs.ruleset.xml` inside of the `bin/` directory you added:

```bash
ln -s bin/.travis.yml . && git add .travis.yml
ln -s bin/.jshintrc . && git add .jshintrc
```

Symlink to `pre-commit` from your project's `.git/hooks/pre-commit`:

```bash
cd .git/hooks && ln -s ../../bin/pre-commit . && cd -
```

You may customize the behavior of the `.travis.yml` and `pre-commit` hook by
specifying a `.ci-env.sh` in the root of the repo, for example:

```bash
export PHPCS_GITHUB_SRC=x-team/PHP_CodeSniffer
export PHPCS_GIT_TREE=master
export WPCS_GIT_TREE=develop
export WPCS_STANDARD=WordPress-Core
export PHPCS_IGNORE='tests/*,includes/vendor/*'
```

The library includes a WordPress README [parser](class-wordpress-readme-parser.php) and [converter](generate-markdown-readme) to Markdown,
so you don't have to manually keep your `readme.txt` on WordPress.org in sync with the `readme.md` you have on GitHub. The
converter will also automatically recognize the presence of projects with Travis CI and include the status image
in the markdown. Screenshots and banner images for WordPress.org are also automatically incorporated into the `readme.md`.

What is also included in this repo is an [`svn-push`](svn-push) to push commits from a GitHub repo to the WordPress.org SVN repo for the plugin.
The `/assets/` directory in the root of the project will get automatically moved one directory above in the SVN repo (alongside
`trunk`, `branches`, and `tags`). To use, include an `svn-url` file in the root of your repo and let this file contains he full root URL
to the WordPress.org repo for plugin (don't include `trunk`).

The utilities in this project were first developed to facilitate development of [X-Team](http://x-team.com/wordpress/)'s [plugins](http://profiles.wordpress.org/x-team/).
