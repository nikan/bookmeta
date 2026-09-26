<?php

declare(strict_types=1);

namespace BookMeta;

/**
 * The HTTP endpoint without globals: query parameters in, response out.
 * index.php wires it to $_GET and the real client; tests use a stub lookup.
 */
final class Endpoint
{
    /**
     * @param array<string, mixed> $query
     * @param callable(string): (array<string, string|null>|null) $findByIsbn may throw UpstreamException
     * @return array{status: int, content_type: string, body: string}
     */
    public static function handle(array $query, callable $findByIsbn): array
    {
        $format = is_string($query['format'] ?? null) && $query['format'] !== '' ? $query['format'] : 'json';
        if (!in_array($format, ['json', 'html'], true)) {
            return self::render(400, 'json', ['error' => 'format must be json or html']);
        }

        $isbn = is_string($query['isbn'] ?? null) ? Isbn::normalize($query['isbn']) : null;
        if ($isbn === null) {
            return self::render(400, $format, ['error' => 'No valid ISBN provided.']);
        }

        try {
            $book = $findByIsbn($isbn);
        } catch (UpstreamException $e) {
            error_log('bookmeta: ' . $e->getMessage());
            return self::render(502, $format, ['error' => 'Could not load data from biblionet.gr.', 'isbn' => $isbn]);
        }

        if ($book === null) {
            return self::render(404, $format, ['error' => 'No book found for this ISBN.', 'isbn' => $isbn]);
        }
        return self::render(200, $format, $book);
    }

    /**
     * @param array<string, string|null> $data
     * @return array{status: int, content_type: string, body: string}
     */
    private static function render(int $status, string $format, array $data): array
    {
        if ($format === 'json') {
            $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            return ['status' => $status, 'content_type' => 'application/json; charset=utf-8', 'body' => $body];
        }

        $e = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = isset($data['isbn']) ? 'Metadata for book with ISBN: ' . $e($data['isbn']) : 'Book metadata';
        $body = "<!DOCTYPE html>\n<html>\n<head>\n<meta charset=\"UTF-8\">\n<title>{$title}</title>\n</head>\n<body>\n";
        foreach ($data as $key => $value) {
            $body .= '<p id="' . $e($key) . '">' . $e($value) . "</p>\n";
        }
        $body .= "</body>\n</html>\n";
        return ['status' => $status, 'content_type' => 'text/html; charset=utf-8', 'body' => $body];
    }
}
