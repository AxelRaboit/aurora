import { ref } from "vue";
import { useI18n } from "vue-i18n";
import { toast } from "vue-sonner";

/**
 * The notes waiting in the trash, and the two things one can do to them.
 *
 * A panel opened on demand rather than a second tree: the trash is consulted
 * when something was deleted by mistake, which is rare, and a permanent branch
 * in the sidebar would cost a query on every page load to show nothing.
 *
 * Only the notes trashed on their own are listed. A sub-note that fell with
 * its parent comes back with it, and offering to restore it separately would
 * put a page under a parent that is still deleted.
 */
export function useNotesTrash(api, { onChanged }) {
    const { t } = useI18n();

    const open = ref(false);
    const loading = ref(false);
    const notes = ref([]);

    async function load() {
        loading.value = true;
        const { ok, payload } = await api.trash();
        loading.value = false;

        if (!ok) {
            toast.error(t("shared.common.error"));

            return;
        }

        notes.value = payload.notes ?? [];
    }

    async function openTrash() {
        open.value = true;
        await load();
    }

    async function restore(note) {
        const { ok } = await api.restore(note.id);
        if (!ok) {
            toast.error(t("shared.common.error"));

            return;
        }

        toast.success(t("notes.markdown.trash.restored"));
        await Promise.all([load(), onChanged?.()]);
    }

    async function forceDelete(note) {
        const { ok } = await api.forceDelete(note.id);
        if (!ok) {
            toast.error(t("shared.common.error"));

            return;
        }

        toast.success(t("notes.markdown.trash.deleted_forever"));
        await load();
    }

    return { open, loading, notes, openTrash, restore, forceDelete };
}
