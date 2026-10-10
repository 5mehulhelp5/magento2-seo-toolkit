<?php
declare(strict_types=1);

namespace Softcode\Canonical\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag('softcode_canonical/general/enabled', ScopeInterface::SCOPE_STORE);
    }

    /** Only when Magento itself is configured not to put categories in product URLs. */
    public function redirectCategoryProductUrls(): bool
    {
        return $this->isEnabled()
            && $this->scopeConfig->isSetFlag('softcode_canonical/general/redirect_category_product_urls', ScopeInterface::SCOPE_STORE)
            && !$this->scopeConfig->isSetFlag('catalog/seo/product_use_categories', ScopeInterface::SCOPE_STORE);
    }
}
