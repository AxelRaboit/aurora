<script setup>
import { useI18n } from "vue-i18n";
import { ArrowLeft, ArrowRight, BookOpen } from "lucide-vue-next";
import AppLightbox from "@/shared/components/overlay/AppLightbox.vue";
import { useDocumentationPage } from "./composables/useDocumentationPage.js";

/**
 * The product's manual, inside the product.
 *
 * Two columns on a wide screen and one on a narrow one: the page at a readable
 * width, and its own sections on the right because a page of six steps is
 * scrolled more often than it is read straight through.
 *
 * It used to carry a third column on the left - the rubrics, and the search
 * above them. That column is `RubricsPanel` now, in the side menu, where it
 * costs the text none of its width and folds away with the menu on a
 * telephone.
 */
const props = defineProps({
    page: { type: Object, required: true },
    neighbours: { type: Object, default: () => ({ previous: null, next: null }) },
    pagePathTemplate: { type: String, required: true },
    imagePathTemplate: { type: String, required: true },
});

const { t } = useI18n();

const { rendered, pictures, outline, pageUrl, TRIGGER } =
    useDocumentationPage(props, t("backend.documentation.enlarge"));
</script>

<template>
    <div class="flex flex-col gap-6 xl:flex-row xl:items-start">
        <!-- `4xl` plutôt que `3xl` : c'est la largeur que la colonne de
             gauche occupait, rendue au texte et surtout aux captures, qui
             font 1600 pixels et se lisaient à l'étroit. -->
        <article class="min-w-0 flex-1 xl:max-w-4xl">
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
             une page d'une seule section n'a pas de sommaire.

             Collé sous la barre du haut, pas à seize pixels du haut de la
             fenêtre : celle-ci est collée elle aussi, et le sommaire passait
             dessous. Sur une page à quatre sections, il n'en restait que la
             dernière ligne visible. D'où la hauteur bornée et le défilement
             qui lui est propre. -->
        <aside
            v-if="outline.length > 1"
            class="hidden w-56 shrink-0 xl:sticky xl:top-[calc(var(--aurora-topbar)+1rem)] xl:block xl:max-h-[calc(100vh-var(--aurora-topbar)-2rem)] xl:overflow-y-auto"
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
