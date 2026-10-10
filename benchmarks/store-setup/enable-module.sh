#!/usr/bin/env bash
# Paths (override via environment): STORE_DIR = docker-magento project, TOOLKIT_DIR = this repo, BENCH_DIR = this folder
: "${STORE_DIR:=$HOME/Sites/magento-seo-baseline}" "${TOOLKIT_DIR:=$HOME/magento2-seo-toolkit}"
: "${BENCH_DIR:=$TOOLKIT_DIR/benchmarks}"
# Step 4: install Softcode_LumaSpeed from the toolkit repo and enable it (production mode needs a recompile).
set -e
cd "$STORE_DIR"
rm -rf src/app/code/Softcode/LumaSpeed
mkdir -p src/app/code/Softcode
cp -r $TOOLKIT_DIR/modules/LumaSpeed src/app/code/Softcode/LumaSpeed
bin/copytocontainer app/code/Softcode >/dev/null
bin/clinotty bin/magento module:enable Softcode_LumaSpeed </dev/null
bin/clinotty bin/magento setup:upgrade --keep-generated </dev/null | tail -2
bin/clinotty bin/magento setup:di:compile </dev/null | tail -2
bin/clinotty bin/magento cache:flush </dev/null
echo STEP4_APPLIED
