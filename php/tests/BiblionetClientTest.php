<?php

declare(strict_types=1);

namespace BookMeta\Tests;

use BookMeta\BiblionetClient;
use PHPUnit\Framework\TestCase;

final class BiblionetClientTest extends TestCase
{
    public function testUrlPercentEncodesGreekPathSegments(): void
    {
        self::assertSame(
            'https://www.biblionet.gr/%CE%B1%CF%83%CF%84%CE%B5%CF%81%CE%B9%CE%BE-306233',
            BiblionetClient::url('/αστεριξ-306233')
        );
    }

    public function testUrlEncodesReservedCharactersInSlug(): void
    {
        self::assertSame('https://www.biblionet.gr/a%3A-b-1', BiblionetClient::url('/a:-b-1'));
    }
}
