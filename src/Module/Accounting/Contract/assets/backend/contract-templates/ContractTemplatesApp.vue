<script setup>
import { useI18n } from "vue-i18n";
import { usePrivileges } from "@/shared/composables/usePrivileges.js";
import { useContractTemplatesList } from "./composables/useContractTemplatesList.js";
import AppButton from "@/shared/components/action/AppButton.vue";
import AppInput from "@/shared/components/form/input/AppInput.vue";
import AppSelect from "@/shared/components/form/select/AppSelect.vue";
import AppSearchInput from "@/shared/components/form/input/AppSearchInput.vue";
import AppListToolbar from "@/shared/components/list/AppListToolbar.vue";
import AppModal from "@/shared/components/overlay/AppModal.vue";
import AppModalFooter from "@/shared/components/overlay/AppModalFooter.vue";
import AppNoData from "@/shared/components/feedback/AppNoData.vue";
import {
    Archive,
    ArchiveRestore,
    FilePlus2,
    Pencil,
    Plus,
    Save,
    ScrollText,
    Trash2,
    X,
} from "lucide-vue-next";

const { t } = useI18n();
const { can } = usePrivileges();

const props = defineProps({
    templates: { type: Array, default: () => [] },
    kinds: { type: Array, default: () => [] },
    createPath: { type: String, required: true },
    updatePath: { type: String, required: true },
    archivePath: { type: String, required: true },
    restorePath: { type: String, required: true },
    deletePath: { type: String, required: true },
    openDraftPath: { type: String, required: true },
    editorPath: { type: String, required: true },
});

const {
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
} = useContractTemplatesList(props);

const kindOptions = props.kinds.map((kind) => ({
    value: kind.value,
    label: t(kind.labelKey),
}));

function kindLabel(value) {
    return kindOptions.find((kind) => kind.value === value)?.label ?? value;
}
</script>

