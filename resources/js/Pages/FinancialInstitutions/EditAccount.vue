<script setup>
import { ref, computed, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ShareholderOwnershipFields from '@/Components/ShareholderOwnershipFields.vue';

const props = defineProps({
    company: Object,
    financialInstitution: Object,
    model: Object,
    accountInterests: Array,
    currencies: Object,
    hasOdooIntegration: Boolean,
    backUrl: String,
    submitUrl: String,
    navUrls: Object,
    // Shareholder ownership — docs/shareholder-accounts.md
    canManageShareholderAccounts: { type: Boolean, default: false },
    shareholders: { type: Array, default: () => [] },
});

const page = usePage();

/*
 * The backend sends dates as "m/d/Y" (e.g. "07/19/2026") — that's what
 * getBalanceDateForSelect() / getStartDateForSelect() return, matching
 * the old jQuery datepicker's format. But HTML's native <input type="date">
 * only accepts ISO format ("Y-m-d", e.g. "2026-07-19") — anything else
 * is silently rejected and the field just shows empty. This converts
 * incoming dates to ISO so they actually display.
 */
function toIsoDate(mdySlashDate) {
    if (!mdySlashDate) return '';
    const [month, day, year] = mdySlashDate.split('/');
    if (!month || !day || !year) return '';
    return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
}

/* ── Main account fields ──────────────────────────────────────── */
const form = ref({
    account_number: props.model.account_number ?? '',
    iban: props.model.iban ?? '',
    odoo_code: props.model.odoo_code ?? '',
    balance_amount: props.model.balance_amount ?? 0,
    balance_date: toIsoDate(props.model.balance_date),
    currency: props.model.currency ?? '',
    exchange_rate: props.model.exchange_rate ?? 1,
    is_shareholder_account: props.model.is_shareholder_account ?? false,
    shareholder_partner_id: props.model.shareholder_partner_id ?? null,
});
const oldCurrency = props.model.currency; // sent back unchanged, matches original hidden field behavior

/* ── Interest rate schedule repeater ──────────────────────────── */
let nextRowId = 1;
function rowFrom(ai) {
    return {
        _rowId: nextRowId++,
        id: ai?.id ?? null,
        start_date: toIsoDate(ai?.start_date),
        interest_rate: ai?.interest_rate ?? 0,
        min_balance: ai?.min_balance ?? 0,
    };
}

const interestRows = ref(
    props.accountInterests.length
        ? props.accountInterests.map(rowFrom)
        : [rowFrom(null)]
);

// The first row's start date always mirrors the account's balance date
// and can't be edited directly — matches the original form's behavior.
watch(() => form.value.balance_date, (newDate) => {
    if (interestRows.value.length) {
        interestRows.value[0].start_date = newDate;
    }
}, { immediate: true });

function addInterestRow() {
    interestRows.value.push(rowFrom(null));
}

function removeInterestRow(rowId) {
    if (interestRows.value.length <= 1) return;
    interestRows.value = interestRows.value.filter(r => r._rowId !== rowId);
}

/* ── Error helpers ────────────────────────────────────────────── */
function fieldError(name) {
    return page.props.errors?.[name] ?? null;
}
function interestError(index, field) {
    return page.props.errors?.[`account_interests.${index}.${field}`] ?? null;
}
const generalError = computed(() => page.props.errors?.beginning_balance_rule ?? null);

/* ── Submit ───────────────────────────────────────────────────── */
const submitting = ref(false);

function submit() {
    submitting.value = true;
    router.put(props.submitUrl, {
        account_number: form.value.account_number,
        iban: form.value.iban,
        odoo_code: form.value.odoo_code,
        balance_amount: form.value.balance_amount,
        balance_date: form.value.balance_date,
        currency: form.value.currency,
        old_currency: oldCurrency,
        exchange_rate: form.value.exchange_rate,
        is_shareholder_account: form.value.is_shareholder_account,
        shareholder_partner_id: form.value.shareholder_partner_id,
        account_interests: interestRows.value.map(({ _rowId, ...rest }) => rest),
    }, {
        onFinish: () => { submitting.value = false; },
    });
}
</script>

<template>
    <AppLayout :nav-urls="navUrls">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-1">
                <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    ← Back to Accounts
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">Edit Financial Institution Account</h1>
            <p class="text-sm cvr-text-muted mb-6">{{ financialInstitution.name }}</p>

            <div v-if="generalError" class="mb-4 px-4 py-3 rounded cvr-badge-overdue text-sm">
                {{ generalError }}
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Account details -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">Company Account Information</h2>
                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">Account Number *</label>
                            <input v-model="form.account_number" required type="text" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            <p v-if="fieldError('account_number')" class="text-xs mt-1" style="color: var(--cvr-danger-text);">{{ fieldError('account_number') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">IBAN</label>
                            <input v-model="form.iban" type="text" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                        </div>
                        <div v-if="hasOdooIntegration">
                            <label class="cvr-form-label">Odoo Code *</label>
                            <input v-model="form.odoo_code" required type="text" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Balance Amount *</label>
                            <input v-model.number="form.balance_amount" required type="number" step="0.01" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Balance Date *</label>
                            <input v-model="form.balance_date" required type="date" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            <p v-if="fieldError('balance_date')" class="text-xs mt-1" style="color: var(--cvr-danger-text);">{{ fieldError('balance_date') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">Currency *</label>
                            <select v-model="form.currency" required class="cvr-select w-full px-2 py-1.5 rounded text-sm">
                                <option value="" disabled>Select</option>
                                <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                            </select>
                            <p v-if="fieldError('currency')" class="text-xs mt-1" style="color: var(--cvr-danger-text);">{{ fieldError('currency') }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">Exchange Rate *</label>
                            <input v-model.number="form.exchange_rate" required type="number" step="0.0001" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                        </div>
                        <ShareholderOwnershipFields
                            :can-manage="canManageShareholderAccounts"
                            :shareholders="shareholders"
                            v-model:is-shareholder-account="form.is_shareholder_account"
                            v-model:shareholder-partner-id="form.shareholder_partner_id"
                            :owner-error="fieldError('shareholder_partner_id')"
                        />
                    </div>
                </div>

                <!-- Interest rate schedule -->
                <div class="cvr-card">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide">Interest Rate Schedule</h2>
                        <button type="button" @click="addInterestRow" class="cvr-btn-primary px-3 py-1.5 rounded text-sm">
                            + Add Period
                        </button>
                    </div>

                    <div v-for="(row, index) in interestRows" :key="row._rowId" class="cvr-border border rounded-lg p-4 mb-3">
                        <div class="cvr-form-grid-3">
                            <div>
                                <label class="cvr-form-label">Interest Calculation Start Date *</label>
                                <input
                                    v-model="row.start_date"
                                    required
                                    type="date"
                                    :readonly="index === 0"
                                    class="cvr-input w-full px-2 py-1.5 rounded text-sm"
                                    :class="{ 'opacity-60 cursor-not-allowed': index === 0 }"
                                />
                                <p v-if="index === 0" class="text-xs mt-1 cvr-text-muted">Always matches the account's balance date above.</p>
                                <p v-if="interestError(index, 'start_date')" class="text-xs mt-1" style="color: var(--cvr-danger-text);">{{ interestError(index, 'start_date') }}</p>
                            </div>
                            <div>
                                <label class="cvr-form-label">Interest Rate *</label>
                                <input v-model.number="row.interest_rate" required type="number" step="0.01" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                            <div>
                                <label class="cvr-form-label">Min Balance *</label>
                                <input v-model.number="row.min_balance" required type="number" step="0.01" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                        </div>
                        <div class="flex justify-end mt-3" v-if="interestRows.length > 1">
                            <button type="button" @click="removeInterestRow(row._rowId)" class="cvr-btn-remove-row">
                                🗑 Remove Period
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <Link :href="backUrl" class="cvr-btn-secondary px-4 py-2 rounded border">Cancel</Link>
                    <button type="submit" :disabled="submitting" class="cvr-btn-primary px-4 py-2 rounded disabled:opacity-50">
                        {{ submitting ? 'Saving...' : 'Update' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
