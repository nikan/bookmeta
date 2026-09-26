#!/usr/bin/env node
/** CLI: bookmeta <isbn> [--format json|html] */

import { parseArgs } from "node:util";

import { BiblionetClient } from "./client.js";
import { handle } from "./endpoint.js";

const USAGE = "usage: bookmeta <isbn> [--format json|html]";

async function main(): Promise<number> {
  const { values, positionals } = parseArgs({
    allowPositionals: true,
    options: { format: { type: "string", default: "json" }, help: { type: "boolean", short: "h" } },
  });
  const [isbn] = positionals;
  if (values.help === true || isbn === undefined || positionals.length > 1) {
    console.error(USAGE);
    return 2;
  }
  if (values.format !== "json" && values.format !== "html") {
    console.error(USAGE);
    return 2;
  }
  const client = new BiblionetClient();
  const result = await handle({ isbn, format: values.format }, (i) => client.findByIsbn(i));
  console.log(result.body);
  return result.status === 200 ? 0 : 1;
}

process.exitCode = await main();
