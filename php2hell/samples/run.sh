#!/usr/bin/env bash
# Run the php2shell (CVE-2026-109618 / 109619) samples on the official Docker
# images for the pre-patch (8.3.35) and post-patch (8.3.36) builds and compare.
set -u
cd "$(dirname "$0")"

VULN=8.3.35-cli   # pre-patch
FIXED=8.3.36-cli  # post-patch

run() {
  local file="$1"
  echo "################ ${file} ################"
  for tag in "$VULN" "$FIXED"; do
    echo "----- php:${tag} -----"
    docker run --rm -v "$PWD":/app "php:${tag}" php "/app/${file}" 2>&1
    echo
  done
}

run 01-oom-trigger.php
run 02-engine-changed.php
run 03-why-invisible.php
