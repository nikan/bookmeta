"""Parser against saved biblionet pages. The expected JSON is the shared contract."""

import json

import pytest
from conftest import CONTRACT, book_fixture_names, fixture_expected, fixture_html
from jsonschema import Draft202012Validator

from bookmeta.parser import normalize_label, parse_book, parse_search_results


@pytest.mark.parametrize("name", book_fixture_names())
def test_parses_book_page(name: str) -> None:
    assert parse_book(fixture_html(name)) == fixture_expected(name)


@pytest.mark.parametrize("name", book_fixture_names())
def test_expected_output_matches_schema(name: str) -> None:
    schema = json.loads((CONTRACT / "schema.json").read_text(encoding="utf-8"))
    Draft202012Validator(schema).validate(fixture_expected(name))


def test_key_order_matches_contract() -> None:
    book = parse_book(fixture_html("book_88309"))
    assert book is not None
    assert list(book) == list(fixture_expected("book_88309"))


def test_non_book_page_returns_none() -> None:
    assert parse_book(fixture_html("search_miss")) is None


def test_empty_document_returns_none() -> None:
    assert parse_book("") is None
    assert parse_search_results("") == []


def test_search_results_list_book_paths() -> None:
    assert parse_search_results(fixture_html("search_hit")) == [
        "/εισαγωγη-σε-δυο-θεωριες-της-κοινωνικης-ανθρωπολογιας-88309"
    ]


def test_search_without_results_is_empty() -> None:
    assert parse_search_results(fixture_html("search_miss")) == []


def test_normalize_label_strips_accents_case_and_colon() -> None:
    assert normalize_label(" Συγγραφέας: \n") == "συγγραφεας"
    assert normalize_label("Γλωσσα Πρωτοτυπου:") == "γλωσσα πρωτοτυπου"
    assert normalize_label("ΘΕΜΑΣ") == "θεμασ"  # no final-sigma rule, same as PHP
