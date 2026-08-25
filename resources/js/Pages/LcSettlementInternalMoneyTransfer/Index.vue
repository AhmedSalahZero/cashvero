<script setup>
/**
 * LcSettlementInternalMoneyTransfer/Index.vue
 * ------------------------------------------------------------------
 * "Pending LC Settlements" (client-requested rework, 2026-08-18).
 * One row per bank-financed LC Issuance that's been paid to the
 * supplier but not yet fully settled with the bank — see
 * LcSettlementInternalMoneyTransferController's class docblock for
 * the full picture. No Create (rows appear automatically), no Delete
 * (replaced by Reset).
 */
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RecordLogButton from '@/Components/RecordLogButton.vue';
import { mapAccountNumberOptions, accountNumberOption } from '@/composables/useAccountNumberOptions';

const props = defineProps({
    company: Object,
    rows: Array,
    pagination: Object, // { current_page, last_page, links, total }
    canSettle: Boolean,
    canUpdate: Boolean,
    canReset: Boolean,
    accountTypes: Array, // [{id, name}]
    interestDestinations: Array, // [{value, title}]
    urls: Object,
});

function goToPage(url) {
    if (!url) return;
    router.get(url, {}, { preserveScroll: true });
}

/**
 * Same reasoning as the other in-page popups in this app (e.g.
 * LetterOfCreditIssuance/Index.vue's Mark as Paid modal): plain
 * fetch() doesn't send X-Requested-With automatically, and Laravel's
 * Request::ajax()/expectsJson() checks lean on it.
 */
