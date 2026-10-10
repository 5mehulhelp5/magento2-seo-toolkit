<?php
declare(strict_types=1);

namespace Softcode\LumaSpeed\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Builds WebP width variants of CMS/WYSIWYG media images and returns a srcset for them.
 * Variants are written once to pub/media/softcode/rsz/{width}/{path}.webp and reused;
 * because this runs while the page is rendered for the full page cache, the cost is paid once per image.
 */
class ResponsiveImages
{
    private const TARGET_DIR = 'softcode/rsz';
    private const QUALITY = 80;

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param int[] $widths
     * @return array{srcset: string, width: int}|null srcset plus the original width (for sizes),
     *     or null when the image can't or needn't be varied
     */
    public function srcset(string $url, array $widths): ?array
    {
        try {
            $mediaBase = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
            $urlPath = strtok($url, '?#');
            if (!function_exists('imagewebp') || !str_starts_with($urlPath, $mediaBase)) {
                return null;
            }
            $urlRelative = substr($urlPath, strlen($mediaBase));
            $relative = rawurldecode($urlRelative);
            if (str_contains($relative, '..') || str_starts_with($relative, self::TARGET_DIR)
                || !preg_match('/\.(jpe?g|png)$/i', $relative)) {
                return null;
            }
            $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            $source = $media->getAbsolutePath($relative);
            $size = is_file($source) ? @getimagesize($source) : false;
            if (!$size) {
                return null;
            }
            $originalWidth = (int)$size[0];
            $variantWidths = array_filter(array_unique($widths), fn (int $w) => $w > 0 && $w < $originalWidth);
            $variantWidths[] = $originalWidth;
            sort($variantWidths);

            $entries = [];
            foreach ($variantWidths as $width) {
                $variant = sprintf('%s/%d/%s.webp', self::TARGET_DIR, $width, $relative);
                $target = $media->getAbsolutePath($variant);
                if (!is_file($target) || filemtime($target) < filemtime($source)) {
                    $media->create(dirname($variant));
                    $this->writeVariant($source, $target, $width);
                }
                $entries[] = sprintf('%s%s/%d/%s.webp %dw', $mediaBase, self::TARGET_DIR, $width, $urlRelative, $width);
            }
            return ['srcset' => implode(', ', $entries), 'width' => $originalWidth];
        } catch (\Throwable $e) {
            $this->logger->warning('Softcode_LumaSpeed: no responsive variants for ' . $url . ': ' . $e->getMessage());
            return null;
        }
    }

    private function writeVariant(string $source, string $target, int $width): void
    {
        $image = preg_match('/\.png$/i', $source) ? imagecreatefrompng($source) : imagecreatefromjpeg($source);
        if ($image === false) {
            throw new \RuntimeException('unreadable image');
        }
        imagepalettetotruecolor($image);
        $scaled = imagesx($image) === $width ? $image : imagescale($image, $width, -1, IMG_BICUBIC);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        if (!imagewebp($scaled, $target, self::QUALITY)) {
            throw new \RuntimeException('could not write ' . $target);
        }
    }
}
