<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormErrorSummary from '@/Components/FormErrorSummary.vue';
import { todayDate } from '@/composables/today';
import { mapAccountNumberOptions, accountNumberOption } from '@/composables/useAccountNumberOptions';
/* أقصى تاريخ مسموح بيه لحركة فلوس فعلية — النهاردة.
   الحماية الحقيقية على السيرفر في الـ Form Request. */
const maxDate = todayDate();

const props = defineProps({
    mode: String, // 'create' | 'edit'
    model: Object, // null on create
    contracts: Array, // pre-seeded contract options in edit mode (see contractsForCompany())
    recourseType: String, // 'with_recourse' | 'without_recourse'
    pageTitle: String,
    company: Object,
    factoringCompanies: Array,
    customers: Array,
    financialInstitutionBanks: Array,
    accountTypes: Array,
    urls: Object,
});

const page = usePage();
const isEdit = computed(() => props.mode === 'edit');
const errors = computed(() => page.props.errors || {});

/**
 * Same reasoning as every other converted form in this app: plain
 * fetch() doesn't send X-Requested-With automatically the way the
 * original jQuery $.ajax() did, and Laravel's ajax()/expectsJson()
 * checks lean on it.
 */
async function fetchJson(url, options = {}) {
    const res = await fetch(url, {
        ...options,
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) },
    });
    const text = await res.text();
    let data = null;
    try { data = JSON.parse(text); } catch (e) { /* leave data null */ }
    return { ok: res.ok, data };
}

const exceptTransactionId = computed(() => props.model?.id || null);

/* ── Header fields ────────────────────────────────────────────── */
const factoringDate = ref(props.model?.factoring_date || new Date().toISOString().slice(0, 10));
const factoringCompanyId = ref(props.model?.factoring_company_id || '');
const factoringContractId = ref(props.model?.factoring_contract_id || '');
const customerId = ref(props.model?.customer_id || '');
const invoiceCurrency = ref(props.model?.invoice_currency || '');
const customerInvoiceId = ref(props.model?.customer_invoice_id || '');

/* Edit mode locks Customer / Invoice Currency / Invoice Number —
   matches the original: these render as readonly text + hidden
   inputs in edit mode, only Factoring Contract stays editable. */
const customerNameDisplay = ref(props.model?.customer_name || '');
const invoiceNumberDisplay = ref(props.model?.invoice_number || '');

/* ── Contract dropdown — cascades off Factoring Company (+ Date) ── */
const contracts = ref(props.contracts || []);
async function fetchContracts() {
    if (!factoringCompanyId.value) { contracts.value = []; return; }
    const params = new URLSearchParams({ factoring_date: factoringDate.value });
    if (exceptTransactionId.value) params.set('except_factoring_transaction_id', exceptTransactionId.value);
    const result = await fetchJson(`${props.urls.getContracts}/${factoringCompanyId.value}?${params.toString()}`);
    contracts.value = result.data?.contracts || [];
}
watch([factoringCompanyId, factoringDate], fetchContracts);

/* ── Invoice Currency options — cascades off Customer (create only) ── */
const invoiceCurrencies = ref(invoiceCurrency.value ? [{ code: invoiceCurrency.value, label: invoiceCurrency.value.toUpperCase() }] : []);
async function fetchInvoiceCurrencies() {
    if (isEdit.value || !customerId.value) { invoiceCurrencies.value = []; return; }
    const params = new URLSearchParams();
    if (exceptTransactionId.value) params.set('except_factoring_transaction_id', exceptTransactionId.value);
    const result = await fetchJson(`${props.urls.getInvoiceCurrencies}/${customerId.value}?${params.toString()}`);
    invoiceCurrencies.value = Object.entries(result.data?.currencies || {}).map(([code, label]) => ({ code, label }));
}
watch(customerId, fetchInvoiceCurrencies);

/* ── Invoices — cascades off Customer + Invoice Currency (create only) ── */
const invoices = ref([]);
const selectedInvoice = computed(() => invoices.value.find(inv => String(inv.id) === String(customerInvoiceId.value)) || null);
async function fetchInvoices() {
    if (isEdit.value || !customerId.value || !invoiceCurrency.value) { invoices.value = []; return; }
    const params = new URLSearchParams();
    if (exceptTransactionId.value) params.set('except_factoring_transaction_id', exceptTransactionId.value);
    const result = await fetchJson(`${props.urls.getInvoices}/${customerId.value}/${invoiceCurrency.value}?${params.toString()}`);
    invoices.value = result.data?.invoices || [];
}
watch([customerId, invoiceCurrency], fetchInvoices);

/* ── Read-only displays for the current invoice ──────────────────
   Create mode: sourced from the selected invoice option.
   Edit mode: locked to the original model values (invoice can't
   change, matches the original's readonly/hidden field pair). */
