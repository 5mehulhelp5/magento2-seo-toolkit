<?php
declare(strict_types=1);

namespace Softcode\LumaSpeed\Model;

/**
 * Rewrites rendered Luma HTML so the browser fetches the LCP image first and
 * nothing non-critical blocks rendering. Pure string work, no Magento
 * dependencies, so it can be unit-tested and reused by any theme on Luma/Blank.
 */
class HtmlOptimizer
{
    private const LISTING_PAGES = ['catalog-category-view', 'catalogsearch-result-index'];
    private const CONTENT_PAGES = ['cms-index-index', 'cms-page-view'];

    /**
     * @param array{
     *     eagerListingImages?: int,
     *     prioritizeLcpImage?: bool,
     *     galleryImageSize?: array{0: int, 1: int}|null,
     *     lazyContentImages?: bool,
     *     eagerContentImages?: int,
     *     responsiveContentImages?: callable(string): ?array{srcset: string, width: int},
     *     deferStylesheets?: string[],
     *     keepFontPreloads?: string[]|null
     * } $options  responsiveContentImages maps an image URL to its srcset and original width (or null to leave it alone)
     */
    public function optimize(string $html, array $options): string
    {
        if (stripos($html, '<head') === false || stripos($html, '<body') === false) {
            return $html;
        }
        $bodyClasses = $this->bodyClasses($html);
        $preloadUrl = $preloadSrcset = null;

        if ($options['prioritizeLcpImage'] ?? true) {
            if (in_array('catalog-product-view', $bodyClasses, true)) {
                [$html, $preloadUrl] = $this->prioritizeGalleryImage($html, $options['galleryImageSize'] ?? null);
            } elseif (array_intersect(self::LISTING_PAGES, $bodyClasses)) {
                [$html, $preloadUrl] = $this->eagerListingImages($html, (int)($options['eagerListingImages'] ?? 4));
            } elseif (array_intersect(self::CONTENT_PAGES, $bodyClasses)) {
                [$html, $preloadUrl, $preloadSrcset] = $this->prioritizeContentImages(
                    $html,
                    (bool)($options['lazyContentImages'] ?? true),
                    max(1, (int)($options['eagerContentImages'] ?? 3)),
                    $options['responsiveContentImages'] ?? null
                );
            }
        }
        if ($preloadUrl !== null) {
            $html = $this->addImagePreload($html, $preloadUrl, $preloadSrcset);
        }
        foreach ($options['deferStylesheets'] ?? [] as $needle) {
            $html = $this->deferStylesheet($html, $needle);
        }
        if (isset($options['keepFontPreloads'])) {
            $html = $this->trimFontPreloads($html, $options['keepFontPreloads']);
        }
        return $html;
    }

    /** @return string[] */
    private function bodyClasses(string $html): array
    {
        if (!preg_match('/<body\b[^>]*\bclass="([^"]*)"/i', $html, $m)) {
            return [];
        }
        return preg_split('/\s+/', trim($m[1])) ?: [];
    }

    /**
     * PDP: Luma renders the main photo as a placeholder <img> that Fotorama replaces later.
     * It has no dimensions, so on mobile the product info below it jumps when it loads (CLS).
     * @param array{0: int, 1: int}|null $size
     * @return array{0: string, 1: ?string}
     */
    private function prioritizeGalleryImage(string $html, ?array $size): array
    {
        $url = null;
        $html = preg_replace_callback(
            '/<img\b[^>]*\bclass="gallery-placeholder__image"[^>]*>/i',
            function (array $m) use (&$url, $size): string {
                $url = $this->attr($m[0], 'src');
                $tag = $this->setPriority($m[0]);
                if ($size && $this->attr($tag, 'width') === null && $this->attr($tag, 'height') === null) {
                    $tag = substr($tag, 0, 4)
                        . sprintf(' width="%d" height="%d" style="height:auto"', $size[0], $size[1])
                        . substr($tag, 4);
                }
                return $tag;
            },
            $html,
            1
        );
        return [$html, $url];
    }

    /**
     * PLP/search: Luma lazy-loads every product image, including the first row on screen.
     * @return array{0: string, 1: ?string}
     */
    private function eagerListingImages(string $html, int $count): array
    {
        $url = null;
        $seen = 0;
        $html = preg_replace_callback(
            '/<img\b[^>]*\bclass="[^"]*\bproduct-image-photo\b[^"]*"[^>]*>/i',
            function (array $m) use (&$url, &$seen, $count): string {
                if ($seen++ >= $count) {
                    return $m[0];
                }
                if ($seen === 1) {
                    $url = $this->attr($m[0], 'src');
                    return $this->setPriority($m[0]);
                }
                return $this->removeAttr($m[0], 'loading');
            },
            $html
        );
        return [$html, $url];
    }

