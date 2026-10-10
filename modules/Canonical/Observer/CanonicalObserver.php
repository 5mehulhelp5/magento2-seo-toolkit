<?php
declare(strict_types=1);

namespace Softcode\Canonical\Observer;

use Magento\Cms\Model\Page as CmsPage;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Store\Model\StoreManagerInterface;
use Softcode\Canonical\Model\Config;

/**
 * Fills the canonical gaps Magento leaves once its own catalog canonicals are switched on:
 *  - listing page 2+ points to itself (Magento points every page to page 1, which hides pages 2+ from Google);
 *  - the homepage and CMS pages get a canonical at all (Magento has none).
 * Filter/sort/limit variants keep Magento's canonical to the plain category URL.
 */
class CanonicalObserver implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly PageConfig $pageConfig,
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly CmsPage $cmsPage
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }
        switch ((string)$observer->getData('full_action_name')) {
            case 'catalog_category_view':
                $this->paginatedCategory();
                break;
            case 'cms_index_index':
                $this->setCanonical($this->storeManager->getStore()->getBaseUrl());
                break;
            case 'cms_page_view':
                if ($this->cmsPage->getId() && $this->cmsPage->getIdentifier()) {
                    $this->setCanonical($this->storeManager->getStore()->getBaseUrl() . $this->cmsPage->getIdentifier());
                }
                break;
        }
    }

    private function paginatedCategory(): void
    {
        $page = (int)$this->request->getParam('p', 1);
        $current = $this->currentCanonical();
        if ($page > 1 && $current !== null) {
            $this->setCanonical($current . (str_contains($current, '?') ? '&' : '?') . 'p=' . $page);
        }
    }

    private function currentCanonical(): ?string
    {
        foreach ($this->pageConfig->getAssetCollection()->getGroups() as $group) {
            if ($group->getProperty('content_type') === 'canonical') {
                foreach ($group->getAll() as $url => $asset) {
                    return (string)$url;
                }
            }
        }
        return null;
    }

    private function setCanonical(string $url): void
    {
        foreach ($this->pageConfig->getAssetCollection()->getGroups() as $group) {
            if ($group->getProperty('content_type') === 'canonical') {
                foreach (array_keys($group->getAll()) as $existing) {
                    $this->pageConfig->getAssetCollection()->remove($existing);
                }
            }
        }
        $this->pageConfig->addRemotePageAsset($url, 'canonical', ['attributes' => ['rel' => 'canonical']]);
    }
}
