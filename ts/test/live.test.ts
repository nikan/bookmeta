/**
 * Hits the real biblionet.gr. Skipped unless LIVE=1; run with: make live-test-ts.
 * A failure here while the fixture tests pass means biblionet changed its markup.
 */

import { describe, expect, it } from "vitest";

import { BiblionetClient } from "../src/client.js";

describe.skipIf(process.env.LIVE !== "1")("live biblionet.gr", () => {
  it("finds a known book", { timeout: 60_000 }, async () => {
    const book = await new BiblionetClient().findByIsbn("9789600316483");
    expect(book?.isbn).toBe("9789600316483");
    expect(book?.authors).toBe("Louis Dumont");
    expect(book?.publisher).toBe("Εκδόσεις Καστανιώτη");
  });

  it("returns null for an unknown ISBN", { timeout: 60_000 }, async () => {
    expect(await new BiblionetClient().findByIsbn("9780000000002")).toBeNull();
  });
});
