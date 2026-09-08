import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";
import { toast } from "vue-sonner";
import { buildPath } from "@/shared/utils/http/buildPath.js";
import { useRequest } from "@/shared/composables/http/backend/useRequest.js";
import { useFormAction } from "@/shared/composables/form/useFormAction.js";
import { useClientFilteredList } from "@/shared/composables/list/useClientFilteredList.js";
import { required } from "@/shared/utils/validation/validators.js";

function emptyForm() {
    return { name: "", kind: "body" };
}

export function useContractTemplatesList(props) {
    const { t } = useI18n();
    const { request } = useRequest();

    const {
        items,
        searchInput: search,
        filteredItems,
    } = useClientFilteredList(props.templates, null, (template, query) =>
        (template.name ?? "").toLowerCase().includes(query),
    );

    /**
     * Archived trames are out of the way by default but never hidden for good:
     * they are what a contract sent last year was built from, and a list that
     * cannot show them cannot answer questions about it.
     */
    const showArchived = ref(false);

    const visibleItems = computed(() =>
        filteredItems.value.filter(
            (template) => showArchived.value || !template.isArchived,
        ),
    );

    const archivedCount = computed(
        () => items.value.filter((template) => template.isArchived).length,
    );

    function applyList(data) {
        if (Array.isArray(data?.templates)) items.value = data.templates;
    }

    const showCreate = ref(false);
    const newTemplate = ref(emptyForm());

    const {
        errors: createErrors,
        loading: createLoading,
        submit: submitCreate,
        clearErrors: clearCreate,
    } = useFormAction({
        rules: () => ({
            name: () =>
                required(
                    t(
                        "backend.accounting.contract_templates.errors.name_required",
                    ),
                )(newTemplate.value.name),
        }),
        url: () => props.createPath,
        body: () => newTemplate.value,
        onSuccess: (data) => {
            showCreate.value = false;
            applyList(data);
            // Straight into the editor: a trame with no wording is not a thing
            // anybody wanted to create and then look at in a list.
            if (data?.draftId) {
                window.location.assign(
                    editorPath(data.template.id, data.draftId),
                );
            }
        },
    });

    function openCreate() {
        newTemplate.value = emptyForm();
        clearCreate();
        showCreate.value = true;
    }

    const showRename = ref(false);
    const renaming = ref(null);
    const renameForm = ref(emptyForm());

    const {
        errors: renameErrors,
        loading: renameLoading,
        submit: submitRename,
        clearErrors: clearRename,
    } = useFormAction({
        rules: () => ({
            name: () =>
                required(
                    t(
                        "backend.accounting.contract_templates.errors.name_required",
                    ),
                )(renameForm.value.name),
        }),
        url: () => buildPath(props.updatePath, { id: renaming.value.id }),
        body: () => renameForm.value,
        onSuccess: (data) => {
            showRename.value = false;
            toast.success(t("backend.accounting.contract_templates.updated"));
            applyList(data);
        },
    });

    function openRename(template) {
        renaming.value = template;
        renameForm.value = { name: template.name, kind: template.kind };
        clearRename();
        showRename.value = true;
    }

    const pendingDelete = ref(null);
    const busy = ref(false);

    async function act(path, template, successKey) {
        if (busy.value) return;
        busy.value = true;

        try {
            const data = await request(
                buildPath(path, { id: template.id }),
                {},
            );

            if (data?.errors) {
                // Pinned nowhere in particular: these actions have no form, so
                // the only honest place is a toast naming what was refused.
                toast.error(Object.values(data.errors)[0]);

                return;
            }

            applyList(data);
            if (successKey) toast.success(t(successKey));
        } finally {
            busy.value = false;
        }
    }

    function archive(template) {
        return act(
            props.archivePath,
            template,
            "backend.accounting.contract_templates.archived",
        );
    }

    function restore(template) {
        return act(
            props.restorePath,
            template,
            "backend.accounting.contract_templates.restored",
        );
    }

    async function confirmDelete() {
        const template = pendingDelete.value;
        pendingDelete.value = null;

        if (template) {
            await act(
                props.deletePath,
                template,
                "backend.accounting.contract_templates.deleted",
            );
        }
    }

    async function openDraft(template) {
        if (busy.value) return;
        busy.value = true;

        try {
            const data = await request(
                buildPath(props.openDraftPath, { id: template.id }),
                {},
            );

            if (data?.errors) {
                toast.error(Object.values(data.errors)[0]);

                return;
            }

            if (data?.draftId) {
                window.location.assign(editorPath(template.id, data.draftId));
            }
        } finally {
            busy.value = false;
        }
    }

    function editorPath(templateId, versionId) {
        return buildPath(props.editorPath, {
            id: templateId,
            versionId,
        });
    }

    return {
        items,
        search,
        visibleItems,
        showArchived,
        archivedCount,
        showCreate,
        newTemplate,
        createErrors,
        createLoading,
        openCreate,
        submitCreate,
        showRename,
        renaming,
        renameForm,
        renameErrors,
        renameLoading,
        openRename,
        submitRename,
        pendingDelete,
        busy,
        archive,
        restore,
        confirmDelete,
        openDraft,
        editorPath,
    };
}
