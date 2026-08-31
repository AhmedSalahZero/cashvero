<script setup>
import { ref } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormErrorSummary from '@/Components/FormErrorSummary.vue';
import ShareholderOwnershipFields from '@/Components/ShareholderOwnershipFields.vue';

const props = defineProps({
    /* Link to this screen's written guide — see
       App\Support\Instructions\PageInstructions. */
    instructionsUrl: String,
    company: Object,
    financialInstitution: Object,
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

/* ── Accounts repeater ────────────────────────────────────────── */
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
    if (accounts.value.length <= 1) return;
    accounts.value = accounts.value.filter(r => r._rowId !== rowId);
}

/*
 * Server validation errors come back keyed like "accounts.0.account_number"
 * (the array index matches the row's position at submit time). We map that
 * back to each row so the message shows up right next to the actual field
 * that failed, instead of the save just silently doing nothing.
 */
function errorFor(index, field) {
    return page.props.errors?.[`accounts.${index}.${field}`] ?? null;
}

/* ── Submit ───────────────────────────────────────────────────── */
const submitting = ref(false);

function submit() {
    submitting.value = true;
    router.post(props.submitUrl, {
        accounts: accounts.value.map(({ _rowId, ...rest }) => rest),
    }, {
        onFinish: () => { submitting.value = false; },
    });
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
            <div class="flex items-center gap-3 mb-1">
                <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    {{ $t('← Back to Accounts') }}
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Add New Account') }}</h1>
            <p class="text-sm cvr-text-muted mb-6">{{ $t('For') }} {{ financialInstitution.name }}</p>

            <!-- General error banner — shows if anything failed, even if we
                 can't map it to a specific row for some reason -->
            <FormErrorSummary />

            <form @submit.prevent="submit" class="space-y-6">
                <div class="cvr-card">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide">{{ $t('Account Information') }}</h2>
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

                <div class="flex justify-end gap-2">
                    <Link :href="backUrl" class="cvr-btn-secondary px-4 py-2 rounded border">{{ $t('Cancel') }}</Link>
                    <button type="submit" :disabled="submitting" class="cvr-btn-primary px-4 py-2 rounded disabled:opacity-50">
                        {{ submitting ? $t('Saving...') : $t('Save') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
