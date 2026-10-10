<?php
declare(strict_types=1);

namespace Softcode\Robots\Observer;

use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Softcode\Robots\Model\Config;

/**
 * Vanilla Magento sends one global robots value (INDEX,FOLLOW) on every page, so layered-navigation
 * combinations, sort orders, search results, cart, checkout and account pages are all indexable.
 * This sets NOINDEX,FOLLOW on those: links are still followed, the pages stay out of the index.
 */
class RobotsObserver implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly PageConfig $pageConfig,
        private readonly RequestInterface $request,
        private readonly LayerResolver $layerResolver
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }
        $action = (string)$observer->getData('full_action_name');
        if ($this->isNoindexAction($action) || ($action === 'catalog_category_view' && $this->isListingVariant())) {
            $this->pageConfig->setRobots($this->config->getNoindexRobots());
        }
    }

    private function isNoindexAction(string $action): bool
    {
        foreach ($this->config->getNoindexActions() as $prefix) {
            if (str_starts_with($action, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /** A filtered, sorted or re-limited listing: duplicates of the category page. Pagination alone is not. */
    private function isListingVariant(): bool
    {
        foreach ($this->config->getNoindexListingParams() as $param) {
            if ($this->request->getParam($param) !== null) {
                return true;
            }
        }
        return (bool)$this->layerResolver->get()->getState()->getFilters();
    }
}
