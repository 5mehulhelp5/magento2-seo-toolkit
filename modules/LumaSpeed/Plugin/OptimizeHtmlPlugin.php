<?php
declare(strict_types=1);

namespace Softcode\LumaSpeed\Plugin;

use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Softcode\LumaSpeed\Model\Config;
use Softcode\LumaSpeed\Model\HtmlOptimizer;
use Softcode\LumaSpeed\Model\ResponsiveImages;

/**
 * Runs before Magento's full-page-cache plugin stores the page (see etc/frontend/di.xml),
 * so the optimised HTML is what gets cached and the cost is paid once per cache entry.
 */
class OptimizeHtmlPlugin
{
    public function __construct(
        private readonly Config $config,
        private readonly HtmlOptimizer $optimizer,
        private readonly ResponsiveImages $responsiveImages
    ) {
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
            $response->setBody($this->optimizer->optimize($body, $options));
        }
        return $result;
    }
}
