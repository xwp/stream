#!/usr/bin/env bash

# Set this to -ex for verbose output.
set -e

ROOT_DIR="$(git rev-parse --show-toplevel)"
COMMIT_MESSAGE="$(git log -1 --oneline)"
SRC_DIR="$ROOT_DIR/build"

DIST_REPO="git@github.com:xwp/stream-dist.git"
DIST_DIR="$ROOT_DIR/dist"
DIST_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
DIST_TAG="$1"

export GIT_DIR="$DIST_DIR/.git"
export GIT_WORK_TREE="$DIST_DIR"

# Start with a clean slate.
rm -rf "$DIST_DIR"

git clone "$DIST_REPO" "$DIST_DIR"
git checkout -B "$DIST_BRANCH" "origin/$DIST_BRANCH"

# Copy over the update.
git rm -r --quiet .
cp -r "$SRC_DIR/*" "$DIST_DIR"
git add --all

# Always commit the changes to a branch.
git commit --allow-empty --message "$COMMIT_MESSAGE"

# And maybe tag a release.
if [ -m "$DIST_TAG" ]; then
	git tag "$DIST_TAG"
fi

git push --set-upstream origin "$DIST_BRANCH" --tags
