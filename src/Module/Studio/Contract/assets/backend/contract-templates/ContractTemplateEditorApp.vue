<script setup>
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import { useContractTemplateEditor } from "./composables/useContractTemplateEditor.js";
import ContractVariablePanel from "./components/ContractVariablePanel.vue";
import AppBlockEditor from "@/shared/components/editor/AppBlockEditor.vue";
import AppButton from "@/shared/components/action/AppButton.vue";
import AppInput from "@/shared/components/form/input/AppInput.vue";
import AppSelect from "@/shared/components/form/select/AppSelect.vue";
import AppMessage from "@/shared/components/feedback/AppMessage.vue";
import AppModal from "@/shared/components/overlay/AppModal.vue";
import AppModalFooter from "@/shared/components/overlay/AppModalFooter.vue";
import { Check, Lock, Save, ScrollText, Trash2, X } from "lucide-vue-next";

const { t } = useI18n();

const props = defineProps({
    template: { type: Object, required: true },
    version: { type: Object, required: true },
    versions: { type: Array, default: () => [] },
    locales: { type: Array, default: () => [] },
    variableGroups: { type: Array, default: () => [] },
    savePath: { type: String, required: true },
    publishPath: { type: String, required: true },
    discardPath: { type: String, required: true },
    indexPath: { type: String, required: true },
    editorPath: { type: String, required: true },
});

const {
    version,
    isPublished,
    locales,
    activeLocale,
    wording,
    switchLocale,
    writtenLocales,
    governingLocale,
    governingOptions,
    needsGoverningLocale,
    canPublish,
    saving,
    publishing,
    errors,
    showPublish,
    showDiscard,
    save,
    publish,
    discard,
    versionPath,
} = useContractTemplateEditor(props);

const otherVersions = computed(() =>
    props.versions.filter((each) => each.id !== version.value.id),
);

const governingSelectOptions = computed(() =>
    governingOptions.value.map((locale) => ({
        value: locale.code,
        label: locale.label,
    })),
);

/** The answer, in words, for a published version that can no longer be asked. */
const governingLabel = computed(
    () =>
        props.locales.find((locale) => locale.code === governingLocale.value)
            ?.label ?? null,
);
</script>

