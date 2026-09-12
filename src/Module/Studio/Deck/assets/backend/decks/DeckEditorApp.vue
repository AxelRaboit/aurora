<script setup>
/**
 * Composing one deck: the slides on the left, the one being written on the
 * right.
 *
 * **No Save button, and that is deliberate.** A deck is edited by hopping from
 * slide to slide, and every hop is a moment where the work on the one being
 * left is finished. Saving there costs the reader nothing to remember; a
 * button would be one more thing to forget before closing the tab.
 *
 * The preview is the same component the thumbnails use, at the same fixed
 * 16:9. A preview that reflowed would be a preview that lies about what lands
 * on the wall, which is the whole reason this module has layouts rather than a
 * flowing grid.
 */
import { onBeforeUnmount, onMounted } from "vue";
import { useI18n } from "vue-i18n";
import { usePrivileges } from "@/shared/composables/usePrivileges.js";
import { useDeckEditor } from "./composables/useDeckEditor.js";
import SlideFrame from "./components/SlideFrame.vue";
import AppButton from "@/shared/components/action/AppButton.vue";
import AppIconButton from "@/shared/components/action/AppIconButton.vue";
import AppInput from "@/shared/components/form/input/AppInput.vue";
import AppSelect from "@/shared/components/form/select/AppSelect.vue";
import AppTextarea from "@/shared/components/form/input/AppTextarea.vue";
import AppModal from "@/shared/components/overlay/AppModal.vue";
import AppModalFooter from "@/shared/components/overlay/AppModalFooter.vue";
import AppNoData from "@/shared/components/feedback/AppNoData.vue";
import {
    ArrowDown,
    ArrowUp,
    Plus,
    Presentation,
    Trash2,
    X,
} from "lucide-vue-next";

const { t } = useI18n();
const { can } = usePrivileges();

const props = defineProps({
    deck: { type: Object, required: true },
    layouts: { type: Array, default: () => [] },
    slideCreatePath: { type: String, required: true },
    slideUpdatePath: { type: String, required: true },
    slideDeletePath: { type: String, required: true },
    slideReorderPath: { type: String, required: true },
});

const {
    slides,
    selectedId,
    selected,
    slots,
    dirty,
    saving,
    pendingDelete,
    select,
    addSlide,
    confirmDeleteSlide,
    move,
    writeSlot,
    writeLayout,
    writeNotes,
    flushCurrent,
} = useDeckEditor(props);

const editable = can("studio.decks.edit");

const layoutOptions = props.layouts.map((layout) => ({
    value: layout.value,
    label: t(layout.labelKey),
}));

const labelFor = (slot) => t(`backend.studio.decks.slots.${slot}`);

/** Bullets are a list in the model and one line per bullet in the form. */
const bulletsText = () => (selected.value?.content.bullets ?? []).join("\n");

function writeBullets(value) {
    writeSlot(
        "bullets",
        value.split("\n").map((line) => line.trim()).filter(Boolean),
    );
}

/**
 * The last write out.
 *
 * `beforeunload` rather than only `onBeforeUnmount`: closing the tab never
 * unmounts anything, and that is exactly the moment somebody has just typed a
 * sentence they expect to find again.
 */
function onLeave() {
    flushCurrent();
}

onMounted(() => window.addEventListener("beforeunload", onLeave));
onBeforeUnmount(() => {
    window.removeEventListener("beforeunload", onLeave);
    flushCurrent();
});
</script>

