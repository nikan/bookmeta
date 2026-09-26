/** Runs contract/http_cases.json against the endpoint with a stubbed lookup. */

import { describe, expect, it } from "vitest";

import { createApp, phpStyleQuery } from "../src/app.js";
import { UpstreamError, type BookResponse } from "../src/client.js";
import { handle, type Lookup } from "../src/endpoint.js";
import { fixtureExpected, readContract, schemaValidator } from "./helpers.js";

interface Case {
  name: string;
  query: Record<string, unknown>;
  lookup?: { returns?: string | null; raises?: string };
  expect_lookup_isbn?: string;
  status: number;
  content_type: string;
  json_fixture?: string;
  json?: unknown;
  html_contains?: string[];
  html_not_contains?: string[];
}

const SPEC = readContract("http_cases.json") as { stub_url: string; cases: Case[] };

function book(fixture: string): BookResponse {
  return { ...fixtureExpected(fixture), url: SPEC.stub_url } as BookResponse;
}

function stub(testCase: Partial<Case>, calls: string[]): Lookup {
  return (isbn) => {
    calls.push(isbn);
    if (testCase.lookup === undefined) {
      throw new Error("lookup must not be called");
    }
    if (testCase.lookup.raises === "upstream") {
      return Promise.reject(new UpstreamError("stubbed failure"));
    }
    const fixture = testCase.lookup.returns;
    return Promise.resolve(fixture === null || fixture === undefined ? null : book(fixture));
  };
}

describe("http_cases.json", () => {
  it.each(SPEC.cases.map((c) => [c.name, c] as const))("%s", async (_, testCase) => {
    const calls: string[] = [];
    const response = await handle(testCase.query, stub(testCase, calls));

    expect(response.status).toBe(testCase.status);
    expect(response.contentType.startsWith(testCase.content_type)).toBe(true);
    if (testCase.expect_lookup_isbn !== undefined) {
      expect(calls).toStrictEqual([testCase.expect_lookup_isbn]);
    }
    if (testCase.json_fixture !== undefined) {
      const body: unknown = JSON.parse(response.body);
      expect(body).toStrictEqual(book(testCase.json_fixture));
      expect(schemaValidator()(body)).toBe(true);
    }
    if (testCase.json !== undefined) {
      expect(JSON.parse(response.body)).toStrictEqual(testCase.json);
    }
    for (const needle of testCase.html_contains ?? []) {
      expect(response.body).toContain(needle);
    }
    for (const needle of testCase.html_not_contains ?? []) {
      expect(response.body).not.toContain(needle);
    }
  });
});

describe("app", () => {
  const found = SPEC.cases[0];

  it.each(["/", "/index.php"])("serves the endpoint at %s", async (path) => {
    const app = createApp(stub(found ?? {}, []));
    const response = await app.request(`${path}?isbn=9789600316483`);
    expect(response.status).toBe(200);
    expect(response.headers.get("content-type")).toBe("application/json; charset=utf-8");
    expect(await response.json()).toStrictEqual(book("book_88309"));
  });

  it.each([
    ["isbn[]=9789600316483", "No valid ISBN provided."],
    ["isbn=9789600316483&format[]=html", "format must be json or html"],
    ["isbn=9789600316483&format[x]=html", "format must be json or html"],
  ])("treats %s like PHP", async (queryString, error) => {
    const response = await createApp(stub({}, [])).request(`/index.php?${queryString}`);
    expect(response.status).toBe(400);
    expect(await response.json()).toStrictEqual({ error });
  });
});

describe("phpStyleQuery", () => {
  it("matches PHP's $_GET", () => {
    const q = (s: string) => phpStyleQuery(new URLSearchParams(s));
    expect(q("isbn=1&format=html")).toStrictEqual({ isbn: "1", format: "html" });
    expect(q("format[]=a&format[]=b")).toStrictEqual({ format: ["a", "b"] });
    expect(q("format=json&format=xml")).toStrictEqual({ format: "xml" });
    expect(q("format[]=a&format=html")).toStrictEqual({ format: "html" });
    expect(q("format=html&format[]=a")).toStrictEqual({ format: ["a"] });
  });
});
