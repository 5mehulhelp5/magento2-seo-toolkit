<?php
declare(strict_types=1);

namespace Softcode\Hreflang\Block;

use Magento\Cms\Model\Page as CmsPage;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Softcode\Hreflang\Model\AlternateList;
use Softcode\Hreflang\Model\Config;
use Softcode\Hreflang\Model\HreflangCode;
use Softcode\Hreflang\Model\PageUrls;

/**
 * <link rel="alternate" hreflang> tags in the head of the homepage, CMS pages, categories and products.
 * Filtered/sorted listings get none (they are NOINDEX and canonicalised away); page 2+ keeps its page number,
 * matching its self-referencing canonical.
 */
class Alternates extends AbstractBlock
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly Registry $registry,
        private readonly CmsPage $cmsPage,
        private readonly PageUrls $pageUrls,
        private readonly HreflangCode $hreflangCode,
        private readonly AlternateList $alternateList,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        /** @var Store $current */
        $current = $this->storeManager->getStore();
        if (!$this->config->isEnabled((int)$current->getId())) {
            return '';
        }
        $page = $this->currentPage();
        if ($page === null) {
            return '';
        }
        [$type, $entity, $suffix] = $page;

        $candidates = [];
        foreach ($this->stores($current) as $store) {
            $url = $this->pageUrls->forStore($store, $type, $entity);
            $candidates[] = [
                'store' => $store->getCode(),
                'code' => $this->code((int)$store->getId()),
                'url' => $url === null ? null : $url . $suffix,
            ];
        }

        $html = '';
        foreach ($this->alternateList->build($candidates, $current->getCode(), $this->xDefaultStore($current)) as $link) {
            $html .= sprintf(
                "<link rel=\"alternate\" hreflang=\"%s\" href=\"%s\">\n",
                $this->_escaper->escapeHtmlAttr($link['hreflang']),
                $this->_escaper->escapeUrl($link['href'])
            );
        }
        return $html;
    }

    /** @return array{0: string, 1: int|string, 2: string}|null type, entity, query suffix */
    private function currentPage(): ?array
    {
        switch ($this->getRequest()->getFullActionName()) {
            case 'cms_index_index':
                return [PageUrls::HOME, 0, ''];
            case 'cms_page_view':
                $identifier = (string)$this->cmsPage->getIdentifier();
                return $identifier === '' ? null : [PageUrls::CMS, $identifier, ''];
            case 'catalog_product_view':
                $product = $this->registry->registry('current_product');
                return $product && $product->getId() ? [PageUrls::PRODUCT, (int)$product->getId(), ''] : null;
            case 'catalog_category_view':
                $category = $this->registry->registry('current_category');
                if (!$category || !$category->getId()) {
                    return null;
                }
                $query = (array)$this->getRequest()->getQueryValue();
                $page = (int)($query['p'] ?? 1);
                unset($query['p']);
                if ($query !== []) {
                    return null;
                }
                return [PageUrls::CATEGORY, (int)$category->getId(), $page > 1 ? '?p=' . $page : ''];
        }
        return null;
    }

    /** @return Store[] active store views with the module enabled, in website/group/store sort order */
    private function stores(Store $current): array
    {
        $stores = [];
        foreach ($this->storeManager->getStores() as $store) {
            if (!$store->isActive() || !$this->config->isEnabled((int)$store->getId())) {
                continue;
            }
            if ($this->config->scope() === Config::SCOPE_WEBSITE && (int)$store->getWebsiteId() !== (int)$current->getWebsiteId()) {
                continue;
            }
            $stores[] = $store;
        }
        usort($stores, static fn (Store $a, Store $b) => [(int)$a->getWebsiteId(), (int)$a->getSortOrder(), (int)$a->getId()]
            <=> [(int)$b->getWebsiteId(), (int)$b->getSortOrder(), (int)$b->getId()]);
        return $stores;
    }

    private function code(int $storeId): ?string
    {
        $override = $this->config->codeOverride($storeId);
        if ($override !== '') {
            return $this->hreflangCode->normalize($override);
        }
        return $this->hreflangCode->fromLocale($this->config->locale($storeId), $this->config->format($storeId));
    }

    private function xDefaultStore(Store $current): ?string
    {
        $setting = $this->config->xDefault((int)$current->getId());
        if ($setting === Config::X_DEFAULT_NONE) {
            return null;
        }
        if ($setting !== Config::X_DEFAULT_WEBSITE) {
            return $setting;
        }
        $default = $this->storeManager->getWebsite($current->getWebsiteId())->getDefaultStore();
        return $default ? $default->getCode() : null;
    }
}
