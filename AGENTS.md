# bookmeta: agent guide

Looks up a book by ISBN on biblionet.gr (the Greek book database) and returns its metadata as JSON or HTML.
It's a web scraper, so it depends 100% on biblionet's markup.

## Commands

PHP is **not** installed on the host. Everything runs in Docker through `make`:

| Command | What it does |
|---|---|
| `make test` | Offline PHPUnit run against saved pages in `tests/fixtures/`. Run it after every change. |
| `make lint` | `php -l` on all PHP files |
| `make live-test` | Hits the real biblionet.gr. Run only when checking whether the site changed. |
| `make fixtures` | Re-downloads fixtures and rewrites `*.expected.json`. Review the diff before committing. |
| `make serve` | `http://localhost:8080/index.php?isbn=9789600316483` (use `PORT=` to change the port) |

The first `make test` runs `composer install` in Docker, which creates `vendor/`.

## Layout

- `index.php`: the HTTP endpoint. It reads `isbn` and `format` (`json`|`html`), sets status codes, and escapes all output. Keep it thin.
- `src/Isbn.php`: ISBN-10/13 validation. `normalize()` always returns an ISBN-13, because biblionet search only matches 13-digit ISBNs.
- `src/BiblionetParser.php`: pure HTML-to-array functions with no network access. All site-specific selectors and labels live here.
- `src/BiblionetClient.php`: curl plus the lookup flow: search, open each candidate, accept only if the page's ISBN matches.
- `tests/fixtures/`: saved live pages. `book_<id>.expected.json` is the output contract (see below).
- `docs/PORTING_PLAN.md`: plan for Python and TypeScript versions.

Runtime has no Composer dependencies. `index.php` uses `require_once` for `src/` so it deploys by copying files. Composer is for PHPUnit only.

## Output contract

All fields are either a string or `null`. Multi-value fields are joined with `", "`.

Fields: `isbn, biblionetid, cover_url, title, subtitle, authors, translators, publisher, yr_published, original_language, original_title, categories, url`.

On errors, the response is `{"error": ..., "isbn"?: ...}` with a status code:
- 400: bad ISBN or bad format
- 404: not found
- 502: biblionet failed

Changing a field name or type is a breaking change. Update every `*.expected.json` and the port plan together.

## How biblionet works (as of 2026-09)

- **Search:** `GET /συνθετη-αναζητηση?q=<isbn13>`. Results are `#result_books a.book-title` links like `/<slug>-<n>`. A miss returns a page with no `#result_books`.
- **Book page:** `section#book_info`. Values come from labelled `<li>` items. Labels are matched after `normalizeLabel()`, which lowercases and strips Greek accents. Contributor labels are accented and attribute labels are not, which is why the normalization is needed.
- **`biblionetid`:** the internal ID from `fav_btn_<id>` and the cover path. It is not the number at the end of the slug.
- **Cover:** a placeholder image (`/placeholders/`) means there is no cover, so `cover_url` is `null`.
- **Path encoding:** paths contain Greek characters. Always build URLs with `BiblionetClient::url()`, which percent-encodes each segment.

## When the site changes

1. `make live-test` fails while `make test` passes.
2. Run `make fixtures` and look at the new HTML.
3. Fix `BiblionetParser` until `make test` passes. Check that the expected JSON diff contains only intended changes.
4. Add a fixture for any new page variant you had to handle.

## Conventions

- Use `declare(strict_types=1)` and final classes. PHP 8.2+ syntax is fine; the Docker image runs 8.3.
- Never echo request input without validating or escaping it.
- Never hit the live site from the default test suite.
