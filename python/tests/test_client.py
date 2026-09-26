"""Lookup flow against a fake biblionet built from the contract fixtures."""

import asyncio
from collections.abc import Callable

import httpx
import pytest
from conftest import fixture_expected, fixture_html

from bookmeta.client import BiblionetClient, UpstreamError, search_url, url

BOOK_PATH = "/εισαγωγη-σε-δυο-θεωριες-της-κοινωνικης-ανθρωπολογιας-88309"


def fake_biblionet(pages: dict[str, httpx.Response]) -> Callable[[httpx.Request], httpx.Response]:
    def respond(request: httpx.Request) -> httpx.Response:
        return pages.get(str(request.url), httpx.Response(404))

    return respond


def find(isbn: str, pages: dict[str, httpx.Response]) -> object:
    async def run() -> object:
        http = httpx.AsyncClient(transport=httpx.MockTransport(fake_biblionet(pages)))
        async with BiblionetClient(http) as client:
            return await client.find_by_isbn(isbn)

    return asyncio.run(run())


def page(name: str) -> httpx.Response:
    return httpx.Response(200, text=fixture_html(name))


def test_url_percent_encodes_greek_path_segments() -> None:
    assert (
        url("/αστεριξ-306233") == "https://www.biblionet.gr/%CE%B1%CF%83%CF%84%CE%B5%CF%81%CE%B9%CE%BE-306233"
    )


def test_url_encodes_reserved_characters_in_slug() -> None:
    assert url("/a:-b-1") == "https://www.biblionet.gr/a%3A-b-1"


def test_finds_book_and_adds_url() -> None:
    pages = {search_url("9789600316483"): page("search_hit"), url(BOOK_PATH): page("book_88309")}
    assert find("9789600316483", pages) == {**fixture_expected("book_88309"), "url": url(BOOK_PATH)}


def test_rejects_candidate_with_different_isbn() -> None:
    pages = {search_url("9789603215066"): page("search_hit"), url(BOOK_PATH): page("book_88309")}
    assert find("9789603215066", pages) is None


def test_no_search_results_is_none() -> None:
    assert find("9780000000002", {search_url("9780000000002"): page("search_miss")}) is None


def test_http_error_raises_upstream_error() -> None:
    with pytest.raises(UpstreamError, match="HTTP 503"):
        find("9789600316483", {search_url("9789600316483"): httpx.Response(503)})


def test_network_error_raises_upstream_error() -> None:
    def fail(request: httpx.Request) -> httpx.Response:
        raise httpx.ConnectError("boom", request=request)

    async def run() -> None:
        async with BiblionetClient(httpx.AsyncClient(transport=httpx.MockTransport(fail))) as client:
            await client.find_by_isbn("9789600316483")

    with pytest.raises(UpstreamError, match="boom"):
        asyncio.run(run())