const invoiceDueDateDisplay = computed(() => isEdit.value ? (props.model?.invoice_due_date || '') : (selectedInvoice.value?.invoice_due_date || ''));
const invoiceAmountDisplay = ref(props.model?.invoice_amount ?? 0);
watch(selectedInvoice, (inv) => {
    if (!isEdit.value) invoiceAmountDisplay.value = inv ? inv.invoice_amount : 0;
});

/* ── Live calculation — recomputes Factoring Amount / Contract
   Interest Rate / Diff In Days / Factoring Interest Amount /
   Received Amount / Remaining Limit whenever any input feeding the
   calculation changes. factoring_interest_amount, other_charges and
   received_amount stay plain editable inputs afterward (matches the
   original: store()/update() read them straight from the submitted
   request, not recomputed server-side on save) — this call only
   supplies the starting/refreshed suggestion, same as the original
   form's own live preview.
   ⚠️ Assumption: the original's custom/factoring-with-recourse.js
   (which drove this exact behavior) wasn't present in the handed-off
   files, so this cascade is rebuilt from the calculate() endpoint's
   own shape and the field grouping in the original blade, not from
   reading the original JS directly. Worth a close look during testing. */
const factoringPercentage = ref(props.model?.factoring_percentage ?? '');
const factoringAmountDisplay = ref(props.model?.factoring_amount ?? 0);
const remainingLimitDisplay = ref(props.model?.remaining_limit ?? 0);
const contractInterestRateDisplay = ref(props.model?.contract_interest_rate ?? 0);
const diffInDaysDisplay = ref(props.model?.diff_in_days ?? '');
const factoringInterestAmount = ref(props.model?.factoring_interest_amount ?? '');
const otherCharges = ref(props.model?.other_charges ?? 0);
const receivedAmount = ref(props.model?.received_amount ?? '');

let calculateTimer = null;
function scheduleCalculate() {
    clearTimeout(calculateTimer);
    calculateTimer = setTimeout(runCalculate, 300);
}
async function runCalculate() {
    if (!customerInvoiceId.value || !factoringContractId.value) return;
    const payload = {
        customer_invoice_id: customerInvoiceId.value,
        factoring_contract_id: factoringContractId.value,
        factoring_percentage: factoringPercentage.value || 0,
        other_charges: otherCharges.value || 0,
        factoring_date: factoringDate.value,
    };
    if (exceptTransactionId.value) payload.except_factoring_transaction_id = exceptTransactionId.value;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const result = await fetchJson(props.urls.calculate, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify(payload),
    });
    if (!result.ok || !result.data?.status) return;
    const d = result.data;
    invoiceAmountDisplay.value = d.invoice_amount;
    remainingLimitDisplay.value = d.remaining_limit;
    factoringAmountDisplay.value = d.factoring_amount;
    contractInterestRateDisplay.value = d.contract_interest_rate;
    diffInDaysDisplay.value = d.diff_in_days;
    factoringInterestAmount.value = d.factoring_interest_amount;
    receivedAmount.value = d.received_amount;
}
watch([customerInvoiceId, factoringContractId, factoringPercentage, otherCharges, factoringDate], scheduleCalculate);

/* ── Bank Details ─────────────────────────────────────────────── */
const financialInstitutionId = ref(props.model?.financial_institution_id || '');
const accountTypeId = ref(props.model?.account_type_id || '');
const accountNumber = ref(props.model?.account_number || '');
const accountNumbers = ref(accountNumberOption(props.model?.account_number));
async function fetchAccountNumbers() {
    accountNumbers.value = [];
    if (!accountTypeId.value || !financialInstitutionId.value || !invoiceCurrency.value) return;
    const url = `${props.urls.getAccountNumbersForType}/${accountTypeId.value}/${invoiceCurrency.value}/${financialInstitutionId.value}`;
    const result = await fetchJson(url);
    accountNumbers.value = mapAccountNumberOptions(result.data?.data);
}
watch([accountTypeId, financialInstitutionId, invoiceCurrency], fetchAccountNumbers, { immediate: true });

/* ── Submit ───────────────────────────────────────────────────── */
const submitting = ref(false);
function submit() {
    submitting.value = true;
    const payload = {
        factoring_date: factoringDate.value,
        factoring_company_id: factoringCompanyId.value,
        factoring_contract_id: factoringContractId.value,
        customer_id: customerId.value,
        invoice_currency: invoiceCurrency.value,
        customer_invoice_id: customerInvoiceId.value,
        factoring_percentage: factoringPercentage.value,
        factoring_interest_amount: factoringInterestAmount.value,
        other_charges: otherCharges.value,
        received_amount: receivedAmount.value,
        financial_institution_id: financialInstitutionId.value,
        account_type_id: accountTypeId.value,
        account_number: accountNumber.value,
    };
    if (isEdit.value) {
        router.put(props.urls.update, payload, { onFinish: () => { submitting.value = false; } });
    } else {
        router.post(props.urls.store, payload, { onFinish: () => { submitting.value = false; } });
    }
}
</script>

