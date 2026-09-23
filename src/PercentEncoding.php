<?php

declare(strict_types=1);

namespace Dirthara\Http;

use function ord;
use function strlen;
use function substr;
use function sprintf;
use function ctype_xdigit;
use function str_contains;

/**
 * RFC 3986 percent-encoding for the components of a URI.
 *
 * @internal
 */
final readonly class PercentEncoding
{
    /**
     * Encodes every character that is neither unreserved nor in $extraAllowed. An existing %XX triplet is kept as it is,
     * so encoding a value twice does not change it.
     */
    public static function encode(string $value, string $extraAllowed): string
    {
        $encoded = '';
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $character = $value[$i];

            if (
                $character === '%'
                && ($i + 2) < $length
                && ctype_xdigit($value[$i + 1])
                && ctype_xdigit($value[$i + 2])
            ) {
                $encoded .= substr($value, $i, length: 3);
                $i += 2;

                continue;
            }

            if (self::isUnreserved($character) || str_contains($extraAllowed, $character)) {
                $encoded .= $character;

                continue;
            }

            $encoded .= sprintf('%%%02X', ord($character));
        }

        return $encoded;
    }

    private static function isUnreserved(string $character): bool
    {
        $ordinal = ord($character);

        return $ordinal >= 65 && $ordinal <= 90
        || $ordinal >= 97 && $ordinal <= 122
        || $ordinal >= 48 && $ordinal <= 57
        || str_contains('-._~', $character);
    }
}
