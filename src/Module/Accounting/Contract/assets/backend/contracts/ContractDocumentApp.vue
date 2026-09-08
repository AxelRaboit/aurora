<script setup>
/**
 * One contract: the document that was sealed, and the seal itself.
 *
 * This is where somebody comes after suspecting something, so the seal block
 * shows the whole answer rather than a green tick: the hash, the algorithm,
 * the canonical form, when it was sealed, and whether it still matches - that
 * last one recomputed on this request, not read back from a column.
 */
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import DOMPurify from "dompurify";
import AppButton from "@/shared/components/action/AppButton.vue";
import AppMessage from "@/shared/components/feedback/AppMessage.vue";
import { ArrowLeft, Lock, ShieldAlert, ShieldCheck } from "lucide-vue-next";

const { t } = useI18n();

const props = defineProps({
    contract: { type: Object, required: true },
    indexPath: { type: String, required: true },
    freezePath: { type: String, required: true },
});

const seal = computed(() => props.contract.seal ?? {});
const isFrozen = computed(() => props.contract.isFrozen === true);

const sealedAt = computed(() => {
    if (!seal.value.frozenAt) return null;

    return new Date(seal.value.frozenAt).toLocaleString();
});

/**
 * The stored document, cleaned again before it is put in the DOM.
 *
 * The renderer that produced it already sanitises every text and escapes every
 * substituted value, so this is belt and braces - but it is HTML read out of a
 * column and handed to `v-html`, and the seal that would report tampering is
 * checked by the reader after the markup has already run. The tag list is the
 * one the contract renderer can emit, so cleaning here can only ever remove
 * something that had no business being there.
 */
const documentHtml = computed(() =>
    DOMPurify.sanitize(props.contract.renderedHtml ?? "", {
        ALLOWED_TAGS: [
            "section",
            "h1",
            "h2",
            "h3",
            "h4",
            "p",
            "ul",
            "ol",
            "li",
            "blockquote",
            "footer",
            "table",
            "tr",
            "th",
            "td",
            "hr",
            "b",
            "strong",
            "i",
            "em",
            "u",
            "s",
            "mark",
            "code",
            "br",
            "span",
        ],
        ALLOWED_ATTR: ["class", "style"],
    }),
);
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-1">
                <h1 class="text-lg font-semibold text-primary">
                    {{ contract.reference ?? t("backend.accounting.contracts.draft_title") }}
                </h1>
                <p class="text-sm text-muted">
                    {{ contract.customerName }} · {{ t(contract.statusLabel) }}
                </p>
            </div>
            <AppButton variant="ghost" size="md" :href="indexPath">
                <ArrowLeft class="w-3.5 h-3.5" :stroke-width="2" />
                {{ t("shared.common.back") }}
            </AppButton>
        </div>

        <!-- A draft has no document yet, and saying so is more useful than an
             empty frame. -->
        <AppMessage v-if="!isFrozen" variant="info">
            <span class="flex items-center gap-2">
                <Lock class="w-4 h-4 shrink-0" :stroke-width="2" />
                {{ t("backend.accounting.contracts.not_sealed_yet") }}
            </span>
        </AppMessage>

        <template v-else>
            <div
                class="rounded-lg border p-4 space-y-3"
                :class="
                    seal.verified
                        ? 'border-emerald-500/40 bg-emerald-500/5'
                        : 'border-red-500/50 bg-red-500/5'
                "
            >
                <div class="flex items-start gap-2">
                    <ShieldCheck
                        v-if="seal.verified"
                        class="w-5 h-5 shrink-0 text-emerald-500 mt-0.5"
                        :stroke-width="2"
                    />
                    <ShieldAlert
                        v-else
                        class="w-5 h-5 shrink-0 text-red-500 mt-0.5"
                        :stroke-width="2"
                    />
                    <div class="space-y-1 min-w-0">
                        <p
                            class="font-medium"
                            :class="seal.verified ? 'text-emerald-500' : 'text-red-500'"
                        >
                            {{
                                seal.verified
                                    ? t("backend.accounting.contracts.seal_intact")
                                    : t("backend.accounting.contracts.seal_broken")
                            }}
                        </p>
                        <p class="text-xs text-secondary">
                            {{
                                seal.verified
                                    ? t("backend.accounting.contracts.seal_intact_hint")
                                    : t("backend.accounting.contracts.seal_broken_hint")
                            }}
                        </p>
                    </div>
                </div>

                <dl class="grid gap-2 sm:grid-cols-2 text-xs">
                    <div class="space-y-0.5 sm:col-span-2">
                        <dt class="text-muted uppercase tracking-wider">
                            {{ t("backend.accounting.contracts.seal_hash") }}
                        </dt>
                        <dd class="font-mono text-primary break-all">
                            {{ seal.contentHash }}
                        </dd>
                    </div>
                    <div class="space-y-0.5">
                        <dt class="text-muted uppercase tracking-wider">
                            {{ t("backend.accounting.contracts.seal_algo") }}
                        </dt>
                        <dd class="font-mono text-primary">
                            {{ seal.hashAlgo }} · c{{ seal.canonicalVersion }}
                        </dd>
                    </div>
                    <div class="space-y-0.5">
                        <dt class="text-muted uppercase tracking-wider">
                            {{ t("backend.accounting.contracts.seal_sealed_at") }}
                        </dt>
                        <dd class="text-primary">{{ sealedAt ?? "-" }}</dd>
                    </div>
                </dl>
            </div>

            <!-- The document as it was rendered and hashed. Printed from the
                 stored HTML, never re-rendered: re-rendering would show what
                 today's code produces rather than what was signed. Cleaned
                 once more on the way into the DOM, see documentHtml. -->
            <article
                class="bg-surface border border-line rounded-lg p-6 prose-contract"
                v-html="documentHtml"
            />

            <p class="text-xs text-muted">
                {{ t("backend.accounting.contracts.deferred_tokens_hint") }}
            </p>
        </template>
    </div>
