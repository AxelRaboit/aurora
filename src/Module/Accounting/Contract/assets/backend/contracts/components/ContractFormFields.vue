<script setup>
/**
 * The choices that make a contract, shared by create and edit.
 *
 * Templates rather than versions, because that is the choice a person makes.
 * The pickers only carry what can actually produce a document, so an option
 * here is never a dead end.
 */
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import AppInput from "@/shared/components/form/input/AppInput.vue";
import AppSelect from "@/shared/components/form/select/AppSelect.vue";
import AppDatePicker from "@/shared/components/form/picker/AppDatePicker.vue";

const props = defineProps({
    modelValue: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    customers: { type: Array, default: () => [] },
    bodies: { type: Array, default: () => [] },
    annexes: { type: Array, default: () => [] },
    locales: { type: Array, default: () => [] },
    currencies: { type: Array, default: () => [] },
    /** Concluded, running, non-amendment contracts this one may amend. */
    amendable: { type: Array, default: () => [] },
});

const emit = defineEmits(["update:modelValue"]);
const { t } = useI18n();
const form = computed(() => props.modelValue);

function set(field, value) {
    emit("update:modelValue", { ...props.modelValue, [field]: value });
}

const localeOptions = computed(() =>
    props.locales.map((locale) => ({ value: locale.code, label: locale.label })),
);

const amendableOptions = computed(() =>
    props.amendable.map((contract) => ({
        value: contract.id,
        label: `${contract.reference} · ${contract.customerName}`,
    })),
);

/**
 * Choosing a parent decides the customer, rather than the other way round.
 *
 * The manager refuses an amendment whose customer is not the parent's, so
 * picking a parent fills the customer in. It is not locked afterwards -
 * `AppSelect` has no disabled state and adding one for this alone would be a
 * shared component changed for one screen - so the hint says where the value
 * came from, and the manager is what actually holds the rule.
 */
const amendedContract = computed(() =>
    props.amendable.find((contract) => contract.id === form.value.amendsId),
);

function setAmends(value) {
    const parent = props.amendable.find((contract) => contract.id === value);

    emit("update:modelValue", {
        ...props.modelValue,
        amendsId: value,
        // Follows the parent when there is one, and is left alone when the
        // choice is cleared: somebody who picked a customer first should not
        // lose it by opening and closing this select.
        customerId: parent ? parent.customerId : props.modelValue.customerId,
    });
}

/**
 * The blanks the chosen trames ask this contract to fill.
 *
 * Read off the picked options rather than declared here: the wording is what
 * asks, so a clause edited tomorrow changes this list without touching the
 * form. Body and annex are merged, since a document is both.
 */
const requiredCustomFields = computed(() => {
    const keys = new Set();

    for (const [options, chosen] of [
        [props.bodies, form.value.bodyTemplateId],
        [props.annexes, form.value.annexTemplateId],
    ]) {
        const option = options.find((each) => each.value === String(chosen));

        for (const key of option?.customFields ?? []) keys.add(key);
    }

    return [...keys];
});

/** `interlocuteur_nom` reads as "Interlocuteur nom" rather than as a token. */
function labelFor(key) {
    const words = key.replace(/_/g, " ");

    return words.charAt(0).toUpperCase() + words.slice(1);
}

function setCustomField(key, value) {
    set("customFields", { ...(props.modelValue.customFields ?? {}), [key]: value });
}
</script>

<template>
    <div class="space-y-4">
        <!-- First, because it is the question that changes the meaning of every
             field under it: an amendment names the document it modifies, and
             that document decides the customer. -->
        <AppSelect
            v-if="amendable.length"
            :model-value="form.amendsId"
            :label="t('backend.accounting.contracts.amends')"
            :placeholder="t('backend.accounting.contracts.amends_placeholder')"
            :options="amendableOptions"
            :hint="t('backend.accounting.contracts.amends_hint')"
            :error="errors.amendsId"
            v-on:update:model-value="setAmends($event)"
        />

        <AppSelect
            :model-value="form.customerId"
            :label="t('backend.accounting.contracts.customer')"
            :placeholder="t('backend.accounting.contracts.customer_placeholder')"
            :options="customers"
            :hint="amendedContract ? t('backend.accounting.contracts.customer_from_amended', { reference: amendedContract.reference }) : ''"
            :error="errors.customerId"
            required
            v-on:update:model-value="set('customerId', $event)"
        />

        <AppSelect
            :model-value="form.bodyTemplateId"
            :label="t('backend.accounting.contracts.body')"
            :placeholder="t('backend.accounting.contracts.body_placeholder')"
            :options="bodies"
            :hint="t('backend.accounting.contracts.body_hint')"
            :error="errors.bodyTemplateId"
            required
            v-on:update:model-value="set('bodyTemplateId', $event)"
        />

        <AppSelect
            :model-value="form.annexTemplateId"
            :label="t('backend.accounting.contracts.annex')"
            :placeholder="t('backend.accounting.contracts.annex_none')"
            :options="annexes"
            :hint="t('backend.accounting.contracts.annex_hint')"
            :error="errors.annexTemplateId"
            v-on:update:model-value="set('annexTemplateId', $event)"
        />

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <AppInput
                    :model-value="form.amount"
                    :label="t('backend.accounting.contracts.amount')"
                    :placeholder="t('backend.accounting.contracts.amount_placeholder')"
                    :hint="t('backend.accounting.contracts.amount_hint')"
                    :error="errors.amountCents"
                    v-on:update:model-value="set('amount', $event)"
                />
            </div>
            <AppSelect
                :model-value="form.amountCurrency"
                :label="t('backend.accounting.contracts.currency')"
                :options="currencies"
                v-on:update:model-value="set('amountCurrency', $event)"
            />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <AppDatePicker
                :model-value="form.effectiveDate"
                :label="t('backend.accounting.contracts.effective_date')"
                :placeholder="t('backend.accounting.contracts.effective_date_placeholder')"
                :hint="t('backend.accounting.contracts.effective_date_hint')"
                :error="errors.effectiveDate"
                v-on:update:model-value="set('effectiveDate', $event)"
            />
            <AppSelect
                :model-value="form.locale"
                :label="t('backend.accounting.contracts.locale')"
                :options="localeOptions"
                :hint="t('backend.accounting.contracts.locale_hint')"
                v-on:update:model-value="set('locale', $event)"
            />
        </div>

        <!-- The blanks the chosen trames leave to this contract. Shown only
             when a trame asks for some, so a form for a trame that asks for
             none looks exactly as it did before. -->
        <div
            v-if="requiredCustomFields.length"
            class="space-y-3 rounded-lg border border-line bg-surface-2/40 p-4"
        >
            <div class="space-y-1">
                <p class="text-sm font-medium text-primary">
                    {{ t("backend.accounting.contracts.custom_fields") }}
                </p>
                <p class="text-xs text-muted">
                    {{ t("backend.accounting.contracts.custom_fields_hint") }}
                </p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <AppInput
                    v-for="key in requiredCustomFields"
                    :key="key"
                    :model-value="form.customFields?.[key] ?? ''"
                    :label="labelFor(key)"
                    :placeholder="t('backend.accounting.contracts.custom_field_placeholder')"
                    :error="errors.customFields"
                    required
                    v-on:update:model-value="setCustomField(key, $event)"
                />
            </div>
        </div>
    </div>
</template>
