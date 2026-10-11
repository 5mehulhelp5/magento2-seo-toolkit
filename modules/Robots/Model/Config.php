<?php
declare(strict_types=1);

namespace Softcode\Robots\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const PREFIX = 'softcode_robots/general/';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX . 'enabled', ScopeInterface::SCOPE_STORE);
    }

    public function getNoindexRobots(): string
    {
        return (string)$this->scopeConfig->getValue(self::PREFIX . 'noindex_value', ScopeInterface::SCOPE_STORE) ?: 'NOINDEX,FOLLOW';
    }

    /** @return string[] full action name prefixes, e.g. checkout_ */
    public function getNoindexActions(): array
    {
        return $this->lines('noindex_actions');
    }

    /** @return string[] request params that make a category listing a duplicate variant */
    public function getNoindexListingParams(): array
    {
        return $this->lines('noindex_listing_params');
    }

    public function isLlmsTxtEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag('softcode_robots/llms/enabled', ScopeInterface::SCOPE_STORE);
    }

    public function getLlmsTxtCustom(): string
    {
        return (string)$this->scopeConfig->getValue('softcode_robots/llms/custom', ScopeInterface::SCOPE_STORE);
    }

    /** @return string[] */
    private function lines(string $field): array
    {
        $value = (string)$this->scopeConfig->getValue(self::PREFIX . $field, ScopeInterface::SCOPE_STORE);
        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $value) ?: [])));
    }
}
