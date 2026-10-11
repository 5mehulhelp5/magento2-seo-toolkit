<?php
declare(strict_types=1);

namespace Softcode\LumaSpeed\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Maps this store's media and static URLs to files under pub/, for the HTML optimizer: natural image sizes
 * and stylesheet contents. Only files that actually exist under pub/media or pub/static are ever read.
 */
class LocalAssets
{
    /** @var array<string, mixed> per-request memo: the same logo or stylesheet appears on every page */
    private array $memo = [];

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /** @return array{0: int, 1: int}|null natural width and height */
    public function dimensions(string $url): ?array
    {
        return $this->memo['dim:' . $url] ??= $this->readDimensions($url);
    }

    /** Raw contents of a local media/static file, e.g. the JS bundle manifest. */
    public function text(string $url): ?string
    {
        if (!array_key_exists('txt:' . $url, $this->memo)) {
            $file = $this->localFile($url);
            $this->memo['txt:' . $url] = $file === null ? null : (string)file_get_contents($file);
        }
        return $this->memo['txt:' . $url];
    }

    /** Stylesheet contents with relative url()s rewritten to absolute ones (they'd break once inlined). */
    public function css(string $url): ?string
    {
        return $this->memo['css:' . $url] ??= $this->readCss($url);
    }

    private function readDimensions(string $url): ?array
    {
        $file = $this->localFile($url);
        if ($file === null) {
            return null;
        }
        if (str_ends_with(strtolower($file), '.svg')) {
            return $this->svgDimensions((string)file_get_contents($file, false, null, 0, 4096));
        }
        $size = @getimagesize($file);
        return $size ? [(int)$size[0], (int)$size[1]] : null;
    }

    private function svgDimensions(string $head): ?array
    {
        if (!preg_match('/<svg\b[^>]*>/i', $head, $m)) {
            return null;
        }
        $num = fn (string $attr) => preg_match('/\s' . $attr . '="\s*([\d.]+)(px)?\s*"/i', $m[0], $v) ? (float)$v[1] : null;
        [$w, $h] = [$num('width'), $num('height')];
        if ((!$w || !$h) && preg_match('/\sviewBox="\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)\s*"/i', $m[0], $vb)) {
            [$w, $h] = [(float)$vb[1], (float)$vb[2]];
        }
        return $w && $h ? [(int)round($w), (int)round($h)] : null;
    }

    private function readCss(string $url): ?string
    {
        $file = $this->localFile($url);
        if ($file === null || !str_ends_with(strtolower($file), '.css')) {
            return null;
        }
        $css = (string)file_get_contents($file);
        $base = substr($url, 0, (int)strrpos(strtok($url, '?#'), '/') + 1);
        return preg_replace_callback(
            '/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i',
            fn (array $m) => preg_match('#^(data:|https?:|//|/|\#)#i', $m[2])
                ? $m[0]
                : 'url(' . $m[1] . self::resolve($base, $m[2]) . $m[1] . ')',
            $css
        );
    }

    /** Resolves a relative reference against a base URL ending in "/", collapsing ./ and ../ segments. */
    public static function resolve(string $base, string $relative): string
    {
        $parts = parse_url($base);
        $prefix = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $segments = explode('/', rtrim($parts['path'] ?? '/', '/') . '/' . $relative);
        $out = [];
        foreach ($segments as $i => $segment) {
            if ($segment === '..') {
                array_pop($out);
            } elseif ($segment !== '.' && ($segment !== '' || $i === 0)) {
                $out[] = $segment;
            }
        }
        return $prefix . implode('/', $out);
    }

    /** Absolute path of a store media/static URL, or null when it's not one or the file doesn't exist. */
    private function localFile(string $url): ?string
    {
        $store = $this->storeManager->getStore();
        $path = strtok($url, '?#');
        $map = [
            [$store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), DirectoryList::MEDIA],
            [$store->getBaseUrl(UrlInterface::URL_TYPE_STATIC), DirectoryList::STATIC_VIEW],
        ];
        foreach ($map as [$baseUrl, $dirCode]) {
            if (!str_starts_with($path, $baseUrl)) {
                continue;
            }
            $relative = rawurldecode(substr($path, strlen($baseUrl)));
            if ($dirCode === DirectoryList::STATIC_VIEW) {
                $relative = (string)preg_replace('#^version\d+/#', '', $relative);   // static file signing
            }
            if (str_contains($relative, '..')) {
                return null;
            }
            $file = $this->filesystem->getDirectoryRead($dirCode)->getAbsolutePath($relative);
            return is_file($file) ? $file : null;
        }
        return null;
    }
}
