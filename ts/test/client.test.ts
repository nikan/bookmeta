/** Lookup flow against a fake biblionet built from the contract fixtures. */

import { describe, expect, it } from "vitest";

import { BiblionetClient, UpstreamError, searchUrl, url } from "../src/client.js";
import { fixtureExpected, fixtureHtml } from "./helpers.js";

const BOOK_PATH = "/εισαγωγη-σε-δυο-θεωριες-της-κοινωνικης-ανθρωπολογιας-88309";

function fakeBiblionet(pages: Record<string, () => Response>): typeof fetch {
  return (input) => {
    const requested = input instanceof Request ? input.url : String(input);
    const page = pages[requested];
    return Promise.resolve(page ? page() : new Response("", { status: 404 }));
  };
}

const page = (name: string) => () => new Response(fixtureHtml(name), { status: 200 });

function find(isbn: string, pages: Record<string, () => Response>) {
  return new BiblionetClient({ fetch: fakeBiblionet(pages) }).findByIsbn(isbn);
}

describe("url", () => {
  it("percent-encodes Greek path segments", () => {
    expect(url("/αστεριξ-306233")).toBe(
      "https://www.biblionet.gr/%CE%B1%CF%83%CF%84%CE%B5%CF%81%CE%B9%CE%BE-306233",
    );
  });

  it("encodes reserved characters like rawurlencode", () => {
    expect(url("/a:-b-1")).toBe("https://www.biblionet.gr/a%3A-b-1");
    expect(url("/a(b)!'*-1")).toBe("https://www.biblionet.gr/a%28b%29%21%27%2A-1");
  });
});

describe("findByIsbn", () => {
  it("finds the book and adds its url", async () => {
    const pages = { [searchUrl("9789600316483")]: page("search_hit"), [url(BOOK_PATH)]: page("book_88309") };
    expect(await find("9789600316483", pages)).toStrictEqual({
      ...fixtureExpected("book_88309"),
      url: url(BOOK_PATH),
    });
  });

  it("rejects a candidate with a different ISBN", async () => {
    const pages = { [searchUrl("9789603215066")]: page("search_hit"), [url(BOOK_PATH)]: page("book_88309") };
    expect(await find("9789603215066", pages)).toBeNull();
  });

  it("returns null without search results", async () => {
    expect(await find("9780000000002", { [searchUrl("9780000000002")]: page("search_miss") })).toBeNull();
  });

  it("raises UpstreamError on an HTTP error", async () => {
    const pages = { [searchUrl("9789600316483")]: () => new Response("", { status: 503 }) };
    await expect(find("9789600316483", pages)).rejects.toThrow(
      new UpstreamError(`Request to ${searchUrl("9789600316483")} returned HTTP 503`),
    );
  });

  it("raises UpstreamError on a network error", async () => {
    const failing: typeof fetch = () => Promise.reject(new TypeError("boom"));
    await expect(new BiblionetClient({ fetch: failing }).findByIsbn("9789600316483")).rejects.toThrow(/boom/);
    await expect(new BiblionetClient({ fetch: failing }).findByIsbn("9789600316483")).rejects.toBeInstanceOf(
      UpstreamError,
    );
  });
});
