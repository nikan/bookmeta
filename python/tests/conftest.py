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


def schema_validator(definition: str | None = None) -> Any:
    """Validator for contract/schema.json: the response (root) or one of its $defs."""
    from jsonschema import Draft202012Validator

    schema = json.loads((CONTRACT / "schema.json").read_text(encoding="utf-8"))
    if definition is not None:
        schema = {"$defs": schema["$defs"], "$ref": f"#/$defs/{definition}"}
    return Draft202012Validator(schema)
