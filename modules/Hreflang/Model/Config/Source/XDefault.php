<?php
declare(strict_types=1);

namespace Softcode\Hreflang\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\StoreManagerInterface;
use Softcode\Hreflang\Model\Config;

class XDefault implements OptionSourceInterface
{
    public function __construct(private readonly StoreManagerInterface $storeManager)
    {
    }

    public function toOptionArray(): array
    {
        $options = [
            ['value' => Config::X_DEFAULT_WEBSITE, 'label' => __('Default store view of the website')],
            ['value' => Config::X_DEFAULT_NONE, 'label' => __('No x-default')],
        ];
        foreach ($this->storeManager->getStores() as $store) {
            $options[] = ['value' => $store->getCode(), 'label' => $store->getName() . ' (' . $store->getCode() . ')'];
        }
        return $options;
    }
}
