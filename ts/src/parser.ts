/**
 * Turns biblionet.gr HTML into book metadata. Pure: no network access.
 *
 * This is 100% dependent on the biblionet page structure. When the site
 * changes its markup, refresh the fixtures (make fixtures) and adjust here.
 * Keep it in step with php/src/BiblionetParser.php and
 * python/src/bookmeta/parser.py: all must produce contract/fixtures/*.expected.json.
 */

import * as cheerio from "cheerio";
import { isText, type AnyNode } from "domhandler";

import { normalizeIsbn } from "./isbn.js";

export const BASE_URL = "https://www.biblionet.gr";

/** Parser output. The HTTP response adds `url`. */
export interface Book {
  isbn: string | null;
  biblionetid: string | null;
  cover_url: string | null;
  title: string | null;
  subtitle: string | null;
  authors: string | null;
  translators: string | null;
  publisher: string | null;
  yr_published: string | null;
  original_language: string | null;
  original_title: string | null;
  categories: string | null;
}

// Labels as they appear on the page, after normalizeLabel().
const LABEL_AUTHORS = "συγγραφεας";
const LABEL_TRANSLATORS = "μεταφραση";
const LABEL_PUBLISHER = "εκδοτης";
const LABEL_PUBLISHED = "ημ. εκδοσης";
const LABEL_ORIGINAL_LANGUAGE = "γλωσσα πρωτοτυπου";
const LABEL_ISBN = "isbn";
const LABEL_SUBJECTS = "θεμα";

const SUBJECT_CODE = /^\[[^\]]*\]\s*/;
const YEAR = /\b([0-9]{4})\b/;

/** Book page paths (e.g. "/some-title-88309") in page order, without duplicates. */
export function parseSearchResults(html: string): string[] {
  const $ = cheerio.load(html);
  const hrefs = $("#result_books a.book-title")
    .map((_, a) => $(a).attr("href"))
    .get()
    .map((href) => href.trim());
  return [...new Set(hrefs)];
}

/**
 * Metadata from a book page, or null if it is not a book page.
 * Missing fields are null. Multi-valued fields are joined with ", ".
 */
export function parseBook(html: string): Book | null {
  const $ = cheerio.load(html);
  const section = $("section#book_info").first();
  if (section.length === 0) {
    return null;
  }

  const attributes = new Map<string, string>();
  const contributors = new Map<string, string[]>();
  let subjects: string[] = [];
  for (const li of section.find("li").toArray()) {
    const $li = $(li);
    const label = normalizeLabel(directText($li.contents().toArray()));
    if ($li.closest("div.contributors-list").length > 0) {
      contributors.set(label, [...(contributors.get(label) ?? []), ...texts($, $li.find("a").toArray())]);
    } else if (label === LABEL_SUBJECTS) {
      subjects = texts($, $li.find("a").toArray()).map((s) => s.replace(SUBJECT_CODE, ""));
    } else {
      const strong = $li.find("strong").first();
      if (strong.length > 0) {
        attributes.set(label, clean(strong.text()));
      }
    }
  }

  const title = section.find("h1").first();
  const hasTitle = title.length > 0;
  const pageIsbn = attributes.get(LABEL_ISBN) ?? "";
  const year = YEAR.exec(attributes.get(LABEL_PUBLISHED) ?? "");

  return {
    isbn: pageIsbn === "" ? null : normalizeIsbn(pageIsbn),
    biblionetid: biblionetId(section),
    cover_url: coverUrl(section),
    title: hasTitle ? nullIfEmpty(clean(title.text())) : null,
    subtitle: hasTitle ? firstText(title.nextAll("p.text-2")) : null,
    authors: join(contributors.get(LABEL_AUTHORS) ?? []),
    translators: join(contributors.get(LABEL_TRANSLATORS) ?? []),
    publisher: nullIfEmpty(attributes.get(LABEL_PUBLISHER) ?? ""),
    yr_published: year?.[1] ?? null,
    original_language: nullIfEmpty(attributes.get(LABEL_ORIGINAL_LANGUAGE) ?? ""),
    original_title: hasTitle ? firstText(title.nextAll("h3")) : null,
    categories: join(subjects),
  };
}

/** Lowercase, accent-free, trimmed label without the trailing colon. */
export function normalizeLabel(label: string): string {
  const stripped = clean(label).normalize("NFD").replace(/\p{M}/gu, "");
  // Per-character lowercasing, like PHP's mb_strtolower (no final-sigma rule).
  return Array.from(stripped, (c) => c.toLowerCase())
    .join("")
    .replace(/[: \u00a0]+$/, "");
}

type Selection = cheerio.Cheerio<AnyNode>;

function biblionetId(section: Selection): string | null {
  const fav = section.find("a[id^='fav_btn_']").first().attr("id");
  if (fav !== undefined) {
    const match = /(\d+)$/.exec(fav);
    if (match?.[1] !== undefined) {
      return match[1];
    }
  }
  const src = section.find("img[src]").first().attr("src");
  return src === undefined ? null : (/\/book_(\d+)\//.exec(src)?.[1] ?? null);
}

function coverUrl(section: Selection): string | null {
  const src = section.find("div.product-thumb-info-image img[src]").first().attr("src")?.trim();
  if (src === undefined || src === "" || src.includes("/placeholders/")) {
    return null;
  }
  return src.startsWith("/") ? BASE_URL + src : src;
}

function firstText(nodes: Selection): string | null {
  const first = nodes.first();
  return first.length > 0 ? nullIfEmpty(clean(first.text())) : null;
}

function directText(nodes: AnyNode[]): string {
  return nodes.map((n) => (isText(n) ? n.data : "")).join("");
}

function texts($: cheerio.CheerioAPI, nodes: AnyNode[]): string[] {
  return nodes.map((n) => clean($(n).text())).filter((t) => t !== "");
}

function clean(text: string): string {
  return text.replace(/\s+/g, " ").trim();
}

function join(values: string[]): string | null {
  return values.length === 0 ? null : values.join(", ");
}

function nullIfEmpty(value: string): string | null {
  return value === "" ? null : value;
}
