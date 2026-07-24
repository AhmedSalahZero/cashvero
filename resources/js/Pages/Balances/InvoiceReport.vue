<script setup>
/**
 * Balances/InvoiceReport.vue
 * ------------------------------------------------------------------
 * Served by CustomerInvoiceDashboardController@showInvoiceReport.
 * Every invoice for one customer + currency. Two of its actions link
 * out to still-Blade pages (Adjust Due Date, Money Received/Payment —
 * plain <a> tags below). The third, "Deduct", is a REAL write action
 * and is rebuilt here as a proper Inertia form — see
 * InvoiceDeductionsController@update for the backend side (validation
 * errors now surface through page.props.errors, same as every other
 * form in this app; the balance math itself is unchanged).
 */
import { ref, computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    company: Object,
    partnerId: [Number, String],
    partnerName: String,
    currency: String,
    moneyReceivedOrPaidText: String,
    modelType: String,
    deductionOptions: Array, // [{ id, name }]
    hasProjectNameColumn: Boolean,
    totalCollectionOrPaidText: String,
    downPaymentSettlementUrl: String,
    exportUrl: String,
    backUrl: String,
    invoices: Array,
});

function formatAmount(value) {
    return Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });
}

function statusBadgeClass(status) {
    // Matches the app-wide "Number Color Rule" meaning, applied to
    // the status badge instead of a number: collected = settled,
    // pastDue = critical, the rest = pending/neutral.
    if (status === 'collected') return 'cvr-badge-active';
    if (status === 'pastDue' || status === 'partiallyCollectedAndPastDue') return 'cvr-badge-overdue';
    return 'cvr-badge-pending';
}

/* ── Deduct modal — one shared form, re-populated per invoice when
   opened. Existing deductions arrive pre-loaded from the controller
   (invoice.deductions), matching the original repeater's starting
   rows. An empty invoice starts with one blank row, same as the
   original Blade's placeholder-row behavior for this specific
   feature (unlike the Opening Balance repeaters, which we changed
   to start empty — this one still mirrors "add your first deduction"
   as a single ready row, since that matches how the modal is used:
   editing THIS invoice's deductions as a complete set each time). ── */
const activeInvoiceId = ref(null);

const deductionForm = useForm({
    deductions: [],
});

function blankDeductionRow() {
    return { deduction_id: '', date: new Date().toISOString().slice(0, 10), amount: '' };
}

function openDeductModal(invoice) {
    activeInvoiceId.value = invoice.id;
    deductionForm.clearErrors();
    deductionForm.deductions = invoice.deductions.length
        ? invoice.deductions.map(d => ({ deduction_id: String(d.deduction_id), date: d.date, amount: d.amount }))
        : [blankDeductionRow()];
}

function closeDeductModal() {
    activeInvoiceId.value = null;
}

const activeInvoice = computed(() => props.invoices.find(inv => inv.id === activeInvoiceId.value) || null);

function addDeductionRow() {
    deductionForm.deductions.push(blankDeductionRow());
}

function removeDeductionRow(index) {
    deductionForm.deductions.splice(index, 1);
}

function submitDeductions(invoice) {
    deductionForm.patch(invoice.update_deductions_url, {
        preserveScroll: true,
        onSuccess: () => { closeDeductModal(); },
    });
}
</script>

