<?php
declare(strict_types=1);

namespace Softcode\Robots\Controller;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Softcode\Robots\Controller\Llms\Index as LlmsTxt;
use Softcode\Robots\Model\Config;

/** Serves /llms.txt the way Magento_Robots serves /robots.txt. */
class LlmsRouter implements RouterInterface
{
    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly Config $config
    ) {
    }

    public function match(RequestInterface $request): ?ActionInterface
    {
        if (trim((string)$request->getPathInfo(), '/') !== 'llms.txt' || !$this->config->isLlmsTxtEnabled()) {
            return null;
        }
        return $this->actionFactory->create(LlmsTxt::class);
    }
}
