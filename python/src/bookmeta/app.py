"""FastAPI app serving the endpoint at / and /index.php (the PHP URL).

Run: uvicorn bookmeta.app:app
"""

from collections.abc import AsyncIterator
from contextlib import asynccontextmanager

from fastapi import FastAPI, Request
from fastapi.responses import Response

from bookmeta.client import BiblionetClient
from bookmeta.endpoint import Lookup, handle


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
        result = await handle(request.query_params, request.app.state.lookup)
        return Response(result.body, status_code=result.status, headers={"content-type": result.content_type})

    app.add_api_route("/", book, methods=["GET"])
    app.add_api_route("/index.php", book, methods=["GET"])
    return app


app = create_app()
