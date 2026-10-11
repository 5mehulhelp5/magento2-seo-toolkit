<?php
declare(strict_types=1);

namespace Softcode\Robots\Model;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sitemap\Model\ResourceModel\Sitemap\CollectionFactory as SitemapCollectionFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds /llms.txt (https://llmstxt.org): a short markdown map of the shop for AI agents, from data the store
 * already has. Text saved in the admin replaces the generated file.
 */
class LlmsTxt
{
    private const SKIP_PAGES = ['no-route', 'enable-cookies', 'privacy-policy-cookie-restriction-mode'];
    private const MAX_CATEGORIES = 200;

    public function __construct(
        private readonly Config $config,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly PageCollectionFactory $pageCollectionFactory,
        private readonly SitemapCollectionFactory $sitemapCollectionFactory
    ) {
    }

    public function content(): string
    {
        $custom = trim($this->config->getLlmsTxtCustom());
        if ($custom !== '') {
            return $custom . "\n";
        }
        $store = $this->storeManager->getStore();
        $name = (string)($this->scopeConfig->getValue('general/store_information/name', ScopeInterface::SCOPE_STORE) ?: $store->getFrontendName());
        $description = trim((string)$this->scopeConfig->getValue('design/head/default_description', ScopeInterface::SCOPE_STORE));

        $out = ["# {$name}", ''];
        if ($description !== '') {
            $out[] = '> ' . preg_replace('/\s+/', ' ', $description);
            $out[] = '';
        }
        $out[] = "Online shop at {$store->getBaseUrl()}";
        $out[] = '';
        $sections = ['Categories' => $this->categories((int)$store->getRootCategoryId()), 'Information' => $this->pages((int)$store->getId())];
        $sitemaps = $this->sitemaps((int)$store->getId());
        if ($sitemaps) {
            $sections['Optional'] = $sitemaps;
        }
        foreach ($sections as $title => $links) {
            if ($links) {
                $out[] = "## {$title}";
                $out[] = '';
                array_push($out, ...$links);
                $out[] = '';
            }
        }
        return implode("\n", $out);
    }

    /** @return string[] markdown list items, top-level categories with their children indented */
    private function categories(int $rootId): array
    {
        $collection = $this->categoryCollectionFactory->create()
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('is_active', 1)
            ->addAttributeToFilter('include_in_menu', 1)
            ->addAttributeToFilter('level', ['in' => [2, 3]])
            ->addPathFilter('^1/' . $rootId . '/')
            ->addUrlRewriteToResult()
            ->setOrder('path', 'ASC')
            ->setPageSize(self::MAX_CATEGORIES);
        $lines = [];
        foreach ($collection as $category) {
            $indent = (int)$category->getLevel() === 3 ? '  ' : '';
            $lines[] = sprintf('%s- [%s](%s)', $indent, $this->md((string)$category->getName()), $category->getUrl());
        }
        return $lines;
    }

    /** @return string[] */
    private function pages(int $storeId): array
    {
        $home = (string)$this->scopeConfig->getValue('web/default/cms_home_page', ScopeInterface::SCOPE_STORE);
        $collection = $this->pageCollectionFactory->create()
            ->addStoreFilter($storeId)
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('identifier', ['nin' => array_merge(self::SKIP_PAGES, [$home])]);
        $base = $this->storeManager->getStore()->getBaseUrl();
        $lines = [];
        foreach ($collection as $page) {
            $lines[] = sprintf('- [%s](%s%s)', $this->md((string)$page->getTitle()), $base, $page->getIdentifier());
        }
        return $lines;
    }

    /** @return string[] */
    private function sitemaps(int $storeId): array
    {
        $base = $this->storeManager->getStore()->getBaseUrl();
        $lines = [];
        foreach ($this->sitemapCollectionFactory->create()->addStoreFilter([$storeId]) as $sitemap) {
            $lines[] = sprintf('- [XML sitemap](%s%s)', rtrim($base, '/'), $sitemap->getSitemapPath() . $sitemap->getSitemapFilename());
        }
        return $lines;
    }

    private function md(string $text): string
    {
        return str_replace(['[', ']'], ['(', ')'], trim($text));
    }
}
