<?php

declare(strict_types=1);

namespace BookMeta\Tests;

use BookMeta\Isbn;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IsbnTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function validInputs(): array
    {
        return [
            'isbn-13' => ['9789600316483', '9789600316483'],
            'isbn-13 with hyphens' => ['978-960-03-1648-3', '9789600316483'],
            'isbn-10 converts to 13' => ['9600316481', '9789600316483'],
            'isbn-10 with hyphens and spaces' => [' 960-03-1648-1 ', '9789600316483'],
            'isbn-10 with X check digit' => ['080442957X', '9780804429573'],
            'isbn-10 with lowercase x' => ['080442957x', '9780804429573'],
            'isbn-13 with 0 check digit' => ['9780000000040', '9780000000040'],
        ];
    }

    #[DataProvider('validInputs')]
    public function testNormalizesValidIsbns(string $input, string $expected): void
    {
        self::assertSame($expected, Isbn::normalize($input));
    }

    /** @return array<string, array{string}> */
    public static function invalidInputs(): array
    {
        return [
            'empty' => [''],
            'wrong isbn-13 check digit' => ['9789600316484'],
            'wrong isbn-10 check digit' => ['9600316483'],
            'too short' => ['960031648'],
            'too long' => ['97896003164830'],
            'X inside isbn-13' => ['978960031648X'],
            'markup after a valid isbn' => ['9789600316483<script>'],
            'letters' => ['ISBN9789600316483'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function testRejectsInvalidIsbns(string $input): void
    {
        self::assertNull(Isbn::normalize($input));
    }
}
