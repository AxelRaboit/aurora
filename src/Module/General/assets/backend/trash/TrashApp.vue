<script setup>
import { useI18n } from "vue-i18n";
import AppCardLink from "@/shared/components/nav/AppCardLink.vue";
import AppNoData from "@/shared/components/feedback/AppNoData.vue";
import { Info } from "lucide-vue-next";
import { useTrashOverview } from "@general/backend/trash/composables/useTrashOverview.js";

/**
 * Where deleted things are, all of them, on one page.
 *
 * Names no module and imports none: each row comes from a
 * TrashSourceInterface on the PHP side, icon and label included, so a module
 * that is switched off simply contributes nothing rather than leaving a link
 * to a screen its reader cannot open.
 *
 * Read-only by design. Restoring a document and restoring a category obey
 * different rules - a folder releases its contents, a category frees its slug -
 * and a single button here would have to restate all of them, in a second
 * place, to be wrong in.
 */
const props = defineProps({
    trashes: { type: Array, default: () => [] },
    retentionDays: { type: Number, default: 0 },
});

const { t } = useI18n();

const { rows, total } = useTrashOverview(props);
</script>

<template>
    <div class="space-y-6">
        <div class="space-y-1">
            <p class="text-sm text-secondary">{{ t("backend.trash.intro") }}</p>
            <p class="text-xs text-muted">
                {{ retentionDays > 0 ? t("backend.trash.retention", { days: retentionDays }) : t("backend.trash.retention_off") }}
            </p>
        </div>

        <AppNoData
            v-if="total === 0"
            :message="t('backend.trash.all_empty')"
            :hint="t('backend.trash.all_empty_hint')"
        />

        <div v-else class="grid gap-3 sm:grid-cols-2">
            <!-- An empty trash keeps its row rather than disappearing: the
                 page answers "where is what I deleted", and a module missing
                 from the list reads as "this one has no trash at all". -->
            <AppCardLink
                v-for="row in rows"
                :key="row.key"
                :href="row.url ?? '#'"
                :title="t(row.labelKey)"
                :icon="row.iconComponent"
                :color="row.count > 0 ? 'rose' : 'accent'"
                :hide-chevron="!row.url"
            >
                <template #description>
                    <span v-if="row.count === 0">{{ t("backend.trash.empty_one") }}</span>
                    <span v-else>
                        {{ t("backend.trash.count", { count: row.count }, row.count) }}
                        <template v-if="row.daysLeft !== null">
                            &middot;
                            {{ row.daysLeft === 0 ? t("backend.trash.purge_due") : t("backend.trash.days_left", { count: row.daysLeft }, row.daysLeft) }}
                        </template>
                    </span>
                </template>
            </AppCardLink>
        </div>

        <p class="flex items-start gap-2 text-xs text-muted">
            <Info class="w-3.5 h-3.5 shrink-0 mt-0.5" :stroke-width="2" />
            {{ t("backend.trash.read_only") }}
        </p>
    </div>
</template>
