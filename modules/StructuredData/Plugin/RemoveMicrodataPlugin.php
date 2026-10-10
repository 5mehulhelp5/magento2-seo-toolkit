<?php
declare(strict_types=1);

namespace Softcode\StructuredData\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Store\Model\ScopeInterface;

/**
 * Luma marks up the product page with schema.org microdata (Product, Offer, AggregateRating). Next to this
 * module's JSON-LD that would describe the same product twice, so the PDP's microdata attributes are stripped.
 * Sorted below PageCache's BuiltinPlugin so the cached page is the stripped one.
 */
class RemoveMicrodataPlugin
{
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function afterRenderResult(ResultInterface $subject, $result, ResponseInterface $response)
    {
        if (!$subject instanceof Page || !$response instanceof HttpResponse
            || !$this->scopeConfig->isSetFlag('softcode_structureddata/general/enabled', ScopeInterface::SCOPE_STORE)
            || !$this->scopeConfig->isSetFlag('softcode_structureddata/product/remove_microdata', ScopeInterface::SCOPE_STORE)) {
            return $result;
        }
        $body = (string)$response->getBody();
        if (preg_match('/<body\b[^>]*\bclass="[^"]*\bcatalog-product-view\b/i', $body)) {
            $response->setBody(self::stripMicrodata($body));
        }
        return $result;
    }

    /** Removes itemscope/itemtype/itemprop attributes from tags; text content and JSON-LD are untouched. */
    public static function stripMicrodata(string $html): string
    {
        return preg_replace_callback(
            '/<[a-z][a-z0-9]*\b[^<>]*\b(?:itemscope|itemtype|itemprop)\b[^<>]*>/i',
            fn (array $m) => preg_replace('/\s+(?:itemscope(?:="[^"]*")?(?=[\s>\/])|itemtype="[^"]*"|itemprop="[^"]*")/i', '', $m[0]),
            $html
        );
    }
}
