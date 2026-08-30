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
    /* Link to this screen's written guide — see App\Support\Instructions\PageInstructions. */
    instructionsUrl: String,
    company: Object,
    model: Object, // null on create
    currencies: Array,
    financialInstitutionBanks: Array,
    accountTypes: Array,
    interestDestinations: { type: Array, default: () => [] }, // [{value, title}]
    urls: Object,
});

const page = usePage();
const isEdit = computed(() => !!props.model);
const errors = computed(() => page.props.errors || {});

/**
 * Same reasoning as MoneyPayment/Form.vue and MoneyReceived/Form.vue:
 * plain fetch() doesn't send X-Requested-With automatically the way
 * jQuery's $.ajax() (used by the original page) did, and Laravel's
 * Request::ajax()/expectsJson() checks lean on it — without it, a
 * server-side exception renders an HTML error page instead of JSON.
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

const transferDate = ref(props.model?.transfer_date || todayDate());
const fromBankId = ref(props.model?.from_bank_id || (props.financialInstitutionBanks[0]?.id ?? ''));
const currency = ref(props.model?.currency || 'EGP');
const toLetterOfCreditIssuanceId = ref(props.model?.to_letter_of_credit_issuance_id || '');
const fromAccountTypeId = ref(props.model?.from_account_type_id || (props.accountTypes[0]?.id ?? ''));
const fromAccountNumber = ref(props.model?.from_account_number || '');
const amount = ref(props.model?.amount ?? 0);
const userComment = ref(props.model?.user_comment || '');
/**
 * Client-requested (2026-08-18): interest now lives on each individual
 * settlement rather than at LC "Mark as Paid" time — see
 * LcSettlementInternalMoneyTransferController's class docblock. Only
 * ever edited here for the LATEST settlement of a bank-financed LC
 * (this Form.vue page is only reachable that way now).
 */
const interestAmount = ref(props.model?.interest_amount ?? 0);
const interestDestination = ref(props.model?.interest_destination || (props.interestDestinations[0]?.value ?? ''));

/* ── LC Issuance options — cascades off From Bank + Currency ────── */
const lcIssuances = ref(
    props.model?.to_letter_of_credit_issuance_id
        ? [{ id: props.model.to_letter_of_credit_issuance_id, name: props.model.to_lc_issuance_name }]
        : []
);
async function fetchLcIssuances() {
    if (!fromBankId.value || !currency.value) { lcIssuances.value = []; return; }
    const params = new URLSearchParams({ financialInstitutionId: fromBankId.value, currency: currency.value });
    const result = await fetchJson(`${props.urls.getLcIssuancesForBank}?${params.toString()}`);
    const map = result.data?.letterOfCreditIssuances || {};
    lcIssuances.value = Object.entries(map).map(([id, name]) => ({ id, name }));
}
watch([fromBankId, currency], fetchLcIssuances, { immediate: !isEdit.value });

/* ── Remaining Balance — cascades off the selected LC Issuance ──── */
const remainingBalance = ref(0);
async function fetchRemainingBalance() {
    if (!toLetterOfCreditIssuanceId.value) { remainingBalance.value = 0; return; }
    const params = new URLSearchParams({
        letterOfCreditIssuanceId: toLetterOfCreditIssuanceId.value,
        internalMoneyTransferId: props.model?.id || 0,
    });
    const result = await fetchJson(`${props.urls.getRemainingBalance}?${params.toString()}`);
    remainingBalance.value = result.data?.remaining_balance ?? 0;
}
watch(toLetterOfCreditIssuanceId, fetchRemainingBalance, { immediate: true });

/* ── From Account Number — cascades off Account Type + Bank + Currency ── */
const fromAccountNumbers = ref(accountNumberOption(props.model?.from_account_number));
async function fetchAccountNumbers() {
    fromAccountNumbers.value = [];
    if (!fromAccountTypeId.value || !fromBankId.value || !currency.value) return;
    const url = `${props.urls.getAccountNumbersForType}/${fromAccountTypeId.value}/${currency.value}/${fromBankId.value}`;
    const result = await fetchJson(url);
    fromAccountNumbers.value = mapAccountNumberOptions(result.data?.data);
}
watch([fromAccountTypeId, fromBankId, currency], fetchAccountNumbers, { immediate: true });

