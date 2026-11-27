#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$root"

# A release is blocked until all externally verified requirements are satisfied.
# No secrets or app signing keys belong in this repository.
blocked=0

if grep -R -n -E '(^|[[:space:]])use[[:space:]]+OCA\\\\DAV\\\\|\\\\OCA\\\\DAV\\\\' lib --include='*.php'; then
  printf '%s\n' 'BLOCKER: private Nextcloud DAV implementation used by TaskDate.'
  blocked=1
fi

meta_license="$(php -r '$xml=simplexml_load_file("appinfo/info.xml");if($xml===false)exit(2);echo strtolower(trim((string)$xml->licence));')"
if grep -q 'GNU GENERAL PUBLIC LICENSE' LICENSE && ! grep -q 'GNU AFFERO GENERAL PUBLIC LICENSE' LICENSE; then
  if [ "$meta_license" != "gpl" ] && [ "$meta_license" != "gpl-3.0" ] && [ "$meta_license" != "gpl-3.0-only" ] && [ "$meta_license" != "gpl-3.0-or-later" ]; then
    printf '%s\n' "BLOCKER: appinfo licence '$meta_license' conflicts with the GPL-3.0 licence file."
    blocked=1
  fi
fi

if [ "$blocked" -ne 0 ]; then
  printf '%s\n' 'Release gate: BLOCKED. Do not merge as public App Store release.'
  exit 1
fi
printf '%s\n' 'Static release gate: passed. A live Nextcloud test and signing are still required.'
