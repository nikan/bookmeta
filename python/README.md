# bookmeta (Python)

Python port of bookmeta. Must match `../contract/` exactly; see `../docs/PORTING_PLAN.md`.

```
uv sync
uv run bookmeta 978-960-03-1648-3            # CLI, prints JSON
uv run uvicorn bookmeta.app:app --port 8080  # GET /index.php?isbn=...&format=json|html
uv run pytest -m "not live"
```
