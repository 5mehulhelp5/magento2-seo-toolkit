<?php
declare(strict_types=1);

namespace Softcode\Hreflang\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Softcode\Hreflang\Model\Config;

class Scope implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::SCOPE_GLOBAL, 'label' => __('All websites')],
            ['value' => Config::SCOPE_WEBSITE, 'label' => __('Current website only')],
        ];
    }
}
