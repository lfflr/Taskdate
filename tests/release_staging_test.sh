#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "$0")/.." && pwd)"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
bash "$repo_root/scripts/prepare-release.sh" "$tmp"
app="$tmp/taskdate"
for file in appinfo/info.xml lib/AppInfo/Application.php lib/Listener/TaskDateListener.php lib/DateParser.php lib/TaskWritePolicy.php lib/CalendarPayload.php img/app.svg; do
  test -f "$app/$file" || { echo "Missing runtime file: $file" >&2; exit 1; }
done
if find "$app" -type f \( -name '*.key' -o -name '*.csr' -o -name '*.pem' -o -name 'signature.json' \) | grep -q .; then
  echo "Unsigned staging must not contain keys, CSRs or signatures" >&2
  exit 1
fi
for item in .git .github tests docs scripts LICENSE README.md; do
  if test -e "$app/$item"; then
    echo "Unexpected non-runtime artifact: $item" >&2
    exit 1
  fi
done
php -r '
$xml = simplexml_load_file($argv[1]);
if ($xml === false) { fwrite(STDERR, "Invalid appinfo XML\n"); exit(1); }
if ((string)$xml->id !== "taskdate") { fwrite(STDERR, "Wrong app ID\n"); exit(1); }
if (!preg_match("/^\d+\.\d+\.\d+$/", (string)$xml->version)) {
  fwrite(STDERR, "Invalid app version\n"); exit(1);
}
if (trim((string)$xml->author) === "" || trim((string)$xml->name) === "") {
  fwrite(STDERR, "Missing author/name\n"); exit(1);
}
echo "App metadata: valid structure and expected ID\n";
' "$app/appinfo/info.xml"
echo "Release staging smoke test: success (unsigned only)"
