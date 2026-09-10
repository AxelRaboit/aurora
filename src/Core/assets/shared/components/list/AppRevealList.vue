<script setup>
import { computed, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { ChevronDown, ChevronUp } from "lucide-vue-next";

/**
 * A list that shows its first few entries and hides the rest behind a toggle.
 *
 * A column of filters grows with the site: a documentation with seventeen
 * rubrics pushes the list itself below the fold, and the reader scrolls past
 * the filters to reach what they are filtering. Five is enough to show what
 * the column is; the rest is one click away.
 *
 * **Driven by `items`, not by slotted children.** Counting what a slot
 * rendered means reading the virtual DOM, which breaks the day a caller
 * wraps its rows in anything. Handing over the list keeps the component's
 * job simple: decide what is visible, and say how much is not.
 *
 * `isActive` is the part that matters for correctness. A hidden entry that
 * is *ticked* filters the screen with nothing on screen to explain it, so
 * the list opens itself when one of the hidden ones is active - on load and
 * whenever it changes.
 */
const props = defineProps({
    /** Everything the list could show, in order. */
    items: { type: Array, required: true },
    /** How many are shown while it is folded. */
    visible: { type: Number, default: 5 },
    /** Tells whether an entry is currently doing something, e.g. ticked. */
    isActive: { type: Function, default: null },
});

const { t } = useI18n();

const expanded = ref(false);

const hidden = computed(() => Math.max(0, props.items.length - props.visible));

const shown = computed(() =>
    expanded.value ? props.items : props.items.slice(0, props.visible),
);

/** An active entry among the hidden ones opens the list and keeps it open. */
const activeIsHidden = computed(() => {
    if (null === props.isActive || 0 === hidden.value) return false;

    return props.items
        .slice(props.visible)
        .some((item) => props.isActive(item));
});

watch(
    activeIsHidden,
    (yes) => {
        if (yes) expanded.value = true;
    },
    { immediate: true },
);
</script>

<template>
    <div class="space-y-1">
        <slot v-for="item in shown" :key="item.id ?? item" :item="item" />

        <button
            v-if="hidden > 0"
            type="button"
            class="flex items-center gap-1 pt-0.5 text-xs text-secondary transition-colors hover:text-primary"
            v-on:click="expanded = !expanded"
        >
            <component :is="expanded ? ChevronUp : ChevronDown" class="h-3.5 w-3.5" :stroke-width="2" />
            {{ expanded ? t("shared.common.collapse") : t("shared.reveal.more", { count: hidden }, hidden) }}
        </button>
    </div>
</template>
