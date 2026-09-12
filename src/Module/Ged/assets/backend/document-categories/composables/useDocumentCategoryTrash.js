import { ref } from "vue";
import { useI18n } from "vue-i18n";
import { toast } from "vue-sonner";
import { useRequest } from "@/shared/composables/http/backend/useRequest.js";

/**
 * What the trash can do to a category: bring it back, or end it.
 *
 * Shaped like `useDocumentTrash` on purpose. The two screens answer the same
 * question and a reader who has seen one should recognise the other; two ways
 * of writing the same flow is how a codebase stops being learnable.
 *
 * Permanent deletion keeps its own confirmation rather than sharing the delete
 * modal, whose wording promises something reversible - and here it also has to
 * say what the documents lose, which the other modal has no reason to mention.
 */
export function useDocumentCategoryTrash(props, { reload }) {
    const { t } = useI18n();
    const { request: restoreRequest } = useRequest();
    const { request: destroyRequest } = useRequest();

    // Can start open: the overview screen links straight to the trash it
    // counted. Read once, after which the page owns which list is on screen.
    const viewingTrash = ref(Boolean(props.trashed));
    const trashedTotal = ref(props.categories?.trashedTotal ?? 0);

    /** The category whose permanent deletion is awaiting confirmation. */
    const pendingForceDelete = ref(null);
    const confirmEmptyTrash = ref(false);
    const emptyingTrash = ref(false);

    function pathFor(template, id) {
        return (template ?? "").replace("__id__", String(id));
    }

    function toggleTrash() {
        viewingTrash.value = !viewingTrash.value;
        reload();
    }

    /** Keeps the badge in step with every listing response. */
    function readTotals(data) {
        trashedTotal.value = data?.trashedTotal ?? trashedTotal.value;
    }

    async function restore(category) {
        const res = await restoreRequest(
            pathFor(props.restorePath, category.id),
        );
        if (!res) return;
        if (!res.success) {
            toast.error(t("shared.common.error"));

            return;
        }

        toast.success(t("backend.ged.categories.trash.restored"));
        await reload();
    }

    function askForceDelete(category) {
        pendingForceDelete.value = category;
    }

    async function doForceDelete() {
        const category = pendingForceDelete.value;
        if (!category) return;

        const res = await destroyRequest(
            pathFor(props.forceDeletePath, category.id),
        );
        pendingForceDelete.value = null;
        if (!res) return;
        if (!res.success) {
            toast.error(t("shared.common.error"));

            return;
        }

        toast.success(t("backend.ged.categories.trash.deleted_forever"));
        await reload();
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

        toast.success(
            t("backend.ged.categories.trash.emptied", {
                count: res.deleted ?? 0,
            }),
        );
        await reload();
    }

    return {
        viewingTrash,
        trashedTotal,
        pendingForceDelete,
        confirmEmptyTrash,
        emptyingTrash,
        toggleTrash,
        readTotals,
        restore,
        askForceDelete,
        doForceDelete,
        emptyTrash,
    };
}
