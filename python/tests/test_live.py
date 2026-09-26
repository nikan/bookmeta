"""Hits the real biblionet.gr. Excluded by default; run with: make live-test-py.

A failure here while the fixture tests pass means biblionet changed its markup.
"""

import asyncio

import pytest

from bookmeta.client import BiblionetClient
from bookmeta.parser import Book

pytestmark = pytest.mark.live


def find(isbn: str) -> Book | None:
    async def run() -> Book | None:
        async with BiblionetClient() as client:
            return await client.find_by_isbn(isbn)

    return asyncio.run(run())


def test_finds_known_book() -> None:
    book = find("9789600316483")
    assert book is not None
    assert book["isbn"] == "9789600316483"
    assert book["authors"] == "Louis Dumont"
    assert book["publisher"] == "Εκδόσεις Καστανιώτη"


def test_unknown_isbn_returns_none() -> None:
    assert find("9780000000002") is None
