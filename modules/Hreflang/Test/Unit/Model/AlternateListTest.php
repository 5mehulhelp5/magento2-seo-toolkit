<?php
declare(strict_types=1);

namespace Softcode\Hreflang\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Softcode\Hreflang\Model\AlternateList;

class AlternateListTest extends TestCase
{
    private function candidate(string $store, ?string $code, ?string $url): array
    {
        return ['store' => $store, 'code' => $code, 'url' => $url];
    }

    public function testSelfReferenceAlternatesAndXDefault(): void
    {
        $links = (new AlternateList())->build([
            $this->candidate('default', 'en-US', 'https://shop.test/bag.html'),
            $this->candidate('da', 'da-DK', 'https://da.shop.test/bag.html'),
        ], 'da', 'default');

        $this->assertSame([
            ['hreflang' => 'en-US', 'href' => 'https://shop.test/bag.html'],
            ['hreflang' => 'da-DK', 'href' => 'https://da.shop.test/bag.html'],
            ['hreflang' => 'x-default', 'href' => 'https://shop.test/bag.html'],
        ], $links);
    }

    public function testStoreWithoutThePageIsLeftOut(): void
    {
        $links = (new AlternateList())->build([
            $this->candidate('default', 'en-US', 'https://shop.test/bag.html'),
            $this->candidate('da', 'da-DK', 'https://da.shop.test/bag.html'),
            $this->candidate('de', 'de-DE', null),
        ], 'default', null);

        $this->assertSame(['en-US', 'da-DK'], array_column($links, 'hreflang'));
    }

    public function testNothingWhenThePageExistsInOneStoreOnly(): void
    {
        $this->assertSame([], (new AlternateList())->build([
            $this->candidate('default', 'en-US', 'https://shop.test/bag.html'),
            $this->candidate('da', 'da-DK', null),
        ], 'default', 'default'));
    }

    public function testNothingWhenTheCurrentStoreIsNotInTheSet(): void
    {
        $this->assertSame([], (new AlternateList())->build([
            $this->candidate('default', 'en-US', 'https://shop.test/a'),
            $this->candidate('da', 'da-DK', 'https://da.shop.test/a'),
        ], 'b2b', 'default'));
    }

    public function testDuplicateCodeKeepsTheCurrentStore(): void
    {
        $links = (new AlternateList())->build([
            $this->candidate('default', 'en-US', 'https://shop.test/a'),
            $this->candidate('us2', 'en-US', 'https://us2.shop.test/a'),
            $this->candidate('da', 'da-DK', 'https://da.shop.test/a'),
        ], 'us2', null);

        $this->assertSame([
            ['hreflang' => 'en-US', 'href' => 'https://us2.shop.test/a'],
            ['hreflang' => 'da-DK', 'href' => 'https://da.shop.test/a'],
        ], $links);
    }

    public function testXDefaultSkippedWhenThatStoreLacksThePage(): void
    {
        $links = (new AlternateList())->build([
            $this->candidate('default', 'en-US', null),
            $this->candidate('da', 'da-DK', 'https://da.shop.test/a'),
            $this->candidate('de', 'de-DE', 'https://de.shop.test/a'),
        ], 'da', 'default');

        $this->assertNotContains('x-default', array_column($links, 'hreflang'));
    }
}
