#!/usr/bin/env bash
# Paths (override via environment): STORE_DIR = docker-magento project, TOOLKIT_DIR = this repo, BENCH_DIR = this folder
: "${STORE_DIR:=$HOME/Sites/magento-seo-baseline}" "${TOOLKIT_DIR:=$HOME/magento2-seo-toolkit}"
: "${BENCH_DIR:=$TOOLKIT_DIR/benchmarks}"
# Step 2: make the vanilla store look like a real shop's frontend load.
#  - a 2560px marketing hero upload instead of the 67 KB sample image
#  - typical third-party scripts added the way shops do it: Content > Design > HTML Head
set -e
cd "$STORE_DIR"
SRC=$BENCH_DIR/store-setup

# third-party stand-ins into pub/media/demo-3p
docker compose cp "$SRC/demo-3p" app:/var/www/html/pub/media/demo-3p
bin/rootnotty chown -R app:app /var/www/html/pub/media/demo-3p

# hero: upscale the sample hero to 2560px wide at quality 92 (keeps the original as .orig)
bin/clinotty php -r '
$f = "pub/media/wysiwyg/home/home-main.jpg";
if (!file_exists("$f.orig")) { copy($f, "$f.orig"); }
$src = imagecreatefromjpeg("$f.orig");
$w = 2560; $h = (int)round(imagesy($src) * $w / imagesx($src));
$dst = imagecreatetruecolor($w, $h);
imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
imagejpeg($dst, $f, 92);
echo "hero: {$w}x{$h}, " . round(filesize($f) / 1024) . " KB\n";
' </dev/null

# head includes, on every scope that already has the sample-data value
INCLUDES=$(cat "$SRC/demo-3p/head-includes.html" | sed "s/'/''/g")
bin/clinotty mysql -h db -u magento -pmagento magento -e "UPDATE core_config_data SET value='$INCLUDES' WHERE path='design/head/includes'; SELECT scope, scope_id, LEFT(value, 80) FROM core_config_data WHERE path='design/head/includes';" </dev/null 2>/dev/null
bin/clinotty bin/magento cache:flush </dev/null
echo STEP2_APPLIED
