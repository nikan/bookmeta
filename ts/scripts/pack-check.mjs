// Packs the package the way `npm publish` would, starting without dist/, then
// installs the tarball into a scratch project and checks that it can be
// imported and that its CLI runs. Run with `npm run pack:check` (needs network
// for the runtime dependencies).

import { execFileSync, spawnSync } from "node:child_process";
import { mkdtempSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { fileURLToPath } from "node:url";

const pkgDir = fileURLToPath(new URL("..", import.meta.url));
const REQUIRED = [
  "package.json",
  "README.md",
  "dist/index.js",
  "dist/index.d.ts",
  "dist/cli.js",
  "dist/server.js",
];

const run = (cmd, args, cwd) => execFileSync(cmd, args, { cwd, encoding: "utf-8" });

function fail(message) {
  console.error(`pack-check: ${message}`);
  process.exit(1);
}

const work = mkdtempSync(join(tmpdir(), "bookmeta-pack-"));
try {
  // Start like a clean checkout: dist/ is gitignored, so prepack must build it.
  rmSync(join(pkgDir, "dist"), { recursive: true, force: true });

  const out = run("npm", ["pack", "--json", "--pack-destination", work], pkgDir);
  const [info] = JSON.parse(out.slice(out.indexOf("[\n")));
  const files = info.files.map((f) => f.path);

  const missing = REQUIRED.filter((f) => !files.includes(f));
  if (missing.length > 0) fail(`tarball is missing ${missing.join(", ")}`);
  const leaked = files.filter((f) => /^(src|test|scripts|node_modules)\//.test(f) || f.endsWith(".map"));
  if (leaked.length > 0) fail(`tarball should not contain ${leaked.join(", ")}`);

  writeFileSync(
    join(work, "package.json"),
    JSON.stringify({ name: "pack-check", private: true, type: "module" }),
  );
  run("npm", ["install", "--no-audit", "--no-fund", "--loglevel=error", join(work, info.filename)], work);

  run(
    "node",
    [
      "--input-type=module",
      "-e",
      `import * as m from "bookmeta";
       for (const name of ["parseBook", "parseSearchResults", "normalizeIsbn", "handle", "createApp", "BiblionetClient"]) {
         if (typeof m[name] !== "function") throw new Error("missing export " + name);
       }
       if (m.normalizeIsbn("960-03-1648-1") !== "9789600316483") throw new Error("normalizeIsbn is broken");`,
    ],
    work,
  );

  // An invalid ISBN is rejected before any network access: exit 1 with a JSON error.
  const cli = spawnSync(join(work, "node_modules", ".bin", "bookmeta"), ["123"], { encoding: "utf-8" });
  if (cli.status !== 1 || JSON.parse(cli.stdout).error !== "No valid ISBN provided.") {
    fail(`CLI did not run as expected (exit ${cli.status}): ${cli.stdout}${cli.stderr}`);
  }

  console.log(`pack-check ok: ${info.filename}, ${files.length} files`);
} finally {
  rmSync(work, { recursive: true, force: true });
}
