<script setup>
/**
 * 2-column responsive toolbar for admin list pages. Mobile = stacked,
 * desktop (sm+) = search left (1fr) + actions right (auto).
 *
 * Default slot = left content (typically AppSearchInput).
 * `actions` slot = right content (typically one or more AppButton).
 * `inline` slot = stays **beside** the search, mobile included.
 *
 * The `inline` slot exists for the controls that belong to the search rather
 * than beside it - a view toggle, a scope switch. Stacked under the field on a
 * phone they read as a second filter and eat a row; the primary action is the
 * one that earns its own row, and it keeps `actions`.
 *
 * The component is layout-only; consumers compose the search input and
 * action buttons themselves so it stays usable for any admin list page.
 */
import { useSlots } from "vue";

const slots = useSlots();
</script>

<template>
    <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-2">
        <div v-if="slots.inline" class="flex items-center gap-2 min-w-0">
            <div class="flex-1 min-w-0">
                <slot />
            </div>
            <slot name="inline" />
        </div>
        <slot v-else />

        <slot name="actions" />
    </div>
</template>
