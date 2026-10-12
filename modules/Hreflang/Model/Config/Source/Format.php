<?php
declare(strict_types=1);

namespace Softcode\Hreflang\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Softcode\Hreflang\Model\HreflangCode;

class Format implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => HreflangCode::FORMAT_LANGUAGE_REGION, 'label' => __('Language and region (en-US, da-DK)')],
            ['value' => HreflangCode::FORMAT_LANGUAGE, 'label' => __('Language only (en, da)')],
        ];
    }
}
