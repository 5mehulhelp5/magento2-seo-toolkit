<?php
declare(strict_types=1);

namespace Softcode\Hreflang\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Softcode\Hreflang\Model\HreflangCode;

class HreflangCodeTest extends TestCase
{
    public function testLocaleBecomesLanguageAndRegion(): void
    {
        $code = new HreflangCode();
        $this->assertSame('en-US', $code->fromLocale('en_US'));
        $this->assertSame('da-DK', $code->fromLocale('da_DK'));
        $this->assertSame('zh-TW', $code->fromLocale('zh_Hant_TW'), 'script subtag dropped');
        $this->assertSame('es-419', $code->fromLocale('es_419'));
    }

    public function testLanguageOnlyFormat(): void
    {
        $code = new HreflangCode();
        $this->assertSame('en', $code->fromLocale('en_GB', HreflangCode::FORMAT_LANGUAGE));
        $this->assertSame('sr', $code->fromLocale('sr_Cyrl_RS', HreflangCode::FORMAT_LANGUAGE));
    }

    public function testUnusableLocaleGivesNull(): void
    {
        $this->assertNull((new HreflangCode())->fromLocale(''));
        $this->assertNull((new HreflangCode())->fromLocale('english'));
    }

    public function testOverrideIsNormalizedOrRejected(): void
    {
        $code = new HreflangCode();
        $this->assertSame('en-GB', $code->normalize(' en_gb '));
        $this->assertSame('zh-Hant-TW', $code->normalize('ZH-hant-tw'));
        $this->assertSame('es-419', $code->normalize('es-419'));
        $this->assertNull($code->normalize('x-default'));
        $this->assertNull($code->normalize('en-US"><script>'));
    }
}
