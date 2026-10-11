<?php
declare(strict_types=1);

namespace Softcode\Robots\Controller\Llms;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Softcode\Robots\Model\LlmsTxt;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly RawFactory $rawFactory,
        private readonly LlmsTxt $llmsTxt
    ) {
    }

    public function execute(): Raw
    {
        return $this->rawFactory->create()
            ->setHeader('Content-Type', 'text/plain; charset=utf-8', true)
            ->setHeader('Cache-Control', 'public, max-age=86400', true)
            ->setContents($this->llmsTxt->content());
    }
}
