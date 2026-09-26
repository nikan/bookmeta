import json
from pathlib import Path
from typing import Any

CONTRACT = Path(__file__).resolve().parents[2] / "contract"
FIXTURES = CONTRACT / "fixtures"


def fixture_html(name: str) -> str:
    return (FIXTURES / f"{name}.html").read_text(encoding="utf-8")


def fixture_expected(name: str) -> Any:
    return json.loads((FIXTURES / f"{name}.expected.json").read_text(encoding="utf-8"))


def book_fixture_names() -> list[str]:
    return sorted(p.name.removesuffix(".expected.json") for p in FIXTURES.glob("book_*.expected.json"))
