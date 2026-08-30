<script setup>
import { ref, computed, watch } from 'vue'
import { router, Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import FormErrorSummary from '@/Components/FormErrorSummary.vue'
import { todayDate } from '@/composables/today'
import { buildContractsForCustomer, normalizeFkId } from './lgFormIds'
/* أقصى تاريخ مسموح بيه لحركة فلوس فعلية — النهاردة.
   الحماية الحقيقية على السيرفر في الـ Form Request. */
const maxDate = todayDate()

const props = defineProps({
  mode: String, // 'create' | 'edit'
  company: Object,
  source: String,
  currencies: Object,
  lgTypes: Object,
  lgCategories: Object,
  commissionIntervals: Object,
  tdAccountTypeId: Number,
  financialInstitutionBanks: Array, // [{id, name}]
  tdAccounts: Array, // [{id, financial_institution_id, account_number, currency, amount}]
  feesAccounts: Array, // [{id, financial_institution_id, account_number, currency}]
  contracts: Array,
  purchaseOrders: Array,
  model: Object,
  customersWithoutContractRequirement: Array,
  lookupUrl: String,
  submitUrl: String,
  backUrl: String,
  navUrls: Object,
})

const page = usePage()
const isEdit = props.mode === 'edit'

const form = ref({
  category_name: props.model?.category_name ?? '',
  transaction_name: props.model?.transaction_name ?? '',
  lg_currency: '',
  financial_institution_id: props.model?.financial_institution_id ?? '',
  cd_or_td_account_type_id: props.tdAccountTypeId,
  cd_or_td_id: props.model?.cd_or_td_id ?? '',
  lg_type: props.model?.lg_type ?? '',
  lg_type_outstanding_balance: 0,
  total_lg_outstanding_balance: 0,
  against_cash_cover: 0,
  lg_code: props.model?.lg_code ?? '',
  partner_id: normalizeFkId(props.model?.partner_id),
  transaction_reference: props.model?.transaction_reference ?? '1',
  contract_id: normalizeFkId(props.model?.contract_id),
  purchase_order_id: normalizeFkId(props.model?.purchase_order_id),
  purchase_order_date: props.model?.purchase_order_date ?? '',
  transaction_date: props.model?.transaction_date ?? '',
  issuance_date: props.model?.issuance_date ?? '',
  lg_duration_months: props.model?.lg_duration_months ?? 1,
  renewal_date: props.model?.renewal_date ?? '',
  lg_amount: props.model?.lg_amount ?? 0,
  lg_commission_rate: props.model?.lg_commission_rate ?? 0,
  lg_commission_amount: props.model?.lg_commission_amount ?? 0,
  min_lg_commission_fees: props.model?.min_lg_commission_fees ?? 0,
  issuance_fees: props.model?.issuance_fees ?? 0,
  lg_commission_interval: props.model?.lg_commission_interval ?? 'quarterly',
  lg_fees_and_commission_account_id: props.model?.lg_fees_and_commission_account_id ?? '',
  user_comment: props.model?.user_comment ?? '',
})

const addingNewCustomer = ref(false)
const newCustomerName = ref('')

/* ── TD selection — filtered by bank + the chosen LG Currency (used
   as a pre-filter, exactly like the original's currency select) ──── */
const tdOptionsForBank = computed(() =>
  props.tdAccounts.filter(
    (td) =>
      td.financial_institution_id === Number(form.value.financial_institution_id) &&
      (!form.value.lg_currency || td.currency === form.value.lg_currency),
  ),
)
const selectedTd = computed(() =>
  props.tdAccounts.find((td) => td.id === Number(form.value.cd_or_td_id)),
)

// Once a TD is actually picked, its own currency is authoritative —
// keep the LG Currency field in sync with it (confirmed: the server
// derives currency from the selected TD itself, not the typed value).
watch(
  selectedTd,
  (td) => {
    if (td) form.value.lg_currency = td.currency
  },
  { immediate: true },
)

const contractsForCustomer = computed(() =>
  buildContractsForCustomer(
    props.contracts,
    form.value.partner_id,
    form.value.contract_id,
  ),
)
const purchaseOrdersForContract = computed(() =>
  props.purchaseOrders.filter((po) => Number(po.contract_id) === Number(form.value.contract_id)),
)

/* ── Bid Bond doesn't link to a Contract/SO — hide those fields and
   clear out any values so a stale selection never gets submitted. ── */
const isBidBond = computed(() => form.value.lg_type === 'bid-bond')
watch(isBidBond, (bidBond) => {
  if (bidBond) {
    form.value.contract_id = ''
    form.value.purchase_order_id = ''
    form.value.purchase_order_date = ''
  }
})

/* ── Picking a specific SO auto-fills its date (and locks the field);
   picking "All SOs" clears it so the user fills it in themselves. ── */
const isSpecificSoSelected = computed(
  () => !!form.value.purchase_order_id && form.value.purchase_order_id !== 'all',
)
watch(
  () => form.value.purchase_order_id,
  (soId) => {
    if (soId && soId !== 'all') {
      const so = props.purchaseOrders.find((p) => p.id === Number(soId))
      form.value.purchase_order_date = so?.so_date ?? ''
    } else if (soId === 'all') {
      form.value.purchase_order_date = ''
    }
  },
)

const feesAccountOptions = computed(() =>
  props.feesAccounts.filter(
    (a) =>
      a.financial_institution_id === Number(form.value.financial_institution_id) &&
      a.currency === form.value.lg_currency,
  ),
)
watch(
  () => [form.value.financial_institution_id, form.value.lg_currency],
  () => {
    const stillValid = feesAccountOptions.value.some(
      (a) => a.id === Number(form.value.lg_fees_and_commission_account_id),
    )
    if (!stillValid) form.value.lg_fees_and_commission_account_id = ''
  },
)

/* ── Live LG Type Outstanding Balance lookup — tracked per the
   specific TD selected here (confirmed: no Limit/Total Outstanding/
   Total Room for this source, those are LG-Facility-only). ───────── */
const customerOptions = ref([])

/*
 * Beneficiaries a contract is NOT required for — an authority, a
 * landlord, anyone flagged "other partner". There is no customer
 * contract behind such a beneficiary to point at.
 *
 * Comes from the same lookup call, and from the same server-side rule
 * the validation applies (LgContractRequirement), so the asterisk the
 * user sees and the rule that runs on save can never disagree.
 */
const customersWithoutContractRequirement = ref(
  (props.customersWithoutContractRequirement ?? []).map(Number),
)

const contractIsRequired = computed(() => {
  if (isBidBond.value) return false
  if (!form.value.partner_id) return true
  return !customersWithoutContractRequirement.value.includes(Number(form.value.partner_id))
})
const lookupLoading = ref(false)
async function runLookup() {
  if (!form.value.financial_institution_id) return
  lookupLoading.value = true
  try {
    const params = new URLSearchParams({
      financialInstitutionId: form.value.financial_institution_id,
      lgType: form.value.lg_type || '',
      source: props.source,
      accountTypeId: form.value.cd_or_td_account_type_id || '',
      cdOrTdAccountId: form.value.cd_or_td_id || '',
    })
    if (props.model?.id) params.set('lgIssuanceId', props.model.id)
    const res = await fetch(`${props.lookupUrl}?${params.toString()}`, {
      headers: { Accept: 'application/json' },
    })
    const data = await res.json()
    form.value.lg_type_outstanding_balance = data.current_lg_type_outstanding_balance ?? 0
    form.value.total_lg_outstanding_balance = data.total_lg_outstanding_balance ?? 0
    form.value.against_cash_cover = data.total_cash_cover_statement_debit ?? 0
    customerOptions.value = Object.entries(data.customers ?? {}).map(([name, id]) => ({
      id: Number(id),
      name,
    }))
    customersWithoutContractRequirement.value = (data.customers_without_contract_requirement ?? []).map(Number)
    if (!isEdit) {
      form.value.min_lg_commission_fees = data.min_lg_commission_fees ?? 0
      form.value.lg_commission_rate = data.lg_commission_rate || form.value.lg_commission_rate
      form.value.issuance_fees =
        data.min_lg_issuance_fees_for_current_lg_type ?? form.value.issuance_fees
    }
  } finally {
    lookupLoading.value = false
  }
}
watch(
  () => [form.value.financial_institution_id, form.value.cd_or_td_id, form.value.lg_type],
  runLookup,
  { immediate: true },
)

/* ── Duration (Months) — read-only, ceiling-rounded, from Issuance
   Date + Renewal Date, same as LG Facility's form. ───────────────── */
function monthsBetweenCeil(startIso, endIso) {
  const start = new Date(startIso)
  const end = new Date(endIso)
  let months = (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth())
  if (end.getDate() > start.getDate()) months += 1
  return Math.max(1, months)
}
watch(
  () => [form.value.issuance_date, form.value.renewal_date],
  () => {
    if (!form.value.issuance_date || !form.value.renewal_date) return
    form.value.lg_duration_months = monthsBetweenCeil(
      form.value.issuance_date,
      form.value.renewal_date,
    )
  },
)
watch(
  () => [form.value.lg_amount, form.value.lg_commission_rate],
  () => {
    form.value.lg_commission_amount =
      Math.round(
        ((Number(form.value.lg_amount || 0) * Number(form.value.lg_commission_rate || 0)) / 100) *
          100,
      ) / 100
  },
)

function errorFor(field) {
  return page.props.errors?.[field] ?? null
}

const submitting = ref(false)
function submit() {
  submitting.value = true
  const payload = { ...form.value }
  if (addingNewCustomer.value) payload.new_customer_name = newCustomerName.value
  // Same fix as LgFacilityForm.vue: storeBasicForm() only converts a
  // submitted value to NULL when it's the literal string 'null' — an
  // empty string sails through as-is and MySQL rejects it as an
  // invalid foreign key.
  if (!payload.contract_id) payload.contract_id = 'null'
  // 'all' is the "All SOs" choice — it means "no specific SO",
  // same as leaving the field empty, so it's stored as null too.
  if (!payload.purchase_order_id || payload.purchase_order_id === 'all')
    payload.purchase_order_id = 'null'
  if (isEdit) {
    router.put(props.submitUrl, payload, {
      onFinish: () => {
        submitting.value = false
      },
    })
  } else {
    router.post(props.submitUrl, payload, {
      onFinish: () => {
        submitting.value = false
      },
    })
  }
}
</script>

<template>
  <AppLayout :nav-urls="navUrls">
    <div class="p-6">
      <div class="flex items-center gap-3 mb-1">
        <Link
          :href="backUrl"
          class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
          {{ $t('← Back to LG Issuance') }}
        </Link>
      </div>
      <h1 class="text-xl font-semibold cvr-text-primary mb-1">
        {{ isEdit ? $t('Edit') : $t('Add') }} {{ $t('LG Issuance — Against TD') }}
      </h1>

      <FormErrorSummary />

      <form
        @submit.prevent="submit"
        class="space-y-6">
        <!-- Main Information -->
        <div class="cvr-card">
          <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">
            {{ $t('Main Information') }}
          </h2>
          <div class="cvr-form-grid-6-4-2">
            <div>
              <label class="cvr-form-label">{{ $t('Bank Name') }} *</label>
              <select
                v-model="form.financial_institution_id"
                class="cvr-input w-full px-3 py-2 rounded">
                <option
                  value=""
                  disabled>
                  {{ $t('Select') }}
                </option>
                <option
                  v-for="b in financialInstitutionBanks"
                  :key="b.id"
                  :value="b.id">
                  {{ b.name }}
                </option>
              </select>
            </div>
            <div>
              <label class="cvr-form-label">{{ $t('Transaction Name') }} *</label>
              <input
                v-model="form.transaction_name"
                type="text"
                class="cvr-input w-full px-3 py-2 rounded" />
              <p
                v-if="errorFor('transaction_name')"
                class="text-xs mt-1 cvr-num-red">
                {{ errorFor('transaction_name') }}
              </p>
            </div>
            <div>
              <label class="cvr-form-label">{{ $t('Issuance Type') }} *</label>
              <select
                v-model="form.category_name"
                class="cvr-input w-full px-3 py-2 rounded">
                <option
                  value=""
                  disabled>
                  {{ $t('Select') }}
                </option>
                <option
                  v-for="(label, code) in lgCategories"
                  :key="code"
                  :value="code">
                  {{ label }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <!-- TD & LG Type -->
        <div class="cvr-card">
          <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">
            {{ $t('TD & LG Type') }}
            <span
              v-if="lookupLoading"
              class="text-xs font-normal cvr-text-muted normal-case"
              >{{ $t('(updating...)') }}</span
            >
          </h2>
          <div class="cvr-form-grid-4">
            <div>
              <label class="cvr-form-label">{{ $t('LG Currency') }}</label>
              <select
                v-model="form.lg_currency"
                class="cvr-input w-full px-3 py-2 rounded">
                <option value="">{{ $t('Any') }}</option>
                <option
                  v-for="(label, code) in currencies"
                  :key="code"
                  :value="code">
                  {{ label }}
                </option>
              </select>
            </div>
            <div>
              <label class="cvr-form-label">{{ $t('TD Account #') }}</label>
              <select
                v-model="form.cd_or_td_id"
                class="cvr-input w-full px-3 py-2 rounded">
                <option
                  value=""
                  disabled>
                  {{ $t('Select') }}
                </option>
                <option
                  v-for="td in tdOptionsForBank"
                  :key="td.id"
                  :value="td.id">
                  {{ td.account_number }}
                </option>
              </select>
            </div>
            <div>
              <label class="cvr-form-label">{{ $t('Amount') }}</label>
              <input
                disabled
                :value="selectedTd?.amount ?? 0"
                class="cvr-input w-full px-3 py-2 rounded opacity-70" />
            </div>
            <div>
              <label class="cvr-form-label">{{ $t('LG Type') }} *</label>
              <select
                v-model="form.lg_type"
                class="cvr-input w-full px-3 py-2 rounded">
                <option
                  value=""
                  disabled>
                  {{ $t('Select') }}
                </option>
                <option
                  v-for="(label, code) in lgTypes"
                  :key="code"
                  :value="code">
                  {{ label }}
                </option>
              </select>
            </div>
            <div>
              <label class="cvr-form-label">{{ $t('LG Outstanding Balance') }}</label>
              <input
                disabled
                :value="form.total_lg_outstanding_balance"
                class="cvr-input w-full px-3 py-2 rounded opacity-70" />
              <p class="text-xs cvr-text-muted mt-1">{{ $t('All LG types combined, against this bank') }}</p>
            </div>
            <div>
              <label class="cvr-form-label">{{ $t('Against Cash Cover') }}</label>
              <input
                disabled
                :value="form.against_cash_cover"
                class="cvr-input w-full px-3 py-2 rounded opacity-70" />
              <p class="text-xs cvr-text-muted mt-1">{{ $t('Already committed against this TD') }}</p>
            </div>
            <div>
              <label class="cvr-form-label">{{ $t('LG Type Outstanding Balance') }}</label>
              <input
                disabled
                :value="form.lg_type_outstanding_balance"
                class="cvr-input w-full px-3 py-2 rounded opacity-70" />
            </div>
            <div>
              <label class="cvr-form-label">{{ $t('LG Code') }} *</label>
              <input
                v-model="form.lg_code"
                type="text"
                class="cvr-input w-full px-3 py-2 rounded" />
            </div>
          </div>
        </div>

        <!-- Beneficiary & Reference -->
        <div class="cvr-card">
          <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">
            {{ $t('Beneficiary & Reference') }}
          </h2>
          <div class="cvr-form-grid-3">
            <div>
              <div class="flex items-center justify-between">
                <label class="cvr-form-label">{{ $t('Customer / Beneficiary') }} *</label>
                <!-- <button type="button" @click="addingNewCustomer = !addingNewCustomer" class="text-xs cvr-text-blue">
                                    {{ addingNewCustomer ? $t('Pick existing') : $t('+ New') }}
                                </button> -->
              </div>
              <select
                v-if="!addingNewCustomer"
                v-model="form.partner_id"
                class="cvr-input w-full px-3 py-2 rounded">
                <option
                  value=""
                  disabled>
                  {{ $t('Select') }}
                </option>
                <option
                  v-for="c in customerOptions"
                  :key="c.id"
                  :value="c.id">
                  {{ c.name }}
                </option>
              </select>
              <input
                v-else
                v-model="newCustomerName"
                type="text"
                :placeholder="$t('Enter new customer name')"
                class="cvr-input w-full px-3 py-2 rounded" />
              <p class="text-xs cvr-text-muted mt-1">
                {{ form.lg_type === 'bid-bond'
                    ? $t('Bid Bond: showing all customers')
                    : $t('Showing only customers with an active contract') }}
              </p>
            </div>
            <div>
              <label class="cvr-form-label">{{ $t('Transaction Reference') }} *</label>
              <input
                v-model="form.transaction_reference"
                type="text"
                class="cvr-input w-full px-3 py-2 rounded" />
            </div>
            <div v-if="!isBidBond">
              <!--
                The asterisk follows the beneficiary: an "other partner" has no
                customer contract to attach, so the field stays available but
                stops being required. Mirrors LgContractRequirement exactly.
              -->
              <label class="cvr-form-label">{{ $t('Contract') }} <span v-if="contractIsRequired">*</span></label>
              <select
                v-model="form.contract_id"
                class="cvr-input w-full px-3 py-2 rounded">
                <option
                  value=""
                  :disabled="contractIsRequired">
                  {{ contractIsRequired ? $t('Select') : $t('None') }}
                </option>
                <option
                  v-for="c in contractsForCustomer"
                  :key="c.id"
                  :value="c.id">
                  {{ c.name }}
                </option>
              </select>
              <p v-if="!contractIsRequired" class="text-xs mt-1 cvr-text-muted">
                {{ $t('Optional for this beneficiary — no customer contract is required.') }}
              </p>
              <p
                v-if="errorFor('contract_id')"
                class="text-xs mt-1 cvr-num-red">
                {{ errorFor('contract_id') }}
              </p>
            </div>
            <div v-if="!isBidBond">
              <label class="cvr-form-label">{{ $t('SO') }} <span v-if="contractIsRequired">*</span></label>
              <select
                v-model="form.purchase_order_id"
                class="cvr-input w-full px-3 py-2 rounded">
                <option
                  value=""
                  disabled>
                  {{ $t('Select') }}
                </option>
                <option value="all">{{ $t('All SOs') }}</option>
                <option
                  v-for="po in purchaseOrdersForContract"
                  :key="po.id"
                  :value="po.id">
                  {{ po.po_number }}
                </option>
              </select>
            </div>
            <div v-if="!isBidBond">
              <label class="cvr-form-label">{{ $t('Sales Order Date') }} *</label>
              <input
                v-model="form.purchase_order_date"
                type="date"
                :disabled="isSpecificSoSelected"
                class="cvr-input w-full px-3 py-2 rounded"
                :class="{ 'opacity-70': isSpecificSoSelected }" />
              <p class="text-xs cvr-text-muted mt-1">
                {{ isSpecificSoSelected
                    ? $t('Auto-filled from the selected SO')
                    : $t('Pick a date for this contract-wide LG') }}
              </p>
            </div>
            <div>
              <label class="cvr-form-label">{{ $t('Transaction Date') }} *</label>
              <input
                v-model="form.transaction_date"
                type="date"
                class="cvr-input w-full px-3 py-2 rounded" />
            </div>
          </div>
        </div>

        <!-- Dates & Amount -->
        <div class="cvr-card">
          <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">
            {{ $t('Dates & Amount') }}
          </h2>
          <div class="cvr-form-grid-4">
            <div>
              <label class="cvr-form-label">{{ $t('Issuance Date') }} *</label>
              <input
                v-model="form.issuance_date"
                type="date"
                :max="maxDate"
                class="cvr-input w-full px-3 py-2 rounded" />
            </div>
            <div>
              <label class="cvr-form-label">{{ $t('Renewal Date') }} *</label>
              <input
                v-model="form.renewal_date"
                type="date"
                class="cvr-input w-full px-3 py-2 rounded" />
            </div>
            <div>
              <label class="cvr-form-label">{{ $t('LG Duration (Months)') }}</label>
              <input
                disabled
                :value="form.lg_duration_months"
                class="cvr-input w-full px-3 py-2 rounded opacity-70" />
              <p class="text-xs cvr-text-muted mt-1">{{ $t('Rounded up — feeds commission calculations') }}</p>
            </div>
            <div>
              <label class="cvr-form-label">{{ $t('LG Amount') }} *</label>
              <input
                v-model="form.lg_amount"
                type="number" step="any"
                class="cvr-input w-full px-3 py-2 rounded" />
              <p
                v-if="errorFor('lg_amount')"
                class="text-xs mt-1 cvr-num-red">
                {{ errorFor('lg_amount') }}
              </p>
            </div>
          </div>
        </div>

        <!-- Commission -->
        <div class="cvr-card">
          <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">
            {{ $t('Commission') }}
          </h2>
          <div class="cvr-form-grid-4">
            <div>
              <label class="cvr-form-label">{{ $t('LG Commission Rate (%)') }} *</label>
              <input
                v-model="form.lg_commission_rate"
                type="number"
                step="any"
                class="cvr-input w-full px-3 py-2 rounded" />
            </div>
            <div>
              <label class="cvr-form-label">{{ $t('LG Commission Amount') }}</label>
              <input
                disabled
                :value="form.lg_commission_amount"
                class="cvr-input w-full px-3 py-2 rounded opacity-70" />
            </div>
            <div>
              <label class="cvr-form-label">{{ $t('Min LG Commission Fees') }}</label>
              <input
                v-model="form.min_lg_commission_fees"
                type="number" step="any"
                class="cvr-input w-full px-3 py-2 rounded" />
            </div>
            <div>
              <label class="cvr-form-label">{{ $t('Issuance Fees') }} *</label>
              <input
                v-model="form.issuance_fees"
                type="number" step="any"
                class="cvr-input w-full px-3 py-2 rounded" />
            </div>
            <div>
              <label class="cvr-form-label">{{ $t('Commission Interval') }} *</label>
              <select
                v-model="form.lg_commission_interval"
                class="cvr-input w-full px-3 py-2 rounded">
                <option
                  v-for="(label, code) in commissionIntervals"
                  :key="code"
                  :value="code">
                  {{ label }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <!-- Fees & Commission Account -->
        <div class="cvr-card">
          <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">
            {{ $t('Fees & Commission Account') }}
          </h2>
          <div class="cvr-form-grid-3">
            <div>
              <label class="cvr-form-label"
                >{{ $t('Deducted From Account # (Fees & Commission)') }} *</label
              >
              <select
                v-model="form.lg_fees_and_commission_account_id"
                class="cvr-input w-full px-3 py-2 rounded">
                <option
                  value=""
                  disabled>
                  {{ $t('Select') }}
                </option>
                <option
                  v-for="a in feesAccountOptions"
                  :key="a.id"
                  :value="a.id">
                  {{ a.account_number }} ({{ a.currency?.toUpperCase() }})
                </option>
              </select>
              <p
                v-if="errorFor('lg_fees_and_commission_account_id')"
                class="text-xs mt-1 cvr-num-red">
                {{ errorFor('lg_fees_and_commission_account_id') }}
              </p>
            </div>
          </div>
          <p class="text-xs cvr-text-muted mt-3">
            {{ $t('Only current accounts for the selected Bank Name, matching the LG\'s currency.') }}
          </p>
        </div>

        <!-- User Comment -->
        <div class="cvr-card">
          <h2 class="text-sm font-semibold cvr-text-secondary uppercase tracking-wide mb-4">
            {{ $t('User Comment') }}
          </h2>
          <textarea
            v-model="form.user_comment"
            rows="3"
            class="cvr-input w-full px-3 py-2 rounded"
            :placeholder="$t('Comment')"></textarea>
        </div>

        <div class="flex justify-end gap-2">
          <Link
            :href="backUrl"
            class="cvr-btn-secondary px-4 py-2 rounded border"
            >{{ $t('Cancel') }}</Link
          >
          <button
            type="submit"
            :disabled="submitting"
            class="cvr-btn-primary px-4 py-2 rounded">
            {{ submitting ? $t('Saving...') : $t('Save') }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
