#!/usr/bin/env bash
# Paths (override via environment): STORE_DIR = docker-magento project, TOOLKIT_DIR = this repo, BENCH_DIR = this folder
: "${STORE_DIR:=$HOME/Sites/magento-seo-baseline}" "${TOOLKIT_DIR:=$HOME/magento2-seo-toolkit}"
: "${BENCH_DIR:=$TOOLKIT_DIR/benchmarks}"
# Shows what Softcode_LumaSpeed changed in the cached HTML of each key page.
get() { curl -sk --resolve magento-seo.test:8443:127.0.0.1 "https://magento-seo.test:8443$1"; }
for p in / /women/tops-women/jackets-women.html /joust-duffle-bag.html; do
  get "$p" >/dev/null   # warm the cache
  h=$(get "$p")
  echo "== $p"
  echo "  image preload:      $(grep -o '<link rel="preload" as="image"[^>]*>' <<<"$h" | head -1 | cut -c1-140)"
  echo "  fetchpriority imgs: $(grep -o '<img [^>]*fetchpriority="high"[^>]*>' <<<"$h" | head -1 | cut -c1-160)"
  echo "  font preloads:      $(grep -c 'as="font"' <<<"$h")"
  echo "  calendar.css:       $(grep -o '<link[^>]*calendar[^>]*>' <<<"$h" | head -1 | cut -c1-140)"
  echo "  lazy content imgs:  $(grep -c 'loading="lazy" decoding="async"' <<<"$h")"
done
