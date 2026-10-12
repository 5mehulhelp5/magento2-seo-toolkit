<?php
declare(strict_types=1);

namespace Softcode\Hreflang\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const SCOPE_GLOBAL = 'global';
    public const SCOPE_WEBSITE = 'website';
    public const X_DEFAULT_WEBSITE = '';
    public const X_DEFAULT_NONE = 'none';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function isEnabled(int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag('softcode_hreflang/general/enabled', ScopeInterface::SCOPE_STORE, $storeId);
    }

    /** Link store views of all websites (one website per country) or only within the current website. */
    public function scope(): string
    {
        return (string)$this->scopeConfig->getValue('softcode_hreflang/general/scope') === self::SCOPE_WEBSITE
            ? self::SCOPE_WEBSITE
            : self::SCOPE_GLOBAL;
    }

    public function format(int $storeId): string
    {
        return (string)$this->scopeConfig->getValue('softcode_hreflang/general/format', ScopeInterface::SCOPE_STORE, $storeId)
            === HreflangCode::FORMAT_LANGUAGE ? HreflangCode::FORMAT_LANGUAGE : HreflangCode::FORMAT_LANGUAGE_REGION;
    }

    /** Hand-typed hreflang value for a store view (es-419, en-GB); empty means derive it from the locale. */
    public function codeOverride(int $storeId): string
    {
        return trim((string)$this->scopeConfig->getValue('softcode_hreflang/general/code', ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function locale(int $storeId): string
    {
        return (string)$this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $storeId);
    }

    /** '' = the default store view of the current website, 'none', or a store view code. */
    public function xDefault(int $storeId): string
    {
        return trim((string)$this->scopeConfig->getValue('softcode_hreflang/general/x_default', ScopeInterface::SCOPE_STORE, $storeId));
    }
}
