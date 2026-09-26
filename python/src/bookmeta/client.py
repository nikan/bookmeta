"""Fetches pages from biblionet.gr and looks books up by ISBN."""

from types import TracebackType
from typing import Self
from urllib.parse import quote, urlencode

import httpx

from bookmeta.parser import BASE_URL, Book, parse_book, parse_search_results

_SEARCH_PATH = "/συνθετη-αναζητηση"
_MAX_CANDIDATES = 5
_USER_AGENT = "bookmeta/1.0 (+https://github.com/nikan/bookmeta)"


class UpstreamError(Exception):
    """biblionet.gr could not be reached or answered with an error."""


def url(path: str) -> str:
    """Absolute URL for a site path, with each (Greek) path segment percent-encoded."""
    segments = path.lstrip("/").split("/")
    return BASE_URL + "/" + "/".join(quote(s, safe="") for s in segments)


def search_url(query: str) -> str:
    return url(_SEARCH_PATH) + "?" + urlencode({"q": query})


class BiblionetClient:
    def __init__(self, http: httpx.AsyncClient | None = None, timeout_seconds: float = 15.0) -> None:
        self._http = http or httpx.AsyncClient(
            follow_redirects=True,
            max_redirects=5,
            timeout=httpx.Timeout(timeout_seconds, connect=5.0),
            headers={"User-Agent": _USER_AGENT},
        )

    async def find_by_isbn(self, isbn13: str) -> Book | None:
        """Metadata for the book with this ISBN-13, or None if biblionet has none.

        The search is free text, so each candidate is checked against its ISBN
        field before it is accepted.
        """
        paths = parse_search_results(await self.get(search_url(isbn13)))
        for path in paths[:_MAX_CANDIDATES]:
            page_url = url(path)
            book = parse_book(await self.get(page_url))
            if book is not None and book["isbn"] == isbn13:
                return {**book, "url": page_url}
        return None

    async def get(self, page_url: str) -> str:
        try:
            response = await self._http.get(page_url)
        except httpx.HTTPError as e:
            raise UpstreamError(f"Request to {page_url} failed: {e}") from e
        if response.status_code != 200:
            raise UpstreamError(f"Request to {page_url} returned HTTP {response.status_code}")
        return response.text

    async def aclose(self) -> None:
        await self._http.aclose()

    async def __aenter__(self) -> Self:
        return self

    async def __aexit__(
        self,
        exc_type: type[BaseException] | None,
        exc: BaseException | None,
        tb: TracebackType | None,
    ) -> None:
        await self.aclose()
