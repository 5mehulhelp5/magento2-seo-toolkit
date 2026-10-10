#!/usr/bin/env bash
# Paths (override via environment): STORE_DIR = docker-magento project, TOOLKIT_DIR = this repo, BENCH_DIR = this folder
: "${STORE_DIR:=$HOME/Sites/magento-seo-baseline}" "${TOOLKIT_DIR:=$HOME/magento2-seo-toolkit}"
: "${BENCH_DIR:=$TOOLKIT_DIR/benchmarks}"
# Installs the Softcode SEO modules from the toolkit repo into the test store.
set -e
cd "$STORE_DIR"
for m in Canonical Robots StructuredData; do
  rm -rf "src/app/code/Softcode/$m"
  cp -r "$TOOLKIT_DIR/modules/$m" "src/app/code/Softcode/$m"
done
bin/copytocontainer app/code/Softcode >/dev/null
for f in $(find src/app/code/Softcode -name '*.php'); do bin/clinotty php -l "${f#src/}" </dev/null | grep -v '^No syntax errors' || true; done
bin/clinotty bin/magento module:enable Softcode_Canonical Softcode_Robots Softcode_StructuredData </dev/null
bin/clinotty bin/magento setup:upgrade --keep-generated </dev/null | tail -1
bin/clinotty bin/magento setup:di:compile </dev/null | tail -1
bin/clinotty bin/magento cache:flush </dev/null >/dev/null
echo SEO_MODULES_INSTALLED
