<script setup>
import { ref, computed } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    modelType: String, // 'CustomerInvoice' | 'SupplierInvoice'
    hasProjectNameColumn: Boolean,
    company: Object,
    contractName: String, // null when this down payment isn't tied to a contract
    invoices: Array,
    currency: String,
    customerNameText: String, // localized label — "Customer Name" or "Supplier Name"
    partnerId: [Number, String],
    partnerName: String,
    downPaymentId: [Number, String],
    downPaymentAmountFormatted: String,
    downPaymentAmount: Number,
    urls: Object,
});

const page = usePage();
const errors = computed(() => page.props.errors || {});
const errorMessages = computed(() => Object.values(errors.value).flat().filter(Boolean));

const settlementDate = ref(new Date().toISOString().slice(0, 10));

/* Each row starts pre-filled with whatever's already settled against
   that invoice for this down payment (matches the original: editing
   an existing settlement shows the current amounts, not zeros). */
const rows = ref(props.invoices.map(inv => ({
    invoice_id: inv.invoice_id,
    project_name: inv.project_name,
    invoice_number: inv.invoice_number,
    invoice_date_formatted: inv.invoice_date_formatted,
    invoice_due_date_formatted: inv.invoice_due_date_formatted,
    currency: inv.currency,
    net_invoice_amount_formatted: inv.net_invoice_amount_formatted,
    collected_amount_formatted: inv.collected_amount_formatted,
    net_balance_formatted: inv.net_balance_formatted,
    settlement_amount: inv.settlement_amount,
    withhold_amount: inv.withhold_amount,
})));

/* Client-side mirror of StoreDownPaymentSettlementRequest's own rule
   (NumberMustBeGreaterThanOrEqualRule: received_amount >= sum of
   settlement_amount) — the server still enforces this independently,
   this is just the same up-front feedback the original form gave via
   its own JS validation. */
const totalSettlementAmount = computed(() =>
    rows.value.reduce((sum, r) => sum + (Number(r.settlement_amount) || 0), 0)
);
const totalExceedsDownPayment = computed(() =>
    Math.round(totalSettlementAmount.value * 100) > Math.round(Number(props.downPaymentAmount) * 100)
);

const submitting = ref(false);
function submit() {
    submitting.value = true;
    const payload = {
        model_type: props.modelType,
        down_payment_id: props.downPaymentId,
        received_amount: props.downPaymentAmount,
        settlement_date: settlementDate.value,
        settlements: rows.value.map(r => ({
            invoice_id: r.invoice_id,
            invoice_number: r.invoice_number,
            ...(props.hasProjectNameColumn ? { project_name: r.project_name } : {}),
            settlement_amount: r.settlement_amount,
            withhold_amount: r.withhold_amount,
        })),
    };
    router.post(props.urls.store, payload, { onFinish: () => { submitting.value = false; } });
}
</script>

<template>
    <AppLayout>
        <div class="p-6 max-w-7xl mx-auto">
            <div class="flex items-center gap-3 mb-1">
                <Link :href="urls.back" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    ← Back to Down Payments
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-6">
                {{ contractName ? `Settlement Using Contract Down Payment [${contractName}]` : 'Settlement Using Down Payment' }}
            </h1>

            <div v-if="errorMessages.length" class="mb-4 px-4 py-3 rounded cvr-badge-overdue text-sm">
                <p v-for="(msg, i) in errorMessages" :key="i">{{ msg }}</p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="cvr-card-bg cvr-border border rounded-lg p-5">
                    <h2 class="text-base font-medium cvr-text-primary mb-4">
                        {{ contractName ? `Settlement Using Contract Down Payment [${contractName}]` : 'Settlement Using Down Payment' }}
                    </h2>
                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">{{ customerNameText }} *</label>
                            <input disabled :value="partnerName" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Down Payment Amount</label>
                            <input disabled :value="downPaymentAmountFormatted" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Currency *</label>
                            <input disabled :value="currency?.toUpperCase()" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Settlement Date</label>
                            <input v-model="settlementDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errors.settlement_date" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.settlement_date }}</p>
                        </div>
                    </div>
                </div>

                <div class="cvr-card-bg cvr-border border rounded-lg p-5">
                    <h2 class="text-base font-medium cvr-text-primary mb-4">Settlement Information</h2>

                    <div v-if="rows.length === 0" class="text-sm cvr-text-muted py-6 text-center">
                        No open invoices found for this customer in this currency.
                    </div>

                    <div v-for="row in rows" :key="row.invoice_id" class="border-b cvr-border pb-4 mb-4 last:border-0 last:pb-0 last:mb-0">
                        <div class="mb-3 max-w-sm" v-if="hasProjectNameColumn">
                            <label class="cvr-form-label">Project Name</label>
                            <input disabled :value="row.project_name" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div class="cvr-form-grid-3 mb-3">
                            <div>
                                <label class="cvr-form-label">Invoice Number</label>
                                <input disabled :value="row.invoice_number" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Invoice Date</label>
                                <input disabled :value="row.invoice_date_formatted" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Due Date</label>
                                <input disabled :value="row.invoice_due_date_formatted" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                            </div>
                        </div>
                        <div class="cvr-form-grid-5">
                            <div>
                                <label class="cvr-form-label">Invoice Amount [{{ row.currency }}]</label>
                                <input disabled :value="row.net_invoice_amount_formatted" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Collected Amount</label>
                                <input disabled :value="row.collected_amount_formatted" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Net Balance</label>
                                <input disabled :value="row.net_balance_formatted" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Settlement Amount *</label>
                                <input v-model="row.settlement_amount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                                <p v-if="errors[`settlements.${rows.indexOf(row)}.settlement_amount`]" class="text-xs mt-1" style="color: var(--cvr-danger-text)">
                                    {{ errors[`settlements.${rows.indexOf(row)}.settlement_amount`] }}
                                </p>
                            </div>
                            <div>
                                <label class="cvr-form-label">Withhold Amount *</label>
                                <input v-model="row.withhold_amount" type="number" step="any" min="0" class="cvr-input w-full px-3 py-2 rounded" />
                            </div>
                        </div>
                    </div>

                    <hr v-if="rows.length" class="cvr-border my-4" />

                    <p v-if="rows.length" class="text-sm">
                        Total Settlement: <strong :class="totalExceedsDownPayment ? 'cvr-num-red' : 'cvr-num'">{{ totalSettlementAmount.toLocaleString() }}</strong>
                        <span class="cvr-text-muted"> / {{ downPaymentAmountFormatted }}</span>
                    </p>
                    <p v-if="totalExceedsDownPayment" class="text-xs cvr-num-red mt-1">
                        Total Settlements Must Be Equal Or Less Than Down Payment Amount
                    </p>
                </div>

                <div class="flex justify-end gap-2">
                    <Link :href="urls.back" class="cvr-btn-secondary px-4 py-2 rounded border">Cancel</Link>
                    <button type="submit" :disabled="submitting || rows.length === 0" class="cvr-btn-primary px-4 py-2 rounded">
                        {{ submitting ? 'Saving...' : 'Save' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
