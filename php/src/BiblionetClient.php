<?php

declare(strict_types=1);

namespace BookMeta;

/**
 * Fetches pages from biblionet.gr and looks books up by ISBN.
 */
final class BiblionetClient
{
    private const SEARCH_PATH = '/συνθετη-αναζητηση';
    private const MAX_CANDIDATES = 5;
    private const USER_AGENT = 'bookmeta/1.0 (+https://github.com/nikan/bookmeta)';

    public function __construct(private readonly int $timeoutSeconds = 15)
    {
    }

    /**
     * Metadata for the book with this ISBN-13, or null if biblionet has none.
     * The search is free text, so each candidate is checked against its
     * ISBN field before it is accepted.
     *
     * @return array<string, string|null>|null
     * @throws UpstreamException
     */
    public function findByIsbn(string $isbn13): ?array
    {
        $searchUrl = self::url(self::SEARCH_PATH) . '?' . http_build_query(['q' => $isbn13]);
        $paths = BiblionetParser::parseSearchResults($this->get($searchUrl));

        foreach (array_slice($paths, 0, self::MAX_CANDIDATES) as $path) {
            $url = self::url($path);
            $book = BiblionetParser::parseBook($this->get($url));
            if ($book !== null && $book['isbn'] === $isbn13) {
                return $book + ['url' => $url];
            }
        }
        return null;
    }

    /** Absolute URL for a site path, with each (Greek) path segment percent-encoded. */
    public static function url(string $path): string
    {
        $segments = explode('/', ltrim($path, '/'));
        return BiblionetParser::BASE_URL . '/' . implode('/', array_map('rawurlencode', $segments));
    }

    /** @throws UpstreamException */
    public function get(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_ENCODING => '',
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);

        if ($body === false) {
            throw new UpstreamException("Request to {$url} failed: {$error}");
        }
        if ($status !== 200) {
            throw new UpstreamException("Request to {$url} returned HTTP {$status}");
        }
        return $body;
    }
}