    /**
     * Home/CMS: the first content image is the hero and is prioritised. The next few stay eager because on
     * phones they are often on screen too (lazy-loading the second promo tile made it a late LCP element);
     * the rest are lazy. Content images are uploaded at full size, so they also get a srcset when available.
     * @return array{0: string, 1: ?string, 2: ?array} html, hero URL, hero srcset/sizes
     */
    private function prioritizeContentImages(string $html, bool $lazyRest, int $eagerCount, ?callable $srcsetFor): array
    {
        $main = stripos($html, 'id="maincontent"');
        if ($main === false) {
            return [$html, null, null];
        }
        $head = substr($html, 0, $main);
        $url = $heroResponsive = null;
        $seen = 0;
        $content = preg_replace_callback(
            '/<img\b[^>]*>/i',
            function (array $m) use (&$url, &$heroResponsive, &$seen, $lazyRest, $eagerCount, $srcsetFor): string {
                $tag = $m[0];
                if (preg_match('/\bproduct-image-photo\b/', $tag)) {
                    return $tag;
                }
                $seen++;
                $responsive = null;
                if ($srcsetFor !== null && $this->attr($tag, 'srcset') === null && $this->attr($tag, 'src') !== null) {
                    $variants = $srcsetFor(html_entity_decode((string)$this->attr($tag, 'src')));
                    if ($variants !== null) {
                        // Never render larger than the original did: sizes="100vw" alone enlarged promo tiles
                        // on mobile, which changed the layout and made a lazy-loaded tile the LCP element.
                        $responsive = [
                            'srcset' => htmlspecialchars($variants['srcset'], ENT_QUOTES),
                            'sizes' => sprintf('(max-width: %1$dpx) 100vw, %1$dpx', $variants['width']),
                        ];
                        $tag = substr($tag, 0, 4)
                            . sprintf(' srcset="%s" sizes="%s"', $responsive['srcset'], $responsive['sizes'])
                            . substr($tag, 4);
                    }
                }
                if ($url === null) {
                    $url = $this->attr($tag, 'src');
                    $heroResponsive = $responsive;
                    return $this->setPriority($tag);
                }
                if ($lazyRest && $seen > $eagerCount && $this->attr($tag, 'loading') === null) {
                    return substr($tag, 0, 4) . ' loading="lazy" decoding="async"' . substr($tag, 4);
                }
                return $tag;
            },
            substr($html, $main)
        );
        return [$head . $content, $url, $heroResponsive];
    }

    /**
     * @param array{srcset: string, sizes: string}|null $responsive already HTML-escaped
     */
    private function addImagePreload(string $html, string $url, ?array $responsive = null): string
    {
        $attributes = $responsive === null ? ''
            : sprintf(' imagesrcset="%s" imagesizes="%s"', $responsive['srcset'], $responsive['sizes']);
        $link = sprintf('<link rel="preload" as="image" href="%s"%s fetchpriority="high"/>', $url, $attributes);
        // After the viewport meta: a responsive preload read before it is resolved against the 980px
        // default viewport, so phones fetched a desktop-size variant on top of the one the <img> picked.
        if (preg_match('/<meta\b[^>]*\bname="viewport"[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            $at = $m[0][1] + strlen($m[0][0]);
            return substr($html, 0, $at) . "\n" . $link . substr($html, $at);
        }
        return preg_replace('/<head\b[^>]*>/i', '$0' . "\n" . $link, $html, 1);
    }

    /** Loads a stylesheet without blocking first render; keeps a <noscript> fallback. */
    private function deferStylesheet(string $html, string $needle): string
    {
        return preg_replace_callback(
            '/<link\b[^>]*\brel="stylesheet"[^>]*>/i',
            function (array $m) use ($needle): string {
                $tag = $m[0];
                if (strpos((string)$this->attr($tag, 'href'), $needle) === false) {
                    return $tag;
                }
                $deferred = $this->removeAttr($this->removeAttr($tag, 'media'), 'onload');
                $deferred = substr($deferred, 0, 5) . ' media="print" onload="this.media=\'all\'"' . substr($deferred, 5);
                return $deferred . '<noscript>' . $tag . '</noscript>';
            },
            $html
        );
    }

    /** Luma preloads five font files; they compete with the LCP image for bandwidth. */
    private function trimFontPreloads(string $html, array $keep): string
    {
        return preg_replace_callback(
            '/<link\b[^>]*\brel="preload"[^>]*\bas="font"[^>]*>\s*/i',
            function (array $m) use ($keep): string {
                $href = (string)$this->attr($m[0], 'href');
                foreach ($keep as $needle) {
                    if ($needle !== '' && strpos($href, $needle) !== false) {
                        return $m[0];
                    }
                }
                return '';
            },
            $html
        );
    }

    private function setPriority(string $tag): string
    {
        $tag = $this->removeAttr($this->removeAttr($tag, 'loading'), 'fetchpriority');
        return substr($tag, 0, 4) . ' fetchpriority="high"' . substr($tag, 4);
    }

    private function attr(string $tag, string $name): ?string
    {
        return preg_match('/\s' . $name . '="([^"]*)"/i', $tag, $m) ? $m[1] : null;
    }

    private function removeAttr(string $tag, string $name): string
    {
        return preg_replace('/\s' . $name . '="[^"]*"/i', '', $tag);
    }
}
