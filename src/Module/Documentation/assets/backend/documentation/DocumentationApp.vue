<script setup>
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import { ArrowLeft, ArrowRight, BookOpen, Search } from "lucide-vue-next";
import AppLightbox from "@/shared/components/overlay/AppLightbox.vue";
import { useDocumentationPage } from "./composables/useDocumentationPage.js";

/**
 * The product's manual, inside the product.
 *
 * Three columns on a wide screen and one on a narrow one: the rubrics on the
 * left because a reader arrives looking for a subject, the page in the
 * middle at a readable width, and its own sections on the right because a
 * page of six steps is scrolled more often than it is read straight through.
 *
 * Server-rendered navigation would have been possible; the page is handed
 * over whole and rendered here instead, because the search replaces the tree
 * in place and a round trip per keystroke would be a worse answer than a
 * hundred kilobytes of Markdown already in the page.
 */
const props = defineProps({
    page: { type: Object, required: true },
    tree: { type: Array, default: () => [] },
    neighbours: { type: Object, default: () => ({ previous: null, next: null }) },
    pagePathTemplate: { type: String, required: true },
    imagePathTemplate: { type: String, required: true },
    searchPath: { type: String, required: true },
});

const { t } = useI18n();

const { rendered, pictures, outline, query, results, searching, pageUrl, TRIGGER } =
    useDocumentationPage(props, t("backend.documentation.enlarge"));

const current = computed(() => props.page.slug);
</script>

<template>
    <div class="flex flex-col gap-6 xl:flex-row xl:items-start">
        <!-- Les rubriques. `sticky` sur grand écran : parcourir une page de
             six étapes ne devrait pas faire perdre la table des matières. -->
        <nav
            class="w-full shrink-0 xl:sticky xl:top-4 xl:max-h-[calc(100vh-2rem)] xl:w-64 xl:overflow-y-auto xl:border-r xl:border-line/60 xl:pr-5"
            :aria-label="t('backend.documentation.sections')"
        >
            <div class="relative mb-3">
                <Search class="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-muted" :stroke-width="2" />
                <input
                    v-model="query"
                    type="search"
                    class="w-full rounded-lg border border-line bg-surface py-2 pr-3 pl-8 text-sm text-primary placeholder:text-muted focus:border-accent focus:outline-none"
                    :placeholder="t('backend.documentation.search_placeholder')"
                    :aria-label="t('backend.documentation.search_placeholder')"
                >
            </div>

            <div v-if="searching" class="space-y-3">
                <p class="m-0 text-xs uppercase tracking-wide text-muted">
                    {{ results.length
                        ? t("backend.documentation.results", { count: results.length }, results.length)
                        : t("backend.documentation.no_result") }}
                </p>
                <a
                    v-for="result in results"
                    :key="result.slug"
                    class="block rounded-lg px-2 py-1.5 no-underline transition-colors hover:bg-surface-2"
                    :href="pageUrl(result.slug)"
                >
                    <span class="block text-sm font-medium text-primary">{{ result.title }}</span>
                    <span class="block text-xs text-muted">{{ result.rubric }}</span>
                    <span class="mt-0.5 block text-xs text-secondary line-clamp-2">{{ result.excerpt }}</span>
                </a>
            </div>

            <div v-else class="space-y-5">
                <div v-for="rubric in tree" :key="rubric.slug" class="space-y-1">
                    <p class="m-0 text-xs font-semibold uppercase tracking-wide text-muted">
                        {{ rubric.label }}
                    </p>
                    <a
                        v-for="entry in rubric.pages"
                        :key="entry.slug"
                        class="block rounded-md px-2 py-1 text-sm no-underline transition-colors"
                        :class="entry.slug === current
                            ? 'bg-surface-2 font-medium text-accent'
                            : 'text-secondary hover:bg-surface-2/60 hover:text-primary'"
                        :href="pageUrl(entry.slug)"
                        :aria-current="entry.slug === current ? 'page' : undefined"
                    >{{ entry.title }}</a>
                </div>
            </div>
        </nav>

        <article class="min-w-0 flex-1 xl:max-w-3xl">
            <header class="mb-6">
                <p class="m-0 flex items-center gap-1.5 text-xs uppercase tracking-wide text-accent">
                    <BookOpen class="h-3.5 w-3.5" :stroke-width="2" /> {{ page.rubric }}
                </p>
                <h1 class="mt-1 mb-1 text-2xl font-semibold text-primary">{{ page.title }}</h1>
                <p v-if="page.description" class="m-0 text-sm text-secondary">{{ page.description }}</p>
            </header>

            <!-- Le rendu vient de fichiers livrés avec le produit et passe par
                 DOMPurify : personne d'autre ne les écrit, et l'assainisseur
                 ne coûte rien. -->
            <!-- eslint-disable-next-line vue/no-v-html -->
            <div class="doc-prose prose max-w-none" v-html="rendered" />

            <nav
                v-if="neighbours.previous || neighbours.next"
                class="mt-10 flex flex-wrap gap-3 border-t border-line pt-6"
                :aria-label="t('backend.documentation.pagination')"
            >
                <a
                    v-if="neighbours.previous"
                    class="group flex max-w-[20rem] flex-col gap-0.5 rounded-xl border border-line bg-surface px-4 py-3 no-underline transition-colors hover:border-accent"
                    :href="pageUrl(neighbours.previous.slug)"
                >
                    <span class="flex items-center gap-1 text-xs uppercase tracking-wide text-muted">
                        <ArrowLeft class="h-3 w-3" :stroke-width="2" /> {{ t("backend.documentation.previous") }}
                    </span>
                    <span class="text-sm font-medium text-primary">{{ neighbours.previous.title }}</span>
                </a>
                <a
                    v-if="neighbours.next"
                    class="group ml-auto flex max-w-[20rem] flex-col gap-0.5 rounded-xl border border-line bg-surface px-4 py-3 text-right no-underline transition-colors hover:border-accent"
                    :href="pageUrl(neighbours.next.slug)"
                >
                    <span class="flex items-center justify-end gap-1 text-xs uppercase tracking-wide text-muted">
                        {{ t("backend.documentation.next") }} <ArrowRight class="h-3 w-3" :stroke-width="2" />
                    </span>
                    <span class="text-sm font-medium text-primary">{{ neighbours.next.title }}</span>
                </a>
            </nav>
        </article>

        <!-- Une capture d'un écran entier est illisible à la largeur d'une
             colonne de texte : le clic l'ouvre en grand, au clavier comme à
             la souris, et les flèches passent d'une étape à la suivante. -->
        <AppLightbox :items="pictures" :trigger="TRIGGER" />

        <!-- Les étapes de la page. Cachée quand elle n'apprendrait rien :
             une page d'une seule section n'a pas de sommaire. -->
        <aside
            v-if="outline.length > 1"
            class="hidden w-56 shrink-0 xl:sticky xl:top-4 xl:block"
            :aria-label="t('backend.documentation.on_this_page')"
        >
            <p class="m-0 mb-2 text-xs font-semibold uppercase tracking-wide text-muted">
                {{ t("backend.documentation.on_this_page") }}
            </p>
            <a
                v-for="heading in outline"
                :key="heading.id"
                class="block border-l border-line py-1 pl-3 text-xs text-secondary no-underline transition-colors hover:border-accent hover:text-primary"
                :href="`#${heading.id}`"
            >{{ heading.label }}</a>
        </aside>
    </div>
</template>
