"""Runs contract/http_cases.json against the endpoint with a stubbed lookup."""

import asyncio
import json
from typing import Any

import pytest
from conftest import CONTRACT, fixture_expected, schema_validator
from fastapi.testclient import TestClient

from bookmeta.app import create_app, php_style_query
from bookmeta.client import UpstreamError
from bookmeta.endpoint import Lookup, handle
from bookmeta.parser import Book

SPEC = json.loads((CONTRACT / "http_cases.json").read_text(encoding="utf-8"))
STUB_URL: str = SPEC["stub_url"]


def book(fixture: str) -> Book:
    return {**fixture_expected(fixture), "url": STUB_URL}


def stub(case: dict[str, Any], calls: list[str]) -> Lookup:
    async def lookup(isbn: str) -> Book | None:
        calls.append(isbn)
        assert "lookup" in case, "lookup must not be called"
        if case["lookup"].get("raises") == "upstream":
            raise UpstreamError("stubbed failure")
        fixture = case["lookup"]["returns"]
        return None if fixture is None else book(fixture)

    return lookup


@pytest.mark.parametrize("case", SPEC["cases"], ids=[c["name"] for c in SPEC["cases"]])
def test_case(case: dict[str, Any]) -> None:
    calls: list[str] = []
    response = asyncio.run(handle(case["query"], stub(case, calls)))

    assert response.status == case["status"]
    assert response.content_type.startswith(case["content_type"])
    if "expect_lookup_isbn" in case:
        assert calls == [case["expect_lookup_isbn"]]
    if "json_fixture" in case:
        body = json.loads(response.body)
        assert body == book(case["json_fixture"])
        schema_validator().validate(body)
    if "json" in case:
        assert json.loads(response.body) == case["json"]
    for needle in case.get("html_contains", []):
        assert needle in response.body
    for needle in case.get("html_not_contains", []):
        assert needle not in response.body


def test_app_serves_endpoint_at_php_path() -> None:
    with TestClient(create_app(stub(SPEC["cases"][0], []))) as client:
        for path in ("/", "/index.php"):
            response = client.get(path, params={"isbn": "9789600316483"})
            assert response.status_code == 200
            assert response.headers["content-type"] == "application/json; charset=utf-8"
            assert response.json() == book("book_88309")


@pytest.mark.parametrize(
    ("query_string", "error"),
    [
        ("isbn[]=9789600316483", "No valid ISBN provided."),
        ("isbn=9789600316483&format[]=html", "format must be json or html"),
        ("isbn=9789600316483&format[x]=html", "format must be json or html"),
    ],
)
def test_app_treats_array_params_like_php(query_string: str, error: str) -> None:
    with TestClient(create_app(stub({}, []))) as client:
        response = client.get(f"/index.php?{query_string}")
        assert response.status_code == 400
        assert response.json() == {"error": error}


def test_php_style_query() -> None:
    assert php_style_query([("isbn", "1"), ("format", "html")]) == {"isbn": "1", "format": "html"}
    assert php_style_query([("format[]", "a"), ("format[]", "b")]) == {"format": ["a", "b"]}
    assert php_style_query([("format", "json"), ("format", "xml")]) == {"format": "xml"}
    assert php_style_query([("format[]", "a"), ("format", "html")]) == {"format": "html"}
    assert php_style_query([("format", "html"), ("format[]", "a")]) == {"format": ["a"]}
