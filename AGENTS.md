# bookmeta: agent guide

Looks up a book by ISBN on biblionet.gr (the Greek book database) and returns its metadata as JSON or HTML.
It's a web scraper, so it depends 100% on biblionet's markup.

The same service exists in PHP (the reference) and Python. TypeScript is planned. All implementations must pass the shared tests in `contract/` (see `docs/PORTING_PLAN.md`).

## Commands

Run everything from the repo root with `make`. PHP is **not** installed on the host, so PHP targets run in Docker. Python runs on the host through `uv`.

| Command | What it does |
|---|---|
| `make test` | Offline tests for all languages (`test-php`, `test-py`). Run it after every change. |
| `make lint` | `php -l`, `ruff check`, `ruff format --check`, `mypy --strict` |
| `make live-test` | Hits the real biblionet.gr. Run only when checking whether the site changed. |
| `make fixtures` | Re-downloads `contract/fixtures` and rewrites `*.expected.json` using PHP. Review the diff. |
| `make serve-php` / `make serve-py` | `http://localhost:8080/index.php?isbn=9789600316483` (use `PORT=` to change the port) |
| `cd python && uv run bookmeta <isbn>` | Python CLI |

## Layout

- `contract/`: the language-neutral spec.
  - `fixtures/*.html`: saved biblionet pages.
  - `fixtures/book_<id>.expected.json`: the parser output every implementation must produce.
  - `http_cases.json`: endpoint cases (status, content type, body), run with a stubbed lookup.
  - `schema.json`: the output schema.
- `php/`: the reference implementation. Deploy it by copying `php/index.php` and `php/src/`; it has no runtime Composer dependencies.
- `python/`: the uv project for package `bookmeta`.
- The same four layers exist in each language:

  | Layer | PHP | Python | Notes |
  |---|---|---|---|
  | ISBN | `Isbn` | `isbn.py` | Always normalizes to ISBN-13, because biblionet search only matches 13-digit ISBNs. |
  | Parser | `BiblionetParser` | `parser.py` | HTML string in, data out. No network. All site-specific selectors and labels live here. |
  | Client | `BiblionetClient` | `client.py` | Searches, opens each candidate, and accepts only a page whose ISBN matches. |
  | Endpoint | `Endpoint` | `endpoint.py` | Framework-free: query in, `{status, content_type, body}` out. `php/index.php` and `python/.../app.py` (FastAPI) are thin wrappers. |

## Output contract

All fields are either a string or `null`. Multi-value fields are joined with `", "`.

Fields, in this order: `isbn, biblionetid, cover_url, title, subtitle, authors, translators, publisher, yr_published, original_language, original_title, categories, url`.

On errors, the response is `{"error": ..., "isbn"?: ...}` with a status code:
- 400: bad ISBN or bad format
- 404: not found
- 502: biblionet failed

JSON and HTML bodies are byte-identical across languages: 4-space pretty JSON with unescaped Unicode and slashes, and HTML escaped like PHP `htmlspecialchars(ENT_QUOTES|ENT_HTML5)`.

Changing a field name or type is a breaking change. Update `contract/` first, then every implementation.

## How biblionet works (as of 2026-09)

- **Search:** `GET /συνθετη-αναζητηση?q=<isbn13>`. Results are `#result_books a.book-title` links like `/<slug>-<n>`. A miss returns a page with no `#result_books`.
- **Book page:** `section#book_info`. Values come from labelled `<li>` items. Labels are matched after normalization, which lowercases and strips Greek accents. Contributor labels are accented and attribute labels are not, which is why the normalization is needed.
- **Lowercasing:** Python uses per-character `lower()` to match PHP `mb_strtolower`, which has no final-sigma rule.
- **`biblionetid`:** the internal ID from `fav_btn_<id>` and the cover path. It is not the number at the end of the slug.
- **Cover:** a placeholder image (`/placeholders/`) means there is no cover, so `cover_url` is `null`.
- **Path encoding:** paths contain Greek characters. Always build URLs with the client's `url()`, which percent-encodes each segment.

## When the site changes

1. `make live-test` fails while `make test` passes.
2. Run `make fixtures` and look at the new HTML.
3. Fix the PHP parser until `make test-php` passes, then port the same change to `python/src/bookmeta/parser.py` until `make test-py` passes.
4. Add a fixture for any new page variant you had to handle.

## Conventions

- **PHP:** `declare(strict_types=1)` and final classes. PHP 8.2+ syntax is fine; the Docker image runs 8.3.
- **Python:** 3.12+ (pinned in `.python-version`), `mypy --strict`, and ruff. Tests turn warnings into errors.
- **Parity:** keep the implementations structurally parallel, with the same function names in each language's style, so a fix ports line by line.
- **Output safety:** never echo request input without validating or escaping it.
- **Tests:** never hit the live site from the default suites.