<template>
    <div class="space-y-4">
        <AppListToolbar>
            <AppSearchInput
                v-model="search"
                :placeholder="t('backend.accounting.contract_templates.search_placeholder')"
            />
            <template #actions>
                <AppButton
                    v-if="archivedCount"
                    variant="ghost"
                    size="md"
                    v-on:click="showArchived = !showArchived"
                >
                    <Archive class="w-4 h-4" :stroke-width="2" />
                    {{
                        showArchived
                            ? t("backend.accounting.contract_templates.hide_archived")
                            : t("backend.accounting.contract_templates.show_archived", {
                                count: archivedCount,
                            })
                    }}
                </AppButton>
                <AppButton
                    v-if="can('accounting.contract_templates.create')"
                    variant="primary"
                    size="md"
                    class="w-full sm:w-auto"
                    v-on:click="openCreate"
                >
                    <Plus class="w-4 h-4" :stroke-width="2" />
                    {{ t("backend.accounting.contract_templates.add") }}
                </AppButton>
            </template>
        </AppListToolbar>

        <AppNoData
            v-if="!visibleItems.length"
            :message="t('backend.accounting.contract_templates.empty')"
        />

        <!-- Cards rather than a table: what a reader needs per trame is its
             state across two versions, published and draft, which does not
             read as a row of cells. -->
        <div v-else class="grid gap-3 md:grid-cols-2">
            <article
                v-for="template in visibleItems"
                :key="template.id"
                class="bg-surface border border-line rounded-lg p-4 space-y-3"
                :class="{ 'opacity-60': template.isArchived }"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 space-y-1">
                        <h2 class="font-medium text-primary flex items-center gap-2">
                            <ScrollText class="w-4 h-4 shrink-0 text-muted" :stroke-width="2" />
                            <span class="truncate">{{ template.name }}</span>
                        </h2>
                        <p class="text-xs text-muted">
                            {{ kindLabel(template.kind) }}
                            <span v-if="template.locales.length">
                                · {{ template.locales.join(", ") }}
                            </span>
                        </p>
                    </div>
                    <span
                        v-if="template.isArchived"
                        class="text-2xs uppercase tracking-wider px-2 py-0.5 rounded-full border border-line text-muted shrink-0"
                    >
                        {{ t("backend.accounting.contract_templates.state_archived") }}
                    </span>
                </div>

                <!-- The two facts that matter, and the two absences that mean
                     different things: never published, versus nothing open. -->
                <dl class="grid grid-cols-2 gap-2 text-xs">
                    <div class="space-y-0.5">
                        <dt class="text-muted uppercase tracking-wider">
                            {{ t("backend.accounting.contract_templates.in_force") }}
                        </dt>
                        <dd class="text-primary">
                            <template v-if="template.publishedVersion">
                                {{
                                    t("backend.accounting.contract_templates.version_label", {
                                        number: template.publishedVersion,
                                    })
                                }}
                            </template>
                            <span v-else class="text-muted">
                                {{ t("backend.accounting.contract_templates.never_published") }}
                            </span>
                        </dd>
                    </div>
                    <div class="space-y-0.5">
                        <dt class="text-muted uppercase tracking-wider">
                            {{ t("backend.accounting.contract_templates.state_draft") }}
                        </dt>
                        <dd class="text-primary">
                            <a
                                v-if="template.draftId"
                                :href="editorPath(template.id, template.draftId)"
                                class="text-accent-500 hover:underline"
                            >
                                {{
                                    t("backend.accounting.contract_templates.version_label", {
                                        number: template.draftVersion,
                                    })
                                }}
                            </a>
                            <span v-else class="text-muted">
                                {{ t("backend.accounting.contract_templates.no_draft") }}
                            </span>
                        </dd>
                    </div>
                </dl>

                <div class="flex flex-wrap gap-2 pt-1 border-t border-line/40">
                    <AppButton
                        v-if="!template.draftId && !template.isArchived && can('accounting.contract_templates.edit')"
                        variant="ghost"
                        size="sm"
                        :loading="busy"
                        v-on:click="openDraft(template)"
                    >
                        <FilePlus2 class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("backend.accounting.contract_templates.open_draft") }}
                    </AppButton>
                    <AppButton
                        v-if="can('accounting.contract_templates.edit')"
                        variant="ghost"
                        size="sm"
                        v-on:click="openRename(template)"
                    >
                        <Pencil class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("shared.common.edit") }}
                    </AppButton>
                    <AppButton
                        v-if="!template.isArchived && can('accounting.contract_templates.edit')"
                        variant="ghost"
                        size="sm"
                        v-on:click="archive(template)"
                    >
                        <Archive class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("backend.accounting.contract_templates.archive") }}
                    </AppButton>
                    <AppButton
                        v-if="template.isArchived && can('accounting.contract_templates.edit')"
                        variant="ghost"
                        size="sm"
                        v-on:click="restore(template)"
                    >
                        <ArchiveRestore class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("backend.accounting.contract_templates.restore") }}
                    </AppButton>
                    <AppButton
                        v-if="can('accounting.contract_templates.delete')"
                        variant="ghost"
                        size="sm"
                        v-on:click="pendingDelete = template"
                    >
                        <Trash2 class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("shared.common.delete") }}
                    </AppButton>
                </div>
            </article>
        </div>

        <AppModal
            :show="showCreate"
            max-width="md"
            :closeable="false"
            :title="t('backend.accounting.contract_templates.create')"
            :icon="ScrollText"
            v-on:close="showCreate = false"
        >
            <form class="space-y-4" v-on:submit.prevent="submitCreate">
                <AppInput
                    v-model="newTemplate.name"
                    :label="t('backend.accounting.contract_templates.name')"
                    :placeholder="t('backend.accounting.contract_templates.name_placeholder')"
                    :error="createErrors.name"
                    required
                />
                <AppSelect
                    v-model="newTemplate.kind"
                    :label="t('backend.accounting.contract_templates.kind_label')"
                    :options="kindOptions"
                    :hint="t('backend.accounting.contract_templates.kind_hint')"
                />
            </form>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" v-on:click="showCreate = false">
                        <X class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("shared.common.cancel") }}
                    </AppButton>
                    <AppButton
                        variant="primary"
                        size="md"
                        :loading="createLoading"
                        v-on:click="submitCreate"
                    >
                        <Save class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("shared.common.save") }}
                    </AppButton>
                </AppModalFooter>
            </template>
        </AppModal>

        <AppModal
            :show="showRename"
            max-width="md"
            :closeable="false"
            :title="t('backend.accounting.contract_templates.edit', { name: renaming?.name ?? '' })"
            :icon="Pencil"
            v-on:close="showRename = false"
        >
            <form class="space-y-4" v-on:submit.prevent="submitRename">
                <AppInput
                    v-model="renameForm.name"
                    :label="t('backend.accounting.contract_templates.name')"
                    :placeholder="t('backend.accounting.contract_templates.name_placeholder')"
                    :error="renameErrors.name"
                    required
                />
                <AppSelect
                    v-model="renameForm.kind"
                    :label="t('backend.accounting.contract_templates.kind_label')"
                    :options="kindOptions"
                    :hint="t('backend.accounting.contract_templates.kind_hint')"
                />
            </form>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" v-on:click="showRename = false">
                        <X class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("shared.common.cancel") }}
                    </AppButton>
                    <AppButton
                        variant="primary"
                        size="md"
                        :loading="renameLoading"
                        v-on:click="submitRename"
                    >
                        <Save class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("shared.common.save") }}
                    </AppButton>
                </AppModalFooter>
            </template>
        </AppModal>

        <AppModal
            :show="!!pendingDelete"
            max-width="sm"
            :closeable="false"
            :title="t('shared.common.delete')"
            :icon="Trash2"
            v-on:close="pendingDelete = null"
        >
            <p class="text-sm text-primary">
                {{
                    t("backend.accounting.contract_templates.delete_confirm", {
                        name: pendingDelete?.name ?? "",
                    })
                }}
            </p>
            <p class="text-sm text-secondary">
                {{ t("backend.accounting.contract_templates.delete_warning") }}
            </p>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" v-on:click="pendingDelete = null">
                        <X class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("shared.common.cancel") }}
                    </AppButton>
                    <AppButton
                        variant="danger"
                        size="md"
                        :loading="busy"
                        v-on:click="confirmDelete"
                    >
                        <Trash2 class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("shared.common.delete") }}
                    </AppButton>
                </AppModalFooter>
            </template>
        </AppModal>
    </div>
</template>