<template>
    <AppLayout>
        <div class="p-6 mx-auto">
            <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm mb-3">
                ← Back to Balances
            </Link>

            <div class="flex items-center justify-between flex-wrap gap-3 mb-1">
                <h1 class="text-xl font-semibold cvr-text-primary">
                    Invoices Table
                    <span class="cvr-text-secondary font-normal">— {{ partnerName }} [ {{ currency }} ]</span>
                </h1>
                <Link :href="downPaymentSettlementUrl" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm whitespace-nowrap">
                    Down Payment Amount Settlement
                </Link>
                <a v-if="exportUrl && invoices.length" :href="exportUrl" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm whitespace-nowrap">
                    ⬇️ Export to Excel
                </a>
            </div>
            <p class="text-sm cvr-text-muted mb-6">Every invoice for this customer in this currency</p>

            <!-- Table -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-center">#</th>
                            <th v-if="hasProjectNameColumn" class="px-4 py-3 text-center min-w-48">Project Name</th>
                            <th class="px-4 py-3 text-center min-w-32">Invoice Date</th>
                            <th class="px-4 py-3 text-center min-w-36">Invoice Number</th>
                            <th class="px-4 py-3 text-center">Invoice Amount</th>
                            <th class="px-4 py-3 text-center">Withhold Amount</th>
                            <th class="px-4 py-3 text-center">VAT Amount</th>
                            <th class="px-4 py-3 text-center">Total Deductions</th>
                            <th class="px-4 py-3 text-center">{{ totalCollectionOrPaidText }}</th>
                            <th class="px-4 py-3 text-center min-w-32">Invoice Due Date</th>
                            <th class="px-4 py-3 text-center">Net Balance</th>
                            <th class="px-4 py-3 text-center min-w-32">Status</th>
                            <th class="px-4 py-3 text-center">Aging</th>
                            <th class="px-4 py-3 text-center">Adjust Due Date</th>
                            <th class="px-4 py-3 text-center">Deductions</th>
                            <th class="px-4 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(invoice, i) in invoices" :key="invoice.id" class="cvr-table-row">
                            <td class="px-4 py-3 text-center cvr-text-secondary">{{ i + 1 }}</td>
                            <td v-if="hasProjectNameColumn" class="px-4 py-3 text-left cvr-text-secondary">{{ invoice.project_name }}</td>
                            <td class="px-4 py-3 text-center cvr-text-secondary">{{ invoice.invoice_date }}</td>
                            <td class="px-4 py-3 text-center cvr-text-primary">{{ invoice.invoice_number }}</td>
                            <td class="px-4 py-3 text-right cvr-num">
                                {{ invoice.invoice_amount_formatted }}
                                <span v-if="invoice.show_exchange_info" class="cvr-text-muted text-xs block">
                                    ({{ invoice.net_invoice_in_main_currency_formatted }} @ {{ invoice.exchange_rate_formatted }})
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right cvr-num">{{ invoice.total_withhold_formatted }}</td>
                            <td class="px-4 py-3 text-right cvr-num">{{ invoice.vat_amount_formatted }}</td>
                            <td class="px-4 py-3 text-right cvr-num">{{ invoice.total_deduction_formatted }}</td>
                            <td class="px-4 py-3 text-right cvr-num-green">{{ invoice.total_collected_or_paid_formatted }}</td>
                            <td class="px-4 py-3 text-center cvr-text-secondary">{{ invoice.due_date_formatted }}</td>
                            <td class="px-4 py-3 text-right font-medium" :class="invoice.net_balance > 0 ? 'cvr-num-amber' : 'cvr-num-green'">{{ invoice.net_balance_formatted }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="cvr-badge" :class="statusBadgeClass(invoice.status_formatted)">{{ invoice.status_formatted }}</span>
                            </td>
                            <td class="px-4 py-3 text-center cvr-text-secondary">{{ invoice.aging }}</td>
                            <td class="px-4 py-3 text-center">
                                <Link v-if="invoice.adjust_due_date_url" :href="invoice.adjust_due_date_url"
                                    class="px-2 py-1 rounded text-xs whitespace-nowrap"
                                    :class="invoice.due_date_history_count ? 'cvr-btn-copper' : 'cvr-btn-primary'">
                                    {{ invoice.due_date_history_count ? 'Adjusted' : 'Adjust Due Date' }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button @click="openDeductModal(invoice)" class="cvr-btn-primary px-3 py-1 rounded text-xs whitespace-nowrap">
                                    Deduct
                                </button>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a v-if="invoice.money_action_url" :href="invoice.money_action_url" class="cvr-btn-copper px-3 py-1 rounded text-xs whitespace-nowrap">
                                    {{ moneyReceivedOrPaidText }}
                                </a>
                            </td>
                        </tr>
                        <tr v-if="invoices.length === 0">
                            <td colspan="16" class="px-4 py-8 text-center cvr-text-muted">No invoices found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Deduct modal -->
            <div v-if="activeInvoiceId" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-5xl">
                    <div class="flex items-baseline justify-between flex-wrap gap-2 mb-4">
                        <h2 class="text-lg font-medium cvr-text-primary">Deduct</h2>
                        <p v-if="activeInvoice" class="text-sm cvr-text-secondary">
                            Invoice <span class="cvr-text-primary font-medium">{{ activeInvoice.invoice_number }}</span>
                            — <span class="cvr-num">{{ activeInvoice.invoice_amount_formatted }}</span>
                            <span class="cvr-text-muted">{{ currency }}</span>
                        </p>
                    </div>

                    <p v-if="deductionForm.errors.deductions" class="text-sm text-red-500 mb-3">{{ deductionForm.errors.deductions }}</p>

                    <div class="space-y-3 max-h-[50vh] overflow-y-auto">
                        <div v-for="(row, index) in deductionForm.deductions" :key="index" class="cvr-form-grid-6-3-3 items-end">
                            <div>
                                <label class="cvr-form-label">Deduction</label>
                                <select v-model="row.deduction_id" class="cvr-input w-full px-3 py-2 rounded">
                                    <option value="" disabled>Select</option>
                                    <option v-for="opt in deductionOptions" :key="opt.id" :value="String(opt.id)">{{ opt.name }}</option>
                                </select>
                                <p v-if="deductionForm.errors[`deductions.${index}.deduction_id`]" class="text-xs text-red-500 mt-1">
                                    {{ deductionForm.errors[`deductions.${index}.deduction_id`] }}
                                </p>
                            </div>
                            <div>
                                <label class="cvr-form-label">Date</label>
                                <input v-model="row.date" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                                <p v-if="deductionForm.errors[`deductions.${index}.date`]" class="text-xs text-red-500 mt-1">
                                    {{ deductionForm.errors[`deductions.${index}.date`] }}
                                </p>
                            </div>
                            <div>
                                <label class="cvr-form-label">Amount</label>
                                <div class="flex gap-2">
                                    <input v-model="row.amount" type="number" min="0" step="0.01" class="cvr-input w-full px-3 py-2 rounded" />
                                    <button @click="removeDeductionRow(index)" type="button" class="cvr-btn-remove-row" title="Remove">🗑</button>
                                </div>
                                <p v-if="deductionForm.errors[`deductions.${index}.amount`]" class="text-xs text-red-500 mt-1">
                                    {{ deductionForm.errors[`deductions.${index}.amount`] }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <button @click="addDeductionRow" type="button" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm mt-4">
                        + Add Deduction
                    </button>

                    <div class="flex justify-end gap-2 mt-6">
                        <button @click="closeDeductModal" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="submitDeductions(activeInvoice)"
                            :disabled="deductionForm.processing"
                            class="cvr-btn-primary px-3 py-1.5 rounded">
                            Save
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
