# bookmeta (Python)

Python port of bookmeta. Must match `../contract/` exactly; see `../docs/PORTING_PLAN.md`.

```
uv sync
uv run bookmeta 978-960-03-1648-3            # CLI, prints JSON
uv run uvicorn bookmeta.app:app --port 8080  # GET /?isbn=...&format=json|html (/index.php also works)
uv run pytest -m "not live"
```
