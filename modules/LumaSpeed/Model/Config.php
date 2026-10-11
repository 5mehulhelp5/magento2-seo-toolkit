<?php
declare(strict_types=1);

namespace Softcode\LumaSpeed\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\ConfigInterface as ViewConfig;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const PREFIX = 'softcode_lumaspeed/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ViewConfig $viewConfig
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX . 'general/enabled', ScopeInterface::SCOPE_STORE);
    }

    public function getOptimizerOptions(): array
    {
        return [
            'prioritizeLcpImage' => $this->flag('images/prioritize_lcp'),
            'galleryImageSize' => $this->galleryImageSize(),
            'eagerListingImages' => (int)$this->value('images/eager_listing_count'),
            'lazyContentImages' => $this->flag('images/lazy_content'),
            'eagerContentImages' => (int)$this->value('images/eager_content_count'),
            'deferStylesheets' => $this->lines('css/defer_stylesheets'),
            'keepFontPreloads' => $this->flag('fonts/trim_preloads') ? $this->lines('fonts/keep_preloads') : null,
            'menuAria' => $this->flag('a11y/menu_aria'),
            'inlineStylesheets' => $this->lines('css/inline_stylesheets'),
            'extraCss' => $this->value('css/extra_css'),
        ];
    }

    public function useUsedCss(): bool
    {
        return $this->flag('css/used_css');
    }

    public function useJsBundles(): bool
    {
        return $this->flag('js/bundles');
    }

    public function sizeImages(): bool
    {
        return $this->flag('images/size_images');
    }

    /**
     * Widths for WebP variants of CMS content images; empty when the feature is off.
     * @return int[]
     */
    public function getResponsiveWidths(): array
    {
        if (!$this->flag('images/responsive_content')) {
            return [];
        }
        return array_values(array_filter(array_map('intval', explode(',', $this->value('images/responsive_widths')))));
    }

    /**
     * Size of the PDP placeholder photo (Luma renders product_page_image_medium), from the active theme's view.xml.
     * @return array{0: int, 1: int}|null
     */
    private function galleryImageSize(): ?array
    {
        $attributes = $this->viewConfig->getViewConfig()
            ->getMediaAttributes('Magento_Catalog', 'images', 'product_page_image_medium');
        $width = (int)($attributes['width'] ?? 0);
        $height = (int)($attributes['height'] ?? $width);
        return $width > 0 && $height > 0 ? [$width, $height] : null;
    }

    private function flag(string $path): bool
    {
        return $this->scopeConfig->isSetFlag(self::PREFIX . $path, ScopeInterface::SCOPE_STORE);
    }

    private function value(string $path): string
    {
        return (string)$this->scopeConfig->getValue(self::PREFIX . $path, ScopeInterface::SCOPE_STORE);
    }

    /** @return string[] */
    private function lines(string $path): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', $this->value($path)) ?: [])));
    }
}
