<?php

declare(strict_types=1);

namespace BookMeta;

/**
 * ISBN-10 / ISBN-13 validation and normalization.
 *
 * Biblionet only matches ISBN-13 in its search, so everything is
 * normalized to a bare 13-digit string.
 */
final class Isbn
{
    /**
     * Returns the ISBN-13 (digits only) for a valid ISBN-10 or ISBN-13,
     * or null if the input is not a valid ISBN. Only spaces and hyphens
     * are tolerated as separators; any other character makes it invalid.
     */
    public static function normalize(string $input): ?string
    {
        $isbn = strtoupper(str_replace([' ', '-'], '', trim($input)));

        if (preg_match('/^\d{9}[\dX]$/', $isbn) === 1) {
            return self::isValid10($isbn) ? self::tenToThirteen($isbn) : null;
        }
        if (preg_match('/^\d{13}$/', $isbn) === 1) {
            return self::isValid13($isbn) ? $isbn : null;
        }
        return null;
    }

    public static function isValid10(string $isbn): bool
    {
        if (preg_match('/^\d{9}[\dX]$/', $isbn) !== 1) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $digit = $isbn[$i] === 'X' ? 10 : (int) $isbn[$i];
            $sum += (10 - $i) * $digit;
        }
        return $sum % 11 === 0;
    }

    public static function isValid13(string $isbn): bool
    {
        if (preg_match('/^\d{13}$/', $isbn) !== 1) {
            return false;
        }
        return self::checkDigit13(substr($isbn, 0, 12)) === $isbn[12];
    }

    public static function tenToThirteen(string $isbn10): string
    {
        $body = '978' . substr($isbn10, 0, 9);
        return $body . self::checkDigit13($body);
    }

    private static function checkDigit13(string $first12): string
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $first12[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        return (string) ((10 - $sum % 10) % 10);
    }
}
