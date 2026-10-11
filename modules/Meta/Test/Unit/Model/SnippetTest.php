<?php
declare(strict_types=1);

namespace Softcode\Meta\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Softcode\Meta\Model\Snippet;

class SnippetTest extends TestCase
{
    public function testHtmlBecomesPlainTextWithoutScriptsOrDirectives(): void
    {
        $html = '<div><h1>New Luma Yoga Collection</h1><p>Get fit &amp; look fab</p><script>var x = 1;</script>{{widget type="x"}}<style>.a{}</style></div>';

        $this->assertSame('New Luma Yoga Collection Get fit & look fab', (new Snippet())->fromHtml($html));
    }

    public function testLongTextIsCutAtAWordBoundary(): void
    {
        $text = str_repeat('word ', 60);
        $out = (new Snippet())->fromHtml($text);

        $this->assertLessThanOrEqual(Snippet::MAX, mb_strlen($out));
        $this->assertStringEndsWith('word…', $out);
    }

    public function testTemplateFillsKnownAndDropsUnknownPlaceholders(): void
    {
        $this->assertSame('Shop Jackets at Luma.', (new Snippet())->template('Shop {{name}} at {{store}}.{{nope}}', ['name' => 'Jackets', 'store' => 'Luma']));
    }
}
