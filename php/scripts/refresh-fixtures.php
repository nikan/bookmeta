<?php
/**
 * Re-downloads the biblionet pages under contract/fixtures and rewrites the
 * expected JSON from the current parser. Run with `make fixtures`, then
 * review the diff: changed expected JSON means the site or the parser changed.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

use BookMeta\BiblionetClient;
use BookMeta\BiblionetParser;

$dir = __DIR__ . '/../../contract/fixtures';
$client = new BiblionetClient();
$json = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
$search = static fn (string $q): string => BiblionetClient::url('/συνθετη-αναζητηση') . '?' . http_build_query(['q' => $q]);

// The live search page embeds a >1 MB debug dump and filter menus; keep only the results block.
$hit = $client->get($search('9789600316483'));
$start = strpos($hit, '<div id="result_books"');
$end = $start === false ? false : strpos($hit, '<footer', $start);
if ($start === false || $end === false) {
    fwrite(STDERR, "search page no longer has #result_books; the parser needs updating\n");
    exit(1);
}
file_put_contents("{$dir}/search_hit.html", "<!DOCTYPE html>\n<html lang=\"el\"><head><meta charset=\"utf-8\"><title>Αναζήτηση</title></head><body>\n"
    . "<!-- Trimmed fixture: only #result_books kept from a live search for 9789600316483. -->\n"
    . substr($hit, $start, $end - $start) . "\n</body></html>\n");
file_put_contents("{$dir}/search_miss.html", $client->get($search('9780000000002')));

// book id => path on biblionet
$books = [
    '88309' => '/εισαγωγη-σε-δυο-θεωριες-της-κοινωνικης-ανθρωπολογιας-88309',
    '306233' => '/αστεριξ-ο-γαλατης-306233',
    '515961' => '/γιωργης-ντελελης-νταμαχης-ο-ζορμπας-της-λημνου-515961',
    '470787' => '/το-κομμουνιστικο-μανιφεστο-470787',
];
foreach ($books as $id => $path) {
    $html = $client->get(BiblionetClient::url($path));
    file_put_contents("{$dir}/book_{$id}.html", $html);
    file_put_contents("{$dir}/book_{$id}.expected.json", json_encode(BiblionetParser::parseBook($html), $json) . "\n");
    echo "book_{$id}: ok\n";
}