<template>
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start">
        <!-- Les slides, dans l'ordre où elles seront montrées. -->
        <aside class="w-full shrink-0 xl:w-64">
            <div class="mb-2 flex items-center justify-between">
                <h2 class="m-0 text-xs font-semibold uppercase tracking-wide text-muted">
                    {{ t("backend.studio.decks.slides") }}
                </h2>
                <span class="text-xs tabular-nums text-muted">{{ slides.length }}</span>
            </div>

            <div class="flex flex-col gap-2">
                <div
                    v-for="(slide, at) in slides"
                    :key="slide.id"
                    class="group relative rounded-lg border p-1 transition-colors"
                    :class="slide.id === selectedId
                        ? 'border-accent bg-accent-600/10'
                        : 'border-line hover:border-line-strong'"
                >
                    <!-- `flex flex-col` plutôt que `block` : le contenu d'un
                         `<button>` se comporte comme une boîte qui étire ses
                         enfants, ce qui écrasait le rapport 16/9 de la
                         vignette et la rendait carrée. -->
                    <button
                        type="button"
                        class="flex w-full cursor-pointer flex-col items-stretch border-0 bg-transparent p-0 text-left"
                        :aria-current="slide.id === selectedId ? 'true' : undefined"
                        v-on:click="select(slide.id)"
                    >
                        <span class="mb-1 block px-1 text-[0.65rem] uppercase tracking-wide text-muted">
                            {{ at + 1 }}. {{ t(`backend.studio.decks.layouts.${slide.layout}`) }}
                        </span>
                        <!-- Dans une simple boîte de bloc : élément flex, la
                             vignette voyait sa hauteur décidée par son contenu
                             et le rapport 16/9 restait lettre morte. -->
                        <span class="block w-full">
                            <SlideFrame :slide="slide" compact />
                        </span>
                    </button>

                    <div
                        v-if="editable"
                        class="absolute top-1 right-1 flex gap-0.5 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100"
                    >
                        <AppIconButton
                            size="sm"
                            variant="ghost"
                            :disabled="at === 0"
                            :title="t('backend.studio.decks.move_up')"
                            v-on:click="move(slide, -1)"
                        >
                            <ArrowUp class="h-3 w-3" :stroke-width="2" />
                        </AppIconButton>
                        <AppIconButton
                            size="sm"
                            variant="ghost"
                            :disabled="at === slides.length - 1"
                            :title="t('backend.studio.decks.move_down')"
                            v-on:click="move(slide, 1)"
                        >
                            <ArrowDown class="h-3 w-3" :stroke-width="2" />
                        </AppIconButton>
                        <AppIconButton
                            size="sm"
                            variant="ghost"
                            :title="t('backend.studio.decks.delete_slide')"
                            v-on:click="pendingDelete = slide"
                        >
                            <Trash2 class="h-3 w-3" :stroke-width="2" />
                        </AppIconButton>
                    </div>
                </div>
            </div>

            <div v-if="editable" class="mt-3 space-y-1">
                <p class="m-0 px-1 text-xs text-muted">{{ t("backend.studio.decks.add_slide") }}</p>
                <div class="grid grid-cols-2 gap-1">
                    <AppButton
                        v-for="layout in layouts"
                        :key="layout.value"
                        variant="ghost"
                        size="sm"
                        v-on:click="addSlide(layout.value)"
                    >
                        <Plus class="h-3 w-3" :stroke-width="2" />
                        {{ t(layout.labelKey) }}
                    </AppButton>
                </div>
            </div>
        </aside>

        <section class="min-w-0 flex-1 space-y-4">
            <AppNoData
                v-if="!selected"
                :icon="Presentation"
                :title="t('backend.studio.decks.no_slide_title')"
                :description="t('backend.studio.decks.no_slide_description')"
            />

            <template v-else>
                <div class="mx-auto max-w-3xl">
                    <SlideFrame :slide="selected" />
                </div>

                <div class="mx-auto max-w-3xl space-y-4 rounded-xl border border-line bg-surface p-4">
                    <div class="flex items-center justify-between gap-3">
                        <AppSelect
                            :model-value="selected.layout"
                            :options="layoutOptions"
                            :label="t('backend.studio.decks.layout')"
                            :disabled="!editable"
                            class="flex-1"
                            v-on:update:model-value="writeLayout"
                        />
                        <span class="shrink-0 self-end pb-2 text-xs text-muted">
                            {{ saving
                                ? t("backend.studio.decks.saving")
                                : dirty
                                    ? t("backend.studio.decks.unsaved")
                                    : t("backend.studio.decks.saved") }}
                        </span>
                    </div>

                    <template v-for="slot in slots" :key="slot">
                        <AppTextarea
                            v-if="slot === 'bullets'"
                            :model-value="bulletsText()"
                            :label="labelFor(slot)"
                            :placeholder="t('backend.studio.decks.bullets_placeholder')"
                            :rows="5"
                            :disabled="!editable"
                            v-on:update:model-value="writeBullets"
                        />
                        <AppTextarea
                            v-else-if="['left', 'right', 'quote'].includes(slot)"
                            :model-value="selected.content[slot] ?? ''"
                            :label="labelFor(slot)"
                            :placeholder="t('backend.studio.decks.prose_placeholder')"
                            :rows="3"
                            :disabled="!editable"
                            v-on:update:model-value="(value) => writeSlot(slot, value)"
                        />
                        <AppInput
                            v-else-if="slot !== 'mediaId'"
                            :model-value="selected.content[slot] ?? ''"
                            :label="labelFor(slot)"
                            :placeholder="t('backend.studio.decks.prose_placeholder')"
                            :disabled="!editable"
                            v-on:update:model-value="(value) => writeSlot(slot, value)"
                        />
                    </template>

                    <AppTextarea
                        :model-value="selected.speakerNotes ?? ''"
                        :label="t('backend.studio.decks.speaker_notes')"
                        :placeholder="t('backend.studio.decks.speaker_notes_placeholder')"
                        :hint="t('backend.studio.decks.speaker_notes_hint')"
                        :rows="3"
                        :disabled="!editable"
                        v-on:update:model-value="writeNotes"
                    />
                </div>
            </template>
        </section>

        <AppModal
            :show="!!pendingDelete"
            max-width="sm"
            :closeable="false"
            :title="t('backend.studio.decks.delete_slide')"
            :icon="Trash2"
            v-on:close="pendingDelete = null"
        >
            <p class="text-sm text-primary">{{ t("backend.studio.decks.delete_slide_confirm") }}</p>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" v-on:click="pendingDelete = null">
                        <X class="h-3.5 w-3.5" :stroke-width="2" />
                        {{ t("shared.common.cancel") }}
                    </AppButton>
                    <AppButton variant="danger" size="md" v-on:click="confirmDeleteSlide">
                        <Trash2 class="h-3.5 w-3.5" :stroke-width="2" />
                        {{ t("shared.common.delete") }}
                    </AppButton>
                </AppModalFooter>
            </template>
        </AppModal>
    </div>
</template>