async function fetchJson(url) {
    const res = await fetch(url, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    const text = await res.text();
    let data = null;
    try { data = JSON.parse(text); } catch (e) { /* leave data null */ }
    return { ok: res.ok, data };
}

/* ── Mark As Settle popup ────────────────────────────────────────── */
const settleTarget = ref(null);
const settleForm = ref({
    transfer_date: '',
    amount: 0,
    from_bank_id: '',
    from_account_type_id: props.accountTypes[0]?.id ?? '',
    from_account_number: '',
    interest_amount: 0,
    interest_destination: props.interestDestinations[0]?.value ?? '',
    user_comment: '',
});
const settleDataInfo = ref({ remaining_amount: 0, days: 0, interest_rate: 0 });
const settleAccountNumbers = ref([]);
const settling = ref(false);

function openSettle(row) {
    settleTarget.value = row;
    settleForm.value = {
        transfer_date: new Date().toISOString().slice(0, 10),
        amount: 0,
        // Client-requested (2026-08-18): the paying bank is always the
        // same bank the LC was issued with, never a user choice — fixed
        // here rather than left for the user to pick.
        from_bank_id: row.bank_id,
        from_account_type_id: props.accountTypes[0]?.id ?? '',
        from_account_number: '',
        interest_amount: 0,
        interest_destination: props.interestDestinations[0]?.value ?? '',
        user_comment: '',
    };
    settleAccountNumbers.value = [];
    fetchSettleData();
    fetchSettleAccountNumbers();
}

/**
 * Re-prices the remaining amount + suggested interest every time the
 * settlement date changes — matches this app's established cascading-
 * field pattern (e.g. Form.vue's own fetchRemainingBalance()). The
 * user can still overwrite interest_amount by hand afterward; this
 * only ever sets the DEFAULT.
 */
async function fetchSettleData() {
    if (!settleTarget.value) return;
    const params = new URLSearchParams({ settlement_date: settleForm.value.transfer_date });
    const result = await fetchJson(`${settleTarget.value.settle_data_url}?${params.toString()}`);
    if (!result.data) return;
    settleDataInfo.value = result.data;
    settleForm.value.amount = result.data.remaining_amount;
    settleForm.value.interest_amount = result.data.calculated_interest;
}
watch(() => settleForm.value.transfer_date, fetchSettleData);

async function fetchSettleAccountNumbers() {
    settleAccountNumbers.value = [];
    if (!settleForm.value.from_account_type_id || !settleForm.value.from_bank_id || !settleTarget.value) return;
    const url = `${props.urls.getAccountNumbersForType}/${settleForm.value.from_account_type_id}/${settleTarget.value.currency}/${settleForm.value.from_bank_id}`;
    const result = await fetchJson(url);
    settleAccountNumbers.value = mapAccountNumberOptions(result.data?.data);
}
watch(() => settleForm.value.from_account_type_id, fetchSettleAccountNumbers);

function submitSettle() {
    settling.value = true;
    router.post(settleTarget.value.settle_url, settleForm.value, {
        onFinish: () => { settling.value = false; settleTarget.value = null; },
    });
}

/* ── Reset confirmation ──────────────────────────────────────────── */
const resetTarget = ref(null);
function confirmReset() {
    router.post(resetTarget.value.reset_url, {}, { onFinish: () => { resetTarget.value = null; } });
}
</script>

<template>
    <AppLayout>
        <div class="p-6">
            <h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Pending LC Settlements') }}</h1>
            <p class="text-sm cvr-text-muted mb-6">
                {{ $t('Bank-financed Letters of Credit already paid to the supplier, waiting to be settled with the bank. To remove one from this list entirely, revert the LC back to Running from the LC Issuance screen.') }}
            </p>

            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-start">#</th>
                            <th class="px-4 py-3 text-start">{{ $t('Transaction') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Supplier') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('LC Code') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Currency') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Paid To Supplier') }}</th>
                            <th class="px-4 py-3 text-right">{{ $t('Remaining Amount') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Status') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Settlements So Far') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Control') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in rows" :key="row.letter_of_credit_issuance_id" class="cvr-table-row">
                            <td class="px-4 py-3">{{ (pagination.current_page - 1) * 50 + index + 1 }}</td>
                            <td class="px-4 py-3">{{ row.transaction_name }}</td>
                            <td class="px-4 py-3">{{ row.supplier_name }}</td>
                            <td class="px-4 py-3">{{ row.lc_code }}</td>
                            <td class="px-4 py-3 uppercase">{{ row.currency }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ row.payment_date_formatted }}</td>
                            <td class="px-4 py-3 text-right cvr-num">{{ row.remaining_amount_formatted }}</td>
                            <td class="px-4 py-3">
                                <span :class="row.is_settled ? 'cvr-badge cvr-badge-active' : 'cvr-badge cvr-badge-pending'">{{ row.status_label }}</span>
                            </td>
                            <td class="px-4 py-3">
                                {{ row.settlements_count }}
                                <span v-if="row.last_settlement_date_formatted" class="cvr-text-muted text-xs">(last {{ row.last_settlement_date_formatted }})</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <RecordLogButton subject="LetterOfCreditIssuance" :id="row.letter_of_credit_issuance_id" :company-id="company.id" />
                                    <button v-if="canSettle && !row.is_settled" @click="openSettle(row)" class="cvr-btn-primary px-3 py-1.5 rounded text-xs whitespace-nowrap">{{ $t('Mark As Settle') }}</button>
                                    <Link v-if="canUpdate && row.edit_url" :href="row.edit_url" class="cvr-action-btn" :title="$t('Edit Most Recent Settlement')">✏️</Link>
                                    <button v-if="canReset && row.settlements_count > 0" @click="resetTarget = row" class="cvr-action-btn-danger cvr-action-btn" :title="$t('Reset — undo every settlement made so far')">↺</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="10" class="px-4 py-8 text-center cvr-text-muted">{{ $t('No bank-financed LC has been paid to a supplier yet.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="pagination?.last_page > 1" class="flex items-center justify-center gap-1 mt-4">
                <button
                    v-for="(link, i) in pagination.links"
                    :key="i"
                    v-html="link.label"
                    :disabled="!link.url"
                    @click="goToPage(link.url)"
                    class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm"
                    :class="{ 'cvr-nav-item-active': link.active, 'opacity-40 cursor-not-allowed': !link.url }"
                ></button>
            </div>

            <!-- Mark As Settle popup -->
            <div v-if="settleTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-5xl max-h-[85vh] overflow-y-auto">
                    <h2 class="text-lg font-medium cvr-text-primary mb-1">Settle LC — {{ settleTarget.transaction_name }}</h2>
                    <p class="text-xs cvr-text-muted mb-4">
                        Remaining before this settlement: {{ Number(settleDataInfo.remaining_amount).toLocaleString('en-EG') }} {{ settleTarget.currency }}.
                        {{ settleDataInfo.days }} day(s) since {{ settleTarget.last_settlement_date_formatted || $t('the LC was paid') }}, at {{ settleDataInfo.interest_rate }}%.
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                        <div>
                            <label class="cvr-form-label">{{ $t('Settlement Date') }} *</label>
                            <input v-model="settleForm.transfer_date" type="date" :max="new Date().toISOString().slice(0,10)" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Amount To Settle') }} *</label>
                            <input v-model="settleForm.amount" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div class="md:col-span-2">
                            <!-- Client-requested (2026-08-18): the paying bank is fixed to
                                 whichever bank the LC itself was issued with — never a
                                 picker. Shown wide since it's a bank name, not a code. -->
                            <label class="cvr-form-label">{{ $t('Pay From Bank') }}</label>
                            <input disabled :value="settleTarget.bank_name" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Account Type') }} *</label>
                            <select v-model="settleForm.from_account_type_id" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="t in accountTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Account Number') }} *</label>
                            <select v-model="settleForm.from_account_number" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="n in settleAccountNumbers" :key="n.value" :value="n.value">{{ n.label }}</option>
                            </select>
                        </div>
                    </div>
                    <p class="text-xs cvr-text-muted -mt-3 mb-4">{{ $t('Amount defaults to the full remaining balance — reduce it for a partial settlement.') }}</p>
                    <div class="cvr-card-bg cvr-border border rounded-lg p-4 mb-4">
                        <h3 class="text-sm font-medium cvr-text-primary mb-3">{{ $t('Interest') }}</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="cvr-form-label">{{ $t('Interest Amount') }}</label>
                                <input v-model="settleForm.interest_amount" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                                <p class="text-xs cvr-text-muted mt-1">{{ $t('Pre-filled from the facility\'s rate — overwrite with what the bank actually charged.') }}</p>
                            </div>
                            <div>
                                <label class="cvr-form-label">{{ $t('Post Interest To') }}</label>
                                <select v-model="settleForm.interest_destination" class="cvr-input w-full px-3 py-2 rounded">
                                    <option v-for="d in interestDestinations" :key="d.value" :value="d.value">{{ d.title }}</option>
                                </select>
                                <p class="text-xs cvr-text-muted mt-1">{{ $t('LC Overdraft: credit + debit there. Current Account: single credit from the account above.') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="cvr-form-label">{{ $t('Comment') }}</label>
                        <textarea v-model="settleForm.user_comment" rows="2" class="cvr-input w-full px-3 py-2 rounded"></textarea>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button @click="settleTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Cancel') }}</button>
                        <button @click="submitSettle" :disabled="settling" class="cvr-btn-primary px-4 py-2 rounded">
                            {{ settling ? $t('Settling...') : $t('Confirm Settlement') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Reset confirmation -->
            <div v-if="resetTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-2">{{ $t('Reset this LC\'s settlement?') }}</h2>
                    <p class="text-sm cvr-text-muted mb-4">
                        This undoes every settlement made so far for {{ resetTarget.transaction_name }} — principal and interest
                        alike — and returns the full original amount to "remaining". This can't be undone.
                    </p>
                    <div class="flex justify-end gap-2">
                        <button @click="resetTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="confirmReset" class="cvr-btn-danger px-3 py-1.5 rounded border">{{ $t('Confirm Reset') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>