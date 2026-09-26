import pytest

from bookmeta.isbn import normalize


@pytest.mark.parametrize(
    ("value", "expected"),
    [
        ("9789600316483", "9789600316483"),
        ("978-960-03-1648-3", "9789600316483"),
        ("9600316481", "9789600316483"),
        (" 960-03-1648-1 ", "9789600316483"),
        ("080442957X", "9780804429573"),
        ("080442957x", "9780804429573"),
        ("9780000000040", "9780000000040"),
    ],
)
def test_normalizes_valid_isbns(value: str, expected: str) -> None:
    assert normalize(value) == expected


@pytest.mark.parametrize(
    "value",
    [
        "",
        "9789600316484",
        "9600316483",
        "960031648",
        "97896003164830",
        "978960031648X",
        "9789600316483<script>",
        "ISBN9789600316483",
        "\u00a09789600316483",  # no-break space is not trimmed, same as PHP
        "".join(chr(0xFF10 + int(d)) for d in "9789600316483"),  # full-width digits
    ],
)
def test_rejects_invalid_isbns(value: str) -> None:
    assert normalize(value) is None
