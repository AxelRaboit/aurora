<script setup>
import { computed, onMounted, ref } from "vue";
import { useI18n } from "vue-i18n";
import { toast } from "vue-sonner";
import { CheckCircle2, ExternalLink, PlugZap, Save, XCircle } from "lucide-vue-next";
import AppButton from "@/shared/components/action/AppButton.vue";
import AppInput from "@/shared/components/form/input/AppInput.vue";
import AppSelect from "@/shared/components/form/select/AppSelect.vue";
import AppLoader from "@/shared/components/feedback/AppLoader.vue";
import { useRequest } from "@/shared/composables/http/backend/useRequest.js";
import { HttpMethod } from "@/shared/utils/http/httpMethod.js";

/**
 * The storage tab of the settings screen.
 *
 * It draws itself rather than declaring fields, because two of the values
 * behind it are credentials that have no business being echoed into a page,
 * and one is a record of when a backend last answered rather than a value
 * anybody retypes.
 *
 * Most of what follows is instructions. Aurora is delivered to clients and the
 * bucket is theirs to open, so someone who has never heard of R2 has to be
 * able to finish this on their own.
 */
defineProps({
    groups: { type: Object, default: () => ({}) },
    updatePath: { type: String, default: "" },
});

const SETTINGS_PATH = "/backend/configuration/storage";

const { t } = useI18n();
const { request } = useRequest();

const loading = ref(true);
const saving = ref(false);
const testing = ref(false);

const activeDisk = ref("local");
const deliveryMode = ref("proxy");
const endpoint = ref("");
const bucket = ref("");
const publicBaseUrl = ref("");
const hasAccessKeyId = ref(false);
const hasSecretAccessKey = ref(false);
const verifiedAt = ref(null);

// Never pre-filled from the server: write-only from here on. Left empty, the
// save keeps whatever is stored.
const accessKeyId = ref("");
const secretAccessKey = ref("");

const probe = ref(null);

/**
 * Switching needs a backend that has actually answered. The server refuses
 * without it; disabling the option here means the reason is visible before
 * the click rather than reported after it.
 */
const canSwitchToR2 = computed(() => null !== verifiedAt.value);

// R2 is offered only once a probe has passed. Withheld rather than shown
// disabled: AppSelect has no disabled state, and an option that cannot be
// chosen is worse than an option that is not there when the hint underneath
// says why.
const diskOptions = computed(() => {
    const options = [{ value: "local", label: t("backend.settings.storage.disk_local") }];
    if (canSwitchToR2.value) {
        options.push({ value: "r2", label: t("backend.settings.storage.disk_r2") });
    }

    return options;
});

const deliveryOptions = computed(() => [
    { value: "proxy", label: t("backend.settings.storage.delivery_proxy") },
    { value: "presigned", label: t("backend.settings.storage.delivery_presigned") },
    { value: "public_url", label: t("backend.settings.storage.delivery_public_url") },
]);

const deliveryHint = computed(
    () => t(`backend.settings.storage.delivery_${deliveryMode.value}_hint`),
);

const verifiedOn = computed(() => {
    if (!verifiedAt.value) return "";
    const date = new Date(verifiedAt.value);

    return Number.isNaN(date.getTime()) ? verifiedAt.value : date.toLocaleString();
});

function apply(state) {
    if (!state) return;
    activeDisk.value = state.activeDisk ?? "local";
    deliveryMode.value = state.deliveryMode ?? "proxy";
    endpoint.value = state.endpoint ?? "";
    bucket.value = state.bucket ?? "";
    publicBaseUrl.value = state.publicBaseUrl ?? "";
    hasAccessKeyId.value = true === state.hasAccessKeyId;
    hasSecretAccessKey.value = true === state.hasSecretAccessKey;
    verifiedAt.value = state.verifiedAt ?? null;
    accessKeyId.value = "";
    secretAccessKey.value = "";
}

function payload() {
    const body = {
        activeDisk: activeDisk.value,
        deliveryMode: deliveryMode.value,
        endpoint: endpoint.value.trim(),
        bucket: bucket.value.trim(),
        publicBaseUrl: publicBaseUrl.value.trim(),
    };
    // Sent only when something was typed, so saving without touching a field
    // keeps the credential already stored.
    if ("" !== accessKeyId.value.trim()) body.accessKeyId = accessKeyId.value.trim();
    if ("" !== secretAccessKey.value.trim()) body.secretAccessKey = secretAccessKey.value.trim();

    return body;
}

onMounted(async () => {
    try {
        apply(await request(SETTINGS_PATH, null, { method: HttpMethod.Get, noGuard: true }));
    } finally {
        loading.value = false;
    }
});

async function save() {
    saving.value = true;
    try {
        const state = await request(SETTINGS_PATH, payload(), { noGuard: true });

        // Nothing on this screen changes visibly when a save works: the keys
        // come back as "a value is stored", not as themselves. Without a word
        // said, pressing the button looks like pressing nothing.
        if (state) {
            apply(state);
            toast.success(t("backend.settings.saved"));
        }
    } finally {
        saving.value = false;
    }
}

/**
 * Saves first, then probes. Testing what is on screen rather than what was
 * stored last time is the only behaviour that makes sense to someone who has
 * just pasted a key.
 */
