import { ref } from "vue";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { useDocumentTrash } from "@ged/backend/documents/composables/useDocumentTrash.js";

const requests = [];

vi.mock("vue-i18n", () => ({ useI18n: () => ({ t: (key) => key }) }));
vi.mock("vue-sonner", () => ({
    toast: { success: vi.fn(), error: vi.fn() },
}));
vi.mock("@/shared/composables/http/backend/useRequest.js", () => ({
    useRequest: () => ({
        request: (url, body) => {
            requests.push({ url, body });

            return Promise.resolve({ success: true, deleted: 2 });
        },
    }),
}));

const props = {
    restorePath: "/backend/ged/documents/__id__/restore",
    forceDeletePath: "/backend/ged/documents/__id__/force-delete",
    bulkRestorePath: "/backend/ged/documents/bulk-restore",
    emptyTrashPath: "/backend/ged/documents/empty-trash",
};

function setup() {
    const selectedIds = ref(new Set([4, 7]));
    const clearSelection = vi.fn(() => selectedIds.value.clear());
    const reload = vi.fn();

    return {
        api: useDocumentTrash(props, { selectedIds, clearSelection, reload }),
        clearSelection,
        reload,
    };
}

describe("useDocumentTrash", () => {
    beforeEach(() => {
        requests.length = 0;
    });

    it("restores one document through its own path, then reloads", async () => {
        const { api, reload } = setup();

        await api.restore({ id: 12 });

        expect(requests[0].url).toBe("/backend/ged/documents/12/restore");
        expect(reload).toHaveBeenCalledTimes(1);
    });

    it("asks before destroying a document, and only then calls force-delete", async () => {
        const { api } = setup();

        api.askForceDelete({ id: 12, title: "Contrat" });

        expect(api.pendingForceDelete.value.id).toBe(12);
        expect(requests).toHaveLength(0);

        await api.doForceDelete();

        expect(requests[0].url).toBe("/backend/ged/documents/12/force-delete");
        expect(api.pendingForceDelete.value).toBeNull();
    });

    it("does nothing when confirming with no document pending", async () => {
        const { api, reload } = setup();

        await api.doForceDelete();

        expect(requests).toHaveLength(0);
        expect(reload).not.toHaveBeenCalled();
    });

    it("restores a selection in one request and clears it", async () => {
        const { api, clearSelection } = setup();

        await api.bulkRestore();

        expect(requests[0].url).toBe("/backend/ged/documents/bulk-restore");
        expect(requests[0].body.ids).toEqual([4, 7]);
        expect(clearSelection).toHaveBeenCalledTimes(1);
    });

    it("empties the trash and closes its confirmation", async () => {
        const { api, reload } = setup();
        api.confirmEmptyTrash.value = true;

        await api.emptyTrash();

        expect(requests[0].url).toBe("/backend/ged/documents/empty-trash");
        expect(api.confirmEmptyTrash.value).toBe(false);
        expect(api.emptyingTrash.value).toBe(false);
        expect(reload).toHaveBeenCalledTimes(1);
    });
});
