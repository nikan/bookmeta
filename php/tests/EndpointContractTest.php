<?php

declare(strict_types=1);

namespace BookMeta\Tests;

use BookMeta\Endpoint;
use BookMeta\UpstreamException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Runs contract/http_cases.json against the endpoint with a stubbed lookup. */
final class EndpointContractTest extends TestCase
{
    private const CONTRACT = __DIR__ . '/../../contract';

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function cases(): array
    {
        $spec = self::load('/http_cases.json');
        $cases = [];
        foreach ($spec['cases'] as $case) {
            $cases[$case['name']] = [$case, $spec['stub_url']];
        }
        return $cases;
    }

    /** @param array<string, mixed> $case */
    #[DataProvider('cases')]
    public function testCase(array $case, string $stubUrl): void
    {
        $calls = [];
        $lookup = function (string $isbn) use ($case, $stubUrl, &$calls): ?array {
            $calls[] = $isbn;
            if (!array_key_exists('lookup', $case)) {
                self::fail('lookup must not be called');
            }
            if (($case['lookup']['raises'] ?? null) === 'upstream') {
                throw new UpstreamException('stubbed failure');
            }
            $fixture = $case['lookup']['returns'];
            return $fixture === null ? null : self::book($fixture, $stubUrl);
        };

        $response = Endpoint::handle($case['query'], $lookup);

        self::assertSame($case['status'], $response['status']);
        self::assertStringStartsWith($case['content_type'], $response['content_type']);
        if (isset($case['expect_lookup_isbn'])) {
            self::assertSame([$case['expect_lookup_isbn']], $calls);
        }
        if (isset($case['json_fixture'])) {
            self::assertSame(self::book($case['json_fixture'], $stubUrl), json_decode($response['body'], true));
        }
        if (isset($case['json'])) {
            self::assertSame($case['json'], json_decode($response['body'], true));
        }
        foreach ($case['html_contains'] ?? [] as $needle) {
            self::assertStringContainsString($needle, $response['body']);
        }
        foreach ($case['html_not_contains'] ?? [] as $needle) {
            self::assertStringNotContainsString($needle, $response['body']);
        }
    }

    /** @return array<string, string|null> */
    private static function book(string $fixture, string $url): array
    {
        return self::load("/fixtures/{$fixture}.expected.json") + ['url' => $url];
    }

    /** @return array<string, mixed> */
    private static function load(string $path): array
    {
        return json_decode(file_get_contents(self::CONTRACT . $path), true, flags: JSON_THROW_ON_ERROR);
    }
}
