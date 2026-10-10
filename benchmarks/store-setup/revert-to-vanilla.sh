#!/usr/bin/env bash
# Paths (override via environment): STORE_DIR = docker-magento project, TOOLKIT_DIR = this repo, BENCH_DIR = this folder
: "${STORE_DIR:=$HOME/Sites/magento-seo-baseline}" "${TOOLKIT_DIR:=$HOME/magento2-seo-toolkit}"
: "${BENCH_DIR:=$TOOLKIT_DIR/benchmarks}"
# Puts the store back to vanilla behaviour for a clean step-1 re-measure:
# module switched off in config (its plugin then returns immediately), Magento dev settings off,
# sample hero and sample head includes restored.
set -e
cd "$STORE_DIR"
bin/clinotty bin/magento config:set softcode_lumaspeed/general/enabled 0 </dev/null
for k in dev/js/minify_files dev/css/minify_files dev/template/minify_html dev/js/move_script_to_bottom dev/css/use_css_critical_path; do
  bin/clinotty bin/magento config:set "$k" 0 </dev/null
done
bin/clinotty bash -c 'f=pub/media/wysiwyg/home/home-main.jpg; [ -f $f.orig ] && cp $f.orig $f' </dev/null
bin/clinotty mysql -h db -u magento -pmagento magento -e "UPDATE core_config_data SET value='<link  rel=\"stylesheet\" type=\"text/css\"  media=\"all\" href=\"{{MEDIA_URL}}styles.css\" />' WHERE path='design/head/includes'" </dev/null 2>/dev/null
bin/clinotty rm -rf var/view_preprocessed pub/static/frontend </dev/null
bin/clinotty bin/magento setup:static-content:deploy -f --area frontend en_US </dev/null | tail -3
bin/clinotty bin/magento cache:flush </dev/null
echo VANILLA_RESTORED
