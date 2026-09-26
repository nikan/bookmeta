/**
 * The HTTP endpoint without a web framework: query parameters in, response out.
 * Mirrors php/src/Endpoint.php and python/src/bookmeta/endpoint.py; all must
 * pass contract/http_cases.json.
 */

import { UpstreamError, type BookResponse } from "./client.js";
import { normalizeIsbn } from "./isbn.js";

export type Lookup = (isbn13: string) => Promise<BookResponse | null>;

export interface EndpointResponse {
  status: number;
  contentType: string;
  body: string;
}

type Data = Record<string, string | null>;

// Same output as PHP's htmlspecialchars(ENT_QUOTES | ENT_HTML5).
const HTML_ESCAPES: Record<string, string> = {
  "&": "&amp;",
  "<": "&lt;",
  ">": "&gt;",
  '"': "&quot;",
  "'": "&apos;",
};

export async function handle(query: Record<string, unknown>, lookup: Lookup): Promise<EndpointResponse> {
  // Absent or empty means json; a present non-string (?format[]=...) is a bad format.
  let format = query.format;
  if (format === undefined || format === null || format === "") {
    format = "json";
  }
  if (format !== "json" && format !== "html") {
    return render(400, "json", { error: "format must be json or html" });
  }

  const isbn = typeof query.isbn === "string" ? normalizeIsbn(query.isbn) : null;
  if (isbn === null) {
    return render(400, format, { error: "No valid ISBN provided." });
  }

  let book: BookResponse | null;
  try {
    book = await lookup(isbn);
  } catch (e) {
    if (!(e instanceof UpstreamError)) {
      throw e;
    }
    console.warn(`bookmeta: ${e.message}`);
    return render(502, format, { error: "Could not load data from biblionet.gr.", isbn });
  }

  if (book === null) {
    return render(404, format, { error: "No book found for this ISBN.", isbn });
  }
  return render(200, format, { ...book });
}

function render(status: number, format: "json" | "html", data: Data): EndpointResponse {
  if (format === "json") {
    return { status, contentType: "application/json; charset=utf-8", body: JSON.stringify(data, null, 4) };
  }

  const e = (value: string | null | undefined): string =>
    (value ?? "").replace(/[&<>"']/g, (c) => HTML_ESCAPES[c] ?? c);
  const title = data.isbn ? `Metadata for book with ISBN: ${e(data.isbn)}` : "Book metadata";
  const lines = [
    "<!DOCTYPE html>",
    "<html>",
    "<head>",
    '<meta charset="UTF-8">',
    `<title>${title}</title>`,
    "</head>",
    "<body>",
    ...Object.entries(data).map(([key, value]) => `<p id="${e(key)}">${e(value)}</p>`),
    "</body>",
    "</html>",
    "",
  ];
  return { status, contentType: "text/html; charset=utf-8", body: lines.join("\n") };
}
