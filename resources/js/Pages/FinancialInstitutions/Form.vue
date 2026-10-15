<script setup>
import { ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormErrorSummary from '@/Components/FormErrorSummary.vue';
import ShareholderOwnershipFields from '@/Components/ShareholderOwnershipFields.vue';

const props = defineProps({
    /* Link to this screen's written guide — see
       App\Support\Instructions\PageInstructions. */
    instructionsUrl: String,
    company: Object,
    model: Object, // null = create mode, populated object = edit mode
    banks: Object,  // { id: "Bank Name", ... }
    currencies: Object, // { EGP: "EGP", ... }
    hasOdooIntegration: Boolean,
    listUrl: String,
    submitUrl: String,
    navUrls: Object,
    // Same ownership control AddAccount.vue already has — docs/shareholder-accounts.md
    canManageShareholderAccounts: { type: Boolean, default: false },
    shareholders: { type: Array, default: () => [] },
});

const page = usePage();
const isEditMode = computed(() => props.model !== null);

/*
 * ⚠️ Real gap found and fixed here (2026-07-25): this form previously had
 * NO error-display wiring at all — not usePage(), not a single error
 * lookup — even though the backend already had a real validation rule
 * (AccountMustHaveAtLeastOneMainCurrencyRule) that could fail here. A
 * failed save just silently did nothing, with no indication why. Fixed
 * to match the same pattern AddAccount.vue already uses correctly.
 * Server errors for repeater rows come back keyed like
 * "accounts.0.balance_date" — mapped back to each row's own field.
 */
function errorFor(index, field) {
    return page.props.errors?.[`accounts.${index}.${field}`] ?? null;
}

/* ── Main institution fields ─────────────────────────────────── */
const form = ref({
    bank_id: props.model?.bank_id ?? '',
    branch_name: props.model?.branch_name ?? '',
    company_account_number: props.model?.company_account_number ?? '',
});

/* ── Initial accounts repeater — create mode only ────────────── */
let nextRowId = 1;
function blankAccountRow() {
    return {
        _rowId: nextRowId++,
        account_number: '',
        odoo_code: '',
        iban: '',
        balance_amount: 0,
        balance_date: '',
        currency: '',
        exchange_rate: 1,
        interest_rate: 0,
        min_balance: 0,
        is_shareholder_account: false,
        shareholder_partner_id: null,
    };
}

const accounts = ref([blankAccountRow()]);

function addAccountRow() {
    accounts.value.push(blankAccountRow());
}

function removeAccountRow(rowId) {
    if (accounts.value.length <= 1) return; // always keep at least one row
    accounts.value = accounts.value.filter(r => r._rowId !== rowId);
}

/* ── Submit ───────────────────────────────────────────────────── */
const submitting = ref(false);

function submit() {
    submitting.value = true;

    const payload = {
        type: 'bank', // this form only handles the "bank" institution type
        bank_id: form.value.bank_id,
        branch_name: form.value.branch_name,
        company_account_number: form.value.company_account_number,
    };

    if (!isEditMode.value) {
        payload.accounts = accounts.value.map(({ _rowId, ...rest }) => rest);
    }

    const options = { onFinish: () => { submitting.value = false; } };

    if (isEditMode.value) {
        router.put(props.submitUrl, payload, options);
    } else {
        router.post(props.submitUrl, payload, options);
    }
}
</script>

<template>
    <AppLayout :nav-urls="navUrls">
        <div class="px-6 pt-4">
            <Link v-if="instructionsUrl" :href="instructionsUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                {{ $t('📖 Instructions') }}
            </Link>
        </div>
        <div class="p-6">
            <div class="flex items-center gap-3 mb-6">
                <Link :href="listUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    {{ $t('← Back to Banks') }}
                </Link>
                <h1 class="text-xl font-semibold cvr-text-primary">
                    {{ isEditMode ? $t('Edit Financial Institution') : $t('Add Financial Institution') }}
                </h1>
            </div>

            <FormErrorSummary />

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Institution details -->
                <div class="cvr-card">
                    <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">{{ $t('Financial Institution Type') }}</h2>
                    <div class="cvr-form-grid-8-2-2">
                        <div>
                            <label class="cvr-form-label">{{ $t('Select Bank') }} *</label>
                            <select v-model="form.bank_id" required class="cvr-select w-full px-3 py-2 rounded">
                                <option value="" disabled>{{ $t('Select...') }}</option>
                                <option v-for="(bankName, bankId) in banks" :key="bankId" :value="bankId">
                                    {{ bankName }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Branch Name') }} *</label>
                            <input v-model="form.branch_name" required type="text" class="cvr-input w-full px-3 py-2 rounded" :placeholder="$t('Branch Name')" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Company Account Number') }} *</label>
                            <input v-model="form.company_account_number" required type="text" class="cvr-input w-full px-3 py-2 rounded" :placeholder="$t('Company Account Number')" />
                        </div>
                    </div>
                </div>

                <!-- Initial accounts repeater — create mode only -->
                <div v-if="!isEditMode" class="cvr-card">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide">{{ $t('Initial Bank Accounts') }}</h2>
                        <button type="button" @click="addAccountRow" class="cvr-btn-primary px-3 py-1.5 rounded text-sm">
                            {{ $t('+ Add Account') }}
                        </button>
                    </div>

                    <div v-for="(row, index) in accounts" :key="row._rowId" class="cvr-border border rounded-lg p-4 mb-3">
                        <div class="cvr-form-grid-4">
                            <div>
                                <label class="cvr-form-label">{{ $t('Account Number') }} *</label>
                                <input
                                    v-model="row.account_number"
                                    required
                                    type="text"
                                    class="cvr-input w-full px-2 py-1.5 rounded text-sm"
                                    :class="{ 'border-2': errorFor(index, 'account_number') }"
                                    :style="errorFor(index, 'account_number') ? { borderColor: 'var(--cvr-danger)' } : {}"
                                />
                                <p v-if="errorFor(index, 'account_number')" class="text-xs mt-1" style="color: var(--cvr-danger-text);">
                                    {{ errorFor(index, 'account_number') }}
                                </p>
                            </div>
                            <div v-if="hasOdooIntegration">
                                <label class="cvr-form-label">{{ $t('Odoo Code') }} *</label>
                                <input v-model="row.odoo_code" required type="text" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                            <div>
                                <label class="cvr-form-label">{{ $t('IBAN') }}</label>
                                <input v-model="row.iban" type="text" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                            <div>
                                <label class="cvr-form-label">{{ $t('Balance Amount') }} *</label>
                                <input v-model.number="row.balance_amount" required type="number" step="0.01" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                            <div>
                                <label class="cvr-form-label">{{ $t('Balance Date') }}</label>
                                <input
                                    v-model="row.balance_date"
                                    type="date"
                                    class="cvr-input w-full px-2 py-1.5 rounded text-sm"
                                    :class="{ 'border-2': errorFor(index, 'balance_date') }"
                                    :style="errorFor(index, 'balance_date') ? { borderColor: 'var(--cvr-danger)' } : {}"
                                />
                                <p v-if="errorFor(index, 'balance_date')" class="text-xs mt-1" style="color: var(--cvr-danger-text);">
                                    {{ errorFor(index, 'balance_date') }}
                                </p>
                            </div>
                            <div>
                                <label class="cvr-form-label">{{ $t('Currency') }} *</label>
                                <select v-model="row.currency" required class="cvr-select w-full px-2 py-1.5 rounded text-sm">
                                    <option value="" disabled>{{ $t('Select') }}</option>
                                    <option v-for="(label, code) in currencies" :key="code" :value="code">{{ label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="cvr-form-label">{{ $t('Exchange Rate') }} *</label>
                                <input v-model.number="row.exchange_rate" required type="number" step="0.0001" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                            <div>
                                <label class="cvr-form-label">{{ $t('Interest Rate') }} *</label>
                                <input v-model.number="row.interest_rate" required type="number" step="0.01" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                            <div>
                                <label class="cvr-form-label">{{ $t('Min Balance') }} *</label>
                                <input v-model.number="row.min_balance" required type="number" step="0.01" class="cvr-input w-full px-2 py-1.5 rounded text-sm" />
                            </div>
                            <ShareholderOwnershipFields
                                :can-manage="canManageShareholderAccounts"
                                :shareholders="shareholders"
                                v-model:is-shareholder-account="row.is_shareholder_account"
                                v-model:shareholder-partner-id="row.shareholder_partner_id"
                                :owner-error="errorFor(index, 'shareholder_partner_id')"
                            />
                        </div>
                        <div class="flex justify-end mt-3" v-if="accounts.length > 1">
                            <button type="button" @click="removeAccountRow(row._rowId)" class="cvr-btn-remove-row">
                                {{ $t('🗑 Remove Account') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex justify-end gap-2">
                    <Link :href="listUrl" class="cvr-btn-secondary px-4 py-2 rounded border">{{ $t('Cancel') }}</Link>
                    <button type="submit" :disabled="submitting" class="cvr-btn-primary px-4 py-2 rounded disabled:opacity-50">
                        {{ submitting ? $t('Saving...') : (isEditMode ? $t('Update') : $t('Save')) }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>