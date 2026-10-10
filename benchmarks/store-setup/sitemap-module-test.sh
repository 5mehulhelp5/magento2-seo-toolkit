#!/usr/bin/env bash
# Paths (override via environment): STORE_DIR = docker-magento project, TOOLKIT_DIR = this repo, BENCH_DIR = this folder
: "${STORE_DIR:=$HOME/Sites/magento-seo-baseline}" "${TOOLKIT_DIR:=$HOME/magento2-seo-toolkit}"
: "${BENCH_DIR:=$TOOLKIT_DIR/benchmarks}"
# Tests Softcode_Sitemap from a vanilla state: removes the sitemap row/file created by the audit, installs the
# module, checks the data patch created a row, generates the file the way the cron job does, checks robots.txt.
set -e
cd "$STORE_DIR"
DB="mysql -h db -u magento -pmagento magento"
bin/clinotty bash -c "$DB -e 'DELETE FROM sitemap' 2>/dev/null; rm -f pub/sitemap.xml" </dev/null
echo "sitemap rows before: $(bin/clinotty bash -c "$DB -N -e 'SELECT COUNT(*) FROM sitemap' 2>/dev/null" </dev/null)"
rm -rf src/app/code/Softcode/Sitemap
cp -r $TOOLKIT_DIR/modules/Sitemap src/app/code/Softcode/Sitemap
bin/copytocontainer app/code/Softcode >/dev/null
bin/clinotty bin/magento module:enable Softcode_Sitemap </dev/null >/dev/null
bin/clinotty bin/magento setup:upgrade --keep-generated </dev/null >/dev/null
bin/clinotty bin/magento setup:di:compile </dev/null >/dev/null
bin/clinotty bin/magento cache:flush </dev/null >/dev/null
echo "sitemap rows after:  $(bin/clinotty bash -c "$DB -N -e 'SELECT CONCAT(store_id, \" \", sitemap_path, sitemap_filename) FROM sitemap' 2>/dev/null" </dev/null)"
for k in sitemap/generate/enabled sitemap/search_engines/submission_robots; do
  echo "$k = $(bin/clinotty php -r "require 'app/bootstrap.php'; \$om = \Magento\Framework\App\Bootstrap::create(BP, [])->getObjectManager(); echo \$om->get(\Magento\Framework\App\Config\ScopeConfigInterface::class)->getValue('$k', 'store', 1);" </dev/null)"
done
bin/clinotty php var/sitemap-generate.php </dev/null | tail -1
echo "--- robots.txt"
curl -sk --resolve magento-seo.test:8443:127.0.0.1 https://magento-seo.test:8443/robots.txt
