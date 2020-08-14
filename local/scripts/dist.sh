#!/usr/bin/env bash

# Set this to -ex for verbose output.
set -ex

ROOT_DIR="$(git rev-parse --show-toplevel)"
WORKING_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
COMMIT_MESSAGE="$(git log -1 --oneline)"
SRC_DIR="$ROOT_DIR/build"

DIST_REPO="git@github.com:xwp/stream-dist.git"
DIST_DIR="$ROOT_DIR/dist"
DIST_BRANCH="${TRAVIS_BRANCH:-$WORKING_BRANCH}"
DIST_TAG="${1:-$TRAVIS_TAG}"

export GIT_DIR="$DIST_DIR/.git"
export GIT_WORK_TREE="$DIST_DIR"

# Start with a clean slate.
rm -rf "$DIST_DIR"

git clone --progress --verbose "$DIST_REPO" "$DIST_DIR/.git"
git checkout -B "$DIST_BRANCH"

# Use the release bundle as the work tree.
export GIT_WORK_TREE="$SRC_DIR"

# Commit the latest changes.
git add --all
git commit --allow-empty --message "$COMMIT_MESSAGE"

# And maybe tag a release.
if [ -n "$DIST_TAG" ]; then
	echo "Tagging a release: $DIST_TAG"
	git tag --force "$DIST_TAG"
fi

git push --force --set-upstream origin "$DIST_BRANCH" --tags
