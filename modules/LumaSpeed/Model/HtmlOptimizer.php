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
     *     keepFontPreloads?: string[]|null,
     *     menuAria?: bool,
     *     dimensionsFor?: callable(string): ?array{0: int, 1: int},
     *     inlineStylesheets?: string[],
     *     cssFor?: callable(string): ?string,
     *     extraCss?: string,
     *     requireBundles?: array{prefix: string, pageTypes: array<string, string>, bundles: array<string, string[]>}|null,
     *     usedCss?: array{pageTypes: array<string, string>, cssFor: callable(string): ?string, defer: string[]}|null,
     *     gatedScripts?: array{consent: string[], interaction: string[], loader: string}|null
     * } $options  gatedScripts: third-party scripts (src or inline code containing a fragment) wait for consent or
     *             the first interaction; loader is the inline JS that starts them  usedCss: per page type, inline the CSS that type uses and load the full stylesheets without blocking  responsiveContentImages maps an image URL to its srcset and original width (or null to leave it alone);
     *             dimensionsFor maps an image URL to its natural [width, height]; cssFor returns a stylesheet's contents
     *             with relative url()s already made absolute
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
        if (!empty($options['usedCss'])) {
            $html = $this->applyUsedCss($html, $options['usedCss'], $bodyClasses);
        }
        if (isset($options['keepFontPreloads'])) {
            $html = $this->trimFontPreloads($html, $options['keepFontPreloads']);
        }
        if ($options['menuAria'] ?? false) {
            $html = $this->fixMenuAria($html);
        }
        if (isset($options['dimensionsFor'])) {
            $html = $this->sizeImages($html, $options['dimensionsFor']);
        }
        if (!empty($options['inlineStylesheets']) && isset($options['cssFor'])) {
            $html = $this->inlineStylesheets($html, $options['inlineStylesheets'], $options['cssFor']);
        }
        if (!empty($options['gatedScripts'])) {
            $html = $this->gateScripts($html, $options['gatedScripts']);
        }
        if (!empty($options['requireBundles'])) {
            $html = $this->addRequireBundles($html, $options['requireBundles'], $bodyClasses);
        }
        if (trim((string)($options['extraCss'] ?? '')) !== '') {
            $html = $this->insertBeforeHeadEnd($html, '<style data-softcode-lumaspeed>' . $this->safeCss($options['extraCss']) . '</style>');
        }
        return $html;
    }

    /**
     * jQuery UI turns the top-menu <ul>s into role="menu" and their links into role="menuitem", but leaves the
     * <li>s in between without a role. Screen readers (and Lighthouse: aria-required-children/-parent, listitem)
     * then see a menu with no items and list items outside a list. role="none" removes the <li>s from the tree.
     */
    private function fixMenuAria(string $html): string
    {
        return preg_replace_callback(
            '/<nav\b[^>]*\bclass="[^"]*\bnavigation\b[^"]*"[^>]*>.*?<\/nav>/is',
            fn (array $nav) => preg_replace_callback(
                '/<li\b[^>]*>/i',
                fn (array $li) => $this->attr($li[0], 'role') === null ? substr($li[0], 0, 3) . ' role="none"' . substr($li[0], 3) : $li[0],
                $nav[0]
            ),
            $html
        );
    }

    /**
     * Images without width/height can't reserve space before they load (layout shift, Lighthouse unsized-images).
     * Adds the natural size, or the missing one of the two in proportion. Luma's CSS (img {max-width: 100%;
     * height: auto}) keeps the rendered size; the attributes only give the browser the aspect ratio up front.
     */
    private function sizeImages(string $html, callable $dimensionsFor): string
    {
        return preg_replace_callback(
            '/<img\b[^>]*>/i',
            function (array $m) use ($dimensionsFor): string {
                $tag = $m[0];
                $width = $this->attr($tag, 'width');
                $height = $this->attr($tag, 'height');
                $src = $this->attr($tag, 'src');
                if (($width !== null && $height !== null) || $src === null || str_starts_with($src, 'data:')) {
                    return $tag;
                }
                $natural = $dimensionsFor(html_entity_decode($src));
                if (!$natural || $natural[0] <= 0 || $natural[1] <= 0) {
                    return $tag;
                }
                if ($width !== null && is_numeric($width)) {
                    $add = sprintf(' height="%d"', (int)round((float)$width * $natural[1] / $natural[0]));
                } elseif ($height !== null && is_numeric($height)) {
                    $add = sprintf(' width="%d"', (int)round((float)$height * $natural[0] / $natural[1]));
                } else {
                    $add = sprintf(' width="%d" height="%d"', $natural[0], $natural[1]);
                }
                return substr($tag, 0, 4) . $add . substr($tag, 4);
            },
            $html
        );
    }

    /**
     * Replaces <link rel="stylesheet"> tags matching one of the patterns with an inline <style>, so the first
     * render doesn't wait for a separate CSS request. Keeps the link's media query.
     * @param string[] $patterns
     */
    private function inlineStylesheets(string $html, array $patterns, callable $cssFor): string
    {
        return preg_replace_callback(
            '/<link\b[^>]*\brel="stylesheet"[^>]*>/i',
            function (array $m) use ($patterns, $cssFor): string {
                $tag = $m[0];
                $href = html_entity_decode((string)$this->attr($tag, 'href'));
                $media = $this->attr($tag, 'media');
                if ($href === '' || $media === 'print') {
                    return $tag;   // print-only and deferred (media="print" onload) stylesheets don't block render
                }
                foreach ($patterns as $pattern) {
                    if ($pattern !== '' && str_contains($href, $pattern)) {
                        $css = $cssFor($href);
                        if ($css === null) {
                            return $tag;
                        }
                        $mediaAttr = $media !== null && $media !== 'all' ? sprintf(' media="%s"', $media) : '';
                        return sprintf('<style data-href="%s"%s>%s</style>', htmlspecialchars($href, ENT_QUOTES), $mediaAttr, $this->safeCss($css));
                    }
                }
                return $tag;
            },
            $html
        );
    }

    /**
     * Luma loads 200+ RequireJS modules one file at a time, many only discovered after their parent arrived
     * (request chains). RequireJS's own "bundles" config maps module ids to a bundle file, so the first request for
     * any of them fetches the whole bundle once. Inserted right after require.js: requirejs-config.js starts
     * loading its deps as soon as it runs, so the bundle map has to be known before that.
     * @param array{prefix: string, pageTypes: array<string, string>, bundles: array<string, string[]>} $manifest
     * @param string[] $bodyClasses
     */
    private function addRequireBundles(string $html, array $manifest, array $bodyClasses): string
    {
        $config = [];
        if (!empty($manifest['bundles']['common'])) {
            $config[$manifest['prefix'] . 'common'] = $manifest['bundles']['common'];
        }
        foreach ($manifest['pageTypes'] ?? [] as $bodyClass => $type) {
            if (in_array($bodyClass, $bodyClasses, true) && !empty($manifest['bundles'][$type])) {
                $config[$manifest['prefix'] . $type] = $manifest['bundles'][$type];
                break;
            }
        }
        if (!$config) {
            return $html;
        }
        $json = str_replace('</', '<\/', (string)json_encode(['bundles' => $config], JSON_UNESCAPED_SLASHES));
        return (string)preg_replace_callback(
            '#<script\b[^>]*\bsrc="[^"]*/requirejs/require(?:\.min)?\.js"[^>]*>\s*</script>#i',
            fn (array $m) => $m[0] . "\n<script>require.config(" . $json . ');</script>',
            $html,
            1
        );
    }

    /**
     * Third-party scripts (tag managers, analytics, chat widgets) run as a few long tasks during page load; measured
     * on Luma they were most of the mobile total blocking time. Tracking scripts may not run before consent anyway
     * (GDPR), and widgets like chat aren't needed before the visitor does anything. Matching scripts become inert
     * (type="text/softcode-<when>") and the loader starts them in their original order once allowed.
     * Magento's own scripts and data scripts (x-magento-init, JSON) are never touched.
     * @param array{consent: string[], interaction: string[], loader: string} $gated
     */
    private function gateScripts(string $html, array $gated): string
    {
        $changed = false;
        $html = preg_replace_callback(
            '#<script\b([^>]*)>(.*?)</script>#is',
            function (array $m) use ($gated, &$changed): string {
                $attrs = $m[1];
                $type = strtolower((string)$this->attr('<script' . $attrs . '>', 'type'));
                if ($type !== '' && $type !== 'text/javascript' && $type !== 'module') {
                    return $m[0];   // x-magento-init, JSON, templates, already gated
                }
                $src = (string)$this->attr('<script' . $attrs . '>', 'src');
                $haystack = $src !== '' ? $src : $m[2];
                foreach (['consent', 'interaction'] as $when) {
                    foreach ($gated[$when] as $needle) {
                        if ($needle !== '' && str_contains($haystack, $needle)) {
                            $changed = true;
                            $attrs = preg_replace('/\s(type|src)="[^"]*"/i', '', $attrs);
                            $data = $src !== '' ? sprintf(' data-src="%s"', $src) : '';
                            if ($type === 'module') {
                                $data .= ' data-module="1"';
                            }
                            return sprintf('<script type="text/softcode-%s"%s%s>%s</script>', $when, $data, $attrs, $m[2]);
                        }
                    }
                }
                return $m[0];
            },
            $html
        );
        return $changed ? $this->insertBeforeHeadEnd($html, '<script data-softcode-loader>' . $gated['loader'] . '</script>') : $html;
    }

    /** CSS can't contain "</style" inside a <style> element; it only appears in comments or strings, so escape it. */
    private function safeCss(string $css): string
    {
        return preg_replace('#</(style)#i', '<\\\\/$1', $css);
    }

    private function insertBeforeHeadEnd(string $html, string $snippet): string
    {
        $at = stripos($html, '</head>');
        return $at === false ? $html : substr($html, 0, $at) . $snippet . "\n" . substr($html, $at);
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
                $media = $this->attr($tag, 'media') ?? 'all';
                if (strpos((string)$this->attr($tag, 'href'), $needle) === false || $media === 'print') {
                    return $tag;   // print stylesheets (and already-deferred ones) never block render
                }
                // restore the stylesheet's own media query once loaded: styles-l is "screen and (min-width: 768px)",
                // switching it to "all" would apply desktop layout on phones
                $restore = htmlspecialchars(addslashes(html_entity_decode($media)), ENT_QUOTES);
                $deferred = $this->removeAttr($this->removeAttr($tag, 'media'), 'onload');
                $deferred = substr($deferred, 0, 5) . ' media="print" onload="this.media=\'' . $restore . '\'"' . substr($deferred, 5);
                return $deferred . '<noscript>' . $tag . '</noscript>';
            },
            $html
        );
    }

    /**
     * Luma's render-blocking CSS is ~440 KB raw, of which a page uses a fraction. With a "used CSS" file for this
     * page type, that subset goes inline where the first stylesheet was (keeping cascade order) and the full
     * stylesheets load without blocking the first render.
     * @param array{pageTypes: array<string, string>, cssFor: callable(string): ?string, defer: string[]} $usedCss
     * @param string[] $bodyClasses
     */
    private function applyUsedCss(string $html, array $usedCss, array $bodyClasses): string
    {
        $type = null;
        foreach ($usedCss['pageTypes'] as $bodyClass => $pageType) {
            if (in_array($bodyClass, $bodyClasses, true)) {
                $type = $pageType;
                break;
            }
        }
        $css = $type === null ? null : ($usedCss['cssFor'])($type);
        if ($css === null || $css === '' || !preg_match('/<link\b[^>]*\brel="stylesheet"[^>]*>/i', $html, $first, PREG_OFFSET_CAPTURE)) {
            return $html;
        }
        $style = '<style data-softcode-used-css="' . htmlspecialchars($type, ENT_QUOTES) . '">' . $this->safeCss($css) . '</style>';
        $html = substr($html, 0, $first[0][1]) . $style . substr($html, $first[0][1]);
        foreach ($usedCss['defer'] as $needle) {
            $html = $this->deferStylesheet($html, $needle);
        }
        return $html;
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
