/**
 * Captures the screenshots the public tour of Aurora is illustrated with.
 *
 * The tour at /fr/page/aurora is one card per subject, each with a picture of
 * the screen it describes. Those pictures were taken by hand, one at a time,
 * which is why two of them are duplicates of the same screen and why the
 * accounting module has none at all: its three screens could not even be
 * opened locally until the route-template bug was fixed.
 *
 * So this exists to make them reproducible. Point it at a local instance
 * loaded with `make demo`, run it, and every card's picture is regenerated at
 * the same size, in the same theme, with the same demo data.
 *
 * **Local only, and demo data only.** These images go on a public page. A
 * capture taken against production would put a real customer's name and SIRET
 * on it, the audit log would carry a real address, and the sidebar footer
 * would show a personal email. The fixtures exist precisely so that none of
 * that is ever in frame.
 *
 * Authentication reuses the fixture account the repository's own end-to-end
 * tests sign in with (`dev@aurora.app`, seeded by `AppFixtures` with a
 * password that is a literal in this public repository). Nothing secret is
 * handled here, and nothing but a local instance will accept it.
 *
 * Usage:
 *   node tools/screenshots/capture-tour.mjs                  # everything
 *   node tools/screenshots/capture-tour.mjs contracts trames  # by name
 *
 * Output: var/screenshots/<name>.png, git-ignored.
 */

import { chromium } from "@playwright/test";
import { mkdir } from "node:fs/promises";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "../..");
const outDir = resolve(root, "var/screenshots");

const BASE_URL = process.env.TOUR_BASE_URL ?? "http://127.0.0.1:8000";
const EMAIL = process.env.TOUR_EMAIL ?? "dev@aurora.app";
const PASSWORD = process.env.TOUR_PASSWORD ?? "password";

// The word typed into the search palette. A parameter because the point of
// that picture is a query that answers in several sections at once, and which
// word does that depends on what the demo fixtures happen to hold.
const SEARCH_QUERY = process.env.TOUR_SEARCH_QUERY ?? "aurora";

// The size every existing tour capture already has. Kept identical so a
// regenerated picture drops into a card without the crop moving.
const VIEWPORT = { width: 1600, height: 1000 };

/**
 * One entry per picture.
 *
 * `prepare` runs after the page has loaded and before the shutter: it is where
 * a panel gets opened or a query typed, because several of these screens only
 * say what they do once something is on them.
 */
const SHOTS = [
    { name: "contracts", path: "/backend/studio/contracts" },
    { name: "trames", path: "/backend/studio/contract-templates" },
    { name: "customers", path: "/backend/studio/customers" },
    {
        name: "contract-document",
        // The concluded one: the only state that shows the seal, both
        // signatures and the amendment chain at once. Reached through the row
        // actions rather than by a hard-coded id, because ids depend on what
        // the database already held when the fixtures ran.
        path: "/backend/studio/contracts",
        async prepare(page) {
            // The countersigned one by its status rather than its reference:
            // references depend on what the sequence had already issued.
            const row = page.getByRole("row").filter({ hasText: "Contresigné" }).first();
            await row.getByRole("button", { name: /^Actions pour/ }).click();
            await page.locator("a[href*='/backend/studio/contracts/']").first().click();
            await page.waitForLoadState("networkidle");
        },
    },
    { name: "audit", path: "/dev/dashboard/audit" },
    {
        name: "calendar-week",
        // The week view rather than the month one the card already shows: it
        // is the view where an event has an hour, and where overlapping
        // appointments have to be drawn side by side rather than on top of
        // each other.
        path: "/backend/planning/calendar",
        async prepare(page) {
            await page.getByRole("button", { name: /^Semaine$/ }).click();
            await page.waitForTimeout(1_000);

            // The grid opens on the current hour, so a capture taken in the
            // evening shows an empty afternoon while every demo event sits in
            // the morning. The wheel over the grid is what the component
            // listens to; setting scrollTop on a guessed element is not.
            // Wound to the top first, then down by a fixed amount: the grid
            // opens on the current hour, so scrolling by a delta alone lands
            // somewhere different depending on when the capture is taken.
            await page.mouse.move(1000, 600);
            await page.mouse.wheel(0, -2_000);
            await page.waitForTimeout(300);
            await page.mouse.wheel(0, 530);
            await page.waitForTimeout(600);
        },
    },
    {
        name: "search",
        path: "/backend",
        async prepare(page) {
            // No keyboard shortcut opens it: the button is the only door, and
            // it announces itself, which is what makes it findable here.
            await page.getByRole("button", { name: "Rechercher…" }).click();

            const field = page.getByPlaceholder(/Rechercher des contenus/);
            await field.waitFor({ state: "visible", timeout: 5_000 });
            await field.fill(SEARCH_QUERY);
            // The palette answers as you type; give it its round trip rather
            // than a fixed sleep long enough to be wrong on a slow machine.
            await page.waitForTimeout(1_500);
        },
    },
];

async function login(page) {
    await page.goto(`${BASE_URL}/backend/platform/login`, { waitUntil: "domcontentloaded" });
    await page.locator("input[type='email'], input[name*='email']").first().fill(EMAIL);
    await page.locator("input[type='password']").first().fill(PASSWORD);
    await page.locator("button[type='submit']").first().click();
    await page.waitForURL(/\/backend/, { timeout: 15_000 });
}

/**
 * Everything that belongs to the developer rather than to the product.
 *
 * The Symfony toolbar is the obvious one. The rest is quieter and shows up
 * only once the picture is beside the others: a focus ring left on whatever
 * was clicked, and the caret blinking in a field.
 */
async function hideChrome(page) {
    await page.addStyleTag({
        content: `
            .sf-toolbar, .sf-minitoolbar, #sfToolbarMainContent, #sfToolbarClearer { display: none !important; }
            *, *::before, *::after { caret-color: transparent !important; }
            :focus-visible { outline: none !important; }
        `,
    });
}

const wanted = process.argv.slice(2);
const shots = wanted.length === 0 ? SHOTS : SHOTS.filter((shot) => wanted.includes(shot.name));

if (shots.length === 0) {
    console.error(`aucune capture nommee ${wanted.join(", ")}`);
    console.error(`disponibles : ${SHOTS.map((shot) => shot.name).join(", ")}`);
    process.exit(1);
}

await mkdir(outDir, { recursive: true });

const browser = await chromium.launch();
const context = await browser.newContext({
    viewport: VIEWPORT,
    deviceScaleFactor: 1,
    locale: "fr-FR",
    timezoneId: "Europe/Paris",
    // The tour is shown in the dark theme, which is what the twenty-eight
    // existing captures are in.
    colorScheme: "dark",
});
const page = await context.newPage();

await login(page);

let failed = 0;

for (const shot of shots) {
    const file = resolve(outDir, `${shot.name}.png`);

    try {
        await page.goto(`${BASE_URL}${shot.path}`, { waitUntil: "networkidle" });
        await hideChrome(page);

        if (shot.prepare) {
            await shot.prepare(page);
            await hideChrome(page);
        }

        await page.screenshot({ path: file });
        console.log(`+ ${shot.name} -> var/screenshots/${shot.name}.png`);
    } catch (error) {
        failed += 1;
        console.error(`! ${shot.name} : ${error.message.split("\n")[0]}`);
    }
}

await browser.close();

process.exit(failed === 0 ? 0 : 1);
