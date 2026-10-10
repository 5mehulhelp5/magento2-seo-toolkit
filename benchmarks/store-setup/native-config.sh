#!/usr/bin/env bash
# Paths (override via environment): STORE_DIR = docker-magento project, TOOLKIT_DIR = this repo, BENCH_DIR = this folder
: "${STORE_DIR:=$HOME/Sites/magento-seo-baseline}" "${TOOLKIT_DIR:=$HOME/magento2-seo-toolkit}"
: "${BENCH_DIR:=$TOOLKIT_DIR/benchmarks}"
# Step 3: everything Magento itself offers for Luma frontend speed (Stores > Config > Advanced > Developer).
# JS bundling is deliberately left off: Magento's built-in bundling ships multi-MB bundles and usually makes Luma slower.
set -e
cd "$STORE_DIR"
for kv in dev/js/minify_files=1 dev/css/minify_files=1 dev/template/minify_html=1 dev/js/move_script_to_bottom=1 dev/css/use_css_critical_path=1; do
  bin/clinotty bin/magento config:set "${kv%%=*}" "${kv#*=}" </dev/null
done
bin/clinotty rm -rf var/view_preprocessed pub/static/frontend </dev/null
bin/clinotty bin/magento setup:static-content:deploy -f --area frontend en_US </dev/null | tail -3
bin/clinotty bin/magento cache:flush </dev/null
echo STEP3_APPLIED
