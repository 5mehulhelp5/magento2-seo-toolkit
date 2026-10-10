<?php
declare(strict_types=1);

namespace Softcode\StructuredData\Test\Unit\Model;

use Softcode\StructuredData\Model\SchemaBuilder;
use Softcode\StructuredData\Plugin\RemoveMicrodataPlugin;
use PHPUnit\Framework\TestCase;

class SchemaBuilderTest extends TestCase
{
    private SchemaBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new SchemaBuilder();
    }

    private function product(array $overrides = []): array
    {
        return $this->builder->product($overrides + [
            'name' => 'Joust Duffle Bag', 'url' => 'https://shop.test/joust-duffle-bag.html', 'sku' => '24-MB01',
            'description' => '<p>The sporty Joust Duffle Bag</p><ul><li>Dual handles.</li></ul>',
            'images' => ['https://shop.test/media/a.jpg', 'https://shop.test/media/a.jpg', 'https://shop.test/media/b.jpg'],
            'currency' => 'USD', 'price' => 34.0, 'inStock' => true,
        ]);
    }

    public function testSimpleProductHasOfferWithRequiredFields(): void
    {
        $p = $this->product();

        $this->assertSame('Product', $p['@type']);
        $this->assertSame(['@type' => 'Offer', 'price' => '34.00', 'priceCurrency' => 'USD',
            'availability' => 'https://schema.org/InStock', 'itemCondition' => 'https://schema.org/NewCondition',
            'url' => 'https://shop.test/joust-duffle-bag.html'], $p['offers']);
        $this->assertSame(['https://shop.test/media/a.jpg', 'https://shop.test/media/b.jpg'], $p['image']);
        $this->assertSame('The sporty Joust Duffle Bag Dual handles.', $p['description']);
        $this->assertArrayNotHasKey('aggregateRating', $p, 'no rating without reviews');
        $this->assertArrayNotHasKey('brand', $p);
    }

    public function testConfigurableProductWithPriceRangeIsAggregateOffer(): void
    {
        $p = $this->product(['price' => null, 'minPrice' => 45.0, 'maxPrice' => 52.5, 'offerCount' => 15, 'inStock' => false]);

        $this->assertSame('AggregateOffer', $p['offers']['@type']);
        $this->assertSame('45.00', $p['offers']['lowPrice']);
        $this->assertSame('52.50', $p['offers']['highPrice']);
        $this->assertSame(15, $p['offers']['offerCount']);
        $this->assertSame('https://schema.org/OutOfStock', $p['offers']['availability']);
    }

    public function testConfigurableProductWithOnePriceIsPlainOffer(): void
    {
        $p = $this->product(['price' => null, 'minPrice' => 45.0, 'maxPrice' => 45.0]);

        $this->assertSame('Offer', $p['offers']['@type']);
        $this->assertSame('45.00', $p['offers']['price']);
    }

    public function testRatingIsConvertedFromMagentoPercentToFiveStars(): void
    {
        $p = $this->product(['ratingPercent' => 80.0, 'reviewCount' => 3, 'brand' => 'Joust']);

        $this->assertSame(['@type' => 'AggregateRating', 'ratingValue' => 4.0, 'bestRating' => 5, 'worstRating' => 1, 'reviewCount' => 3], $p['aggregateRating']);
        $this->assertSame(['@type' => 'Brand', 'name' => 'Joust'], $p['brand']);
    }

    public function testBreadcrumbsArePositionedAndNeedTwoItems(): void
    {
        $this->assertNull($this->builder->breadcrumbs([['name' => 'Home', 'url' => '/']]));
        $b = $this->builder->breadcrumbs([['name' => 'Home', 'url' => '/'], ['name' => 'Gear', 'url' => '/gear.html'], ['name' => 'Bags', 'url' => '/gear/bags.html']]);

        $this->assertSame([1, 2, 3], array_column($b['itemListElement'], 'position'));
        $this->assertSame('/gear/bags.html', $b['itemListElement'][2]['item']);
    }

    public function testWebsiteHasSearchAction(): void
    {
        $w = $this->builder->website('Luma', 'https://shop.test/', 'https://shop.test/catalogsearch/result/?q={search_term_string}');

        $this->assertSame('https://shop.test/catalogsearch/result/?q={search_term_string}', $w['potentialAction']['target']['urlTemplate']);
        $this->assertSame('required name=search_term_string', $w['potentialAction']['query-input']);
    }

    public function testScriptCannotBreakOutOfScriptElement(): void
    {
        $script = $this->builder->toScript(['name' => 'Bad </script><script>alert(1)</script>', 'url' => 'https://shop.test/a?b=1&c=2']);

        $this->assertSame(1, substr_count(strtolower($script), '</script>'));
        $this->assertStringContainsString('"url":"https://shop.test/a?b=1' . chr(92) . 'u0026c=2"', $script, '& is escaped too');
        $this->assertSame('Bad </script><script>alert(1)</script>', json_decode(substr($script, 35, -9), true)['name']);
    }

    public function testMicrodataAttributesAreStrippedButContentKept(): void
    {
        $html = '<div class="product-info-main" itemscope itemtype="http://schema.org/Product"><span itemprop="name">Bag</span>'
            . '<meta itemprop="priceCurrency" content="USD" /><p>itemprop in text stays</p></div>';

        $this->assertSame(
            '<div class="product-info-main"><span>Bag</span><meta content="USD" /><p>itemprop in text stays</p></div>',
            RemoveMicrodataPlugin::stripMicrodata($html)
        );
    }
}
