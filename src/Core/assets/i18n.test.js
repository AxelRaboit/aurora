import { describe, it, expect } from "vitest";
import { createAppI18n } from "./i18n.js";

/**
 * A named format vue-i18n cannot resolve renders as an empty string, silently.
 * That is how the submissions list came to read "SUB-000001 · · fr": nothing
 * declared "short", so every date in the product was blank.
 *
 * The list below is what the components actually ask for - grep for `d(` with
 * a string second argument and it is these two, in these three languages.
 */
describe("createAppI18n", () => {
    const at = new Date("2026-03-02T14:30:00Z");

    for (const locale of ["fr", "en", "es"]) {
        for (const format of ["short", "long"]) {
            it(`formats a date as "${format}" in ${locale}`, () => {
                const rendered = createAppI18n(locale).global.d(at, format);

                expect(rendered).not.toBe("");
                expect(rendered).toMatch(/2026/);
            });
        }
    }

    /** "long" is the all-day shape: a day, and no clock beside it. */
    it("leaves the clock out of the long format", () => {
        expect(createAppI18n("fr").global.d(at, "long")).not.toMatch(
            /\d{1,2}:\d{2}/,
        );
    });
});
