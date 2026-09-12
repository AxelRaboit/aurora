import { ref } from "vue";
import { useI18n } from "vue-i18n";
import { toast } from "vue-sonner";
import { useRequest } from "@/shared/composables/http/backend/useRequest.js";

/**
 * What the trash can do to a document: bring it back, or end it.
 *
 * Separate from the row actions and the bulk actions because the two verbs
 * here are the only ones that do not exist in the library view, and because
 * one of them is the single irreversible action on this screen - it deserves
 * its own confirmation rather than sharing the delete modal, whose wording
 * promises something reversible.
 *
 * Every call reloads the listing rather than patching the array in place: a
 * restored document leaves the trash and a purged one leaves both views, and
 * the counter in the toolbar has to follow either way.
 */
export function useDocumentTrash(
    props,
    { selectedIds, clearSelection, reload },
) {
    const { t } = useI18n();
    const { request: restoreRequest } = useRequest();
    const { request: destroyRequest } = useRequest();

    /** The document whose permanent deletion is awaiting confirmation. */
    const pendingForceDelete = ref(null);
    const emptyingTrash = ref(false);
    const confirmEmptyTrash = ref(false);

    function pathFor(template, id) {
        return template.replace("__id__", String(id));
    }

    async function restore(doc) {
        const res = await restoreRequest(pathFor(props.restorePath, doc.id));
        if (!res) return;
        if (!res.success) {
            toast.error(t("shared.common.error"));
            return;
        }

        toast.success(t("backend.ged.documents.trash.restored"));
        await reload?.();
    }

    function askForceDelete(doc) {
        pendingForceDelete.value = doc;
    }

    async function doForceDelete() {
        const doc = pendingForceDelete.value;
        if (!doc) return;

        const res = await destroyRequest(
            pathFor(props.forceDeletePath, doc.id),
        );
        pendingForceDelete.value = null;
        if (!res) return;
        if (!res.success) {
            toast.error(t("shared.common.error"));
            return;
        }

        toast.success(t("backend.ged.documents.trash.deleted_forever"));
        await reload?.();
    }

    async function bulkRestore() {
        if (!props.bulkRestorePath || selectedIds.value.size === 0) return;

        const res = await restoreRequest(props.bulkRestorePath, {
            ids: [...selectedIds.value],
        });
        if (!res) return;
        if (!res.success) {
            toast.error(t("shared.common.error"));
            return;
        }

        clearSelection();
        toast.success(t("backend.ged.documents.trash.restored"));
        await reload?.();
    }

    async function emptyTrash() {
        if (!props.emptyTrashPath) return;

        emptyingTrash.value = true;
        const res = await destroyRequest(props.emptyTrashPath);
        emptyingTrash.value = false;
        confirmEmptyTrash.value = false;
        if (!res) return;
        if (!res.success) {
            toast.error(t("shared.common.error"));
            return;
        }

        clearSelection();
        toast.success(
            t("backend.ged.documents.trash.emptied", {
                count: res.deleted ?? 0,
            }),
        );
        await reload?.();
    }

    return {
        pendingForceDelete,
        confirmEmptyTrash,
        emptyingTrash,
        restore,
        askForceDelete,
        doForceDelete,
        bulkRestore,
        emptyTrash,
    };
}
