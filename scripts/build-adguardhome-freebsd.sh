#!/bin/sh

set -eu

VERSION="${1:?usage: $0 <adguardhome-version-tag>}"
ROOT=$(cd -- "$(dirname -- "$0")/.." && pwd)
WORK="${ROOT}/work"
TARBALL="AdGuardHome_freebsd_amd64.tar.gz"
URL="https://github.com/AdguardTeam/AdGuardHome/releases/download/${VERSION}/${TARBALL}"

rm -rf "${WORK}"
mkdir -p "${WORK}" "${ROOT}/dist"

fetch -o "${WORK}/${TARBALL}" "${URL}"
tar -xzf "${WORK}/${TARBALL}" -C "${WORK}"
install -m 0755 "${WORK}/AdGuardHome/AdGuardHome" "${ROOT}/dist/AdGuardHome"
"${ROOT}/dist/AdGuardHome" --version