</template>

<style scoped>
/**
 * The document's own typography, scoped to it.
 *
 * The stored HTML is semantic and carries no classes on purpose - it has to
 * survive a PDF engine and a decade of storage - so the styling lives here
 * rather than in the markup that was hashed.
 */
.prose-contract :deep(h1) {
    font-size: 1.125rem;
    font-weight: 600;
    text-align: center;
    margin: 0 0 1.5rem;
}

.prose-contract :deep(h2) {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 1.75rem 0 0.5rem;
}

.prose-contract :deep(h3),
.prose-contract :deep(h4) {
    font-size: 0.9rem;
    font-weight: 600;
    margin: 1.25rem 0 0.35rem;
}

.prose-contract :deep(p) {
    margin: 0 0 0.75rem;
    line-height: 1.65;
    text-align: justify;
}

.prose-contract :deep(ul),
.prose-contract :deep(ol) {
    margin: 0 0 0.75rem 1.25rem;
    line-height: 1.65;
}

.prose-contract :deep(blockquote) {
    margin: 0 0 0.75rem;
    padding-left: 0.75rem;
    border-left: 2px solid currentColor;
    opacity: 0.85;
}

.prose-contract :deep(table) {
    width: 100%;
    border-collapse: collapse;
    margin: 0 0 1rem;
    font-size: 0.85rem;
}

.prose-contract :deep(th),
.prose-contract :deep(td) {
    border: 1px solid currentColor;
    padding: 0.35rem 0.5rem;
    text-align: left;
}

.prose-contract :deep(th) {
    font-weight: 600;
}

.prose-contract :deep(hr) {
    margin: 1.5rem 0;
    border: 0;
    border-top: 1px solid currentColor;
    opacity: 0.25;
}

.prose-contract :deep(section + section) {
    margin-top: 3rem;
    padding-top: 2rem;
    border-top: 1px solid currentColor;
}
</style>
