#!/usr/bin/env bash
# Paths (override via environment): STORE_DIR = docker-magento project, TOOLKIT_DIR = this repo, BENCH_DIR = this folder
: "${STORE_DIR:=$HOME/Sites/magento-seo-baseline}" "${TOOLKIT_DIR:=$HOME/magento2-seo-toolkit}"
: "${BENCH_DIR:=$TOOLKIT_DIR/benchmarks}"
# Checks the responsive WebP feature on the cached homepage: preload placement, sizes, generated variants.
cd "$STORE_DIR"
get() { curl -sk --resolve magento-seo.test:8443:127.0.0.1 "https://magento-seo.test:8443$1"; }
get / >/dev/null
h=$(get /)
echo "line of viewport meta:  $(grep -n 'name="viewport"' <<<"$h" | cut -d: -f1)"
echo "line of image preload:  $(grep -n 'rel="preload" as="image"' <<<"$h" | cut -d: -f1)"
echo "preload imagesizes:     $(grep -o 'imagesizes="[^"]*"' <<<"$h")"
echo "img sizes values:       $(grep -o ' sizes="[^"]*"' <<<"$h" | sort | uniq -c | tr '\n' ';')"
bin/clinotty bash -c 'find pub/media/softcode/rsz -name "home-main*" -printf "%s bytes  %p\n" | sort -n' </dev/null
echo "original: $(bin/clinotty stat -c %s pub/media/wysiwyg/home/home-main.jpg </dev/null) bytes"
