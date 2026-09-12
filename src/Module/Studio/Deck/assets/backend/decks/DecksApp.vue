<script setup>
/**
 * The decks: what gets shown to somebody, listed.
 *
 * A deck is filed under a category and may name the customer it was written
 * for. The customer picker is empty when the customers sub-module is off, and
 * the field simply offers nothing rather than disappearing: a deck without a
 * client is the ordinary internal case, not a degraded one.
 *
 * Duplicating is a row action rather than a button inside the deck, because
 * "start from this one" is decided while looking at the list.
 */
import { useI18n } from "vue-i18n";
import { usePrivileges } from "@/shared/composables/usePrivileges.js";
import { useDecksList } from "./composables/useDecksList.js";
import AppButton from "@/shared/components/action/AppButton.vue";
import AppInput from "@/shared/components/form/input/AppInput.vue";
import AppSearchInput from "@/shared/components/form/input/AppSearchInput.vue";
import AppSelect from "@/shared/components/form/select/AppSelect.vue";
import AppTextarea from "@/shared/components/form/input/AppTextarea.vue";
import AppListToolbar from "@/shared/components/list/AppListToolbar.vue";
import AppModal from "@/shared/components/overlay/AppModal.vue";
import AppModalFooter from "@/shared/components/overlay/AppModalFooter.vue";
import AppRowActions from "@/shared/components/action/AppRowActions.vue";
import AppNoData from "@/shared/components/feedback/AppNoData.vue";
import {
    Copy,
    Pencil,
    Plus,
    Presentation,
    Save,
    Trash2,
    X,
} from "lucide-vue-next";

const { t } = useI18n();
const { can } = usePrivileges();

const props = defineProps({
    decks: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    customers: { type: Array, default: () => [] },
    layouts: { type: Array, default: () => [] },
    createPath: { type: String, required: true },
    updatePath: { type: String, required: true },
    deletePath: { type: String, required: true },
    duplicatePath: { type: String, required: true },
    categoryCreatePath: { type: String, required: true },
    categoryUpdatePath: { type: String, required: true },
    categoryDeletePath: { type: String, required: true },
});

const {
    search,
    categoryFilter,
    filteredItems,
    categoryOptions,
    customerOptions,
    showCreate,
    newDeck,
    createErrors,
    createLoading,
    openCreate,
    submitCreate,
    showEdit,
    editingDeck,
    editForm,
    editErrors,
    editLoading,
    openEdit,
    submitEdit,
    pendingDelete,
    deleteLoading,
    confirmDelete,
    doDelete,
    duplicatingId,
    duplicate,
} = useDecksList(props);

/**
 * Three actions, and the middle one is not an edit.
 *
 * `useEditDeleteActions` covers the two-action case; a duplicate sits between
 * them here, so the list is written out rather than borrowed.
 */
function actionsFor(deck) {
    const actions = [];

    if (can("studio.decks.edit")) {
        actions.push({
            label: t("shared.common.edit"),
            icon: Pencil,
            onClick: () => openEdit(deck),
        });
    }

    if (can("studio.decks.create")) {
        actions.push({
            label: t("backend.studio.decks.duplicate"),
            description: t("backend.studio.decks.duplicate_hint"),
            icon: Copy,
            loading: duplicatingId.value === deck.id,
            onClick: () => duplicate(deck),
        });
    }

    if (can("studio.decks.delete")) {
        actions.push({
            label: t("shared.common.delete"),
            icon: Trash2,
            variant: "danger",
            onClick: () => confirmDelete(deck),
        });
    }

    return actions;
}

const filterOptions = () => categoryOptions.value;
</script>

