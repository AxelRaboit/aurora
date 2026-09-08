<script setup>
import { useI18n } from "vue-i18n";
import { usePrivileges } from "@/shared/composables/usePrivileges.js";
import { useContractsList } from "./composables/useContractsList.js";
import ContractFormFields from "./components/ContractFormFields.vue";
import AppButton from "@/shared/components/action/AppButton.vue";
import AppSearchInput from "@/shared/components/form/input/AppSearchInput.vue";
import AppListToolbar from "@/shared/components/list/AppListToolbar.vue";
import AppModal from "@/shared/components/overlay/AppModal.vue";
import AppModalFooter from "@/shared/components/overlay/AppModalFooter.vue";
import AppNoData from "@/shared/components/feedback/AppNoData.vue";
import {
    AlertTriangle,
    FileSignature,
    Lock,
    Pencil,
    Plus,
    Save,
    Trash2,
    X,
} from "lucide-vue-next";

const { t } = useI18n();
const { can } = usePrivileges();

const props = defineProps({
    contracts: { type: Array, default: () => [] },
    customers: { type: Array, default: () => [] },
    bodies: { type: Array, default: () => [] },
    annexes: { type: Array, default: () => [] },
    locales: { type: Array, default: () => [] },
    currencies: { type: Array, default: () => [] },
    createPath: { type: String, required: true },
    updatePath: { type: String, required: true },
    deletePath: { type: String, required: true },
    freezePath: { type: String, required: true },
    showPath: { type: String, required: true },
});

const {
    search,
    drafts,
    sealed,
    showCreate,
    newContract,
    createErrors,
    createLoading,
    openCreate,
    submitCreate,
    showEdit,
    editing,
    editForm,
    editErrors,
    editLoading,
    openEdit,
    submitEdit,
    pendingDelete,
    pendingFreeze,
    busy,
    confirmDelete,
    confirmFreeze,
    documentPath,
    formatAmount,
} = useContractsList(props);
</script>

