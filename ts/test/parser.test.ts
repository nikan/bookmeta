/** Parser against saved biblionet pages. The expected JSON is the shared contract. */

import { describe, expect, it } from "vitest";

import { normalizeLabel, parseBook, parseSearchResults } from "../src/parser.js";
import { bookFixtureNames, fixtureExpected, fixtureHtml, schemaValidator } from "./helpers.js";

describe("parseBook", () => {
  it.each(bookFixtureNames())("parses %s", (name) => {
    expect(parseBook(fixtureHtml(name))).toStrictEqual(fixtureExpected(name));
  });

  it.each(bookFixtureNames())("expected output of %s matches the book schema", (name) => {
    const validate = schemaValidator("book");
    expect(validate(fixtureExpected(name)), JSON.stringify(validate.errors)).toBe(true);
  });

  it("keeps the contract's key order", () => {
    const book = parseBook(fixtureHtml("book_88309"));
    expect(Object.keys(book ?? {})).toStrictEqual(Object.keys(fixtureExpected("book_88309")));
  });

  it("returns null for a non-book page", () => {
    expect(parseBook(fixtureHtml("search_miss"))).toBeNull();
  });

  it("handles an empty document", () => {
    expect(parseBook("")).toBeNull();
    expect(parseSearchResults("")).toStrictEqual([]);
  });
});

describe("schema", () => {
  it("separates parser output from the response", () => {
    const book = fixtureExpected("book_88309");
    const withUrl = { ...book, url: "https://www.biblionet.gr/x" };
    expect(schemaValidator("book")(book)).toBe(true);
    expect(schemaValidator("book")(withUrl)).toBe(false);
    expect(schemaValidator()(withUrl)).toBe(true);
    expect(schemaValidator()(book)).toBe(false); // response without url
    expect(schemaValidator()({ ...withUrl, extra: null })).toBe(false);
  });
});

describe("parseSearchResults", () => {
  it("lists book paths", () => {
    expect(parseSearchResults(fixtureHtml("search_hit"))).toStrictEqual([
      "/εισαγωγη-σε-δυο-θεωριες-της-κοινωνικης-ανθρωπολογιας-88309",
    ]);
  });

  it("is empty without results", () => {
    expect(parseSearchResults(fixtureHtml("search_miss"))).toStrictEqual([]);
  });
});

describe("normalizeLabel", () => {
  it("strips accents, case and the colon", () => {
    expect(normalizeLabel(" Συγγραφέας: \n")).toBe("συγγραφεας");
    expect(normalizeLabel("Γλωσσα Πρωτοτυπου:")).toBe("γλωσσα πρωτοτυπου");
    expect(normalizeLabel("ΘΕΜΑΣ")).toBe("θεμασ"); // no final-sigma rule, same as PHP
  });
});
