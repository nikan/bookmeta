"""FastAPI app serving the endpoint at / and /index.php (the PHP URL).

Run: uvicorn bookmeta.app:app
"""

from collections.abc import AsyncIterator, Iterable
from contextlib import asynccontextmanager

from fastapi import FastAPI, Request
from fastapi.responses import Response

from bookmeta.client import BiblionetClient
from bookmeta.endpoint import Lookup, handle


def php_style_query(items: Iterable[tuple[str, str]]) -> dict[str, object]:
    """Query parameters as PHP's $_GET sees them, so both endpoints agree.

    "name[]=v" (or "name[k]=v") makes "name" a list, i.e. present but not a
    string. Later parameters override earlier ones, as in PHP.
    """
    query: dict[str, object] = {}
    for key, value in items:
        bracket = key.find("[")
        if bracket > 0:
            name = key[:bracket]
            current = query.get(name)
            query[name] = [*current, value] if isinstance(current, list) else [value]
        else:
            query[key] = value
    return query


def create_app(lookup: Lookup | None = None) -> FastAPI:
    """Pass a lookup to stub biblionet (tests); otherwise a real client is used."""

    @asynccontextmanager
    async def lifespan(app: FastAPI) -> AsyncIterator[None]:
        if lookup is not None:
            app.state.lookup = lookup
            yield
            return
        async with BiblionetClient() as client:
            app.state.lookup = client.find_by_isbn
            yield

    app = FastAPI(title="bookmeta", lifespan=lifespan, docs_url=None, redoc_url=None, openapi_url=None)

    async def book(request: Request) -> Response:
        query = php_style_query(request.query_params.multi_items())
        result = await handle(query, request.app.state.lookup)
        return Response(result.body, status_code=result.status, headers={"content-type": result.content_type})

    app.add_api_route("/", book, methods=["GET"])
    app.add_api_route("/index.php", book, methods=["GET"])
    return app


app = create_app()
