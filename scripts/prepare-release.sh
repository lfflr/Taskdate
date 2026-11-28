#!/usr/bin/env bash
set -euo pipefail
# Creates a clean, unsigned staging directory for Nextcloud's signing step.
# Do not submit the unsigned archive to the App Store.
repo_root="$(cd "$(dirname "$0")/.." && pwd)"
out="${1:-$repo_root/dist}"
mkdir -p "$out"
stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT
mkdir -p "$stage/taskdate/appinfo" "$stage/taskdate/lib" "$stage/taskdate/img"
cp "$repo_root/appinfo/info.xml" "$stage/taskdate/appinfo/info.xml"
cp -R "$repo_root/lib/." "$stage/taskdate/lib/"
cp "$repo_root/img/app.svg" "$stage/taskdate/img/app.svg"
cp "$repo_root/LICENSE" "$stage/taskdate/LICENSE"
test -f "$stage/taskdate/appinfo/info.xml"
test -f "$stage/taskdate/lib/AppInfo/Application.php"
test -f "$stage/taskdate/lib/Sabre/TaskDateSabrePlugin.php"
# Make the staging directory available for manual integrity:sign-app signing.
rm -rf "$out/taskdate"
cp -R "$stage/taskdate" "$out/taskdate"
printf 'Unsigned staging directory: %s/taskdate\n' "$out"
printf 'Sign that directory with occ integrity:sign-app before creating a release archive.\n'
