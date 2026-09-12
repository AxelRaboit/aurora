import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";
import { toast } from "vue-sonner";
import { buildPath } from "@/shared/utils/http/buildPath.js";
import { useRequest } from "@/shared/composables/http/backend/useRequest.js";
import { useFormAction } from "@/shared/composables/form/useFormAction.js";
import { useClientFilteredList } from "@/shared/composables/list/useClientFilteredList.js";
import { useQueryState } from "@/shared/composables/useQueryState.js";
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

    /**
     * The type filter, in the query string like the view mode beside it.
     *
     * An empty value means every type and is left out of the URL, so an
     * untouched list keeps a clean address and a filtered one is a link
     * somebody can send. Only the types the application declares are
     * accepted, so a hand-edited parameter cannot empty the list.
     */
    const kindValues = props.kinds.map((kind) => kind.value);
    const { value: kind, set: setKind } = useQueryState("kind", {
        defaultValue: "",
        valid: ["", ...kindValues],
    });

    const visibleItems = computed(() =>
        filteredItems.value.filter(
            (template) =>
                (showArchived.value || !template.isArchived) &&
                ("" === kind.value || template.kind === kind.value),
        ),
    );

    const archivedCount = computed(
        () => items.value.filter((template) => template.isArchived).length,
    );

    /**
     * How many trames each type holds, for the filter's own labels.
     *
     * Counted on what the filter would show rather than on everything: a
     * count that ignores the search or the archived toggle promises rows the
     * next click does not deliver.
     */
    const kindCounts = computed(() => {
        const counts = { "": 0 };

        for (const value of kindValues) counts[value] = 0;

        for (const template of filteredItems.value) {
            if (!showArchived.value && template.isArchived) continue;

            counts[""] += 1;

            if (undefined !== counts[template.kind]) counts[template.kind] += 1;
        }

        return counts;
    });

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
                    t("backend.studio.contract_templates.errors.name_required"),
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
                    t("backend.studio.contract_templates.errors.name_required"),
                )(renameForm.value.name),
        }),
        url: () => buildPath(props.updatePath, { id: renaming.value.id }),
        body: () => renameForm.value,
        onSuccess: (data) => {
            showRename.value = false;
            toast.success(t("backend.studio.contract_templates.updated"));
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
    const pendingDuplicate = ref(null);
    const pendingDiscard = ref(null);
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
            "backend.studio.contract_templates.archived",
        );
    }

    function restore(template) {
        return act(
            props.restorePath,
            template,
            "backend.studio.contract_templates.restored",
        );
    }

    async function confirmDelete() {
        const template = pendingDelete.value;
        pendingDelete.value = null;

        if (template) {
            await act(
                props.deletePath,
                template,
                "backend.studio.contract_templates.deleted",
            );
        }
    }

    /**
     * Opens a draft and stays on the list.
     *
     * An action taken on a row answers on that row: the draft badge appears,
     * amber and clickable, and going in is the reader's next click rather
     * than something the list decides for them. Jumping straight into the
     * editor also stranded whoever opened a draft on the wrong trame - the way
     * back was the browser's Back button and a draft nobody wanted.
     */
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

            applyList(data);
            toast.success(t("backend.studio.contract_templates.draft_opened"));
        } finally {
            busy.value = false;
        }
    }

    /**
     * Abandons the open draft and leaves the trame as it was published.
     *
     * Almost as if the draft had never been opened: the version in force does
     * not move, no contract is touched, and the trame offers to open a draft
     * again. What does not come back is the number the draft claimed - the
     * counter never reissues one, so the next draft is the one after it. That
     * gap is the point of the counter, not a defect: a version number that
     * once existed must never name a different text.
     */
    async function confirmDiscard() {
        const template = pendingDiscard.value;
        pendingDiscard.value = null;

        if (!template?.draftId || busy.value) return;

        busy.value = true;

        try {
            const data = await request(
                buildPath(props.discardDraftPath, {
                    id: template.id,
                    versionId: template.draftId,
                }),
                {},
            );

            if (data?.errors) {
                toast.error(Object.values(data.errors)[0]);

                return;
            }

            applyList(data);
            toast.success(t("backend.studio.contract_templates.discarded"));
        } finally {
            busy.value = false;
        }
    }

    /**
     * Duplicates a trame, and stays on the list like every other row action.
     *
     * The copy arrives as a row with its own draft badge, which is where it
     * belongs: the list is what the reader was looking at, and the copy is
     * one click from being opened.
     */
    async function confirmDuplicate() {
        const template = pendingDuplicate.value;
        pendingDuplicate.value = null;

        if (!template || busy.value) return;

        busy.value = true;

        try {
            const data = await request(
                buildPath(props.duplicatePath, { id: template.id }),
                {},
            );

            if (data?.errors) {
                toast.error(Object.values(data.errors)[0]);

                return;
            }

            applyList(data);

            toast.success(t("backend.studio.contract_templates.duplicated"));
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
        kind,
        setKind,
        kindCounts,
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
        pendingDuplicate,
        pendingDiscard,
        busy,
        archive,
        restore,
        confirmDelete,
        confirmDuplicate,
        confirmDiscard,
        openDraft,
        editorPath,
    };
}
