<script setup>
/**
 * The manual's table of contents, in the side menu.
 *
 * This was a `w-64` column inside the documentation page, with the search
 * field on top of it. It cost the text a quarter of its width on every read,
 * and on a telephone it stacked above the page, so the first thing a reader
 * met was ninety links to somewhere else. Here it costs the page nothing: the
 * menu is already on screen, already scrolls, and already folds away on a
 * narrow screen.
 *
 * **Rows are plain links, and there is no bridge.** Unlike the GED and Notes
 * panels, a documentation page is a server-rendered address: moving to one is
 * a real navigation, which mounts this panel again with the new page's slug in
 * the URL. There is no page-side listener to ask, and nothing to keep in step.
 *
 * **Only the rubric being read opens by itself.** Eight rubrics and ninety-two
 * pages all open at once is a column the reader has to scroll before it says
 * anything. A fold the reader opened or closed is remembered and wins over
 * that default - `usePersistedExpanded` stores only what was said explicitly,
 * which is exactly the distinction needed here.
 */
import { computed, nextTick, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { ChevronDown, ChevronRight } from "lucide-vue-next";
import AppNavLink from "@/shared/components/nav/AppNavLink.vue";
import AppSearchInput from "@/shared/components/form/input/AppSearchInput.vue";
import AppModulePanel from "@/shared/nav/AppModulePanel.vue";
import { useModulePanelData } from "@/shared/nav/useModulePanelData.js";
import { useRequest } from "@/shared/composables/http/backend/useRequest.js";
import { usePersistedExpanded } from "@/shared/composables/usePersistedExpanded.js";
import { useSidemenuSectionTheme } from "@/backend/sidemenu/composables/useSidemenuSectionTheme.js";
import { HttpMethod } from "@/shared/utils/http/httpMethod.js";

const BASE_PATH = "/backend/documentation";
const TREE_ENDPOINT = `${BASE_PATH}/tree`;
const SEARCH_ENDPOINT = `${BASE_PATH}/search`;

/** Below this, a search says more about the reader's typing than their intent. */
const MIN_QUERY = 2;

const { t } = useI18n();
const { itemClasses } = useSidemenuSectionTheme();
const { request } = useRequest();

const {
    data: tree,
    loading,
    failed,
} = useModulePanelData(TREE_ENDPOINT, { key: "tree" });

/**
 * Which page is being read, from the address.
 *
 * The server knows it - it rendered the page - but the menu mounts this panel
 * with no props, and the address carries the answer already.
 */
const current = computed(() => {
    const path = window.location.pathname;

    return path.startsWith(`${BASE_PATH}/`)
        ? path.slice(BASE_PATH.length + 1).split("/")[0]
        : "";
});

const currentRubric = computed(
    () =>
        tree.value.find((rubric) =>
            rubric.pages.some((page) => page.slug === current.value),
        )?.slug ?? "",
);

const { isExpanded, toggle, getRaw } = usePersistedExpanded(
    "aurora-documentation-rubrics",
);

/** Folded unless the reader said otherwise, or unless they are reading it. */
function isOpen(rubric) {
    return undefined === getRaw(rubric.slug)
        ? rubric.slug === currentRubric.value
        : isExpanded(rubric.slug);
}

const query = ref("");
const results = ref([]);
const searching = computed(() => query.value.trim().length >= MIN_QUERY);

let rank = 0;

/**
 * Debounced by `AppSearchInput`, ordered by the counter.
 *
 * A slow answer to "cy" arriving after a fast one to "cycle" would replace the
 * results the reader is looking at with the ones they had already refined past.
 */
async function runSearch(value) {
    const term = value.trim();

    if (term.length < MIN_QUERY) {
        results.value = [];

        return;
    }

    rank += 1;
    const mine = rank;

    const url = `${SEARCH_ENDPOINT}?q=${encodeURIComponent(term)}`;
    // `silent`: a search nobody can reach leaves the rubrics in place, which is
    // still a way to find a page. `noGuard`: the page and the panel talk to the
    // server at the same time.
    const payload = await request(url, null, {
        method: HttpMethod.Get,
        silent: true,
        noGuard: true,
    });

    if (mine !== rank) return;

    results.value = payload?.results ?? [];
}

const pageUrl = (slug) => `${BASE_PATH}/${slug}`;
const isCurrent = (slug) => slug === current.value;
const rowClasses = (active) => itemClasses("documentation", { isActive: active });

const isEmpty = computed(() => 0 === tree.value.length);

/**
 * The page being read, brought into view.
 *
 * The menu does this for its own rows on mount, which is too early for a panel
 * that is still fetching; and the rubric this row lives in may have opened only
 * once the tree arrived.
 */
const listRef = ref(null);

function revealCurrent() {
    nextTick(() => {
        listRef.value
            ?.querySelector("[data-sidemenu-active='true']")
            ?.scrollIntoView({ block: "nearest", behavior: "instant" });
    });
}

watch(tree, revealCurrent);
</script>

<template>
    <AppModulePanel
        :title="t('backend.documentation.rubrics')"
        :loading="loading"
        :failed="failed"
        :empty="isEmpty"
        :empty-label="t('backend.documentation.empty')"
    >
        <div class="px-3 pb-1">
            <AppSearchInput
                v-model="query"
                :placeholder="t('backend.documentation.search_placeholder')"
                v-on:search="runSearch"
            />
        </div>

        <template v-if="searching">
            <p class="px-3 py-1 text-xs text-muted">
                {{
                    results.length
                        ? t(
                            "backend.documentation.results",
                            { count: results.length },
                            results.length,
                        )
                        : t("backend.documentation.no_result")
                }}
            </p>
            <AppNavLink
                v-for="result in results"
                :key="result.slug"
                :href="pageUrl(result.slug)"
                :tooltip-title="result.title"
                :tooltip-description="result.rubric"
                :link-classes-override="rowClasses(isCurrent(result.slug))"
            >
                <span class="min-w-0 flex-1">
                    <span class="block truncate">{{ result.title }}</span>
                    <span class="block truncate text-xs text-muted">{{
                        result.rubric
                    }}</span>
                </span>
            </AppNavLink>
        </template>

        <div v-else ref="listRef" class="flex flex-col gap-0.5">
            <template v-for="rubric in tree" :key="rubric.slug">
                <!-- Un bouton, pas un lien : une rubrique n'a pas de page à
                     elle, elle ouvre et ferme sa liste. -->
                <button
                    type="button"
                    class="si flex w-full items-center rounded-lg text-left text-sm font-medium text-secondary transition-colors hover:bg-surface-2 hover:text-primary"
                    :aria-expanded="isOpen(rubric)"
                    v-on:click="toggle(rubric.slug)"
                >
                    <component
                        :is="isOpen(rubric) ? ChevronDown : ChevronRight"
                        class="h-4 w-4 shrink-0 text-muted"
                        :stroke-width="2"
                    />
                    <span class="min-w-0 flex-1 truncate">{{
                        rubric.label
                    }}</span>
                </button>

                <!-- Décalées d'un cran par leur conteneur, pas par une classe
                     sur le lien : `AppNavLink` a plusieurs racines, donc rien
                     ne tombe dessus, et son `linkClassesOverride` sert la
                     couleur de la section. -->
                <div
                    v-if="isOpen(rubric)"
                    class="flex flex-col gap-0.5 pl-3"
                >
                    <AppNavLink
                        v-for="entry in rubric.pages"
                        :key="entry.slug"
                        :href="pageUrl(entry.slug)"
                        :tooltip-title="entry.title"
                        :tooltip-description="entry.description"
                        :sidemenu-active="isCurrent(entry.slug)"
                        :link-classes-override="rowClasses(isCurrent(entry.slug))"
                    >
                        <span class="min-w-0 flex-1 truncate">{{
                            entry.title
                        }}</span>
                    </AppNavLink>
                </div>
            </template>
        </div>
    </AppModulePanel>
</template>
