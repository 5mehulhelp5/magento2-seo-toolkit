#!/usr/bin/env bash
# Paths (override via environment): STORE_DIR = docker-magento project, TOOLKIT_DIR = this repo, BENCH_DIR = this folder
: "${STORE_DIR:=$HOME/Sites/magento-seo-baseline}" "${TOOLKIT_DIR:=$HOME/magento2-seo-toolkit}"
: "${BENCH_DIR:=$TOOLKIT_DIR/benchmarks}"
# Copies the module from the toolkit repo, recompiles DI and checks that cached pages carry the optimised HTML.
set -e
cd "$STORE_DIR"
rm -rf src/app/code/Softcode/LumaSpeed
cp -r $TOOLKIT_DIR/modules/LumaSpeed src/app/code/Softcode/LumaSpeed
bin/copytocontainer app/code/Softcode >/dev/null
bin/clinotty bin/magento config:set dev/css/use_css_critical_path 0 </dev/null
bin/clinotty bin/magento setup:di:compile </dev/null | tail -1
bin/clinotty bin/magento cache:flush </dev/null >/dev/null
U="https://magento-seo.test:8443/men/tops-men.html"
for i in 1 2 3; do echo "request $i fetchpriority count: $(curl -sk --resolve magento-seo.test:8443:127.0.0.1 "$U" | grep -c fetchpriority)"; done