<template>
    <AppLayout>
        <div class="p-6 mx-auto">
            <div class="flex items-center gap-3 mb-1">
                <Link :href="urls.back" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    {{ $t('← Back to') }} {{ $t(pageTitle) }}
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-6">
                {{ isEdit ? $t('Edit') : $t('Create') }} {{ $t(pageTitle) }}
            </h1>

            <FormErrorSummary />

            <form @submit.prevent="submit" class="space-y-6">
                <div class="cvr-card-bg cvr-border border rounded-lg p-5">
                    <h2 class="text-base font-medium cvr-text-primary mb-4">{{ $t(pageTitle) }}</h2>
                    <div class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">{{ $t('Factoring Date') }} *</label>
                            <input v-model="factoringDate" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errors.factoring_date" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.factoring_date }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Factoring Company') }} *</label>
                            <select v-model="factoringCompanyId" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="c in factoringCompanies" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <p v-if="errors.factoring_company_id" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.factoring_company_id }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Factoring Contract') }} *</label>
                            <select v-model="factoringContractId" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="c in contracts" :key="c.id" :value="c.id">{{ c.label }}</option>
                            </select>
                            <p v-if="errors.factoring_contract_id" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.factoring_contract_id }}</p>
                        </div>

                        <div>
                            <label class="cvr-form-label">{{ $t('Customer') }} *</label>
                            <input v-if="isEdit" disabled :value="customerNameDisplay" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                            <select v-else v-model="customerId" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Invoice Currency') }} *</label>
                            <input v-if="isEdit" disabled :value="invoiceCurrency.toUpperCase()" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                            <select v-else v-model="invoiceCurrency" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="c in invoiceCurrencies" :key="c.code" :value="c.code">{{ c.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Invoice Number') }} *</label>
                            <input v-if="isEdit" disabled :value="invoiceNumberDisplay" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                            <select v-else v-model="customerInvoiceId" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="inv in invoices" :key="inv.id" :value="inv.id">{{ inv.invoice_number }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="cvr-form-label">{{ $t('Invoice Due Date') }}</label>
                            <input disabled :value="invoiceDueDateDisplay" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Invoice Amount') }}</label>
                            <input disabled :value="Number(invoiceAmountDisplay).toLocaleString('en-EG')" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Factoring Percentage') }} *</label>
                            <input v-model="factoringPercentage" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errors.factoring_percentage" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.factoring_percentage }}</p>
                        </div>

                        <div>
                            <label class="cvr-form-label">{{ $t('Factoring Amount') }}</label>
                            <input disabled :value="Number(factoringAmountDisplay).toLocaleString('en-EG')" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Remaining Limit') }}</label>
                            <input disabled :value="Number(remainingLimitDisplay).toLocaleString('en-EG')" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Contract Interest Rate (%)') }}</label>
                            <input disabled :value="contractInterestRateDisplay" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>

                        <div>
                            <label class="cvr-form-label">{{ $t('Diff In Days') }}</label>
                            <input disabled :value="diffInDaysDisplay" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Factoring Interest Amount') }} *</label>
                            <input v-model="factoringInterestAmount" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errors.factoring_interest_amount" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.factoring_interest_amount }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Other Charges') }} *</label>
                            <input v-model="otherCharges" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errors.other_charges" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.other_charges }}</p>
                        </div>

                        <div>
                            <label class="cvr-form-label">{{ $t('Received Amount') }}</label>
                            <input v-model="receivedAmount" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errors.received_amount" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.received_amount }}</p>
                        </div>
                    </div>
                </div>

                <div class="cvr-card-bg cvr-border border rounded-lg p-5">
                    <h2 class="text-base font-medium cvr-text-primary mb-4">{{ $t('Bank Details') }}</h2>
                    <div class="cvr-form-grid-6-3-3">
                        <div>
                            <label class="cvr-form-label">{{ $t('Bank') }} *</label>
                            <select v-model="financialInstitutionId" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="b in [...financialInstitutionBanks].sort((a, b) => a.name.localeCompare(b.name))" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                            <p v-if="errors.financial_institution_id" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.financial_institution_id }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Account Type') }} *</label>
                            <select v-model="accountTypeId" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="a in accountTypes" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                            <p v-if="errors.account_type_id" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.account_type_id }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Account Number') }} *</label>
                            <select v-model="accountNumber" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="n in accountNumbers" :key="n.value" :value="n.value">{{ n.label }}</option>
                            </select>
                            <p v-if="errors.account_number" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.account_number }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <Link :href="urls.back" class="cvr-btn-secondary px-4 py-2 rounded border">{{ $t('Cancel') }}</Link>
                    <button type="submit" :disabled="submitting" class="cvr-btn-primary px-4 py-2 rounded">
                        {{ submitting ? $t('Saving...') : $t('Save') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
