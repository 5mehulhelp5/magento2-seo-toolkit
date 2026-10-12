<?php
declare(strict_types=1);

namespace Softcode\Hreflang\Model;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Cms\Model\ResourceModel\Page as PageResource;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

/**
 * The URL of the same page in another store view, or null when the page doesn't exist there.
 * Uses the same URLs as the canonicals: plain product URL (no category path), category URL, CMS identifier.
 */
class PageUrls
{
    public const HOME = 'home';
    public const PRODUCT = 'product';
    public const CATEGORY = 'category';
    public const CMS = 'cms';

    public function __construct(
        private readonly UrlFinderInterface $urlFinder,
        private readonly ProductResource $productResource,
        private readonly CategoryResource $categoryResource,
        private readonly PageResource $pageResource
    ) {
    }

    public function forStore(Store $store, string $type, int|string $entity): ?string
    {
        if (!$store->isActive()) {
            return null;
        }
        $storeId = (int)$store->getId();
        $base = $store->getBaseUrl(UrlInterface::URL_TYPE_LINK, $store->isFrontUrlSecure());

        switch ($type) {
            case self::HOME:
                return $base;
            case self::CMS:
                // Same identifier, active and assigned to this store view (or all store views).
                return $this->pageResource->checkIdentifier((string)$entity, $storeId) ? $base . $entity : null;
            case self::PRODUCT:
                // One attribute per call: with several, Magento drops attributes that only have a default value
                // as soon as another one has a store-level value.
                $status = $this->productResource->getAttributeRawValue((int)$entity, 'status', $storeId);
                $visibility = $this->productResource->getAttributeRawValue((int)$entity, 'visibility', $storeId);
                if ((int)$status !== Status::STATUS_ENABLED || (int)$visibility === Visibility::VISIBILITY_NOT_VISIBLE) {
                    return null;
                }
                $path = $this->requestPath('product', (int)$entity, $storeId);
                return $path === null ? null : $base . $path;
            case self::CATEGORY:
                if (!(int)$this->categoryResource->getAttributeRawValue((int)$entity, 'is_active', $storeId)) {
                    return null;
                }
                $path = $this->requestPath('category', (int)$entity, $storeId);
                return $path === null ? null : $base . $path;
        }
        return null;
    }

    /**
     * The direct (non-redirect) URL rewrite. Exists only where Magento generated one: products in the store's
     * website, categories under the store's root category. Category-path product URLs (with metadata) are skipped.
     */
    private function requestPath(string $entityType, int $id, int $storeId): ?string
    {
        $rewrites = $this->urlFinder->findAllByData([
            UrlRewrite::ENTITY_TYPE => $entityType,
            UrlRewrite::ENTITY_ID => $id,
            UrlRewrite::STORE_ID => $storeId,
            UrlRewrite::REDIRECT_TYPE => 0,
        ]);
        foreach ($rewrites as $rewrite) {
            if (empty($rewrite->getMetadata())) {
                return $rewrite->getRequestPath();
            }
        }
        return null;
    }
}
