<?php
declare(strict_types=1);

namespace Softcode\StructuredData\Block;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Softcode\StructuredData\Model\SchemaBuilder;
use Psr\Log\LoggerInterface;

/**
 * Renders JSON-LD for the page it is placed on (layout argument "schemas": product, breadcrumbs,
 * organization, website). Any failure is logged and renders nothing: structured data must never break a page.
 */
class JsonLd extends AbstractBlock
{
    public function __construct(
        Context $context,
        private readonly SchemaBuilder $builder,
        private readonly Registry $registry,
        private readonly StoreManagerInterface $storeManager,
        private readonly ReviewFactory $reviewFactory,
        private readonly LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        if (!$this->_scopeConfig->isSetFlag('softcode_structureddata/general/enabled', ScopeInterface::SCOPE_STORE)) {
            return '';
        }
        $html = '';
        foreach (array_filter(array_map('trim', explode(',', (string)$this->getData('schemas')))) as $type) {
            try {
                $schema = match ($type) {
                    'product' => $this->productSchema(),
                    'breadcrumbs' => $this->breadcrumbSchema(),
                    'organization' => $this->organizationSchema(),
                    'website' => $this->websiteSchema(),
                    default => null,
                };
                if ($schema) {
                    $html .= $this->builder->toScript($schema) . "\n";
                }
            } catch (\Throwable $e) {
                $this->logger->warning("Softcode_StructuredData: {$type} schema skipped: " . $e->getMessage());
            }
        }
        return $html;
    }

    private function productSchema(): ?array
    {
        $product = $this->currentProduct();
        if (!$product) {
            return null;
        }
        $store = $this->storeManager->getStore();
        $finalPrice = $product->getPriceInfo()->getPrice('final_price');
        $data = [
            'name' => (string)$product->getName(),
            'url' => $product->getUrlModel()->getUrl($product, ['_ignore_category' => true]),
            'sku' => (string)$product->getSku(),
            'description' => (string)($product->getData('short_description') ?: $product->getData('description')),
            'images' => $this->productImages($product),
            'brand' => $this->brand($product),
            'currency' => $store->getCurrentCurrencyCode(),
            'inStock' => (bool)$product->isSalable(),
        ];
        if ($product->getTypeId() === Configurable::TYPE_CODE) {
            $data['minPrice'] = (float)$finalPrice->getMinimalPrice()->getValue();
            $data['maxPrice'] = (float)$finalPrice->getMaximalPrice()->getValue();
            $data['offerCount'] = count($product->getTypeInstance()->getUsedProducts($product));
        } else {
            $data['price'] = (float)$finalPrice->getAmount()->getValue();
        }

        $this->reviewFactory->create()->getEntitySummary($product, (int)$store->getId());
        $summary = $product->getRatingSummary();
        if ($summary instanceof \Magento\Framework\DataObject) {
            $data['ratingPercent'] = (float)$summary->getData('rating_summary');
            $data['reviewCount'] = (int)$summary->getData('reviews_count');
        }
        return $this->builder->product($data);
    }

    /** @return string[] full-size gallery URLs (Google prefers large images), main image first */
    private function productImages(Product $product): array
    {
        $images = [];
        $mediaUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product';
        if ($product->getImage() && $product->getImage() !== 'no_selection') {
            $images[] = $mediaUrl . $product->getImage();
        }
        foreach ($product->getMediaGalleryImages() ?? [] as $image) {
            if (!$image->getDisabled() && $image->getUrl()) {
                $images[] = $image->getUrl();
            }
        }
        return $images;
    }

    private function brand(Product $product): ?string
    {
        $code = (string)$this->_scopeConfig->getValue('softcode_structureddata/product/brand_attribute', ScopeInterface::SCOPE_STORE);
        if ($code === '' || !$product->getData($code)) {
            return null;
        }
        $text = $product->getAttributeText($code);
        return is_string($text) && $text !== '' ? $text : (string)$product->getData($code);
    }

    private function breadcrumbSchema(): ?array
    {
        $store = $this->storeManager->getStore();
        $items = [['name' => (string)__('Home'), 'url' => $store->getBaseUrl()]];
        $product = $this->currentProduct();
        $category = $this->registry->registry('current_category') ?: ($product ? $this->deepestCategory($product) : null);
        if ($category instanceof Category) {
            $parents = $category->getParentCategories();
            usort($parents, fn ($a, $b) => (int)$a->getLevel() <=> (int)$b->getLevel());
            foreach ($parents as $parent) {
                if ((int)$parent->getLevel() >= 2) {
                    $items[] = ['name' => (string)$parent->getName(), 'url' => $parent->getUrl()];
                }
            }
        }
        if ($product) {
            $items[] = ['name' => (string)$product->getName(), 'url' => $product->getUrlModel()->getUrl($product, ['_ignore_category' => true])];
        }
        return $this->builder->breadcrumbs($items);
    }

    /** Products reached by their own URL have no current category; use their deepest active one. */
    private function deepestCategory(Product $product): ?Category
    {
        $collection = $product->getCategoryCollection()
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('is_active', 1)
            ->setOrder('level', 'DESC')
            ->setPageSize(1);
        $category = $collection->getFirstItem();
        return $category->getId() ? $category : null;
    }

    private function organizationSchema(): array
    {
        $store = $this->storeManager->getStore();
        $logoBlock = $this->getLayout()->getBlock('logo');
        $logo = $logoBlock && method_exists($logoBlock, 'getLogoSrc') ? (string)$logoBlock->getLogoSrc() : null;
        return $this->builder->organization($this->shopName(), $store->getBaseUrl(), $logo);
    }

    private function websiteSchema(): array
    {
        $base = $this->storeManager->getStore()->getBaseUrl();
        return $this->builder->website($this->shopName(), $base, $base . 'catalogsearch/result/?q={search_term_string}');
    }

    private function shopName(): string
    {
        return (string)($this->_scopeConfig->getValue('general/store_information/name', ScopeInterface::SCOPE_STORE)
            ?: $this->storeManager->getStore()->getFrontendName());
    }

    private function currentProduct(): ?Product
    {
        $product = $this->registry->registry('current_product');
        return $product instanceof Product && $product->getId() ? $product : null;
    }
}
