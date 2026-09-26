# Porting plan: Python and TypeScript

## Recommendation
Keep one repository with a shared, language-neutral test contract. Port to Python first, then to TypeScript. Keep the PHP version as the reference until both ports pass the same contract, then retire it.

## Why
The code is small. The fragile part is biblionet's markup, and shared fixtures with expected JSON are the only reliable way to keep three scrapers in agreement.

## Target layout
```
contract/
  fixtures/        # moved from tests/fixtures: *.html + book_*.expected.json
  http_cases.json  # request -> status + body cases for the endpoint
  schema.json      # JSON Schema for the output (all fields string|null)
php/               # current code (index.php, src/, tests/)
python/            # package "bookmeta"
ts/                # package "bookmeta"
Makefile           # make test = all three; make test-php|test-py|test-ts
```

## Module mapping
Each port keeps the same three layers and the same public functions.

| PHP | Python | TypeScript |
|---|---|---|
| `Isbn::normalize` | `isbn.normalize(s) -> str \| None` | `normalizeIsbn(s): string \| null` |
| `BiblionetParser::parseSearchResults` / `parseBook` | `parser.parse_search_results` / `parse_book` | `parseSearchResults` / `parseBook` |
| `BiblionetClient::findByIsbn` | `client.find_by_isbn` (async) | `findByIsbn` (async) |
| `index.php` | `app.py` (FastAPI) + `__main__` CLI | `server.ts` (Hono) + `cli.ts` |

## Stack
| Concern | Python | TypeScript |
|---|---|---|
| Tooling | uv, Python 3.12+, ruff, mypy --strict | Node 22+, pnpm, tsc strict, eslint |
| HTTP | httpx (async, timeouts, follow redirects) | built-in `fetch` + `AbortSignal.timeout` |
| HTML | lxml, whose XPath lets the PHP queries carry over almost 1:1 | cheerio, with the XPath rewritten as CSS plus `.nextAll().first()` |
| Accent stripping | `unicodedata.normalize("NFD")`, then drop combining marks | `s.normalize("NFD").replace(/\p{M}/gu, "")` |
| Tests | pytest, parametrized over `contract/fixtures` | vitest, `test.each` over `contract/fixtures` |
| Server | FastAPI + uvicorn | Hono on `@hono/node-server` |
| Container | `python:3.12-slim` | `node:22-slim` |

## To do it
Status (2026-09-26): steps 1-3 are done. PHP and Python pass the same contract and return byte-identical responses on live requests. Next is step 4.

1. **[Done] Freeze the contract.** Move `tests/fixtures` to `contract/fixtures` and move the PHP code into `php/`. Add `schema.json` and `http_cases.json`. These cover: valid ISBN-13, ISBN-10 converted to 13, invalid ISBN (400), bad format (400), not found (404), escaped HTML output. Point the PHP tests at `contract/`. Run `make test`; it must still pass.
2. **[Done] Python: ISBN and parser.** Port `Isbn` and `BiblionetParser`. Done when pytest passes every `book_*.expected.json` (deep-equal after JSON parse) and the search fixtures.
3. **[Done] Python: client and server.** Port the client and add the FastAPI app and a `python -m bookmeta <isbn>` CLI. Run `http_cases.json` against the app with a stubbed client. Add an opt-in live test marked `@pytest.mark.live`.
4. **TypeScript: ISBN and parser.** Same gate as step 2, using vitest.
5. **TypeScript: client and server.** Same as step 3: Hono app, `npx bookmeta <isbn>` CLI, and a live test behind `LIVE=1`.
6. **CI.** Build a matrix job per language that runs lint, test, and the shared contract. Add a weekly scheduled `live-test` for all three so site changes surface early.
7. **Refresh fixtures from one place.** Only the reference implementation (PHP now, Python once PHP is retired) downloads the HTML and rewrites `*.expected.json`. A person reviews the diff, and the other ports must then match it. They never regenerate expected output themselves.
8. **Retire PHP (optional).** Once one port runs in production, drop `php/` or mark it unmaintained in the README.

## Traps to carry over
- **ISBN-13 only.** Biblionet search matches only ISBN-13, so always normalize before searching.
- **Verify the ISBN.** Accept a search candidate only if its page ISBN equals the query.
- **Encode paths.** Greek path segments must be percent-encoded per segment.
- **Strict ISBN input.** Allow only spaces and hyphens as separators. Reject anything else; don't strip it.
- **Missing cover.** A placeholder cover image means `cover_url: null`.
- **Which ID.** `biblionetid` comes from `fav_btn_<id>`, not from the slug number.
- **Fixture load.** Search pages can be over 1.6 MB, but the fixture is trimmed to `#result_books`.

## When this changes
- **Richer output (v2).** If consumers want lists instead of `", "`-joined strings, add a v2 contract with arrays and a `contributors[{role, name}]` field. Keep v1 as a view over v2.
- **Blocked by biblionet.** If biblionet starts serving Cloudflare challenges to scripts, add caching (for example SQLite, keyed by ISBN-13, 30-day TTL) and back-off in the client layer before anything else.
- **Official API.** If biblionet publishes an API, replace the parser layer only. The contract and the endpoints stay the same.
- **One language only.** If only one language is needed, do steps 1-3 or 1 plus 4-5 and skip the rest.
