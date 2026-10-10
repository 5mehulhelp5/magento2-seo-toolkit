<?php
declare(strict_types=1);

namespace Softcode\Robots\Test\Unit\Observer;

use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Catalog\Model\Layer\State;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\View\Page\Config as PageConfig;
use Softcode\Robots\Model\Config;
use Softcode\Robots\Observer\RobotsObserver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RobotsObserverTest extends TestCase
{
    private PageConfig&MockObject $pageConfig;
    private RequestInterface&MockObject $request;
    private array $activeFilters = [];

    private function observer(): RobotsObserver
    {
        $config = $this->createMock(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('getNoindexRobots')->willReturn('NOINDEX,FOLLOW');
        $config->method('getNoindexActions')->willReturn(['checkout_', 'catalogsearch_']);
        $config->method('getNoindexListingParams')->willReturn(['product_list_order']);

        $state = $this->createMock(State::class);
        $state->method('getFilters')->willReturnCallback(fn () => $this->activeFilters);
        $layer = $this->createMock(Layer::class);
        $layer->method('getState')->willReturn($state);
        $resolver = $this->createMock(LayerResolver::class);
        $resolver->method('get')->willReturn($layer);

        return new RobotsObserver($config, $this->pageConfig, $this->request, $resolver);
    }

    protected function setUp(): void
    {
        $this->pageConfig = $this->createMock(PageConfig::class);
        $this->request = $this->createMock(RequestInterface::class);
    }

    private function event(string $action): Observer
    {
        return new Observer(['full_action_name' => $action]);
    }

    public function testExcludedPageTypesGetNoindex(): void
    {
        $this->pageConfig->expects($this->once())->method('setRobots')->with('NOINDEX,FOLLOW');
        $this->observer()->execute($this->event('checkout_cart_index'));
    }

    public function testPlainCategoryAndPaginationStayIndexable(): void
    {
        $this->request->method('getParam')->willReturnMap([['p', null, '2'], ['product_list_order', null, null]]);
        $this->pageConfig->expects($this->never())->method('setRobots');
        $this->observer()->execute($this->event('catalog_category_view'));
    }

    public function testSortedCategoryGetsNoindex(): void
    {
        $this->request->method('getParam')->willReturnMap([['product_list_order', null, 'price']]);
        $this->pageConfig->expects($this->once())->method('setRobots')->with('NOINDEX,FOLLOW');
        $this->observer()->execute($this->event('catalog_category_view'));
    }

    public function testFilteredCategoryGetsNoindex(): void
    {
        $this->activeFilters = ['price-filter'];
        $this->pageConfig->expects($this->once())->method('setRobots')->with('NOINDEX,FOLLOW');
        $this->observer()->execute($this->event('catalog_category_view'));
    }

    public function testProductPagesAreLeftAlone(): void
    {
        $this->pageConfig->expects($this->never())->method('setRobots');
        $this->observer()->execute($this->event('catalog_product_view'));
    }
}
