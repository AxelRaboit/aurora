import { describe, it, expect, vi, beforeEach } from "vitest";

vi.mock("vue-i18n", () => ({
    useI18n: () => ({ t: (key) => key }),
}));

vi.mock("vue-sonner", () => ({
    toast: { success: vi.fn(), error: vi.fn() },
}));

const { useNotesTrash } = await import("./useNotesTrash.js");

function makeApi(notes = []) {
    return {
        trash: vi.fn().mockResolvedValue({ ok: true, payload: { notes } }),
        restore: vi.fn().mockResolvedValue({ ok: true, payload: {} }),
        forceDelete: vi.fn().mockResolvedValue({ ok: true, payload: {} }),
    };
}

describe("useNotesTrash", () => {
    let api;
    let onChanged;

    beforeEach(() => {
        api = makeApi([{ id: 3, title: "Brouillon" }]);
        onChanged = vi.fn();
    });

    it("stays closed and empty until asked", () => {
        const trash = useNotesTrash(api, { onChanged });

        expect(trash.open.value).toBe(false);
        expect(trash.notes.value).toEqual([]);
        expect(api.trash).not.toHaveBeenCalled();
    });

    it("opens and loads what is waiting", async () => {
        const trash = useNotesTrash(api, { onChanged });

        await trash.openTrash();

        expect(trash.open.value).toBe(true);
        expect(trash.loading.value).toBe(false);
        expect(trash.notes.value).toHaveLength(1);
    });

    it("tells the tree about a restore, since the note comes back into it", async () => {
        const trash = useNotesTrash(api, { onChanged });

        await trash.restore({ id: 3 });

        expect(api.restore).toHaveBeenCalledWith(3);
        expect(onChanged).toHaveBeenCalledTimes(1);
    });

    it("keeps the tree out of a permanent deletion, which changes nothing in it", async () => {
        const trash = useNotesTrash(api, { onChanged });

        await trash.forceDelete({ id: 3 });

        expect(api.forceDelete).toHaveBeenCalledWith(3);
        expect(onChanged).not.toHaveBeenCalled();
        expect(api.trash).toHaveBeenCalledTimes(1);
    });

    it("leaves the list alone when the server refuses", async () => {
        api.trash.mockResolvedValue({ ok: false, payload: {} });
        const trash = useNotesTrash(api, { onChanged });

        await trash.openTrash();

        expect(trash.notes.value).toEqual([]);
        expect(trash.loading.value).toBe(false);
    });
});
