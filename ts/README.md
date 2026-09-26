# bookmeta (TypeScript)

TypeScript port of bookmeta. Must match `../contract/` exactly; see `../docs/PORTING_PLAN.md`.

```
npm ci
npm run cli -- 978-960-03-1648-3   # CLI, prints JSON (or: npm run build && node dist/cli.js <isbn>)
npm run serve                      # GET http://localhost:8080/?isbn=...&format=json|html (/index.php also works)
npm test                           # offline; LIVE=1 npm test also hits biblionet.gr
npm run lint                       # eslint, prettier --check, tsc --noEmit
```
