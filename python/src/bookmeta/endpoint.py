"""The HTTP endpoint without a web framework: query parameters in, response out.

Mirrors php/src/Endpoint.php; both must pass contract/http_cases.json.
"""

import json
import logging
from collections.abc import Awaitable, Callable, Mapping
from dataclasses import dataclass

from bookmeta import isbn as isbn_mod
from bookmeta.client import UpstreamError
from bookmeta.parser import Book

Lookup = Callable[[str], Awaitable[Book | None]]

_log = logging.getLogger("bookmeta")

# Same output as PHP's htmlspecialchars(ENT_QUOTES | ENT_HTML5).
_HTML_ESCAPES = str.maketrans({"&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&apos;"})


@dataclass(frozen=True)
class Response:
    status: int
    content_type: str
    body: str


async def handle(query: Mapping[str, object], lookup: Lookup) -> Response:
    raw_format = query.get("format")
    fmt = raw_format if isinstance(raw_format, str) and raw_format != "" else "json"
    if fmt not in ("json", "html"):
        return _render(400, "json", {"error": "format must be json or html"})

    raw_isbn = query.get("isbn")
    isbn = isbn_mod.normalize(raw_isbn) if isinstance(raw_isbn, str) else None
    if isbn is None:
        return _render(400, fmt, {"error": "No valid ISBN provided."})

    try:
        book = await lookup(isbn)
    except UpstreamError as e:
        _log.warning("bookmeta: %s", e)
        return _render(502, fmt, {"error": "Could not load data from biblionet.gr.", "isbn": isbn})

    if book is None:
        return _render(404, fmt, {"error": "No book found for this ISBN.", "isbn": isbn})
    return _render(200, fmt, book)


def _render(status: int, fmt: str, data: Book) -> Response:
    if fmt == "json":
        body = json.dumps(data, ensure_ascii=False, indent=4)
        return Response(status, "application/json; charset=utf-8", body)

    def e(value: str | None) -> str:
        return (value or "").translate(_HTML_ESCAPES)

    title = f"Metadata for book with ISBN: {e(data['isbn'])}" if data.get("isbn") else "Book metadata"
    lines = ["<!DOCTYPE html>", "<html>", "<head>", '<meta charset="UTF-8">', f"<title>{title}</title>"]
    lines += ["</head>", "<body>"]
    lines += [f'<p id="{e(key)}">{e(value)}</p>' for key, value in data.items()]
    lines += ["</body>", "</html>", ""]
    return Response(status, "text/html; charset=utf-8", "\n".join(lines))
