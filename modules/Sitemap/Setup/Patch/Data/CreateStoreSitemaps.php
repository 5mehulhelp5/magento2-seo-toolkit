<?php
declare(strict_types=1);

namespace Softcode\Sitemap\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Sitemap\Model\ResourceModel\Sitemap\CollectionFactory;
use Magento\Sitemap\Model\SitemapFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Vanilla Magento has no sitemap until someone creates one under Marketing > Site Map, so its daily
 * generation has nothing to generate. This registers one per store view that doesn't have one yet:
 * sitemap.xml for a single-store shop, sitemap_<store code>.xml otherwise. Files are written by Magento's
 * own sitemap_generate cron job. Store views added later need a sitemap added in the admin.
 */
class CreateStoreSitemaps implements DataPatchInterface
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly CollectionFactory $collectionFactory,
        private readonly SitemapFactory $sitemapFactory
    ) {
    }

    public function apply(): self
    {
        $stores = array_filter($this->storeManager->getStores(), fn ($store) => (bool)$store->getIsActive());
        $existing = array_map('intval', $this->collectionFactory->create()->getColumnValues('store_id'));
        foreach ($stores as $store) {
            if (in_array((int)$store->getId(), $existing, true)) {
                continue;
            }
            $this->sitemapFactory->create()->setData([
                'sitemap_filename' => count($stores) === 1 ? 'sitemap.xml' : 'sitemap_' . $store->getCode() . '.xml',
                'sitemap_path' => '/',
                'store_id' => (int)$store->getId(),
            ])->save();
        }
        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
