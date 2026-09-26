/** Hono app serving the endpoint at / and /index.php (the PHP URL). */

import { Hono } from "hono";

import { BiblionetClient } from "./client.js";
import { handle, type Lookup } from "./endpoint.js";

/**
 * Query parameters as PHP's $_GET sees them, so all endpoints agree.
 * "name[]=v" (or "name[k]=v") makes "name" a list, i.e. present but not a
 * string. Later parameters override earlier ones, as in PHP.
 */
export function phpStyleQuery(params: Iterable<[string, string]>): Record<string, unknown> {
  const query: Record<string, unknown> = {};
  for (const [key, value] of params) {
    const bracket = key.indexOf("[");
    if (bracket > 0) {
      const name = key.slice(0, bracket);
      const current = query[name];
      query[name] = Array.isArray(current) ? [...(current as unknown[]), value] : [value];
    } else {
      query[key] = value;
    }
  }
  return query;
}

/** Pass a lookup to stub biblionet (tests); otherwise a real client is used. */
export function createApp(lookup?: Lookup): Hono {
  let find = lookup;
  if (find === undefined) {
    const client = new BiblionetClient();
    find = (isbn) => client.findByIsbn(isbn);
  }

  const app = new Hono();
  const book = async (requestUrl: string): Promise<Response> => {
    const query = phpStyleQuery(new URL(requestUrl).searchParams);
    const result = await handle(query, find);
    return new Response(result.body, {
      status: result.status,
      headers: { "content-type": result.contentType },
    });
  };
  app.get("/", (c) => book(c.req.url));
  app.get("/index.php", (c) => book(c.req.url));
  return app;
}
