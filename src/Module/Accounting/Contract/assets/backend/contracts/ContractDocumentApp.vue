<script setup>
/**
 * One contract: the document that was sealed, and the seal itself.
 *
 * This is where somebody comes after suspecting something, so the seal block
 * shows the whole answer rather than a green tick: the hash, the algorithm,
 * the canonical form, when it was sealed, and whether it still matches - that
 * last one recomputed on this request, not read back from a column.
 */
import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";
import { toast } from "vue-sonner";
import DOMPurify from "dompurify";
import { usePrivileges } from "@/shared/composables/usePrivileges.js";
import { useRequest } from "@/shared/composables/http/backend/useRequest.js";
import AppSignaturePad from "@/shared/components/form/input/AppSignaturePad.vue";
import AppButton from "@/shared/components/action/AppButton.vue";
import AppInput from "@/shared/components/form/input/AppInput.vue";
import AppMessage from "@/shared/components/feedback/AppMessage.vue";
import AppModal from "@/shared/components/overlay/AppModal.vue";
import AppModalFooter from "@/shared/components/overlay/AppModalFooter.vue";
import {
    ArrowLeft,
    Check,
    FileDown,
    Lock,
    PenLine,
    ShieldAlert,
    ShieldCheck,
    X,
} from "lucide-vue-next";

const { t } = useI18n();

const props = defineProps({
    contract: { type: Object, required: true },
    indexPath: { type: String, required: true },
    freezePath: { type: String, required: true },
    countersignPath: { type: String, required: true },
    pdfPath: { type: String, required: true },
});

const { can } = usePrivileges();
const { request } = useRequest();

const contract = ref({ ...props.contract });
const seal = computed(() => contract.value.seal ?? {});
const isFrozen = computed(() => contract.value.isFrozen === true);

/**
 * Only after the customer has signed, and only once.
 *
 * The countersignature concludes, so there has to be something to conclude -
 * the server enforces that too, and this is the affordance that matches it.
 */
const canCountersign = computed(
    () =>
        contract.value.status === "signed_by_customer" &&
        can("accounting.contracts.countersign"),
);

const showCountersign = ref(false);
const countersigning = ref(false);
const countersignErrors = ref({});
const countersignForm = ref({
    firstName: "",
    lastName: "",
    email: "",
    place: "",
    date: new Date().toISOString().slice(0, 10),
    signatureImage: "",
    consent: true,
    // Unused by this path: the provider is authenticated, so identity comes
    // from the session rather than from a mailed code. Sent because the DTO is
    // shared with the public form, where it is the credential.
    code: "n/a",
});

const canSubmitCountersign = computed(
    () =>
        countersignForm.value.firstName.trim() !== "" &&
        countersignForm.value.lastName.trim() !== "" &&
        countersignForm.value.email.trim() !== "" &&
        countersignForm.value.place.trim() !== "" &&
        countersignForm.value.signatureImage !== "" &&
        !countersigning.value,
);

async function countersign() {
    if (!canSubmitCountersign.value) return;

    countersigning.value = true;
    countersignErrors.value = {};

    try {
        const data = await request(
            props.countersignPath,
            countersignForm.value,
            { noGuard: true },
        );

        if (data?.errors) {
            countersignErrors.value = data.errors;

            return;
        }

        if (data?.contract) contract.value = data.contract;
        showCountersign.value = false;
        toast.success(t("backend.accounting.contracts.countersigned"));
    } finally {
        countersigning.value = false;
    }
}

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
            <div class="flex flex-wrap items-center gap-2">
                <AppButton variant="ghost" size="md" :href="indexPath">
                    <ArrowLeft class="w-3.5 h-3.5" :stroke-width="2" />
                    {{ t("shared.common.back") }}
                </AppButton>
                <!-- Only once there is a file. The row says whether one
                     exists, so the button never leads to a 404. -->
                <AppButton
                    v-if="contract.hasPdf"
                    variant="secondary"
                    size="md"
                    :href="pdfPath"
                >
                    <FileDown class="w-3.5 h-3.5" :stroke-width="2" />
                    {{ t("backend.accounting.contracts.download_pdf") }}
                </AppButton>
                <AppButton
                    v-if="canCountersign"
                    variant="primary"
                    size="md"
                    v-on:click="showCountersign = true"
                >
                    <PenLine class="w-3.5 h-3.5" :stroke-width="2" />
                    {{ t("backend.accounting.contracts.countersign") }}
                </AppButton>
            </div>
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

        <AppModal
            :show="showCountersign"
            max-width="lg"
            :closeable="false"
            :title="t('backend.accounting.contracts.countersign')"
            :icon="PenLine"
            v-on:close="showCountersign = false"
        >
            <div class="space-y-4">
                <AppMessage v-if="countersignErrors.status" variant="danger">
                    {{ countersignErrors.status }}
                </AppMessage>

                <!-- The consequence, before the click: this is what concludes
                     the contract. -->
                <p class="text-sm text-secondary">
                    {{ t("backend.accounting.contracts.countersign_intro") }}
                </p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <AppInput
                        v-model="countersignForm.firstName"
                        :label="t('accounting.public.sign.first_name')"
                        :placeholder="t('accounting.public.sign.first_name_placeholder')"
                        :error="countersignErrors.firstName"
                        required
                    />
                    <AppInput
                        v-model="countersignForm.lastName"
                        :label="t('accounting.public.sign.last_name')"
                        :placeholder="t('accounting.public.sign.last_name_placeholder')"
                        :error="countersignErrors.lastName"
                        required
                    />
                </div>

                <AppInput
                    v-model="countersignForm.email"
                    :label="t('accounting.public.sign.email')"
                    :placeholder="t('accounting.public.sign.email_placeholder')"
                    :error="countersignErrors.email"
                    type="email"
                    required
                />

                <div class="grid gap-4 sm:grid-cols-2">
                    <AppInput
                        v-model="countersignForm.place"
                        :label="t('accounting.public.sign.place')"
                        :placeholder="t('accounting.public.sign.place_placeholder')"
                        :error="countersignErrors.place"
                        required
                    />
                    <AppInput
                        v-model="countersignForm.date"
                        :label="t('accounting.public.sign.date')"
                        :placeholder="t('accounting.public.sign.date_placeholder')"
                        :error="countersignErrors.date"
                        type="date"
                        required
                    />
                </div>

                <!-- The same pad the customer used, so the two signatures are
                     the same kind of thing. -->
                <AppSignaturePad
                    v-model="countersignForm.signatureImage"
                    :label="t('accounting.public.sign.signature')"
                />
                <p v-if="countersignErrors.signatureImage" class="text-xs text-red-500">
                    {{ countersignErrors.signatureImage }}
                </p>
            </div>
            <template #footer>
                <AppModalFooter>
                    <AppButton variant="ghost" size="md" v-on:click="showCountersign = false">
                        <X class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("shared.common.cancel") }}
                    </AppButton>
                    <AppButton
                        variant="primary"
                        size="md"
                        :disabled="!canSubmitCountersign"
                        :loading="countersigning"
                        v-on:click="countersign"
                    >
                        <Check class="w-3.5 h-3.5" :stroke-width="2" />
                        {{ t("backend.accounting.contracts.countersign") }}
                    </AppButton>
                </AppModalFooter>
            </template>
        </AppModal>
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
