<?php
declare(strict_types=1);

namespace Softcode\LumaSpeed\Test\Unit\Model;

use Softcode\LumaSpeed\Model\HtmlOptimizer;
use PHPUnit\Framework\TestCase;

class HtmlOptimizerTest extends TestCase
{
    private HtmlOptimizer $optimizer;

    protected function setUp(): void
    {
        $this->optimizer = new HtmlOptimizer();
    }

    private function page(string $bodyClass, string $main, string $head = ''): string
    {
        return "<!doctype html><html><head><meta charset=\"utf-8\"/>{$head}</head>"
            . "<body class=\"{$bodyClass} page-layout-1column\"><main id=\"maincontent\">{$main}</main></body></html>";
    }

    public function testPdpGalleryPlaceholderIsPreloadedWithHighPriority(): void
    {
        $html = $this->page('catalog-product-view', "<img\n alt=\"main product photo\"\n class=\"gallery-placeholder__image\"\n src=\"https://shop.test/media/bag.jpg\"\n/>");
        $out = $this->optimizer->optimize($html, []);

        $this->assertStringContainsString('<link rel="preload" as="image" href="https://shop.test/media/bag.jpg" fetchpriority="high"/>', $out);
        $this->assertMatchesRegularExpression('/<img fetchpriority="high"[^>]*gallery-placeholder__image/', $out);
    }

    public function testPdpGalleryPlaceholderGetsDimensionsToPreventLayoutShift(): void
    {
        $html = $this->page('catalog-product-view', '<img alt="main product photo" class="gallery-placeholder__image" src="/bag.jpg"/>');
        $out = $this->optimizer->optimize($html, ['galleryImageSize' => [700, 700]]);

        $this->assertStringContainsString('<img width="700" height="700" style="height:auto" fetchpriority="high"', $out);
    }

    public function testListingLoadsFirstImagesEagerlyAndKeepsTheRestLazy(): void
    {
        $img = fn (int $n) => "<img class=\"product-image-photo\" src=\"/p{$n}.jpg\" loading=\"lazy\" width=\"240\"/>";
        $html = $this->page('catalog-category-view', implode('', array_map($img, range(1, 6))));
        $out = $this->optimizer->optimize($html, ['eagerListingImages' => 4]);

        $this->assertStringContainsString('<img fetchpriority="high" class="product-image-photo" src="/p1.jpg" width="240"/>', $out);
        $this->assertStringContainsString('<img class="product-image-photo" src="/p4.jpg" width="240"/>', $out);
        $this->assertStringContainsString('src="/p5.jpg" loading="lazy"', $out);
        $this->assertStringContainsString('href="/p1.jpg"', $out);
    }

    public function testCmsHeroIsPrioritisedAndLaterImagesLazy(): void
    {
        $html = $this->page('cms-index-index', '<img src="/hero.jpg" alt=""/><img class="product-image-photo" src="/p.jpg" loading="lazy"/><img src="/promo.jpg" alt=""/>');
        $out = $this->optimizer->optimize($html, ['eagerContentImages' => 1]);

        $this->assertStringContainsString('<img fetchpriority="high" src="/hero.jpg"', $out);
        $this->assertStringContainsString('<img loading="lazy" decoding="async" src="/promo.jpg"', $out);
        $this->assertStringContainsString('src="/p.jpg" loading="lazy"', $out, 'product widget images are left alone');
    }

    public function testFirstContentImagesStayEagerByDefault(): void
    {
        $img = fn (int $n) => "<img src=\"/c{$n}.jpg\"/>";
        $out = $this->optimizer->optimize($this->page('cms-index-index', implode('', array_map($img, range(1, 5)))), []);

        $this->assertStringContainsString('<img src="/c2.jpg"/>', $out);
        $this->assertStringContainsString('<img src="/c3.jpg"/>', $out);
        $this->assertStringContainsString('<img loading="lazy" decoding="async" src="/c4.jpg"/>', $out);
    }

