"""Turns biblionet.gr HTML into book metadata. Pure: no network access.

This is 100% dependent on the biblionet page structure. When the site
changes its markup, refresh the fixtures (make fixtures) and adjust here.
Keep it in step with php/src/BiblionetParser.php: both must produce
contract/fixtures/*.expected.json.
"""

import re
import unicodedata
from typing import cast

from lxml import html as lxml_html
from lxml.etree import ParserError
from lxml.html import HtmlElement

from bookmeta import isbn as isbn_mod

BASE_URL = "https://www.biblionet.gr"

Book = dict[str, str | None]

# Labels as they appear on the page, after normalize_label().
_LABEL_AUTHORS = "συγγραφεας"
_LABEL_TRANSLATORS = "μεταφραση"
_LABEL_PUBLISHER = "εκδοτης"
_LABEL_PUBLISHED = "ημ. εκδοσης"
_LABEL_ORIGINAL_LANGUAGE = "γλωσσα πρωτοτυπου"
_LABEL_ISBN = "isbn"
_LABEL_SUBJECTS = "θεμα"

_WHITESPACE = re.compile(r"\s+")
_SUBJECT_CODE = re.compile(r"^\[[^\]]*\]\s*")
_YEAR = re.compile(r"\b([0-9]{4})\b", re.ASCII)


def parse_search_results(html: str) -> list[str]:
    """Book page paths (e.g. "/some-title-88309") in page order, without duplicates."""
    root = _load(html)
    if root is None:
        return []
    hrefs = _strings(root.xpath(f"//div[@id='result_books']//a[{_has_class('book-title')}]/@href"))
    return list(dict.fromkeys(h.strip() for h in hrefs))


def parse_book(html: str) -> Book | None:
    """Metadata from a book page, or None if it is not a book page.

    Missing fields are None. Multi-valued fields are joined with ", ".
    """
    root = _load(html)
    if root is None:
        return None
    sections = _elements(root.xpath("//section[@id='book_info']"))
    if not sections:
        return None
    section = sections[0]

    attributes: dict[str, str] = {}
    contributors: dict[str, list[str]] = {}
    subjects: list[str] = []
    for li in _elements(section.xpath(".//li")):
        label = normalize_label("".join(_strings(li.xpath("text()"))))
        if li.xpath(f"ancestor::div[{_has_class('contributors-list')}]"):
            contributors.setdefault(label, []).extend(_texts(li.xpath(".//a")))
        elif label == _LABEL_SUBJECTS:
            subjects = [_SUBJECT_CODE.sub("", s) for s in _texts(li.xpath(".//a"))]
        else:
            strong = _elements(li.xpath(".//strong"))
            if strong:
                attributes[label] = _clean(strong[0].text_content())

    headings = _elements(section.xpath(".//h1"))
    title = headings[0] if headings else None
    page_isbn = attributes.get(_LABEL_ISBN, "")
    year = _YEAR.search(attributes.get(_LABEL_PUBLISHED, ""))

    return {
        "isbn": isbn_mod.normalize(page_isbn) if page_isbn else None,
        "biblionetid": _biblionet_id(section),
        "cover_url": _cover_url(section),
        "title": _none_if_empty(_clean(title.text_content())) if title is not None else None,
        "subtitle": _first_text(title, f"following-sibling::p[{_has_class('text-2')}][1]"),
        "authors": _join(contributors.get(_LABEL_AUTHORS, [])),
        "translators": _join(contributors.get(_LABEL_TRANSLATORS, [])),
        "publisher": _none_if_empty(attributes.get(_LABEL_PUBLISHER, "")),
        "yr_published": year.group(1) if year else None,
        "original_language": _none_if_empty(attributes.get(_LABEL_ORIGINAL_LANGUAGE, "")),
        "original_title": _first_text(title, "following-sibling::h3[1]"),
        "categories": _join(subjects),
    }


def normalize_label(label: str) -> str:
    """Lowercase, accent-free, trimmed label without the trailing colon."""
    decomposed = unicodedata.normalize("NFD", _clean(label))
    stripped = "".join(c for c in decomposed if not unicodedata.combining(c))
    # Per-character lowercasing, like PHP's mb_strtolower (no final-sigma rule).
    return "".join(c.lower() for c in stripped).rstrip(": \u00a0")


def _load(html: str) -> HtmlElement | None:
    try:
        return lxml_html.document_fromstring(html)
    except ParserError:  # empty document
        return None


def _has_class(name: str) -> str:
    return f"contains(concat(' ', normalize-space(@class), ' '), ' {name} ')"


def _biblionet_id(section: HtmlElement) -> str | None:
    for value in _strings(section.xpath(".//a[starts-with(@id, 'fav_btn_')]/@id"))[:1]:
        if match := re.search(r"(\d+)$", value, re.ASCII):
            return match.group(1)
    for value in _strings(section.xpath(".//img/@src"))[:1]:
        if match := re.search(r"/book_(\d+)/", value, re.ASCII):
            return match.group(1)
    return None


def _cover_url(section: HtmlElement) -> str | None:
    sources = _strings(section.xpath(f".//div[{_has_class('product-thumb-info-image')}]//img/@src"))
    if not sources:
        return None
    src = sources[0].strip()
    if not src or "/placeholders/" in src:
        return None
    return BASE_URL + src if src.startswith("/") else src


def _first_text(context: HtmlElement | None, query: str) -> str | None:
    if context is None:
        return None
    nodes = _elements(context.xpath(query))
    return _none_if_empty(_clean(nodes[0].text_content())) if nodes else None


def _texts(nodes: object) -> list[str]:
    return [t for t in (_clean(n.text_content()) for n in _elements(nodes)) if t]


def _elements(result: object) -> list[HtmlElement]:
    return cast(list[HtmlElement], result)


def _strings(result: object) -> list[str]:
    return [str(s) for s in cast(list[object], result)]


def _clean(text: str) -> str:
    return _WHITESPACE.sub(" ", text).strip()


def _join(values: list[str]) -> str | None:
    return ", ".join(values) if values else None


def _none_if_empty(value: str) -> str | None:
    return value or None
