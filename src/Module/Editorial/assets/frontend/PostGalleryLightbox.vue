<script setup>
/**
 * The public site's enlargeable pictures, mounted from Twig.
 *
 * The behaviour lives in `AppLightbox`, shared with the back office's manual
 * and with whatever else comes to need it. What stays here is the mount
 * point: `_gallery.html.twig` and `_grid.html.twig` name this component, and
 * a name in a template is an address that should not move because a
 * component was promoted to the shared library.
 */
import AppLightbox from "@/shared/components/overlay/AppLightbox.vue";

defineProps({
    items: { type: Array, default: () => [] },
    trigger: { type: String, default: "data-gallery-open" },
});
</script>

<template>
    <AppLightbox :items="items" :trigger="trigger" />
</template>
