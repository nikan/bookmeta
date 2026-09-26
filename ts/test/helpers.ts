import { readdirSync, readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";

import { Ajv2020, type ValidateFunction } from "ajv/dist/2020.js";
import addFormats from "ajv-formats";

export const CONTRACT = fileURLToPath(new URL("../../contract/", import.meta.url));
export const FIXTURES = CONTRACT + "fixtures/";

export function fixtureHtml(name: string): string {
  return readFileSync(`${FIXTURES}${name}.html`, "utf-8");
}

export function fixtureExpected(name: string): Record<string, string | null> {
  return JSON.parse(readFileSync(`${FIXTURES}${name}.expected.json`, "utf-8")) as Record<
    string,
    string | null
  >;
}

export function bookFixtureNames(): string[] {
  return readdirSync(FIXTURES)
    .filter((f) => f.startsWith("book_") && f.endsWith(".expected.json"))
    .map((f) => f.replace(/\.expected\.json$/, ""))
    .sort();
}

export function readContract(name: string): unknown {
  return JSON.parse(readFileSync(CONTRACT + name, "utf-8"));
}

/** Validator for contract/schema.json: the response (root) or one of its $defs. */
export function schemaValidator(definition?: string): ValidateFunction {
  const schema = readContract("schema.json") as { $defs: object };
  const ajv = new Ajv2020({ strict: true, allErrors: true });
  addFormats.default(ajv);
  return ajv.compile(
    definition === undefined ? schema : { $defs: schema.$defs, $ref: `#/$defs/${definition}` },
  );
}