    public function testCmsImagesGetSrcsetCappedAtOriginalWidthAndHeroPreloadMatchesIt(): void
    {
        $html = $this->page('cms-index-index', '<img src="/media/hero.jpg" alt=""/><img src="/media/promo.jpg"/><img src="/media/icon.svg"/>');
        $srcsetFor = fn (string $url) => str_ends_with($url, '.svg') ? null
            : ['srcset' => "{$url}-480.webp 480w, {$url}-1920.webp 1920w", 'width' => str_contains($url, 'hero') ? 1920 : 417];
        $out = $this->optimizer->optimize($html, ['responsiveContentImages' => $srcsetFor, 'eagerContentImages' => 2]);

        $this->assertStringContainsString('<img fetchpriority="high" srcset="/media/hero.jpg-480.webp 480w, /media/hero.jpg-1920.webp 1920w" sizes="(max-width: 1920px) 100vw, 1920px" src="/media/hero.jpg"', $out);
        $this->assertStringContainsString('imagesrcset="/media/hero.jpg-480.webp 480w, /media/hero.jpg-1920.webp 1920w" imagesizes="(max-width: 1920px) 100vw, 1920px"', $out);
        $this->assertStringContainsString('sizes="(max-width: 417px) 100vw, 417px"', $out, 'promo never renders wider than its original');
        $this->assertStringContainsString('<img loading="lazy" decoding="async" src="/media/icon.svg"/>', $out, 'no srcset when none is available');
    }

    public function testImagePreloadComesAfterViewportMeta(): void
    {
        $html = $this->page('cms-index-index', '<img src="/hero.jpg"/>', '<meta name="viewport" content="width=device-width, initial-scale=1"/>');
        $out = $this->optimizer->optimize($html, []);

        $this->assertLessThan(strpos($out, 'rel="preload" as="image"'), strpos($out, 'name="viewport"'));
    }

    public function testHeaderLogoBeforeMainContentIsNotTreatedAsHero(): void
    {
        $html = preg_replace('/<body[^>]*>/', '$0<img src="/logo.svg"/>', $this->page('cms-index-index', '<img src="/hero.jpg"/>'));
        $out = $this->optimizer->optimize($html, []);

        $this->assertStringContainsString('<img src="/logo.svg"/>', $out);
        $this->assertStringContainsString('href="/hero.jpg"', $out);
    }

    public function testStylesheetIsDeferredWithNoscriptFallback(): void
    {
        $link = '<link  rel="stylesheet" type="text/css"  media="all" href="/static/mage/calendar.css" />';
        $out = $this->optimizer->optimize($this->page('cms-page-view', '', $link), ['deferStylesheets' => ['mage/calendar.css']]);

        $this->assertStringContainsString('media="print" onload="this.media=\'all\'"', $out);
        $this->assertStringContainsString('<noscript>' . $link . '</noscript>', $out);
    }

    public function testDeferredStylesheetGetsItsOwnMediaQueryBack(): void
    {
        $link = '<link  rel="stylesheet" type="text/css"  media="screen and (min-width: 768px)" href="/static/css/styles-l.min.css" />';
        $out = $this->optimizer->optimize($this->page('cms-page-view', '', $link), ['deferStylesheets' => ['css/styles-l']]);

        $this->assertStringContainsString('media="print" onload="this.media=\'screen and (min-width: 768px)\'"', $out);
    }

    public function testUsedCssIsInlinedBeforeTheFirstStylesheetWhichThenLoadsWithoutBlocking(): void
    {
        $head = '<link  rel="stylesheet" type="text/css"  media="all" href="/static/css/styles-m.min.css" />'
            . '<link  rel="stylesheet" type="text/css"  media="screen and (min-width: 768px)" href="/static/css/styles-l.min.css" />';
        $usedCss = ['pageTypes' => ['catalog-category-view' => 'plp'], 'cssFor' => fn (string $t) => $t === 'plp' ? '.a{b:c}' : null, 'defer' => ['css/styles-']];
        $out = $this->optimizer->optimize($this->page('catalog-category-view', '', $head), ['usedCss' => $usedCss, 'prioritizeLcpImage' => false]);

        $this->assertStringContainsString('<style data-softcode-used-css="plp">.a{b:c}</style><link media="print" onload="this.media=\'all\'"', $out);
        $this->assertStringContainsString('onload="this.media=\'screen and (min-width: 768px)\'"', $out);

        $other = $this->optimizer->optimize($this->page('cms-page-view', '', $head), ['usedCss' => $usedCss, 'prioritizeLcpImage' => false]);
        $this->assertStringNotContainsString('data-softcode-used-css', $other, 'page types without used CSS keep blocking stylesheets');
        $this->assertStringNotContainsString('media="print"', $other);
    }