<template>
    <div class="space-y-4">
        <AppListToolbar>
            <AppSearchInput
                v-model="search"
                :placeholder="t('backend.studio.decks.search_placeholder')"
            />
            <!-- `inline` plutôt qu'une ligne à part : le filtre appartient à la
                 recherche, il ne se lit pas comme une seconde barre. -->
            <template #inline>
                <AppSelect
                    v-model="categoryFilter"
                    :options="filterOptions()"
                    :placeholder="t('backend.studio.decks.all_categories')"
                />
            </template>
            <template #actions>
                <AppButton
                    v-if="can('studio.decks.create')"
                    variant="primary"
                    v-on:click="openCreate"
                >
                    <Plus class="h-4 w-4" :stroke-width="2" />
                    {{ t("backend.studio.decks.create") }}
                </AppButton>
            </template>
        </AppListToolbar>

        <AppNoData
            v-if="!filteredItems.length"
            :icon="Presentation"
            :title="t('backend.studio.decks.empty_title')"
            :description="t('backend.studio.decks.empty_description')"
        />

        <div v-else class="overflow-x-auto rounded-xl border border-line bg-surface">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-line text-xs uppercase tracking-wide text-muted">
                        <th class="px-6 py-3 text-left font-medium">{{ t("backend.studio.decks.title_column") }}</th>
                        <th class="hidden px-6 py-3 text-left font-medium lg:table-cell">{{ t("backend.studio.decks.category") }}</th>
                        <th class="hidden px-6 py-3 text-left font-medium lg:table-cell">{{ t("backend.studio.decks.customer") }}</th>
                        <th class="px-6 py-3 text-right font-medium">{{ t("backend.studio.decks.slides") }}</th>
                        <th class="px-6 py-3 text-right font-medium">{{ t("shared.common.actions") }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="deck in filteredItems"
                        :key="deck.id"
                        class="border-b border-line/60 last:border-0 hover:bg-surface-2/50"
                    >
                        <td class="px-6 py-3">
                            <span class="block font-medium text-primary">{{ deck.title }}</span>
                            <span v-if="deck.description" class="block text-xs text-muted line-clamp-1">{{ deck.description }}</span>
                        </td>
                        <td class="hidden px-6 py-3 lg:table-cell">
                            <span
                                v-if="deck.category"
                                class="inline-flex items-center gap-1.5 rounded-full border border-line px-2 py-0.5 text-xs"
                            >
                                <span
                                    v-if="deck.category.color"
                                    class="h-2 w-2 rounded-full"
                                    :style="{ backgroundColor: deck.category.color }"
                                />
                                {{ deck.category.name }}
                            </span>
                            <span v-else class="text-xs text-muted">{{ t("backend.studio.decks.uncategorised") }}</span>
                        </td>
                        <td class="hidden px-6 py-3 text-secondary lg:table-cell">
                            {{ deck.customer?.legalName ?? "—" }}
                        </td>
                        <td class="px-6 py-3 text-right tabular-nums text-secondary">{{ deck.slideCount }}</td>
                        <td class="px-6 py-3 text-right">
                            <AppRowActions :actions="actionsFor(deck)" :label="deck.title" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AppModal
            :show="showCreate"
            max-width="lg"
            :closeable="false"
            :title="t('backend.studio.decks.create')"
            :icon="Presentation"
            v-on:close="showCreate = false"
        >
            <form class="space-y-4" v-on:submit.prevent="submitCreate">
                <AppInput
                    v-model="newDeck.title"
                    :label="t('backend.studio.decks.title_column')"
                    :placeholder="t('backend.studio.decks.title_placeholder')"
                    :error="createErrors.title ?? ''"
                    required
                />
                <AppTextarea
                    v-model="newDeck.description"
                    :label="t('backend.studio.decks.description')"
                    :placeholder="t('backend.studio.decks.description_placeholder')"
                    :rows="2"
                />
                <AppSelect
                    v-model="newDeck.categoryId"
                    :options="categoryOptions"
                    :label="t('backend.studio.decks.category')"
                    :placeholder="t('backend.studio.decks.uncategorised')"
                />
                <AppSelect
                    v-if="customerOptions.length"
                    v-model="newDeck.customerId"
                    :options="customerOptions"
                    :label="t('backend.studio.decks.customer')"
                    :placeholder="t('backend.studio.decks.no_customer')"
                />
            </form>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" v-on:click="showCreate = false">
                        <X class="h-3.5 w-3.5" :stroke-width="2" />
                        {{ t("shared.common.cancel") }}
                    </AppButton>
                    <AppButton variant="primary" size="md" :loading="createLoading" v-on:click="submitCreate">
                        <Save class="h-3.5 w-3.5" :stroke-width="2" />
                        {{ t("shared.common.save") }}
                    </AppButton>
                </AppModalFooter>
            </template>
        </AppModal>

        <AppModal
            :show="showEdit"
            max-width="lg"
            :closeable="false"
            :title="editingDeck?.title ?? ''"
            :icon="Pencil"
            v-on:close="showEdit = false"
        >
            <form class="space-y-4" v-on:submit.prevent="submitEdit">
                <AppInput
                    v-model="editForm.title"
                    :label="t('backend.studio.decks.title_column')"
                    :placeholder="t('backend.studio.decks.title_placeholder')"
                    :error="editErrors.title ?? ''"
                    required
                />
                <AppTextarea
                    v-model="editForm.description"
                    :label="t('backend.studio.decks.description')"
                    :placeholder="t('backend.studio.decks.description_placeholder')"
                    :rows="2"
                />
                <AppSelect
                    v-model="editForm.categoryId"
                    :options="categoryOptions"
                    :label="t('backend.studio.decks.category')"
                    :placeholder="t('backend.studio.decks.uncategorised')"
                />
                <AppSelect
                    v-if="customerOptions.length"
                    v-model="editForm.customerId"
                    :options="customerOptions"
                    :label="t('backend.studio.decks.customer')"
                    :placeholder="t('backend.studio.decks.no_customer')"
                />
            </form>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" v-on:click="showEdit = false">
                        <X class="h-3.5 w-3.5" :stroke-width="2" />
                        {{ t("shared.common.cancel") }}
                    </AppButton>
                    <AppButton variant="primary" size="md" :loading="editLoading" v-on:click="submitEdit">
                        <Save class="h-3.5 w-3.5" :stroke-width="2" />
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
                {{ t("backend.studio.decks.delete_confirm", { title: pendingDelete?.title }) }}
            </p>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" v-on:click="pendingDelete = null">
                        <X class="h-3.5 w-3.5" :stroke-width="2" />
                        {{ t("shared.common.cancel") }}
                    </AppButton>
                    <AppButton variant="danger" size="md" :loading="deleteLoading" v-on:click="doDelete">
                        <Trash2 class="h-3.5 w-3.5" :stroke-width="2" />
                        {{ t("shared.common.delete") }}
                    </AppButton>
                </AppModalFooter>
            </template>
        </AppModal>
    </div>
</template>
