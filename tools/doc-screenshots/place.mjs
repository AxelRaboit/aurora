/**
 * Range les captures fraîches là où les pages les réclament.
 *
 * La source de vérité est le Markdown : une page écrit
 * `![…](../../images/02-editorial/cycle-de-vie-01-….png)`, et ce chemin dit
 * à la fois la rubrique et le nom du fichier. Tenir une seconde table qui
 * dirait la même chose serait une table à garder en phase avec la première,
 * ce qui est exactement la mécanique qui a fini par publier un écran vide.
 *
 * Ne touche à rien d'autre : ce qui est cité mais absent du dossier de
 * transit est signalé, ce qui traîne dans le transit sans être cité est
 * ignoré. La suppression d'une capture devenue inutile se fait à la main,
 * et le test `DocumentationImagesTest` la réclame.
 *
 * Usage : node tools/doc-screenshots/place.mjs [--dry]
 */
import { copyFile, mkdir, readdir, readFile, stat } from "node:fs/promises";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, "../..");
const contentDir = resolve(root, "src/Module/Documentation/content");
const imagesDir = resolve(root, "src/Module/Documentation/images");
const stagingDir = resolve(root, "var/doc-screenshots/out");
const dry = process.argv.includes("--dry");

const REFERENCE = /\]\(\.\.\/\.\.\/images\/([\w-]+)\/([\w.-]+\.png)\)/g;

async function markdownFiles(dir) {
  const found = [];

  for (const entry of await readdir(dir, { withFileTypes: true })) {
    const path = resolve(dir, entry.name);

    if (entry.isDirectory()) {
      found.push(...(await markdownFiles(path)));
    } else if (entry.name.endsWith(".md")) {
      found.push(path);
    }
  }

  return found;
}

const wanted = new Map();

for (const file of await markdownFiles(contentDir)) {
  const text = await readFile(file, "utf8");

  for (const [, rubric, name] of text.matchAll(REFERENCE)) {
    wanted.set(`${rubric}/${name}`, { rubric, name });
  }
}

let placed = 0;
let absent = 0;
let already = 0;

for (const [reference, { rubric, name }] of wanted) {
  const target = resolve(imagesDir, rubric, name);
  const source = resolve(stagingDir, name);

  const fresh = await stat(source).catch(() => null);

  if (null === fresh) {
    // Déjà en place : la capture n'a pas été reprise, c'est le cas courant.
    if (null !== (await stat(target).catch(() => null))) {
      already += 1;

      continue;
    }

    console.log(`  ! ${reference} : citée, absente du transit et du module`);
    absent += 1;

    continue;
  }

  const current = await stat(target).catch(() => null);

  if (null !== current && current.mtimeMs >= fresh.mtimeMs) {
    already += 1;

    continue;
  }

  console.log(`  + ${reference}`);

  if (!dry) {
    await mkdir(dirname(target), { recursive: true });
    await copyFile(source, target);
  }

  placed += 1;
}

console.log(
  `${wanted.size} captures citées : ${placed} ${dry ? "à copier" : "copiées"}, ${already} déjà à jour, ${absent} introuvables`,
);

if (absent > 0) {
  process.exitCode = 1;
}
