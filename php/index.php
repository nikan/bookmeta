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
require_once __DIR__ . '/src/Endpoint.php';

use BookMeta\BiblionetClient;
use BookMeta\Endpoint;

$response = Endpoint::handle($_GET, static fn (string $isbn): ?array => (new BiblionetClient())->findByIsbn($isbn));

http_response_code($response['status']);
header('Content-Type: ' . $response['content_type']);
echo $response['body'];
