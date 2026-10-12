<?php
declare(strict_types=1);

namespace Softcode\LumaSpeed\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Softcode\LumaSpeed\Model\ScriptGateLoader;

class ScriptGateLoaderTest extends TestCase
{
    /** @dataProvider sources */
    public function testEachSourceBuildsASingleLineLoaderWithItsOwnHook(string $source, string $hook): void
    {
        $js = (new ScriptGateLoader())->build($source);

        $this->assertStringNotContainsString("\n", $js);
        $this->assertStringContainsString('text/softcode-', $js);
        $this->assertStringContainsString($hook, $js);
        $this->assertStringNotContainsString('%SOURCE%', $js);
    }

    public static function sources(): array
    {
        return [
            'generic' => ['generic', "addEventListener('softcode:consent-granted'"],
            'magento' => ['magento', 'user_allowed_save_cookie'],
            'cookiebot' => ['cookiebot', "'CookiebotOnAccept'"],
            'cookieyes' => ['cookieyes', "'cookieyes_consent_update'"],
            'onetrust' => ['onetrust', "'OneTrustGroupsUpdated'"],
        ];
    }

    public function testUnknownSourceFallsBackToGeneric(): void
    {
        $this->assertSame((new ScriptGateLoader())->build('generic'), (new ScriptGateLoader())->build('nope'));
    }
}
