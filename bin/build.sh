#!/usr/bin/env bash
# Builds dist/alibiewi-console-<version>.zip with only the files a WordPress site needs.
set -euo pipefail
cd "$(dirname "$0")/.."
VERSION=$(grep -m1 "define( 'ALC_VERSION'" alibiewi-console.php | sed -E "s/.*'([0-9.]+)'.*/\1/")
STAGE=$(mktemp -d)
mkdir -p "$STAGE/alibiewi-console" dist
cp -r alibiewi-console.php uninstall.php readme.txt assets includes languages "$STAGE/alibiewi-console/"
rm -f "dist/alibiewi-console-$VERSION.zip"
(cd "$STAGE" && zip -rq "$OLDPWD/dist/alibiewi-console-$VERSION.zip" alibiewi-console -x "*.DS_Store" "*Thumbs.db")
rm -rf "$STAGE"
echo "Built dist/alibiewi-console-$VERSION.zip"
