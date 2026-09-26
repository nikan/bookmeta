/** Book metadata from biblionet.gr, looked up by ISBN. */

export { createApp, phpStyleQuery } from "./app.js";
export {
  BiblionetClient,
  UpstreamError,
  url,
  searchUrl,
  type BookResponse,
  type ClientOptions,
} from "./client.js";
export { handle, type EndpointResponse, type Lookup } from "./endpoint.js";
export { normalizeIsbn, isValid10, isValid13, tenToThirteen } from "./isbn.js";
export { parseBook, parseSearchResults, normalizeLabel, BASE_URL, type Book } from "./parser.js";
