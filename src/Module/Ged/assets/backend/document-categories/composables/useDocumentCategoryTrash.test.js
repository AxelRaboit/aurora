import { beforeEach, describe, expect, it, vi } from "vitest";
import { useDocumentCategoryTrash } from "@ged/backend/document-categories/composables/useDocumentCategoryTrash.js";

const requests = [];

vi.mock("vue-i18n", () => ({ useI18n: () => ({ t: (key) => key }) }));
vi.mock("vue-sonner", () => ({
    toast: { success: vi.fn(), error: vi.fn() },
}));
vi.mock("@/shared/composables/http/backend/useRequest.js", () => ({
    useRequest: () => ({
        request: (url, body) => {
            requests.push({ url, body });

            return Promise.resolve({ success: true, deleted: 3 });
        },
    }),
}));

const props = {
    categories: { trashedTotal: 2 },
    restorePath: "/backend/ged/categories/__id__/restore",
    forceDeletePath: "/backend/ged/categories/__id__/force-delete",
    emptyTrashPath: "/backend/ged/categories/empty-trash",
};

function setup() {
    const reload = vi.fn();

    return { api: useDocumentCategoryTrash(props, { reload }), reload };
}

describe("useDocumentCategoryTrash", () => {
    beforeEach(() => {
        requests.length = 0;
    });

    it("starts on the library, with the badge the server sent", () => {
        const { api } = setup();

        expect(api.viewingTrash.value).toBe(false);
        expect(api.trashedTotal.value).toBe(2);
    });

    it("reloads when switching to the trash and back", () => {
        const { api, reload } = setup();

        api.toggleTrash();
        expect(api.viewingTrash.value).toBe(true);

        api.toggleTrash();
        expect(api.viewingTrash.value).toBe(false);
        expect(reload).toHaveBeenCalledTimes(2);
    });

    it("keeps the badge in step with every listing response", () => {
        const { api } = setup();

        api.readTotals({ trashedTotal: 7 });
        expect(api.trashedTotal.value).toBe(7);

        // A payload without the key must not blank the badge.
        api.readTotals({});
        expect(api.trashedTotal.value).toBe(7);
    });

    it("restores one category through its own path", async () => {
        const { api, reload } = setup();

        await api.restore({ id: 5 });

        expect(requests[0].url).toBe("/backend/ged/categories/5/restore");
        expect(reload).toHaveBeenCalledTimes(1);
    });

    it("asks before destroying a category, and only then calls force-delete", async () => {
        const { api } = setup();

        api.askForceDelete({ id: 5, name: "Factures" });
        expect(api.pendingForceDelete.value.id).toBe(5);
        expect(requests).toHaveLength(0);

        await api.doForceDelete();

        expect(requests[0].url).toBe("/backend/ged/categories/5/force-delete");
        expect(api.pendingForceDelete.value).toBeNull();
    });

    it("empties the trash and closes its confirmation", async () => {
        const { api } = setup();
        api.confirmEmptyTrash.value = true;

        await api.emptyTrash();

        expect(requests[0].url).toBe("/backend/ged/categories/empty-trash");
        expect(api.confirmEmptyTrash.value).toBe(false);
        expect(api.emptyingTrash.value).toBe(false);
    });
});
