"""CLI: python -m bookmeta <isbn> [--format json|html]"""

import argparse
import asyncio
import sys

from bookmeta.client import BiblionetClient
from bookmeta.endpoint import handle


async def _run(isbn: str, fmt: str) -> int:
    async with BiblionetClient() as client:
        result = await handle({"isbn": isbn, "format": fmt}, client.find_by_isbn)
    print(result.body)
    return 0 if result.status == 200 else 1


def main() -> None:
    parser = argparse.ArgumentParser(prog="bookmeta", description="Book metadata from biblionet.gr by ISBN.")
    parser.add_argument("isbn", help="ISBN-10 or ISBN-13, hyphens allowed")
    parser.add_argument("--format", choices=["json", "html"], default="json")
    args = parser.parse_args()
    sys.exit(asyncio.run(_run(args.isbn, args.format)))


if __name__ == "__main__":
    main()