<template>
    <div class="space-y-4">
        <!-- A published version is readable but not writable, and the page says
             so before the reader tries. Hiding the fields instead would leave
             them wondering where the text went. -->
        <AppMessage v-if="isPublished" variant="info">
            <span class="flex items-center gap-2">
                <Lock class="w-4 h-4 shrink-0" :stroke-width="2" />
                {{
                    t("backend.studio.contract_templates.published_notice", {
                        number: version.number,
                    })
                }}
            </span>
        </AppMessage>

        <AppMessage v-if="errors.version" variant="danger">
            {{ errors.version }}
        </AppMessage>
        <AppMessage v-if="errors.translations" variant="danger">
            {{ errors.translations }}
        </AppMessage>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-baseline gap-2">
                <h1 class="text-lg font-semibold text-primary">
                    {{ template.name }}
                </h1>
                <span class="text-sm text-muted">
                    {{
                        t("backend.studio.contract_templates.version_label", {
                            number: version.number,
                        })
                    }}
                </span>
                <span
                    class="text-xs px-2 py-0.5 rounded-full border"
                    :class="
                        isPublished
                            ? 'border-emerald-500/40 text-emerald-500'
                            : 'border-amber-500/40 text-amber-500'
                    "
                >
                    {{
                        isPublished
                            ? t("backend.studio.contract_templates.state_published")
                            : t("backend.studio.contract_templates.state_draft")
                    }}
                </span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <AppButton variant="ghost" size="md" :href="indexPath">
                    {{ t("shared.common.back") }}
                </AppButton>
                <AppButton
                    v-if="!isPublished"
                    variant="ghost"
                    size="md"
                    v-on:click="showDiscard = true"
                >
                    <Trash2 class="w-3.5 h-3.5" :stroke-width="2" />
                    {{ t("backend.studio.contract_templates.discard") }}
                </AppButton>
                <AppButton
                    v-if="!isPublished"
                    variant="secondary"
                    size="md"
                    :loading="saving"
                    v-on:click="save"
                >
                    <Save class="w-3.5 h-3.5" :stroke-width="2" />
                    {{ t("shared.common.save") }}
                </AppButton>
                <AppButton
                    v-if="!isPublished"
                    variant="primary"
                    size="md"
                    :disabled="!canPublish"
                    v-on:click="showPublish = true"
                >
                    <Check class="w-3.5 h-3.5" :stroke-width="2" />
                    {{ t("backend.studio.contract_templates.publish") }}
                </AppButton>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
            <div class="min-w-0 space-y-3">
                <!-- Language tabs. Each carries a dot when it has wording, so
                     "which languages does this version actually have" is
                     answered without opening all three. -->
                <div
                    class="flex flex-wrap gap-1 border-b border-line/60"
                    role="tablist"
                >
                    <button
                        v-for="locale in locales"
                        :key="locale.code"
                        type="button"
                        role="tab"
                        :aria-selected="locale.code === activeLocale"
                        class="px-3 py-2 text-sm border-b-2 -mb-px transition-colors flex items-center gap-1.5"
                        :class="
                            locale.code === activeLocale
                                ? 'border-accent-500 text-primary'
                                : 'border-transparent text-muted hover:text-primary'
                        "
                        v-on:click="switchLocale(locale.code)"
                    >
                        {{ locale.label }}
                        <span
                            v-if="writtenLocales.includes(locale.code)"
                            class="w-1.5 h-1.5 rounded-full bg-emerald-500"
                            :title="t('backend.studio.contract_templates.locale_written')"
                        />
                    </button>
                </div>

                <template v-for="locale in locales" :key="locale.code">
                    <div v-show="locale.code === activeLocale" class="space-y-3">
                        <AppInput
                            v-model="wording[locale.code].title"
                            :label="t('backend.studio.contract_templates.document_title')"
                            :placeholder="
                                t('backend.studio.contract_templates.document_title_placeholder')
                            "
                            :hint="t('backend.studio.contract_templates.document_title_hint')"
                            :readonly="isPublished"
                        />
                        <div
                            class="rounded-lg border border-line bg-surface p-3"
                            :class="{ 'opacity-70 pointer-events-none': isPublished }"
                        >
                            <AppBlockEditor
                                v-model="wording[locale.code].blocks"
                                :placeholder="
                                    t('backend.studio.contract_templates.content_placeholder')
                                "
                            />
                        </div>
                    </div>
                </template>
            </div>

            <div class="space-y-4">
                <!-- Which language prevails. Asked here rather than at
                     publication time, because it is a decision about the
                     wording somebody is writing, not a step in a dialog. -->
                <div class="rounded-lg border border-line bg-surface p-3 space-y-2">
                    <p class="text-xs font-medium uppercase tracking-wider text-muted">
                        {{ t("backend.studio.contract_templates.governing_locale") }}
                    </p>
                    <AppSelect
                        v-if="!isPublished"
                        :model-value="governingLocale ?? ''"
                        :options="governingSelectOptions"
                        :placeholder="t('backend.studio.contract_templates.governing_locale_none')"
                        :hint="t('backend.studio.contract_templates.governing_locale_hint')"
                        :error="errors.governingLocale"
                        v-on:update:model-value="governingLocale = $event === '' ? null : $event"
                    />
                    <p v-else class="text-sm text-secondary">
                        {{
                            governingLabel
                                ?? t("backend.studio.contract_templates.governing_locale_none")
                        }}
                    </p>
                    <!-- Said here, next to the field, rather than only by a
                         disabled publish button on the other side of the page. -->
                    <p v-if="needsGoverningLocale" class="text-xs text-amber-500">
                        {{ t("backend.studio.contract_templates.governing_locale_needed") }}
                    </p>
                </div>

                <ContractVariablePanel :groups="variableGroups" />

                <div
                    v-if="otherVersions.length"
                    class="rounded-lg border border-line bg-surface p-3 space-y-2"
                >
                    <p class="text-xs font-medium uppercase tracking-wider text-muted">
                        {{ t("backend.studio.contract_templates.other_versions") }}
                    </p>
                    <ul class="space-y-1">
                        <li v-for="each in otherVersions" :key="each.id">
                            <a
                                :href="versionPath(each.id)"
                                class="text-sm text-secondary hover:text-primary flex items-center gap-2"
                            >
                                <ScrollText class="w-3.5 h-3.5 shrink-0" :stroke-width="2" />
                                {{
                                    t("backend.studio.contract_templates.version_label", {
                                        number: each.number,
                                    })
                                }}
                                <span class="text-xs text-muted">
                                    {{
                                        each.isPublished
                                            ? t("backend.studio.contract_templates.state_published")
                                            : t("backend.studio.contract_templates.state_draft")
                                    }}
                                </span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <AppModal
            :show="showPublish"
            max-width="md"
            :closeable="false"
            :title="t('backend.studio.contract_templates.publish')"
            :icon="Check"
            v-on:close="showPublish = false"
        >
            <p class="text-sm text-primary">
                {{
                    t("backend.studio.contract_templates.publish_confirm", {
                        number: version.number,
                    })
                }}
            </p>
            <!-- The consequence, stated before the click rather than discovered
                 after it: this is the one action on the page that cannot be
                 undone. -->
            <p class="text-sm text-secondary">
                {{ t("backend.studio.contract_templates.publish_warning") }}
            </p>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" v-on:click="showPublish = false">
                        <X class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("shared.common.cancel") }}
                    </AppButton>
                    <AppButton
                        variant="primary"
                        size="md"
                        :loading="publishing || saving"
                        v-on:click="publish"
                    >
                        <Check class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("backend.studio.contract_templates.publish") }}
                    </AppButton>
                </AppModalFooter>
            </template>
        </AppModal>

        <AppModal
            :show="showDiscard"
            max-width="sm"
            :closeable="false"
            :title="t('backend.studio.contract_templates.discard')"
            :icon="Trash2"
            v-on:close="showDiscard = false"
        >
            <p class="text-sm text-primary">
                {{
                    t("backend.studio.contract_templates.discard_confirm", {
                        number: version.number,
                    })
                }}
            </p>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" v-on:click="showDiscard = false">
                        <X class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("shared.common.cancel") }}
                    </AppButton>
                    <AppButton variant="danger" size="md" v-on:click="discard">
                        <Trash2 class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("backend.studio.contract_templates.discard") }}
                    </AppButton>
                </AppModalFooter>
            </template>
        </AppModal>
    </div>
</template>
