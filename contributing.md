# Contributing to this project

Please take a moment to review this document in order to make the contribution process easy and effective for everyone involved.

Following these guidelines will help us get back to you more quickly, and will show that you care about making Stream better just like we do. In return, we'll do our best to respond to your issue or pull request as soon as possible with the same respect.


## Use the issue tracker

The [issue tracker](https://github.com/x-team/wp-stream/issues) is the preferred channel for [bug reports](#bugs), [features requests](#features) and [submitting pull requests](#pull-requests), but please respect the following restrictions:

* Support issues or usage questions that are not bugs should be posted on the [Plugin Support Forum](http://wordpress.org/support/plugin/stream).
* Please **do not** derail or troll issues. Keep the discussion on topic and respect the opinions of others.


<a name="bugs"></a>
## Bug reports

A bug is a _demonstrable problem_ that is caused by the code in the repository. Good bug reports with complete error messages, environment details and screenshots are extremely helpful &mdash; thank you!

Guidelines for bug reports:

1. **Check if the bug has already been fixed** &mdash; Someone may already be on top of it, so try to reproduce it using the latest from the `master` branch.

2. **Use the [GitHub issue search](https://github.com/x-team/wp-stream/search?type=Issues)** &mdash; Someone might already know about it, so please check if the issue has already been reported.

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
## Feature requests

Feature requests are very welcome! But take a moment to find out whether your idea fits with the scope and aims of the project. It's up to *you* to make a strong case to convince the project's developers of the merits of this feature. Please provide as much detail and context as possible.

Building something great means choosing features carefully especially because it is much, much easier to add features than it is to take them away. Additions to Stream will be evaluated on a combination of scope (how well it fits into the project), maintenance burden and general usefulness to users.

<a name="pull-requests"></a>
## Pull requests

Good pull requests &mdash; patches, improvements, new features &mdash; are a fantastic help.
They should remain focused in scope and avoid containing unrelated commits.

**Please ask first** before embarking on any significant pull request (e.g. implementing features, refactoring code), otherwise you risk spending a lot of time working on something that the project's developers might not want to merge into the project. You can solicit feedback and opinions in an open enhancement issue, or [create a new one](https://github.com/x-team/wp-stream/issues/new).

Please use the [git flow for pull requests](#git-flow) and follow [WordPress Coding Standards](http://make.wordpress.org/core/handbook/coding-standards/) before submitting your work. Adhering to these guidelines is the best way to get your work included in Stream.

<a name="git-flow"></a>
#### Git Flow for pull requests

1. [Fork](http://help.github.com/fork-a-repo/) the project, clone your fork, and configure the remotes:

   ```bash
   # Clone your fork of the repo into the current directory
   git clone git@github.com:<YOUR_USERNAME>/wp-stream.git
   # Navigate to the newly cloned directory
   cd wp-stream
   # Assign the original repo to a remote called "upstream"
   git remote add upstream https://github.com/x-team/wp-stream
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

4. Commit your changes in logical chunks. Please adhere to these [git commit message guidelines](http://tbaggery.com/2008/04/19/a-note-about-git-commit-messages.html) or your code is unlikely be merged into the main project. Use Git's [interactive rebase](https://help.github.com/articles/interactive-rebase) feature to tidy up your commits before making them public.

5. Locally merge (or rebase) the upstream development branch into your topic branch:

   ```bash
   git pull [--rebase] upstream master
   ```

6. Push your topic branch up to your fork:

   ```bash
   git push origin <topic-branch-name>
   ```

7. [Open a Pull Request](https://help.github.com/articles/using-pull-requests/) (with a clear title and description) to the `develop` branch.

**IMPORTANT**: By submitting a patch, you agree to allow the project owner to license your work under the [GPL v2 license](http://www.gnu.org/licenses/gpl-2.0.html).
