# BOOKMETA - a book metadata extractor

## Intro
A small service that extracts book metadata from the most comprehensive modern Greek book database: https://www.biblionet.gr.
Give it an ISBN-10 or ISBN-13; it returns the book's metadata as JSON (default) or HTML.

## Use
```
GET /index.php?isbn=<isbn>[&format=json|html]
```

Example: `index.php?isbn=978-960-03-1648-3`
```json
{
    "isbn": "9789600316483",
    "biblionetid": "45238",
    "cover_url": "https://www.biblionet.gr/assets/images/books/book_45238/45238.jpg",
    "title": "Εισαγωγή σε δύο θεωρίες της κοινωνικής ανθρωπολογίας",
    "subtitle": "Ομάδες καταγωγής και σχέσεις επιγαμίας",
    "authors": "Louis Dumont",
    "translators": "Δώρα Λαφαζάνη",
    "publisher": "Εκδόσεις Καστανιώτη",
    "yr_published": "1996",
    "original_language": "γαλλικά",
    "original_title": "Introduction à deux théories d' anthropologie sociale",
    "categories": "Κοινωνική ανθρωπολογία",
    "url": "https://www.biblionet.gr/..."
}
```
Missing fields are `null`. Multiple authors, translators or categories are joined with `", "`.
Errors return `{"error": "..."}` with HTTP 400 (invalid ISBN or format), 404 (not on biblionet) or 502 (biblionet unreachable).

## Install
Requires PHP 8.2+ with the `curl`, `dom` and `mbstring` extensions.
Copy `index.php` and `src/` to a PHP-enabled web server. There are no runtime dependencies.

## Develop
Only Docker and `make` are needed. See `AGENTS.md` for details.
```
make test    # offline tests against saved pages
make serve   # http://localhost:8080/index.php?isbn=9789600316483&format=html
```

## License
GNU General Public License v2.0.