async function test() {
    testing.value = true;
    probe.value = null;
    try {
        const saved = await request(SETTINGS_PATH, payload(), { noGuard: true });
        if (saved) apply(saved);

        const result = await request(`${SETTINGS_PATH}/test`, { disk: "r2" }, { noGuard: true });
        if (!result) return;

        probe.value = result;
        if (result.state) apply(result.state);
    } finally {
        testing.value = false;
    }
}

defineExpose({ save, apply, canSwitchToR2 });
</script>

<template>
    <div class="relative space-y-6">
        <AppLoader :active="loading" />

        <section class="space-y-2">
            <p class="text-sm text-secondary">{{ t("backend.settings.storage.intro") }}</p>
            <p class="text-sm text-muted">{{ t("backend.settings.storage.when_useful") }}</p>
        </section>

        <section class="space-y-2">
            <h3 class="text-sm font-medium text-primary">{{ t("backend.settings.storage.how_title") }}</h3>
            <ol class="list-decimal space-y-1 pl-5 text-sm text-secondary">
                <li>{{ t("backend.settings.storage.how_step_bucket") }}</li>
                <li>{{ t("backend.settings.storage.how_step_token") }}</li>
                <li>{{ t("backend.settings.storage.how_step_copy") }}</li>
            </ol>
            <a
                href="https://developers.cloudflare.com/r2/api/tokens/"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-1 text-sm text-accent hover:underline"
            >
                {{ t("backend.settings.storage.how_link") }}
                <ExternalLink class="w-3.5 h-3.5" :stroke-width="2" />
            </a>
        </section>

        <section class="space-y-3">
            <AppInput
                v-model="endpoint"
                :label="t('backend.settings.storage.endpoint_label')"
                :hint="t('backend.settings.storage.endpoint_hint')"
                placeholder="https://<compte>.r2.cloudflarestorage.com"
            />
            <AppInput
                v-model="bucket"
                :label="t('backend.settings.storage.bucket_label')"
                :hint="t('backend.settings.storage.bucket_hint')"
                placeholder="mon-projet-fichiers"
            />
            <AppInput
                v-model="accessKeyId"
                type="password"
                :toggleable="true"
                :label="t('backend.settings.storage.access_key_label')"
                :hint="hasAccessKeyId ? t('backend.settings.storage.key_stored') : t('backend.settings.storage.key_hint')"
                :placeholder="hasAccessKeyId ? '••••••••••••••••' : ''"
            />
            <AppInput
                v-model="secretAccessKey"
                type="password"
                :toggleable="true"
                :label="t('backend.settings.storage.secret_key_label')"
                :hint="hasSecretAccessKey ? t('backend.settings.storage.key_stored') : t('backend.settings.storage.key_hint')"
                :placeholder="hasSecretAccessKey ? '••••••••••••••••' : ''"
            />
            <AppInput
                v-model="publicBaseUrl"
                :label="t('backend.settings.storage.public_url_label')"
                :hint="t('backend.settings.storage.public_url_hint')"
                placeholder="https://files.exemple.fr"
            />
        </section>

        <section class="space-y-3 rounded-lg border border-line bg-surface-2 p-4">
            <p class="text-sm text-secondary">{{ t("backend.settings.storage.test_intro") }}</p>

            <div class="flex flex-wrap items-center gap-3">
                <AppButton variant="secondary" size="md" :loading="testing" v-on:click="test">
                    <PlugZap class="w-3.5 h-3.5" :stroke-width="2" /> {{ t("backend.settings.storage.test_button") }}
                </AppButton>
                <span v-if="verifiedAt" class="text-xs text-muted">
                    {{ t("backend.settings.storage.test_passed", { date: verifiedOn }) }}
                </span>
                <span v-else class="text-xs text-muted">{{ t("backend.settings.storage.test_never") }}</span>
            </div>

            <ul v-if="probe" class="space-y-1 text-sm">
                <li v-for="step in probe.steps" :key="step.key" class="flex items-start gap-2">
                    <CheckCircle2 v-if="step.ok" class="mt-0.5 w-3.5 h-3.5 shrink-0 text-success" :stroke-width="2" />
                    <XCircle v-else class="mt-0.5 w-3.5 h-3.5 shrink-0 text-danger" :stroke-width="2" />
                    <span :class="step.ok ? 'text-secondary' : 'text-danger'">
                        {{ t(`backend.settings.storage.probe.${step.key}`) }}
                        <span v-if="step.error" class="text-muted">- {{ step.error }}</span>
                    </span>
                </li>
            </ul>

            <p v-if="probe && probe.hint" class="text-sm text-warning">{{ t(probe.hint) }}</p>
        </section>

        <section class="space-y-3">
            <AppSelect
                v-model="deliveryMode"
                :options="deliveryOptions"
                :label="t('backend.settings.storage.delivery_title')"
                :hint="deliveryHint"
            />
            <p class="text-xs text-muted">{{ t("backend.settings.storage.delivery_hint") }}</p>

            <AppSelect
                v-model="activeDisk"
                :options="diskOptions"
                :label="t('backend.settings.storage.active_disk_label')"
                :hint="canSwitchToR2 ? t('backend.settings.storage.switch_hint') : t('backend.settings.storage.switch_blocked')"
            />
        </section>

        <div class="flex justify-end">
            <AppButton variant="primary" size="md" :loading="saving" v-on:click="save">
                <Save class="w-3.5 h-3.5" :stroke-width="2" /> {{ t("shared.common.save") }}
            </AppButton>
        </div>
    </div>
</template>
