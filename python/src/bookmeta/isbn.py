"""ISBN-10 / ISBN-13 validation and normalization.

Biblionet only matches ISBN-13 in its search, so everything is normalized
to a bare 13-digit string.
"""

import re

_ISBN10 = re.compile(r"[0-9]{9}[0-9X]")
_ISBN13 = re.compile(r"[0-9]{13}")


def normalize(value: str) -> str | None:
    """ISBN-13 (digits only) for a valid ISBN-10 or ISBN-13, else None.

    Only spaces and hyphens are tolerated as separators; any other character
    makes the input invalid.
    """
    # Same characters as PHP trim(), so both reject e.g. a leading no-break space.
    isbn = value.strip(" \t\n\r\0\x0b").replace(" ", "").replace("-", "").upper()
    if _ISBN10.fullmatch(isbn):
        return ten_to_thirteen(isbn) if is_valid10(isbn) else None
    if _ISBN13.fullmatch(isbn):
        return isbn if is_valid13(isbn) else None
    return None


def is_valid10(isbn: str) -> bool:
    if not _ISBN10.fullmatch(isbn):
        return False
    total = sum((10 - i) * (10 if c == "X" else int(c)) for i, c in enumerate(isbn))
    return total % 11 == 0


def is_valid13(isbn: str) -> bool:
    return bool(_ISBN13.fullmatch(isbn)) and _check_digit13(isbn[:12]) == isbn[12]


def ten_to_thirteen(isbn10: str) -> str:
    body = "978" + isbn10[:9]
    return body + _check_digit13(body)


def _check_digit13(first12: str) -> str:
    total = sum(int(c) * (1 if i % 2 == 0 else 3) for i, c in enumerate(first12))
    return str((10 - total % 10) % 10)
