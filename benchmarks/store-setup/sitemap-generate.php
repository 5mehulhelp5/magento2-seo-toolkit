<?php
// Generates Magento's native sitemap for store 1 (same code path as the sitemap_generate cron job).
// Run inside the container: php sitemap-generate.php
use Magento\Framework\App\Bootstrap;

require '/var/www/html/app/bootstrap.php';
$bootstrap = Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();
$om->get(\Magento\Framework\App\State::class)->setAreaCode('frontend');

$collection = $om->create(\Magento\Sitemap\Model\ResourceModel\Sitemap\Collection::class);
$sitemap = $collection->getFirstItem();
if (!$sitemap->getId()) {
    $sitemap = $om->create(\Magento\Sitemap\Model\Sitemap::class);
    $sitemap->setData(['sitemap_filename' => 'sitemap.xml', 'sitemap_path' => '/', 'store_id' => 1]);
    $sitemap->save();
}
$sitemap->generateXml();
echo "generated {$sitemap->getSitemapPath()}{$sitemap->getSitemapFilename()} at {$sitemap->getSitemapTime()}\n";
