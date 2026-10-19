#!/usr/bin/env bash
#
# Build a production-ready release zip: releases/cp-library-{version}.zip
#
# Ported from cp-sync's bin/build-zip.sh. Recipe:
#   1. Prune vendor/ to runtime dependencies ( no composer scripts or dev-only
#      tooling in this plugin today, but the step keeps the zip honest if any
#      are ever added ).
#   2. Compile both build targets: the src/ app and the blocks/.
#   3. Zip via wp-scripts plugin-zip, driven by the "files" allowlist in
#      package.json. The npm script then strips README.md and package.json,
#      which npm's packlist force-includes.
#   4. Rewrap the archive under a top-level cp-library/ directory. wp-scripts
#      stores files at the archive root, so WordPress derives the install
#      folder from the ZIP FILENAME — fine for cp-library.zip, wrong once the
#      filename carries a version suffix. With the cp-library/ root inside
#      the archive, the plugin always installs as `cp-library` regardless of
#      what the zip file is called.
#   5. Restore dev dependencies — always, even when a step fails.
#
# The version comes from the cp-library.php plugin header — package.json's
# "version" is not kept in sync and must not be trusted here.
#
# Usage: bin/build-zip.sh  ( from anywhere; output lands in releases/ )

set -euo pipefail
cd "$( dirname "${BASH_SOURCE[0]}" )/.."

if [ ! -d node_modules ]; then
	echo "node_modules missing — running npm install first." >&2
	npm install
fi

restore_dev_deps() {
	echo "Restoring dev dependencies..."
	composer install --quiet
}
trap restore_dev_deps EXIT

echo "Pruning vendor/ to runtime dependencies..."
composer install --no-dev --no-scripts --quiet

echo "Building the src app and blocks..."
npm run build

echo "Creating the zip..."
npm run plugin-zip

VERSION=$( sed -n 's/^ \* Version:[[:space:]]*//p' cp-library.php )
if [ -z "${VERSION}" ]; then
	echo "Could not read the version from cp-library.php" >&2
	exit 1
fi
RELEASE_ZIP="releases/cp-library-${VERSION}.zip"

echo "Rewrapping under a cp-library/ root as ${RELEASE_ZIP}..."
mkdir -p releases
STAGE=$( mktemp -d )
mkdir "${STAGE}/cp-library"
unzip -q cp-library.zip -d "${STAGE}/cp-library"
# Strip VCS internals — the ChurchPlugins checkout carries a full .git
# directory here, which must never ship in a customer zip.
find "${STAGE}/cp-library" \( -name '.git' -o -name '.github' -o -name '.gitignore' -o -name '.gitattributes' \) -prune -exec rm -rf {} +
( cd "${STAGE}" && zip -qr wrapped.zip cp-library )
mv "${STAGE}/wrapped.zip" "${RELEASE_ZIP}"
rm -f cp-library.zip
rm -rf "${STAGE}"

echo
echo "Done: $( pwd )/${RELEASE_ZIP}"
