<?php

declare(strict_types=1);

namespace BookMeta\Tests\Live;

use BookMeta\BiblionetClient;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Hits the real biblionet.gr. Excluded from the default run; use `make live-test`.
 * A failure here while the fixture tests pass means biblionet changed its markup.
 */
#[Group('live')]
final class LiveBiblionetTest extends TestCase
{
    public function testFindsKnownBook(): void
    {
        $book = (new BiblionetClient())->findByIsbn('9789600316483');

        self::assertNotNull($book);
        self::assertSame('9789600316483', $book['isbn']);
        self::assertSame('Louis Dumont', $book['authors']);
        self::assertSame('Εκδόσεις Καστανιώτη', $book['publisher']);
    }

    public function testUnknownIsbnReturnsNull(): void
    {
        self::assertNull((new BiblionetClient())->findByIsbn('9780000000002'));
    }
}
