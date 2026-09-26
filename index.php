<?php
/**
 *  Version: 0.2
 *  Author: Nikos Anagnostou (http://github.com/nikan)
 *  Licensed under The GNU General Licence v 2.0
 *  Redistributions of files must retain the above copyright notice.
 *
 *  DESCRIPTION:
 *  Retrieves book metadata from biblionet.gr based on an ISBN search and returns it as JSON (default) or HTML.
 *  Endpoint: http://<yourserver>/index.php?isbn=<an isbn>[&format=json|html]
 */

declare(strict_types=1);

require_once __DIR__ . '/src/Isbn.php';
require_once __DIR__ . '/src/UpstreamException.php';
require_once __DIR__ . '/src/BiblionetParser.php';
require_once __DIR__ . '/src/BiblionetClient.php';

use BookMeta\BiblionetClient;
use BookMeta\Isbn;
use BookMeta\UpstreamException;

$format = is_string($_GET['format'] ?? null) && $_GET['format'] !== '' ? $_GET['format'] : 'json';
if (!in_array($format, ['json', 'html'], true)) {
    respond(400, 'json', ['error' => 'format must be json or html']);
}

$isbn = is_string($_GET['isbn'] ?? null) ? Isbn::normalize($_GET['isbn']) : null;
if ($isbn === null) {
    respond(400, $format, ['error' => 'No valid ISBN provided.']);
}

try {
    $book = (new BiblionetClient())->findByIsbn($isbn);
} catch (UpstreamException $e) {
    error_log('bookmeta: ' . $e->getMessage());
    respond(502, $format, ['error' => 'Could not load data from biblionet.gr.', 'isbn' => $isbn]);
}

if ($book === null) {
    respond(404, $format, ['error' => 'No book found for this ISBN.', 'isbn' => $isbn]);
}
respond(200, $format, $book);

/** @param array<string, string|null> $data */
function respond(int $status, string $format, array $data): never
{
    http_response_code($status);
    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    $e = static fn (?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $title = isset($data['isbn']) ? 'Metadata for book with ISBN: ' . $e($data['isbn']) : 'Book metadata';
    echo "<!DOCTYPE html>\n<html>\n<head>\n<meta charset=\"UTF-8\">\n<title>{$title}</title>\n</head>\n<body>\n";
    foreach ($data as $key => $value) {
        echo '<p id="' . $e($key) . '">' . $e($value) . "</p>\n";
    }
    echo "</body>\n</html>\n";
    exit;
}
