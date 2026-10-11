<?php
declare(strict_types=1);

namespace Softcode\Meta\Model;

/**
 * Turns HTML or text into a meta description: plain text, whitespace collapsed, cut at a word boundary.
 * No Magento dependencies, so the text rules are unit-tested on their own.
 */
class Snippet
{
    public const MAX = 155;

    public function fromHtml(string $html, int $max = self::MAX): string
    {
        $html = preg_replace('#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', $html);
        $html = preg_replace('/\{\{[^}]*\}\}/', ' ', (string)$html);   // unrendered CMS directives
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', (string)$html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $this->cut(trim((string)preg_replace('/\s+/u', ' ', $text)), $max);
    }

    /** Replaces {{name}}-style placeholders; unknown placeholders are dropped. */
    public function template(string $template, array $values, int $max = self::MAX): string
    {
        $text = preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', fn (array $m) => (string)($values[$m[1]] ?? ''), $template);
        return $this->cut(trim((string)preg_replace('/\s+/u', ' ', (string)$text)), $max);
    }

    private function cut(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max - 1);
        $space = mb_strrpos($cut, ' ');
        return rtrim($space > $max * 0.6 ? mb_substr($cut, 0, $space) : $cut, " ,;:-") . '…';
    }
}
