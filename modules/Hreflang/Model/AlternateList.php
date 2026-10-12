<?php
declare(strict_types=1);

namespace Softcode\Hreflang\Model;

/**
 * Builds the final hreflang set from one candidate per store view, following Google's rules:
 *  - a store view where the page doesn't exist (no URL) is left out;
 *  - the current page must be in the set (self reference), and a set of one is no set at all;
 *  - every hreflang value appears once (two store views with the same code would contradict each other;
 *    the current store view wins, otherwise the first one);
 *  - x-default points to the chosen store view's URL, when that store view has the page.
 */
class AlternateList
{
    /**
     * @param array<int, array{store: string, code: ?string, url: ?string}> $candidates in store view sort order
     * @return array<int, array{hreflang: string, href: string}>
     */
    public function build(array $candidates, string $currentStore, ?string $xDefaultStore): array
    {
        $byCode = [];
        $current = null;
        foreach ($candidates as $candidate) {
            if (empty($candidate['url']) || empty($candidate['code'])) {
                continue;
            }
            $code = $candidate['code'];
            if ($candidate['store'] === $currentStore) {
                $current = $candidate;
                $byCode[$code] = $candidate;
            } elseif (!isset($byCode[$code])) {
                $byCode[$code] = $candidate;
            }
        }
        if ($current === null || count($byCode) < 2) {
            return [];
        }

        $out = [];
        foreach ($byCode as $code => $candidate) {
            $out[] = ['hreflang' => (string)$code, 'href' => (string)$candidate['url']];
        }
        foreach ($candidates as $candidate) {
            if ($xDefaultStore !== null && $candidate['store'] === $xDefaultStore && !empty($candidate['url'])) {
                $out[] = ['hreflang' => 'x-default', 'href' => (string)$candidate['url']];
                break;
            }
        }
        return $out;
    }
}
