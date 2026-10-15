<script setup>
/**
 * Statements/BankStatement/Index.vue
 * ------------------------------------------------------------------
 * Served by BankStatementController@index. Filter form for the Bank
 * Statement report: pick a Bank, then an Account Type, then a real
 * Account Number under that bank+type (cascading — each step narrows
 * the next dropdown's options), plus Currency and a date range.
 *
 * Submits as a normal GET to BankStatementController@result, matching
 * the original Blade form's method="get" (so the result page's URL
 * itself carries every filter, and can be bookmarked/shared/paged
 * through without losing them).
 */
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import { todayDate, clampDateToToday } from '@/composables/today'
import { mapAccountNumberOptions, hasAccountNumber } from '@/composables/useAccountNumberOptions'

const maxDate = todayDate()

const props = defineProps({
	company: Object,
	financialInstitutionBanks: Array, // [{ id, name }]
	accountTypes: Array,              // [{ id, name, modelName }]
	currencies: Object,               // { code: label }
	selectedAccountTypeName: String,
	selectedCurrency: String,
	urls: Object,                     // { result, accountNumbers }
})

const financialInstitutionId = ref('')
const accountTypeId = ref('')
const accountNumber = ref('')
const currency = ref(props.selectedCurrency || '')
const startDate = ref(new Date(new Date().setFullYear(new Date().getFullYear() - 1)).toISOString().slice(0, 10))
const endDate = ref(todayDate())
// const endDate = ref(new Date(new Date().setFullYear(new Date().getFullYear() + 1)).toISOString().slice(0, 10));
// const startDate = ref(new Date().toISOString().slice(0, 10));

const accountNumberOptions = ref([])
const loadingAccountNumbers = ref(false)

const bankOptions = computed(() =>
	[...(props.financialInstitutionBanks || [])]
		.slice()
		.sort((a, b) => String(a.name).localeCompare(String(b.name)))
		.map((bank) => ({ value: bank.id, label: bank.name }))
)

/* ── Account Number cascade — reloaded whenever Bank, Account Type,
   or Currency changes. Same JSON contract every other cascading
   account-number dropdown in the app already uses (see
   MoneyReceivedController@getAccountNumbersForAccountType), just
   served by BankStatementController@getAccountNumbers for this page. */
async function loadAccountNumbers() {
	if (!financialInstitutionId.value || !accountTypeId.value) {
		accountNumberOptions.value = []
		accountNumber.value = ''
		return
	}
	loadingAccountNumbers.value = true
	try {
		const { data } = await window.axios.get(props.urls.accountNumbers, {
			params: {
				account_type: accountTypeId.value,
				financial_institution_id: financialInstitutionId.value,
				currency: currency.value,
			},
		})
		accountNumberOptions.value = mapAccountNumberOptions(data.data)
		if (!hasAccountNumber(accountNumberOptions.value, accountNumber.value)) {
			accountNumber.value = accountNumberOptions.value[0]?.value || ''
		}
	} finally {
		loadingAccountNumbers.value = false
	}
}
watch([financialInstitutionId, accountTypeId, currency], loadAccountNumbers)

watch(endDate, (value) => {
	const clamped = clampDateToToday(value)
	if (clamped !== value) {
		endDate.value = clamped
	}
})

const canSubmit = computed(() =>
	financialInstitutionId.value && accountTypeId.value && accountNumber.value && currency.value && startDate.value && endDate.value && endDate.value <= maxDate
)

function submit() {
	if (!canSubmit.value) return
	endDate.value = clampDateToToday(endDate.value)
	router.get(props.urls.result, {
		start_date: startDate.value,
		end_date: endDate.value,
		financial_institution_id: financialInstitutionId.value,
		account_type: accountTypeId.value,
		account_number: accountNumber.value,
		currency: currency.value,
	})
}
</script>
<template>
	<AppLayout>
		<div class="p-6">
			<h1 class="text-xl font-semibold cvr-text-primary mb-1">{{ $t('Bank Statement') }}</h1>
			<p class="text-sm cvr-text-muted mb-6"> {{ $t('A transaction-by-transaction ledger for one bank account or facility, for a chosen date range.') }} </p>
			<div class="cvr-card-bg cvr-border border rounded-lg p-5">
				<div class="cvr-form-grid-3 mb-4">
					<div>
						<label class="cvr-form-label">{{ $t('Start Date') }} *</label>
						<input v-model="startDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
					</div>
					<div>
						<label class="cvr-form-label">{{ $t('End Date') }} *</label>
						<input v-model="endDate" type="date" :max="maxDate" class="cvr-input w-full px-3 py-2 rounded" />
					</div>
					<div>
						<label class="cvr-form-label">{{ $t('Currency') }} *</label>
						<select v-model="currency" class="cvr-input w-full px-3 py-2 rounded">
							<option value="" disabled>{{ $t('Select currency') }}</option>
							<option v-for="(label, code) in currencies" :key="code" :value="code">
								{{ String(label).toUpperCase() }}</option>
						</select>
					</div>
				</div>
				<div class="cvr-form-grid-6-4-2">
					<div>
						<label class="cvr-form-label">{{ $t('Bank') }} *</label>
						<SearchableSelect
							v-model="financialInstitutionId"
							:options="bankOptions"
							:placeholder="$t('Select bank')"
						/>
					</div>
					<div>
						<label class="cvr-form-label">{{ $t('Account Type') }} *</label>
						<select v-model="accountTypeId" class="cvr-input w-full px-3 py-2 rounded">
							<option value="" disabled>{{ $t('Select account type') }}</option>
							<option v-for="type in accountTypes" :key="type.id" :value="type.id">{{ type.name }}
							</option>
						</select>
					</div>
					<div>
						<label class="cvr-form-label">{{ $t('Account Number') }} *</label>
						<select v-model="accountNumber" class="cvr-input w-full px-3 py-2 rounded"
							:disabled="loadingAccountNumbers || accountNumberOptions.length === 0">
							<option value="" disabled>
								{{ loadingAccountNumbers ? $t('Loading…') : (accountNumberOptions.length ? $t('Select account number') : $t('Select bank & account type first')) }}
							</option>
							<option v-for="num in accountNumberOptions" :key="num.value" :value="num.value">{{ num.label }}</option>
						</select>
					</div>
				</div>
				<button @click="submit" :disabled="!canSubmit" class="cvr-btn-primary px-4 py-1.5 rounded text-sm mt-5"
					:class="{ 'opacity-40 cursor-not-allowed': !canSubmit }"> {{ $t('View Statement') }} </button>
			</div>
		</div>
	</AppLayout>
</template>
