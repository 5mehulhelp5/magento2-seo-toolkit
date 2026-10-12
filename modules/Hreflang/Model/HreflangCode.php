<?php
declare(strict_types=1);

namespace Softcode\Hreflang\Model;

/**
 * Turns a Magento locale (en_US, da_DK, zh_Hant_TW) into an hreflang value (en-US / en, da-DK / da, zh-TW / zh).
 * Script subtags are dropped: Google documents hreflang as language plus optional region.
 */
class HreflangCode
{
    public const FORMAT_LANGUAGE_REGION = 'language_region';
    public const FORMAT_LANGUAGE = 'language';

    public function fromLocale(string $locale, string $format = self::FORMAT_LANGUAGE_REGION): ?string
    {
        $parts = preg_split('/[_-]/', trim($locale)) ?: [];
        $language = strtolower((string)array_shift($parts));
        if (!preg_match('/^[a-z]{2,3}$/', $language)) {
            return null;
        }
        if ($format === self::FORMAT_LANGUAGE) {
            return $language;
        }
        $region = null;
        foreach ($parts as $part) {
            if (preg_match('/^([A-Za-z]{2}|\d{3})$/', $part)) {
                $region = strtoupper($part);
            }
        }
        return $region === null ? $language : $language . '-' . $region;
    }

    /** A value typed in by hand (es-419, en-GB, x-default is not allowed here); null when it isn't usable. */
    public function normalize(string $code): ?string
    {
        $code = trim(str_replace('_', '-', $code));
        if (!preg_match('/^([a-zA-Z]{2,3})(?:-([a-zA-Z]{4}))?(?:-([a-zA-Z]{2}|\d{3}))?$/', $code, $m)) {
            return null;
        }
        $out = strtolower($m[1]);
        if (!empty($m[2])) {
            $out .= '-' . ucfirst(strtolower($m[2]));
        }
        if (!empty($m[3])) {
            $out .= '-' . strtoupper($m[3]);
        }
        return $out;
    }
}
