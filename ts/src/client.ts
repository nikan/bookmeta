/** Fetches pages from biblionet.gr and looks books up by ISBN. */

import { BASE_URL, parseBook, parseSearchResults, type Book } from "./parser.js";

const SEARCH_PATH = "/συνθετη-αναζητηση";
const MAX_CANDIDATES = 5;
const USER_AGENT = "bookmeta/1.0 (+https://github.com/nikan/bookmeta)";

/** The HTTP response body: parser output plus the book page URL. */
export type BookResponse = Book & { url: string };

/** biblionet.gr could not be reached or answered with an error. */
export class UpstreamError extends Error {
  override name = "UpstreamError";
}

/**
 * Absolute URL for a site path, with each (Greek) path segment percent-encoded
 * exactly like PHP's rawurlencode (encodeURIComponent leaves !'()* alone).
 */
export function url(path: string): string {
  const segments = path.replace(/^\/+/, "").split("/");
  return BASE_URL + "/" + segments.map(rawUrlEncode).join("/");
}

export function searchUrl(query: string): string {
  return url(SEARCH_PATH) + "?" + new URLSearchParams({ q: query }).toString();
}

function rawUrlEncode(segment: string): string {
  return encodeURIComponent(segment).replace(
    /[!'()*]/g,
    (c) => "%" + c.charCodeAt(0).toString(16).toUpperCase(),
  );
}

export interface ClientOptions {
  /** Injected for tests; defaults to the global fetch. */
  fetch?: typeof fetch;
  timeoutMs?: number;
}

export class BiblionetClient {
  readonly #fetch: typeof fetch;
  readonly #timeoutMs: number;

  constructor(options: ClientOptions = {}) {
    this.#fetch = options.fetch ?? fetch;
    this.#timeoutMs = options.timeoutMs ?? 15_000;
  }

  /**
   * Metadata for the book with this ISBN-13, or null if biblionet has none.
   * The search is free text, so each candidate is checked against its ISBN
   * field before it is accepted.
   */
  async findByIsbn(isbn13: string): Promise<BookResponse | null> {
    const paths = parseSearchResults(await this.get(searchUrl(isbn13)));
    for (const path of paths.slice(0, MAX_CANDIDATES)) {
      const pageUrl = url(path);
      const book = parseBook(await this.get(pageUrl));
      if (book !== null && book.isbn === isbn13) {
        return { ...book, url: pageUrl };
      }
    }
    return null;
  }

  async get(pageUrl: string): Promise<string> {
    let response: Response;
    try {
      response = await this.#fetch(pageUrl, {
        headers: { "User-Agent": USER_AGENT },
        redirect: "follow",
        signal: AbortSignal.timeout(this.#timeoutMs),
      });
    } catch (e) {
      throw new UpstreamError(`Request to ${pageUrl} failed: ${String(e)}`, { cause: e });
    }
    if (response.status !== 200) {
      throw new UpstreamError(`Request to ${pageUrl} returned HTTP ${String(response.status)}`);
    }
    try {
      return await response.text();
    } catch (e) {
      throw new UpstreamError(`Reading ${pageUrl} failed: ${String(e)}`, { cause: e });
    }
  }
}
