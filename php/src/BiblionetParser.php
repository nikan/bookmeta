<?php

declare(strict_types=1);

namespace BookMeta;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Turns biblionet.gr HTML into book metadata. Pure: no network access.
 *
 * This is 100% dependent on the biblionet page structure. When the site
 * changes its markup, refresh the fixtures (make fixtures) and adjust here.
 */
final class BiblionetParser
{
    public const BASE_URL = 'https://www.biblionet.gr';

    // Labels as they appear on the page, after normalizeLabel().
    private const LABEL_AUTHORS = 'συγγραφεας';
    private const LABEL_TRANSLATORS = 'μεταφραση';
    private const LABEL_PUBLISHER = 'εκδοτης';
    private const LABEL_PUBLISHED = 'ημ. εκδοσης';
    private const LABEL_ORIGINAL_LANGUAGE = 'γλωσσα πρωτοτυπου';
    private const LABEL_ISBN = 'isbn';
    private const LABEL_SUBJECTS = 'θεμα';

    private const GREEK_ACCENTS = [
        'ά' => 'α', 'έ' => 'ε', 'ή' => 'η', 'ί' => 'ι', 'ό' => 'ο', 'ύ' => 'υ', 'ώ' => 'ω',
        'ϊ' => 'ι', 'ϋ' => 'υ', 'ΐ' => 'ι', 'ΰ' => 'υ',
        'Ά' => 'Α', 'Έ' => 'Ε', 'Ή' => 'Η', 'Ί' => 'Ι', 'Ό' => 'Ο', 'Ύ' => 'Υ', 'Ώ' => 'Ω',
        'Ϊ' => 'Ι', 'Ϋ' => 'Υ',
    ];

    /**
     * Book page paths (e.g. "/some-title-88309") from a search results page,
     * in page order, without duplicates.
     *
     * @return list<string>
     */
    public static function parseSearchResults(string $html): array
    {
        $xpath = self::load($html);
        $paths = [];
        $links = $xpath->query("//div[@id='result_books']//a[" . self::hasClass('book-title') . ']/@href');
        foreach ($links as $href) {
            $paths[] = trim($href->nodeValue);
        }
        return array_values(array_unique($paths));
    }

    /**
     * Metadata from a book page, or null if the page is not a book page.
     * Missing fields are null. Multi-valued fields are joined with ", ".
     *
     * @return array<string, string|null>|null
     */
    public static function parseBook(string $html): ?array
    {
        $xpath = self::load($html);
        $section = $xpath->query("//section[@id='book_info']")->item(0);
        if (!$section instanceof DOMElement) {
            return null;
        }

        $attributes = [];
        $contributors = [];
        $subjects = [];
        foreach ($xpath->query('.//li', $section) as $li) {
            $label = self::normalizeLabel(self::directText($li));
            if ($xpath->query("ancestor::div[" . self::hasClass('contributors-list') . ']', $li)->length > 0) {
                $contributors[$label] = array_merge($contributors[$label] ?? [], self::texts($xpath->query('.//a', $li)));
            } elseif ($label === self::LABEL_SUBJECTS) {
                $subjects = array_map(
                    static fn (string $s): string => preg_replace('/^\[[^\]]*\]\s*/u', '', $s),
                    self::texts($xpath->query('.//a', $li))
                );
            } else {
                $strong = $xpath->query('.//strong', $li)->item(0);
                if ($strong !== null) {
                    $attributes[$label] = self::clean($strong->textContent);
                }
            }
        }

        $title = $xpath->query('.//h1', $section)->item(0);
        $published = $attributes[self::LABEL_PUBLISHED] ?? '';
        $pageIsbn = $attributes[self::LABEL_ISBN] ?? '';

        return [
            'isbn' => $pageIsbn === '' ? null : Isbn::normalize($pageIsbn),
            'biblionetid' => self::biblionetId($xpath, $section),
            'cover_url' => self::coverUrl($xpath, $section),
            'title' => $title === null ? null : self::nullIfEmpty(self::clean($title->textContent)),
            'subtitle' => self::firstText($xpath, "following-sibling::p[" . self::hasClass('text-2') . '][1]', $title),
            'authors' => self::join($contributors[self::LABEL_AUTHORS] ?? []),
            'translators' => self::join($contributors[self::LABEL_TRANSLATORS] ?? []),
            'publisher' => self::nullIfEmpty($attributes[self::LABEL_PUBLISHER] ?? ''),
            'yr_published' => preg_match('/\b(\d{4})\b/', $published, $m) === 1 ? $m[1] : null,
            'original_language' => self::nullIfEmpty($attributes[self::LABEL_ORIGINAL_LANGUAGE] ?? ''),
            'original_title' => self::firstText($xpath, 'following-sibling::h3[1]', $title),
            'categories' => self::join($subjects),
        ];
    }

    /** Lowercase, accent-free, trimmed label without the trailing colon. */
    public static function normalizeLabel(string $label): string
    {
        $label = mb_strtolower(strtr(self::clean($label), self::GREEK_ACCENTS), 'UTF-8');
        return rtrim($label, ": \u{00A0}");
    }

    private static function load(string $html): DOMXPath
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // The XML prolog forces libxml to read the input as UTF-8.
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return new DOMXPath($doc);
    }

    private static function hasClass(string $class): string
    {
        return "contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')";
    }

    private static function biblionetId(DOMXPath $xpath, DOMNode $section): ?string
    {
        $fav = $xpath->query(".//a[starts-with(@id, 'fav_btn_')]/@id", $section)->item(0);
        if ($fav !== null && preg_match('/(\d+)$/', $fav->nodeValue, $m) === 1) {
            return $m[1];
        }
        $src = $xpath->query('.//img/@src', $section)->item(0);
        if ($src !== null && preg_match('#/book_(\d+)/#', $src->nodeValue, $m) === 1) {
            return $m[1];
        }
        return null;
    }

    private static function coverUrl(DOMXPath $xpath, DOMNode $section): ?string
    {
        $src = $xpath->query('.//div[' . self::hasClass('product-thumb-info-image') . ']//img/@src', $section)->item(0);
        if ($src === null) {
            return null;
        }
        $src = trim($src->nodeValue);
        if ($src === '' || str_contains($src, '/placeholders/')) {
            return null;
        }
        return str_starts_with($src, '/') ? self::BASE_URL . $src : $src;
    }

    private static function firstText(DOMXPath $xpath, string $query, ?DOMNode $context): ?string
    {
        if ($context === null) {
            return null;
        }
        $node = $xpath->query($query, $context)->item(0);
        return $node === null ? null : self::nullIfEmpty(self::clean($node->textContent));
    }

    private static function directText(DOMNode $node): string
    {
        $text = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text .= $child->nodeValue;
            }
        }
        return $text;
    }

    /** @return list<string> */
    private static function texts(iterable $nodes): array
    {
        $out = [];
        foreach ($nodes as $node) {
            $text = self::clean($node->textContent);
            if ($text !== '') {
                $out[] = $text;
            }
        }
        return $out;
    }

    private static function clean(string $text): string
    {
        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text));
    }

    private static function join(array $values): ?string
    {
        return $values === [] ? null : implode(', ', $values);
    }

    private static function nullIfEmpty(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
