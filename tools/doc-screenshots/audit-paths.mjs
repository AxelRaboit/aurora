/**
 * Chaque adresse photographiée rend-elle une page, ou du JSON ?
 *
 * Usage : node tools/doc-screenshots/audit-paths.mjs (serveur local lancé)
 *
 * Quatre captures publiées venaient d'un point d'API : le navigateur
 * affichait le JSON sur fond sombre, et la page de documentation montrait un
 * rectangle noir ou un pavé de code. Aucun contrôle ne le voyait, parce que
 * la requête répondait 200.
 */
import { chromium } from "@playwright/test";
import { readFile } from "node:fs/promises";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const here = dirname(fileURLToPath(import.meta.url));
const BASE = "http://127.0.0.1:8000";
const source = await readFile(resolve(here, "capture-doc.mjs"), "utf8");

const pages = [...source.matchAll(/\{\s*name:\s*"([\w-]+)",\s*path:\s*"([^"]+)"/g)]
  .map(([, name, path]) => ({ name, path }));

const b = await chromium.launch();
const c = await b.newContext();
const p = await c.newPage();
p.setDefaultTimeout(15000);
await p.goto(`${BASE}/backend/platform/login`, { waitUntil: "domcontentloaded" });
await p.locator("input[type='email']").first().fill("dev@aurora.app");
await p.locator("input[type='password']").first().fill("password");
await p.locator("button[type='submit']").first().click();
await p.waitForURL(/\/backend/, { timeout: 20000 });

let bad = 0;

for (const { name, path } of pages) {
  const response = await p.request.get(`${BASE}${path}`);
  const type = response.headers()["content-type"] ?? "";
  const ok = response.status() === 200 && type.includes("text/html");

  if (!ok) {
    bad += 1;
    console.log(`  ! ${name.padEnd(26)} ${path}  -> ${response.status()} ${type.split(";")[0]}`);
  }
}

console.log(`${pages.length} adresses, ${bad} qui ne rendent pas une page`);
await b.close();
