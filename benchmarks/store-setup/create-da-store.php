<?php
// Adds a Danish store view "da" (Dansk, da_DK) to the default website, on https://da.magento-seo.test:8443/.
// Run inside the container: php var/create-da-store.php
use Magento\Framework\App\Bootstrap;

require '/var/www/html/app/bootstrap.php';
$om = Bootstrap::create(BP, $_SERVER)->getObjectManager();
$om->get(\Magento\Framework\App\State::class)->setAreaCode('adminhtml');

$storeManager = $om->get(\Magento\Store\Model\StoreManagerInterface::class);
$default = $storeManager->getStore('default');
$store = $om->create(\Magento\Store\Model\Store::class)->load('da', 'code');
if (!$store->getId()) {
    $store->setData([
        'code' => 'da', 'name' => 'Dansk', 'website_id' => $default->getWebsiteId(),
        'group_id' => $default->getStoreGroupId(), 'sort_order' => 10, 'is_active' => 1,
    ]);
    // saving through the resource model dispatches store_add: catalog URL rewrites for the new view are generated
    $om->get(\Magento\Store\Model\ResourceModel\Store::class)->save($store);
    echo "created store view da (id {$store->getId()})\n";
} else {
    echo "store view da exists (id {$store->getId()})\n";
}
$config = $om->get(\Magento\Framework\App\Config\Storage\WriterInterface::class);
foreach ([
    'web/unsecure/base_url' => 'https://da.magento-seo.test:8443/',
    'web/secure/base_url' => 'https://da.magento-seo.test:8443/',
    'general/locale/code' => 'da_DK',
] as $path => $value) {
    $config->save($path, $value, 'stores', (int)$store->getId());
}
$count = $om->get(\Magento\Framework\App\ResourceConnection::class)->getConnection()
    ->fetchOne('SELECT COUNT(*) FROM url_rewrite WHERE store_id = ?', [(int)$store->getId()]);
echo "url rewrites for da: $count\n";
