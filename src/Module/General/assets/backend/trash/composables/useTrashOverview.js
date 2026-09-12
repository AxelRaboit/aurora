import { computed } from "vue";
import { resolveNavIcon } from "@/shared/nav/navMeta.js";

/**
 * The rows of the overview, and the one number that is not on the server.
 *
 * How long something has been in a trash is not what the reader is asking:
 * they want to know how long is left before it goes for good. That is the
 * retention window minus the age of the oldest row, and it is computed here
 * rather than on the server so it stays right on a page left open.
 *
 * A negative result is possible and shown as zero: the purge runs nightly, so
 * between the expiry and 3 a.m. there are rows past their window still sitting
 * in the trash. Saying "in -1 day" would be a bug report waiting to happen.
 */
export function useTrashOverview(props) {
    const rows = computed(() =>
        (props.trashes ?? []).map((trash) => ({
            ...trash,
            iconComponent: resolveNavIcon(trash.icon),
            daysLeft: daysLeft(trash.oldestDeletedAt, props.retentionDays),
        })),
    );

    const total = computed(() =>
        rows.value.reduce((sum, row) => sum + row.count, 0),
    );

    return { rows, total };
}

/**
 * Whole days between now and the moment this row is purged, or null when
 * nothing is waiting or the automatic purge is switched off.
 */
export function daysLeft(oldestDeletedAt, retentionDays) {
    if (!oldestDeletedAt || !retentionDays || retentionDays <= 0) return null;

    const deletedAt = new Date(oldestDeletedAt);
    if (Number.isNaN(deletedAt.getTime())) return null;

    const purgeAt = deletedAt.getTime() + retentionDays * 86_400_000;
    const remaining = Math.ceil((purgeAt - Date.now()) / 86_400_000);

    return Math.max(remaining, 0);
}
