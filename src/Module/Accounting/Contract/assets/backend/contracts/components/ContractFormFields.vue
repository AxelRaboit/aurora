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

const props = defineProps({
    modelValue: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    customers: { type: Array, default: () => [] },
    bodies: { type: Array, default: () => [] },
    annexes: { type: Array, default: () => [] },
    locales: { type: Array, default: () => [] },
    currencies: { type: Array, default: () => [] },
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
</script>

<template>
    <div class="space-y-4">
        <AppSelect
            :model-value="form.customerId"
            :label="t('backend.accounting.contracts.customer')"
            :placeholder="t('backend.accounting.contracts.customer_placeholder')"
            :options="customers"
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
            <AppInput
                :model-value="form.effectiveDate"
                :label="t('backend.accounting.contracts.effective_date')"
                :placeholder="t('backend.accounting.contracts.effective_date_placeholder')"
                :hint="t('backend.accounting.contracts.effective_date_hint')"
                :error="errors.effectiveDate"
                type="date"
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
    </div>
</template>
