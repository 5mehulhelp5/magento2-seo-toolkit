<?php
declare(strict_types=1);

namespace Softcode\Canonical\Observer;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Softcode\Canonical\Model\Config;

/**
 * Magento keeps serving /category/product.html with a 200 even when "Use Categories Path for Product URLs"
 * is off, so every product exists under several URLs. This 301s those requests to the product's own URL.
 * It only acts when that setting is off: shops that deliberately use category paths keep them.
 */
class ProductCategoryPathRedirect implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly ActionFlag $actionFlag
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var HttpRequest $request */
        $request = $observer->getData('request');
        $controller = $observer->getData('controller_action');
        if (!$this->config->redirectCategoryProductUrls() || !$request->getParam('category') || !$request->isGet()) {
            return;
        }
        try {
            $product = $this->productRepository->getById(
                (int)$request->getParam('id'),
                false,
                (int)$this->storeManager->getStore()->getId()
            );
        } catch (NoSuchEntityException) {
            return;
        }
        $target = $product->getUrlModel()->getUrl($product, ['_ignore_category' => true]);
        $targetPath = (string)parse_url($target, PHP_URL_PATH);
        if ($targetPath === '' || $targetPath === $request->getPathInfo()
            || $targetPath === $request->getOriginalPathInfo() || str_contains($targetPath, '/catalog/product/view')) {
            return;
        }
        $query = (string)parse_url((string)$request->getRequestUri(), PHP_URL_QUERY);
        /** @var HttpResponse $response */
        $response = $controller->getResponse();
        $response->setRedirect($target . ($query !== '' ? '?' . $query : ''), 301);
        $this->actionFlag->set('', ActionInterface::FLAG_NO_DISPATCH, true);
    }
}
