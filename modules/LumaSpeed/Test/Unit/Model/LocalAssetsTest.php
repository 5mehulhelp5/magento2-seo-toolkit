<?php
declare(strict_types=1);

namespace Softcode\LumaSpeed\Test\Unit\Model;

use Softcode\LumaSpeed\Model\LocalAssets;
use PHPUnit\Framework\TestCase;

class LocalAssetsTest extends TestCase
{
    /** @dataProvider relativeUrls */
    public function testRelativeCssUrlsResolveAgainstTheStylesheet(string $relative, string $expected): void
    {
        $base = 'https://shop.test:8443/static/version1/frontend/Magento/luma/en_US/css/';
        $this->assertSame($expected, LocalAssets::resolve($base, $relative));
    }

    public static function relativeUrls(): array
    {
        return [
            'parent dir' => ['../fonts/opensans/regular/opensans-400.woff2', 'https://shop.test:8443/static/version1/frontend/Magento/luma/en_US/fonts/opensans/regular/opensans-400.woff2'],
            'same dir' => ['./img.png', 'https://shop.test:8443/static/version1/frontend/Magento/luma/en_US/css/img.png'],
            'plain' => ['img.png', 'https://shop.test:8443/static/version1/frontend/Magento/luma/en_US/css/img.png'],
            'two up' => ['../../x.svg', 'https://shop.test:8443/static/version1/frontend/Magento/luma/x.svg'],
        ];
    }
}
