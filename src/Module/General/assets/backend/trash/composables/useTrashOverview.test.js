import { describe, it, expect, vi } from "vitest";

vi.mock("@/shared/nav/navMeta.js", () => ({
    resolveNavIcon: (name) => `icon:${name}`,
}));

const { useTrashOverview, daysLeft } = await import("./useTrashOverview.js");

const DAY = 86_400_000;

describe("daysLeft", () => {
    it("counts the days left before the purge takes the oldest row", () => {
        const deletedAt = new Date(Date.now() - 4 * DAY).toISOString();

        expect(daysLeft(deletedAt, 30)).toBe(26);
    });

    it("says nothing when the trash is empty or the purge is off", () => {
        expect(daysLeft(null, 30)).toBeNull();
        expect(daysLeft(new Date().toISOString(), 0)).toBeNull();
    });

    it("never counts backwards, since the purge only runs at night", () => {
        const deletedAt = new Date(Date.now() - 40 * DAY).toISOString();

        expect(daysLeft(deletedAt, 30)).toBe(0);
    });
});

describe("useTrashOverview", () => {
    it("resolves each row's icon and totals what is waiting", () => {
        const { rows, total } = useTrashOverview({
            trashes: [
                {
                    key: "ged_documents",
                    icon: "file-text",
                    count: 3,
                    oldestDeletedAt: null,
                },
                {
                    key: "notes",
                    icon: "sticky-note",
                    count: 2,
                    oldestDeletedAt: null,
                },
            ],
            retentionDays: 30,
        });

        expect(rows.value[0].iconComponent).toBe("icon:file-text");
        expect(total.value).toBe(5);
    });

    it("holds up with nothing contributed", () => {
        const { rows, total } = useTrashOverview({
            trashes: [],
            retentionDays: 30,
        });

        expect(rows.value).toEqual([]);
        expect(total.value).toBe(0);
    });
});