    public function testDeferMatchesMinifiedStylesheet(): void
    {
        $link = '<link  rel="stylesheet" type="text/css"  media="all" href="/static/mage/calendar.min.css" />';
        $out = $this->optimizer->optimize($this->page('cms-page-view', '', $link), ['deferStylesheets' => ['mage/calendar.']]);

        $this->assertStringContainsString('<noscript>' . $link . '</noscript>', $out);
    }

    public function testOnlyEssentialFontPreloadsAreKept(): void
    {
        $font = fn (string $f) => "<link rel=\"preload\" as=\"font\" crossorigin=\"anonymous\" href=\"/fonts/{$f}\" />\n";
        $head = $font('opensans-300.woff2') . $font('opensans-400.woff2') . $font('Luma-Icons.woff2');
        $out = $this->optimizer->optimize($this->page('cms-page-view', '', $head), ['keepFontPreloads' => ['opensans-400.woff2', 'Luma-Icons.woff2']]);

        $this->assertStringNotContainsString('opensans-300', $out);
        $this->assertStringContainsString('opensans-400', $out);
        $this->assertStringContainsString('Luma-Icons', $out);
    }

    public function testMenuListItemsGetRoleNoneOnlyInsideNavigation(): void
    {
        $nav = '<nav class="navigation" data-action="navigation"><ul><li class="level0 nav-1"><a href="/a">A</a></li>'
            . '<li class="level0" role="presentation"><a href="/b">B</a></li></ul></nav>';
        $html = str_replace('<main', $nav . '<main', $this->page('cms-page-view', '<ul><li>content list</li></ul>'));
        $out = $this->optimizer->optimize($html, ['menuAria' => true]);

        $this->assertStringContainsString('<li role="none" class="level0 nav-1">', $out);
        $this->assertStringContainsString('<li class="level0" role="presentation">', $out, 'existing roles are kept');
        $this->assertStringContainsString('<ul><li>content list</li></ul>', $out, 'lists outside the menu are untouched');
    }

    public function testMissingImageDimensionsAreAddedInProportion(): void
    {
        $html = $this->page('cms-page-view', '<img src="/logo.svg" width="170" alt=""><img src="/photo.jpg"><img src="/sized.jpg" width="10" height="10"><img src="data:image/gif;base64,R0lG">');
        $dims = fn (string $url) => ['/logo.svg' => [189, 64], '/photo.jpg' => [800, 600], '/sized.jpg' => [1, 1]][$url] ?? null;
        $out = $this->optimizer->optimize($html, ['dimensionsFor' => $dims, 'prioritizeLcpImage' => false]);

        $this->assertStringContainsString('<img height="58" src="/logo.svg" width="170"', $out);
        $this->assertStringContainsString('<img width="800" height="600" src="/photo.jpg">', $out);
        $this->assertStringContainsString('<img src="/sized.jpg" width="10" height="10">', $out);
        $this->assertStringContainsString('<img src="data:image/gif;base64,R0lG">', $out);
    }

    public function testMatchingStylesheetIsInlinedWithItsMediaQuery(): void
    {
        $head = '<link  rel="stylesheet" type="text/css"  media="all" href="https://shop.test/static/version1/css/styles-m.min.css" />'
            . '<link  rel="stylesheet" type="text/css"  media="screen and (min-width: 768px)" href="https://shop.test/static/version1/css/styles-l.min.css" />'
            . '<link  rel="stylesheet" type="text/css"  media="print" href="https://shop.test/static/version1/css/print.min.css" />';
        $css = fn (string $url) => str_contains($url, 'styles-l') ? 'b{c:d}' : 'a{content:"</style>"}';
        $out = $this->optimizer->optimize($this->page('cms-page-view', '', $head), ['inlineStylesheets' => ['css/styles-'], 'cssFor' => $css]);

        $this->assertStringContainsString('<style data-href="https://shop.test/static/version1/css/styles-m.min.css">a{content:"<\/style>"}</style>', $out);
        $this->assertStringContainsString('media="screen and (min-width: 768px)">b{c:d}</style>', $out);
        $this->assertStringContainsString('print.min.css', $out, 'print stylesheets never block render, so they stay links');
        $this->assertStringNotContainsString('styles-m.min.css" />', $out);
    }

