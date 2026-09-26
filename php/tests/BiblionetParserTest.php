<?php

declare(strict_types=1);

namespace BookMeta\Tests;

use BookMeta\BiblionetParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs the parser on saved biblionet pages. Each book_<id>.expected.json is
 * the shared contract that every port (PHP, Python, TypeScript) must match.
 */
final class BiblionetParserTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../contract/fixtures';

    /** @return array<string, array{string}> */
    public static function bookFixtures(): array
    {
        $cases = [];
        foreach (glob(self::FIXTURES . '/book_*.expected.json') as $expected) {
            $cases[basename($expected)] = [basename($expected, '.expected.json')];
        }
        return $cases;
    }

    #[DataProvider('bookFixtures')]
    public function testParsesBookPage(string $name): void
    {
        $expected = json_decode(file_get_contents(self::FIXTURES . "/{$name}.expected.json"), true, flags: JSON_THROW_ON_ERROR);
        $actual = BiblionetParser::parseBook(file_get_contents(self::FIXTURES . "/{$name}.html"));

        self::assertSame($expected, $actual);
    }

    public function testNonBookPageReturnsNull(): void
    {
        self::assertNull(BiblionetParser::parseBook(file_get_contents(self::FIXTURES . '/search_miss.html')));
    }

    public function testSearchResultsListBookPaths(): void
    {
        self::assertSame(
            ['/εισαγωγη-σε-δυο-θεωριες-της-κοινωνικης-ανθρωπολογιας-88309'],
            BiblionetParser::parseSearchResults(file_get_contents(self::FIXTURES . '/search_hit.html'))
        );
    }

    public function testSearchWithoutResultsIsEmpty(): void
    {
        self::assertSame([], BiblionetParser::parseSearchResults(file_get_contents(self::FIXTURES . '/search_miss.html')));
    }

    public function testNormalizeLabelStripsAccentsCaseAndColon(): void
    {
        self::assertSame('συγγραφεας', BiblionetParser::normalizeLabel(" Συγγραφέας: \n"));
        self::assertSame('γλωσσα πρωτοτυπου', BiblionetParser::normalizeLabel('Γλωσσα Πρωτοτυπου:'));
    }
}
