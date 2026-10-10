#!/usr/bin/env bash
# Paths (override via environment): STORE_DIR = docker-magento project, TOOLKIT_DIR = this repo, BENCH_DIR = this folder
: "${STORE_DIR:=$HOME/Sites/magento-seo-baseline}" "${TOOLKIT_DIR:=$HOME/magento2-seo-toolkit}"
: "${BENCH_DIR:=$TOOLKIT_DIR/benchmarks}"
# Generates Magento's native sitemap and prints the sitemap-related settings.
cd "$STORE_DIR"
docker compose cp $BENCH_DIR/store-setup/sitemap-generate.php app:/var/www/html/var/sitemap-generate.php
bin/clinotty php var/sitemap-generate.php </dev/null 2>&1 | tail -3
for k in sitemap/product/image_include sitemap/search_engines/submission_robots sitemap/generate/enabled; do
  echo "$k = $(bin/clinotty bin/magento config:show $k </dev/null 2>&1 | tail -1)"
done