<template>
    <div class="space-y-6">
        <AppListToolbar>
            <AppSearchInput
                v-model="search"
                :placeholder="t('backend.accounting.contracts.search_placeholder')"
            />
            <template #actions>
                <AppButton
                    v-if="can('accounting.contracts.create')"
                    variant="primary"
                    size="md"
                    class="w-full sm:w-auto"
                    v-on:click="openCreate"
                >
                    <Plus class="w-4 h-4" :stroke-width="2" />
                    {{ t("backend.accounting.contracts.add") }}
                </AppButton>
            </template>
        </AppListToolbar>

        <AppNoData
            v-if="!drafts.length && !sealed.length"
            :message="t('backend.accounting.contracts.empty')"
        />

        <!-- Drafts first: what somebody is working on comes before history. -->
        <section v-if="drafts.length" class="space-y-2">
            <h2 class="text-xs font-medium uppercase tracking-wider text-muted">
                {{ t("backend.accounting.contracts.in_preparation") }}
            </h2>
            <div class="grid gap-3 md:grid-cols-2">
                <article
                    v-for="contract in drafts"
                    :key="contract.id"
                    class="bg-surface border border-line rounded-lg p-4 space-y-3"
                >
                    <div class="space-y-1">
                        <h3 class="font-medium text-primary">
                            {{ contract.customerName }}
                        </h3>
                        <p class="text-xs text-muted">
                            {{ contract.body?.templateName ?? "-" }}
                            <span v-if="contract.body">
                                ·
                                {{
                                    t("backend.accounting.contracts.version_label", {
                                        number: contract.body.versionNumber,
                                    })
                                }}
                            </span>
                            <span v-if="formatAmount(contract)">
                                · {{ formatAmount(contract) }}
                            </span>
                        </p>
                        <p v-if="contract.annex" class="text-xs text-muted">
                            {{ t("backend.accounting.contracts.with_annex", {
                                name: contract.annex.templateName,
                            }) }}
                        </p>
                    </div>

                    <!-- A draft pinned to an older version is not wrong, but it
                         is a choice somebody should see and be able to redo. -->
                    <p
                        v-if="contract.body?.isOutdated || contract.annex?.isOutdated"
                        class="flex items-start gap-2 text-xs text-amber-500"
                    >
                        <AlertTriangle class="w-3.5 h-3.5 shrink-0 mt-0.5" :stroke-width="2" />
                        {{ t("backend.accounting.contracts.outdated_version") }}
                    </p>

                    <div class="flex flex-wrap gap-2 pt-1 border-t border-line/40">
                        <AppButton
                            v-if="can('accounting.contracts.edit')"
                            variant="ghost"
                            size="sm"
                            v-on:click="openEdit(contract)"
                        >
                            <Pencil class="w-3.5 h-3.5" :stroke-width="2" />
                            {{ t("shared.common.edit") }}
                        </AppButton>
                        <AppButton
                            v-if="can('accounting.contracts.edit')"
                            variant="secondary"
                            size="sm"
                            v-on:click="pendingFreeze = contract"
                        >
                            <Lock class="w-3.5 h-3.5" :stroke-width="2" />
                            {{ t("backend.accounting.contracts.freeze") }}
                        </AppButton>
                        <AppButton
                            v-if="can('accounting.contracts.delete')"
                            variant="ghost"
                            size="sm"
                            v-on:click="pendingDelete = contract"
                        >
                            <Trash2 class="w-3.5 h-3.5" :stroke-width="2" />
                            {{ t("shared.common.delete") }}
                        </AppButton>
                    </div>
                </article>
            </div>
        </section>

        <section v-if="sealed.length" class="space-y-2">
            <h2 class="text-xs font-medium uppercase tracking-wider text-muted">
                {{ t("backend.accounting.contracts.sealed") }}
            </h2>
            <div
                class="bg-surface border border-line rounded-lg overflow-x-auto scrollbar-thin"
            >
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-surface-2/50 border-b border-line/40">
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">
                                {{ t("backend.accounting.contracts.col_reference") }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted">
                                {{ t("backend.accounting.contracts.col_customer") }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted hidden md:table-cell">
                                {{ t("backend.accounting.contracts.col_status") }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted hidden lg:table-cell">
                                {{ t("backend.accounting.contracts.col_sealed_at") }}
                            </th>
                            <th class="px-6 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line/40">
                        <tr
                            v-for="contract in sealed"
                            :key="contract.id"
                            class="hover:bg-surface-2/40 transition-colors"
                        >
                            <td class="px-6 py-3 font-mono text-xs text-primary whitespace-nowrap">
                                {{ contract.reference }}
                            </td>
                            <td class="px-6 py-3 text-primary">
                                {{ contract.customerName }}
                            </td>
                            <td class="px-6 py-3 text-muted hidden md:table-cell">
                                {{ t(contract.statusLabel) }}
                            </td>
                            <td class="px-6 py-3 text-muted text-xs hidden lg:table-cell whitespace-nowrap">
                                {{
                                    contract.frozenAt
                                        ? new Date(contract.frozenAt).toLocaleDateString()
                                        : "-"
                                }}
                            </td>
                            <td class="px-6 py-3 text-right">
                                <AppButton
                                    variant="ghost"
                                    size="sm"
                                    :href="documentPath(contract)"
                                >
                                    <FileSignature class="w-3.5 h-3.5" :stroke-width="2" />
                                    {{ t("backend.accounting.contracts.read_document") }}
                                </AppButton>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <AppModal
            :show="showCreate"
            max-width="lg"
            :closeable="false"
            :title="t('backend.accounting.contracts.create')"
            :icon="FileSignature"
            v-on:close="showCreate = false"
        >
            <form v-on:submit.prevent="submitCreate">
                <ContractFormFields
                    v-model="newContract"
                    :errors="createErrors"
                    :customers="customers"
                    :bodies="bodies"
                    :annexes="annexes"
                    :locales="locales"
                    :currencies="currencies"
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
            :show="showEdit"
            max-width="lg"
            :closeable="false"
            :title="t('backend.accounting.contracts.edit', { name: editing?.customerName ?? '' })"
            :icon="Pencil"
            v-on:close="showEdit = false"
        >
            <form v-on:submit.prevent="submitEdit">
                <ContractFormFields
                    v-model="editForm"
                    :errors="editErrors"
                    :customers="customers"
                    :bodies="bodies"
                    :annexes="annexes"
                    :locales="locales"
                    :currencies="currencies"
                />
            </form>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" v-on:click="showEdit = false">
                        <X class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("shared.common.cancel") }}
                    </AppButton>
                    <AppButton
                        variant="primary"
                        size="md"
                        :loading="editLoading"
                        v-on:click="submitEdit"
                    >
                        <Save class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("shared.common.save") }}
                    </AppButton>
                </AppModalFooter>
            </template>
        </AppModal>

        <AppModal
            :show="!!pendingFreeze"
            max-width="md"
            :closeable="false"
            :title="t('backend.accounting.contracts.freeze')"
            :icon="Lock"
            v-on:close="pendingFreeze = null"
        >
            <p class="text-sm text-primary">
                {{
                    t("backend.accounting.contracts.freeze_confirm", {
                        name: pendingFreeze?.customerName ?? "",
                    })
                }}
            </p>
            <!-- The consequence, before the click. This is the one irreversible
                 action on the page. -->
            <p class="text-sm text-secondary">
                {{ t("backend.accounting.contracts.freeze_warning") }}
            </p>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" v-on:click="pendingFreeze = null">
                        <X class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("shared.common.cancel") }}
                    </AppButton>
                    <AppButton
                        variant="primary"
                        size="md"
                        :loading="busy"
                        v-on:click="confirmFreeze"
                    >
                        <Lock class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("backend.accounting.contracts.freeze") }}
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
                    t("backend.accounting.contracts.delete_confirm", {
                        name: pendingDelete?.customerName ?? "",
                    })
                }}
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
