---
name: "\U0001F680 Release pull request"
about: "\U0001F512 Maintainers only: fixes/features in development get released"
title: 'Release x.x.x'
labels: ''
assignees: ''

---

# Release Changelog

- Fix: Describe a bug fix included in this release.
- New: Describe a new feature in this release.

# Release Checklist

- [ ] This pull request is to the `master` branch.
- [ ] Release version follows [semantic versioning](https://semver.org). Does it include breaking changes?
- [ ] Update changelog in `readme.txt`.
- [ ] Bump version in `stream.php`.
- [ ] Bump `Stable tag` in `readme.txt`.
- [ ] Bump version in `classes/class-plugin.php`.
- [ ] Draft a release [on GitHub](https://github.com/xwp/stream/releases/new).

Change `[ ]` to `[x]` to mark the items as done.
