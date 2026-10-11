<?php
declare(strict_types=1);

namespace Softcode\Meta\Observer;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Softcode\Meta\Model\Snippet;

/**
 * Vanilla Magento leaves the meta description empty on the homepage, CMS pages and categories unless someone
 * types one in, and its own fallback (Content > Design > HTML Head > Default Description) is empty too.
 * Search engines then write their own snippet; Lighthouse fails the page. This fills only empty descriptions:
 *  - categories: the category description, else a template ("Shop {{name}} at {{store}}.");
 *  - CMS pages: the page's own text;
 *  - homepage: its text if it has any (often it's all widgets), else a template with the top menu categories;
 *  - any other page: a template from its title.
 * Runs on layout_render_before: by then every controller and block has set its title and description.
 */
class MetaDescriptionFallback implements ObserverInterface
{
    private const PREFIX = 'softcode_meta/description/';
    private const MIN_TEXT = 50;

    public function __construct(
        private readonly PageConfig $pageConfig,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly Registry $registry,
        private readonly CmsPage $cmsPage,
        private readonly FilterProvider $filterProvider,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly HttpRequest $request,
        private readonly Snippet $snippet,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->scopeConfig->isSetFlag(self::PREFIX . 'enabled', ScopeInterface::SCOPE_STORE)
            || trim((string)$this->pageConfig->getDescription()) !== '') {
            return;   // getDescription() already includes Magento's own default description
        }
        try {
            $description = match ($this->request->getFullActionName()) {
                'catalog_category_view' => $this->category(),
                'cms_index_index' => $this->cmsText() ?? $this->home(),
                'cms_page_view' => $this->cmsText() ?? $this->generic(),
                default => $this->generic(),
            };
        } catch (\Throwable $e) {
            $this->logger->warning('Softcode_Meta: no fallback description: ' . $e->getMessage());
            return;
        }
        if ($description !== '') {
            $this->pageConfig->setDescription($description);
        }
    }

    private function category(): string
    {
        $category = $this->registry->registry('current_category');
        if (!$category) {
            return $this->generic();
        }
        $text = $this->snippet->fromHtml((string)$category->getDescription());
        return $text !== '' ? $text : $this->fromTemplate('category_template', ['name' => $category->getName()]);
    }

    private function cmsText(): ?string
    {
        if (!$this->cmsPage->getId()) {
            return null;
        }
        $html = $this->filterProvider->getPageFilter()->filter((string)$this->cmsPage->getContent());
        $text = $this->snippet->fromHtml($html);
        return mb_strlen($text) >= self::MIN_TEXT ? $text : null;
    }

    private function home(): string
    {
        $store = $this->storeManager->getStore();
        $names = $this->categoryCollectionFactory->create()
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('is_active', 1)
            ->addAttributeToFilter('include_in_menu', 1)
            ->addAttributeToFilter('level', 2)
            ->addPathFilter('^1/' . (int)$store->getRootCategoryId() . '/')
            ->setOrder('position', 'ASC')
            ->setPageSize(8)
            ->getColumnValues('name');
        $list = count($names) > 1 ? implode(', ', array_slice($names, 0, -1)) . ' and ' . end($names) : implode('', $names);
        return $this->fromTemplate('home_template', ['categories' => $list]);
    }

    private function generic(): string
    {
        $title = trim(html_entity_decode((string)$this->pageConfig->getTitle()->get(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return $title === '' ? $this->storeName() : $this->fromTemplate('generic_template', ['title' => $title]);
    }

    private function fromTemplate(string $field, array $values): string
    {
        return $this->snippet->template(
            (string)$this->scopeConfig->getValue(self::PREFIX . $field, ScopeInterface::SCOPE_STORE),
            $values + ['store' => $this->storeName()]
        );
    }

    private function storeName(): string
    {
        return (string)($this->scopeConfig->getValue('general/store_information/name', ScopeInterface::SCOPE_STORE)
            ?: $this->storeManager->getStore()->getFrontendName());
    }
}