    public function testExtraCssIsAddedToTheHead(): void
    {
        $out = $this->optimizer->optimize($this->page('cms-page-view', ''), ['extraCss' => '.swatch-option.text{color:#666}']);

        $this->assertStringContainsString('<style data-softcode-lumaspeed>.swatch-option.text{color:#666}</style>' . "\n</head>", $out);
    }

    public function testRequireBundlesConfigGoesRightAfterRequireJsForThePageType(): void
    {
        $head = '<script  type="text/javascript"  src="https://shop.test/static/v1/requirejs/require.min.js"></script>'
            . '<script  type="text/javascript"  src="https://shop.test/static/v1/requirejs-config.min.js"></script>';
        $manifest = ['prefix' => 'Softcode_LumaSpeed/bundle/', 'pageTypes' => ['catalog-product-view' => 'pdp', 'cms-index-index' => 'home'],
            'bundles' => ['common' => ['jquery', 'text!Magento_Ui/t.html'], 'pdp' => ['Magento_Catalog/js/gallery'], 'home' => ['x']]];
        $out = $this->optimizer->optimize($this->page('catalog-product-view', '', $head), ['requireBundles' => $manifest, 'prioritizeLcpImage' => false]);

        $config = '<script>require.config({"bundles":{"Softcode_LumaSpeed/bundle/common":["jquery","text!Magento_Ui/t.html"],'
            . '"Softcode_LumaSpeed/bundle/pdp":["Magento_Catalog/js/gallery"]}});</script>';
        $this->assertStringContainsString('require.min.js"></script>' . "\n" . $config . '<script  type="text/javascript"  src="https://shop.test/static/v1/requirejs-config.min.js">', $out);
    }

    public function testThirdPartyScriptsAreGatedAndMagentoScriptsUntouched(): void
    {
        $head = '<script async src="https://www.googletagmanager.com/gtm.js?id=GTM-X"></script>'
            . '<script>window.addEventListener("load",function(){var s=document.createElement("script");s.src="https://widget.chat.test/chat.js";document.body.appendChild(s);});</script>'
            . '<script type="text/x-magento-init">{"*":{"chat.js":{}}}</script>'
            . '<script src="https://shop.test/static/requirejs/require.min.js"></script>';
        $gated = ['consent' => ['googletagmanager.com'], 'interaction' => ['chat.js'], 'loader' => 'LOADER();'];
        $out = $this->optimizer->optimize($this->page('cms-page-view', '', $head), ['gatedScripts' => $gated, 'prioritizeLcpImage' => false]);

        $this->assertStringContainsString('<script type="text/softcode-consent" data-src="https://www.googletagmanager.com/gtm.js?id=GTM-X" async></script>', $out);
        $this->assertStringContainsString('<script type="text/softcode-interaction">window.addEventListener("load"', $out);
        $this->assertStringContainsString('<script type="text/x-magento-init">{"*":{"chat.js":{}}}</script>', $out, 'data scripts are never gated');
        $this->assertStringContainsString('<script src="https://shop.test/static/requirejs/require.min.js"></script>', $out);
        $this->assertStringContainsString('<script data-softcode-loader>LOADER();</script>' . "\n</head>", $out);

        $none = $this->optimizer->optimize($this->page('cms-page-view', '', '<script src="/x.js"></script>'), ['gatedScripts' => $gated, 'prioritizeLcpImage' => false]);
        $this->assertStringNotContainsString('data-softcode-loader', $none, 'no loader when nothing was gated');
    }

    public function testNonHtmlResponsesAreUntouched(): void
    {
        $json = '{"img":"<img src=\"/x.jpg\">"}';
        $this->assertSame($json, $this->optimizer->optimize($json, ['keepFontPreloads' => []]));
    }
}
