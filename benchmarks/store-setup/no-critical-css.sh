#!/usr/bin/env bash
# Paths (override via environment): STORE_DIR = docker-magento project, TOOLKIT_DIR = this repo, BENCH_DIR = this folder
: "${STORE_DIR:=$HOME/Sites/magento-seo-baseline}" "${TOOLKIT_DIR:=$HOME/magento2-seo-toolkit}"
: "${BENCH_DIR:=$TOOLKIT_DIR/benchmarks}"
# Step 5: keep step 4 (module + Magento minify/move-JS) but switch off Magento's critical-CSS option,
# whose incomplete Luma critical.css caused a full-page layout shift on desktop (CLS 1.26 in step 3).
set -e
cd "$STORE_DIR"
bin/clinotty bin/magento config:set dev/css/use_css_critical_path 0 </dev/null
bin/clinotty bin/magento cache:flush </dev/null
echo STEP5_APPLIED
