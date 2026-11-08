#!/usr/bin/env bash
# Baut eine direkt installierbare WP-Plugin-ZIP mit genau einem Plugin-Ordner.
set -euo pipefail
cd "$(dirname "$0")/.."

slug='alowidat-geschenkgutscheine'
plugin_file="${slug}.php"

[[ -f "$plugin_file" ]] || { echo 'Plugin-Hauptdatei fehlt' >&2; exit 1; }
[[ -d includes && -d assets && -f README.md && -f CHANGELOG.md ]] || {
  echo 'Plugin-Quellen oder Dokumentation fehlen' >&2; exit 1;
}

version="$(sed -nE 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([0-9]+\.[0-9]+\.[0-9]+).*/\1/p' "$plugin_file" | head -n 1)"
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || {
  echo "Keine gültige Plugin-Version im Header gefunden: '$version'" >&2; exit 1;
}
grep -Fq "define( 'ALW_GC_VERSION', '${version}' );" "$plugin_file" || {
  echo 'Plugin-Header und Versionskonstante stimmen nicht überein' >&2; exit 1;
}
grep -Fq "**Version:** ${version}" README.md || {
  echo 'README-Version stimmt nicht mit Plugin überein' >&2; exit 1;
}
grep -Fq "## ${version} " CHANGELOG.md || {
  echo 'CHANGELOG enthält die Version nicht' >&2; exit 1;
}

while IFS= read -r -d '' path; do
  php -l "$path" >/dev/null
  echo "PHP OK: $path"
done < <(find . -type f -name '*.php' -not -path './dist/*' -print0)

mkdir -p dist
rm -rf "dist/${slug}"
mkdir -p "dist/${slug}"
cp "$plugin_file" README.md CHANGELOG.md "dist/${slug}/"
cp -R includes assets "dist/${slug}/"
rm -f "dist/${slug}-v${version}.zip"
(
  cd dist
  zip -q -r "${slug}-v${version}.zip" "$slug" -x '*/.DS_Store' '*/Thumbs.db'
)
unzip -t "dist/${slug}-v${version}.zip" >/dev/null
# WordPress muss unmittelbar den Plugin-Ordner mit der Hauptdatei finden.
unzip -Z -1 "dist/${slug}-v${version}.zip" | grep -Fxq "${slug}/${plugin_file}"
# Kein zusätzlicher äußerer Ordner im Archiv.
if unzip -Z -1 "dist/${slug}-v${version}.zip" | grep -Ev "^${slug}/"; then
  echo 'ZIP enthält Dateien außerhalb des Plugin-Ordners' >&2
  exit 1
fi
printf '%s\n' "$version" > dist/VERSION
cat > dist/RELEASE_NOTES.md <<EOF
Direkt in WordPress installierbare Plugin-ZIP für **Alowidat Geschenkgutscheine ${version}**.

**Download:** Datei \`${slug}-v${version}.zip\` unter **Assets** herunterladen. Nicht „Source code (zip)“ verwenden.

**Installation:** WordPress → Plugins → Neues Plugin → Plugin hochladen → ZIP auswählen. Bei bestehenden Installationen die vorhandene Version ersetzen.

**Achtung:** Vor dem produktiven Einsatz Gutschein-Einlösung, Steuern, Rückerstattungen und Zahlungsanbieter testen. Nur klassischer WooCommerce-Warenkorb/Checkout unterstützt.

Änderungen und rechtliche Hinweise: [CHANGELOG.md](https://github.com/${GITHUB_REPOSITORY:-IT-Kayali/Alowidat-Geschenkgutscheine}/blob/main/CHANGELOG.md), [README.md](https://github.com/${GITHUB_REPOSITORY:-IT-Kayali/Alowidat-Geschenkgutscheine}/blob/main/README.md).
EOF
printf 'OK: dist/%s-v%s.zip\n' "$slug" "$version"