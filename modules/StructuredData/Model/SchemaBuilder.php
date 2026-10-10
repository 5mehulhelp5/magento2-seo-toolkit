<?php
declare(strict_types=1);

namespace Softcode\StructuredData\Model;

/**
 * Turns plain shop data into schema.org JSON-LD arrays. No Magento dependencies, so the output
 * shape (which is what Google validates) is unit-tested independently of where the data comes from.
 */
class SchemaBuilder
{
    /**
     * @param array{
     *     name: string, url: string, sku?: ?string, description?: ?string, images?: string[], brand?: ?string,
     *     currency: string, price?: ?float, minPrice?: ?float, maxPrice?: ?float, offerCount?: ?int,
     *     inStock: bool, ratingPercent?: ?float, reviewCount?: ?int
     * } $p  ratingPercent is Magento's 0-100 rating summary; minPrice/maxPrice make it an AggregateOffer
     */
    public function product(array $p): array
    {
        $availability = 'https://schema.org/' . ($p['inStock'] ? 'InStock' : 'OutOfStock');
        if (isset($p['minPrice'], $p['maxPrice']) && $p['minPrice'] !== $p['maxPrice']) {
            $offers = array_filter([
                '@type' => 'AggregateOffer',
                'lowPrice' => $this->price($p['minPrice']),
                'highPrice' => $this->price($p['maxPrice']),
                'offerCount' => $p['offerCount'] ?? null,
                'priceCurrency' => $p['currency'],
                'availability' => $availability,
                'url' => $p['url'],
            ], fn ($v) => $v !== null);
        } else {
            $offers = [
                '@type' => 'Offer',
                'price' => $this->price($p['price'] ?? $p['minPrice'] ?? 0.0),
                'priceCurrency' => $p['currency'],
                'availability' => $availability,
                'itemCondition' => 'https://schema.org/NewCondition',
                'url' => $p['url'],
            ];
        }

        $product = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $p['name'],
            'url' => $p['url'],
            'sku' => $p['sku'] ?? null,
            'description' => $this->text($p['description'] ?? null, 5000),
            'image' => array_values(array_unique($p['images'] ?? [])) ?: null,
            'brand' => !empty($p['brand']) ? ['@type' => 'Brand', 'name' => $p['brand']] : null,
            'offers' => $offers,
        ];
        if (!empty($p['reviewCount']) && isset($p['ratingPercent']) && $p['ratingPercent'] > 0) {
            $product['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round($p['ratingPercent'] / 20, 1),
                'bestRating' => 5,
                'worstRating' => 1,
                'reviewCount' => (int)$p['reviewCount'],
            ];
        }
        return array_filter($product, fn ($v) => $v !== null && $v !== '');
    }

    /** @param array<array{name: string, url: string}> $items in order, starting with the homepage */
    public function breadcrumbs(array $items): ?array
    {
        if (count($items) < 2) {
            return null;
        }
        $list = [];
        foreach (array_values($items) as $i => $item) {
            $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $item['name'], 'item' => $item['url']];
        }
        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
    }

    public function organization(string $name, string $url, ?string $logo): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $name,
            'url' => $url,
            'logo' => $logo,
        ], fn ($v) => $v !== null && $v !== '');
    }

    public function website(string $name, string $url, string $searchUrlTemplate): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $name,
            'url' => $url,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $searchUrlTemplate],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /** Safe inside a <script> element: no "</script>" breakout, readable URLs and accents. */
    public function toScript(array $schema): string
    {
        $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
        return '<script type="application/ld+json">' . $json . '</script>';
    }

    private function price(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function text(?string $html, int $max): ?string
    {
        if ($html === null) {
            return null;
        }
        // space before every tag so "<p>a</p><p>b</p>" becomes "a b", not "ab"
        $plain = strip_tags(str_replace('<', ' <', $html));
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        return $text === '' ? null : mb_substr($text, 0, $max);
    }
}
