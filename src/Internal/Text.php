<?php

declare(strict_types=1);

namespace CambodiaGeo\Internal;

/**
 * Text normalisation shared by lookups and search (see SPEC.md). Internal: not part of the public API.
 *
 * @internal
 */
final class Text
{
    // Whitespace, zero-width characters, hyphen, apostrophes, dot and comma are ignored in names.
    private const IGNORED = '/[\s\p{Z}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}\-\'\x{2019}.,]+/u';

    private const KHMER_DIGITS = [
        '០' => '0', '១' => '1', '២' => '2', '៣' => '3', '៤' => '4',
        '៥' => '5', '៦' => '6', '៧' => '7', '៨' => '8', '៩' => '9',
    ];

    private function __construct()
    {
    }

    /** Trims (Unicode) whitespace. Invalid UTF-8 gives an empty string. */
    public static function trim(string $text): string
    {
        return preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $text) ?? '';
    }

    /** Case-, space- and punctuation-insensitive form used for name matching. */
    public static function norm(string $text): string
    {
        if (class_exists(\Normalizer::class)) {
            $normalised = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if ($normalised !== false) {
                $text = $normalised;
            }
        }

        return mb_strtolower(preg_replace(self::IGNORED, '', $text) ?? '', 'UTF-8');
    }

    /** Canonical form of a code: '01', '1', ' 1 ' and '០១' all become '1'. */
    public static function codeKey(string $code): string
    {
        $code = self::trim($code);
        $length = mb_strlen($code, 'UTF-8');
        if ($length > 0 && $length <= 32 && preg_match('/^[0-9\x{17E0}-\x{17E9}]+\z/u', $code) === 1) {
            $digits = ltrim(strtr($code, self::KHMER_DIGITS), '0');

            return $digits === '' ? '0' : $digits;
        }

        return self::norm($code);
    }
}
