<?php
declare(strict_types=1);

namespace Softcode\LumaSpeed\Plugin;

use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Framework\View\Result\Page;
use Softcode\LumaSpeed\Model\Config;
use Softcode\LumaSpeed\Model\CspNonce;
use Softcode\LumaSpeed\Model\HtmlOptimizer;
use Softcode\LumaSpeed\Model\LocalAssets;
use Softcode\LumaSpeed\Model\ResponsiveImages;
use Softcode\LumaSpeed\Model\ScriptGateLoader;

/**
 * Runs before Magento's full-page-cache plugin stores the page (see etc/frontend/di.xml),
 * so the optimised HTML is what gets cached and the cost is paid once per cache entry.
 */
class OptimizeHtmlPlugin
{
    public function __construct(
        private readonly Config $config,
        private readonly HtmlOptimizer $optimizer,
        private readonly ResponsiveImages $responsiveImages,
        private readonly LocalAssets $localAssets,
        private readonly AssetRepository $assetRepository,
        private readonly ScriptGateLoader $scriptGateLoader,
        private readonly CspNonce $cspNonce
    ) {
    }

    /**
     * Used-CSS files written by the used-CSS builder into this theme's static folder (usedcss/manifest.json plus
     * usedcss/<type>.json), or null when there are none: then stylesheets keep loading as before.
     */
    private function usedCss(): ?array
    {
        $json = $this->localAssets->text($this->assetRepository->getUrl('Softcode_LumaSpeed::usedcss/manifest.json'));
        $manifest = $json ? json_decode($json, true) : null;
        if (!is_array($manifest) || empty($manifest['pageTypes']) || empty($manifest['defer'])) {
            return null;
        }
        return [
            'pageTypes' => $manifest['pageTypes'],
            'defer' => $manifest['defer'],
            'cssFor' => function (string $type): ?string {
                $file = $this->localAssets->text($this->assetRepository->getUrl('Softcode_LumaSpeed::usedcss/' . $type . '.json'));
                $data = $file ? json_decode($file, true) : null;
                return is_array($data) && is_string($data['css'] ?? null) ? $data['css'] : null;
            },
        ];
    }

    /**
     * The bundle manifest written by the bundle builder into this theme's static folder, or null when there is
     * none (e.g. right after a static content deploy): then pages keep loading modules one by one.
     */
    private function bundleManifest(): ?array
    {
        $json = $this->localAssets->text($this->assetRepository->getUrl('Softcode_LumaSpeed::bundle/manifest.json'));
        $manifest = $json ? json_decode($json, true) : null;
        return is_array($manifest) && isset($manifest['bundles'], $manifest['prefix']) ? $manifest : null;
    }

    public function afterRenderResult(ResultInterface $subject, $result, ResponseInterface $response)
    {
        if (!$subject instanceof Page || !$response instanceof HttpResponse || !$this->config->isEnabled()) {
            return $result;
        }
        $body = (string)$response->getBody();
        if ($body !== '') {
            $options = $this->config->getOptimizerOptions();
            $widths = $this->config->getResponsiveWidths();
            if ($widths) {
                $options['responsiveContentImages'] = fn (string $url) => $this->responsiveImages->srcset($url, $widths);
            }
            if ($this->config->sizeImages()) {
                $options['dimensionsFor'] = fn (string $url) => $this->localAssets->dimensions($url);
            }
            if ($options['inlineStylesheets']) {
                $options['cssFor'] = fn (string $url) => $this->localAssets->css($url);
            }
            if ($this->config->useJsBundles()) {
                $options['requireBundles'] = $this->bundleManifest();
            }
            if ($this->config->useUsedCss()) {
                $options['usedCss'] = $this->usedCss();
            }
            // Only uncached pages (checkout, cart, account) get a nonce: Magento enforces its CSP there, and a nonce
            // stored in the full page cache would no longer match the policy of later responses.
            if (!$subject->getLayout()->isCacheable()) {
                $options['nonce'] = $this->cspNonce->get();
            }
            $gating = $this->config->scriptGating();
            if ($gating !== null) {
                $options['gatedScripts'] = [
                    'consent' => $gating['consent'],
                    'interaction' => $gating['interaction'],
                    'loader' => $this->scriptGateLoader->build($gating['source']),
                ];
            }
            $response->setBody($this->optimizer->optimize($body, $options));
        }
        return $result;
    }
}