const submitting = ref(false);
function submit() {
    submitting.value = true;
    const payload = {
        transfer_date: transferDate.value,
        from_bank_id: fromBankId.value,
        currency: currency.value,
        to_letter_of_credit_issuance_id: toLetterOfCreditIssuanceId.value,
        from_account_type_id: fromAccountTypeId.value,
        from_account_number: fromAccountNumber.value,
        amount: amount.value,
        interest_amount: interestAmount.value,
        interest_destination: interestDestination.value,
        user_comment: userComment.value,
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
        <div class="px-6 pt-4">
            <Link v-if="instructionsUrl" :href="instructionsUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                {{ $t('📖 Instructions') }}
            </Link>
        </div>
        <div class="p-6 max-w-6xl mx-auto">
            <div class="flex items-center gap-3 mb-1">
                <Link :href="urls.back" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                    {{ $t('← Back to LC Settlement Internal Transfers') }}
                </Link>
            </div>
            <h1 class="text-xl font-semibold cvr-text-primary mb-6">
                {{ isEdit ? $t('Edit') : $t('Add') }} {{ $t('Bank To Letter Of Credit Internal Money Transfer') }}
            </h1>

            <FormErrorSummary />

            <form @submit.prevent="submit" class="space-y-6">
                <div class="cvr-card-bg cvr-border border rounded-lg p-5">
                    <h2 class="text-base font-medium cvr-text-primary mb-4">{{ $t('Bank To Letter Of Credit Transfer Information') }}</h2>
                    <div class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">{{ $t('Date') }} *</label>
                            <input v-model="transferDate" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errors.transfer_date" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.transfer_date }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('From Bank') }} *</label>
                            <select v-model="fromBankId" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="b in [...financialInstitutionBanks].sort((a, b) => a.name.localeCompare(b.name))" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Currency') }} *</label>
                            <select v-model="currency" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('To Letter Of Credit Issuance') }} *</label>
                            <select v-model="toLetterOfCreditIssuanceId" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="lc in lcIssuances" :key="lc.id" :value="lc.id">{{ lc.name }}</option>
                            </select>
                            <p v-if="errors.to_letter_of_credit_issuance_id" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.to_letter_of_credit_issuance_id }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Remaining Balance') }}</label>
                            <input disabled :value="Number(remainingBalance).toLocaleString('en-EG')" class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('From Account Type') }} *</label>
                            <select v-model="fromAccountTypeId" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="a in accountTypes" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('From Account Number') }} *</label>
                            <select v-model="fromAccountNumber" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="n in fromAccountNumbers" :key="n.value" :value="n.value">{{ n.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Amount') }} *</label>
                            <input v-model="amount" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errors.amount" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.amount }}</p>
                        </div>
                    </div>
                </div>

                <!-- Interest -->
                <div class="cvr-card-bg cvr-border border rounded-lg p-5">
                    <h2 class="text-base font-medium cvr-text-primary mb-4">{{ $t('Interest') }}</h2>
                    <div class="cvr-form-grid-3">
                        <div>
                            <label class="cvr-form-label">{{ $t('Interest Amount') }}</label>
                            <input v-model="interestAmount" type="number" step="any" class="cvr-input w-full px-3 py-2 rounded" />
                            <p v-if="errors.interest_amount" class="text-xs mt-1" style="color: var(--cvr-danger-text)">{{ errors.interest_amount }}</p>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Post Interest To') }}</label>
                            <select v-model="interestDestination" class="cvr-input w-full px-3 py-2 rounded">
                                <option v-for="d in interestDestinations" :key="d.value" :value="d.value">{{ d.title }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Comment -->
                <div class="cvr-card-bg cvr-border border rounded-lg p-5">
                    <label class="cvr-form-label">{{ $t('Comment') }}</label>
                    <textarea v-model="userComment" rows="2" class="cvr-input w-full px-3 py-2 rounded"></textarea>
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
