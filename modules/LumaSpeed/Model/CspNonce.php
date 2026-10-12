<?php
declare(strict_types=1);

namespace Softcode\LumaSpeed\Model;

use Magento\Framework\App\ObjectManager;

/**
 * A CSP nonce for the inline scripts this module adds. Magento enforces its Content Security Policy on checkout
 * (nonces and hashes only, no 'unsafe-inline'), so without one those scripts are blocked there.
 * Magento's CspNonceProvider (Magento_Csp, 2.4.7 and recent 2.4.6 patches) both creates the nonce and adds it to
 * the page's policy; on versions without it this returns null and nothing changes.
 */
class CspNonce
{
    private const PROVIDER = 'Magento\Csp\Helper\CspNonceProvider';

    private ?string $nonce = null;

    public function get(): ?string
    {
        if ($this->nonce === null && class_exists(self::PROVIDER)) {
            try {
                // looked up lazily: a constructor dependency would break installs without the class
                $this->nonce = (string)ObjectManager::getInstance()->get(self::PROVIDER)->generateNonce();
            } catch (\Throwable) {
                $this->nonce = '';
            }
        }
        return $this->nonce ?: null;
    }
}
