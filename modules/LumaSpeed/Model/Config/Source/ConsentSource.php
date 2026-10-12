<?php
declare(strict_types=1);

namespace Softcode\LumaSpeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ConsentSource implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'generic', 'label' => __('Generic: window event "softcode:consent-granted" or window.softcodeConsentGranted()')],
            ['value' => 'magento', 'label' => __('Magento cookie restriction mode (Allow Cookies notice)')],
            ['value' => 'cookiebot', 'label' => __('Cookiebot (statistics or marketing consent)')],
            ['value' => 'cookieyes', 'label' => __('CookieYes (analytics or advertisement consent)')],
            ['value' => 'onetrust', 'label' => __('OneTrust (performance C0002 or targeting C0004 groups)')],
        ];
    }
}
