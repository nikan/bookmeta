import { describe, expect, it } from "vitest";

import { normalizeIsbn } from "../src/isbn.js";

describe("normalizeIsbn", () => {
  it.each([
    ["9789600316483", "9789600316483"],
    ["978-960-03-1648-3", "9789600316483"],
    ["9600316481", "9789600316483"],
    [" 960-03-1648-1 ", "9789600316483"],
    ["080442957X", "9780804429573"],
    ["080442957x", "9780804429573"],
    ["9780000000040", "9780000000040"],
  ])("normalizes %j to %s", (value, expected) => {
    expect(normalizeIsbn(value)).toBe(expected);
  });

  it.each([
    "",
    "9789600316484",
    "9600316483",
    "960031648",
    "97896003164830",
    "978960031648X",
    "9789600316483<script>",
    "ISBN9789600316483",
    " 9789600316483", // no-break space is not trimmed, same as PHP
    Array.from("9789600316483", (d) => String.fromCharCode(0xff10 + Number(d))).join(""), // full-width digits
  ])("rejects %j", (value) => {
    expect(normalizeIsbn(value)).toBeNull();
  });
});
