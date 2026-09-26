/** Runs the endpoint on Node. PORT defaults to 8080. */

import { serve } from "@hono/node-server";

import { createApp } from "./app.js";

const port = Number(process.env.PORT ?? 8080);
serve({ fetch: createApp().fetch, port }, (info) => {
  console.log(`bookmeta listening on http://localhost:${String(info.port)}/?isbn=9789600316483`);
});
