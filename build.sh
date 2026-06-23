#!/bin/sh

set -eu

PORTNAME="pfSense-pkg-adguardhome"
PORTVERSION="${PORTVERSION:-0.0.0}"
ABI="FreeBSD:15:amd64"
PREFIX="/usr/local"
ROOT=$(cd -- "$(dirname -- "$0")" && pwd)
FILES="${ROOT}/files"
BUILD="${ROOT}/build"
STAGE="${BUILD}/stage"
OUTPUT="${BUILD}/pkg"
BINARY="${ROOT}/dist/AdGuardHome"

clean() {
	rm -rf "${BUILD}"
}

json_escape() {
	awk '{ gsub(/\\/, "\\\\"); gsub(/\t/, "\\t"); gsub(/"/, "\\\""); printf "%s\\n", $0 }'
}

stage() {
	if [ ! -x "${BINARY}" ]; then
		echo "Missing FreeBSD/amd64 binary: ${BINARY}" >&2
		echo "Run scripts/build-adguardhome-freebsd.sh or place AdGuardHome at dist/AdGuardHome." >&2
		exit 1
	fi

	rm -rf "${STAGE}"
	mkdir -p \
		"${STAGE}${PREFIX}/bin" \
		"${STAGE}${PREFIX}/etc/rc.d" \
		"${STAGE}${PREFIX}/pkg" \
		"${STAGE}${PREFIX}/www" \
		"${STAGE}${PREFIX}/www/shortcuts" \
		"${STAGE}${PREFIX}/www/widgets/widgets" \
		"${STAGE}${PREFIX}/share/${PORTNAME}" \
		"${STAGE}/etc/inc/priv"

	install -m 0755 "${BINARY}" "${STAGE}${PREFIX}/bin/AdGuardHome"
	install -m 0555 "${FILES}${PREFIX}/etc/rc.d/adguardhome" "${STAGE}${PREFIX}/etc/rc.d/adguardhome"
	install -m 0644 "${FILES}${PREFIX}/pkg/adguardhome.xml" "${STAGE}${PREFIX}/pkg/adguardhome.xml"
	install -m 0644 "${FILES}${PREFIX}/pkg/adguardhome.inc" "${STAGE}${PREFIX}/pkg/adguardhome.inc"
	install -m 0644 "${FILES}${PREFIX}/www/shortcuts/pkg_adguardhome.inc" "${STAGE}${PREFIX}/www/shortcuts/pkg_adguardhome.inc"
	install -m 0644 "${FILES}${PREFIX}/www/status_adguardhome.php" "${STAGE}${PREFIX}/www/status_adguardhome.php"
	install -m 0644 "${FILES}${PREFIX}/www/widgets/widgets/adguardhome.widget.php" "${STAGE}${PREFIX}/www/widgets/widgets/adguardhome.widget.php"
	install -m 0644 "${FILES}${PREFIX}/share/${PORTNAME}/info.xml" "${STAGE}${PREFIX}/share/${PORTNAME}/info.xml"
	install -m 0644 "${FILES}/etc/inc/priv/adguardhome.priv.inc" "${STAGE}/etc/inc/priv/adguardhome.priv.inc"

	for file in \
		"${STAGE}${PREFIX}/pkg/adguardhome.xml" \
		"${STAGE}${PREFIX}/share/${PORTNAME}/info.xml"; do
		sed "s/%%PKGVERSION%%/${PORTVERSION}/g" "${file}" > "${file}.tmp"
		mv "${file}.tmp" "${file}"
	done
}

manifest() {
	post_install_script=$(sed "s/%%PORTNAME%%/${PORTNAME}/g" "${FILES}/pkg-install.in" | json_escape)
	pre_deinstall_script=$(sed "s/%%PORTNAME%%/${PORTNAME}/g" "${FILES}/pkg-deinstall.in" | json_escape)

	cat > "${BUILD}/+MANIFEST" <<EOF
name: "${PORTNAME}"
version: "${PORTVERSION}"
origin: "net/${PORTNAME}"
comment: "AdGuard Home for pfSense"
maintainer: "noreply@github.com"
prefix: "${PREFIX}"
abi: "${ABI}"
desc: "AdGuard Home package for pfSense with WebGUI service integration and status visibility."
www: "https://github.com/r0bb10/pfSense-pkg-adguardhome"
licenselogic: "single"
licenses: ["GPL3"]
categories: ["net"]
scripts: {
  post-install: "${post_install_script}",
  pre-deinstall: "${pre_deinstall_script}"
}
EOF

	sed "s|%%DATADIR%%|share/${PORTNAME}|g" "${ROOT}/pkg-plist" > "${BUILD}/plist"
}

package() {
	stage
	manifest
	mkdir -p "${OUTPUT}"
	pkg create -M "${BUILD}/+MANIFEST" -p "${BUILD}/plist" -r "${STAGE}" -o "${OUTPUT}"
	find "${OUTPUT}" -maxdepth 1 -type f -print
}

case "${1:-package}" in
	clean) clean ;;
	stage) stage ;;
	package) package ;;
	*) echo "Usage: $0 [package|stage|clean]" >&2; exit 2 ;;
esac
