<script setup lang="ts">
/**
 * Result table for Consolidated Cash Flow (for use with Inertia when wired in Vite).
 * Add the `xlsx` package and wire this page in `vite.config` before enabling Excel export here.
 * The live app uses Blade + SheetJS CDN (`resources/views/reports/consolidated_cash_flow/result.blade.php`).
 */
import { computed, ref } from 'vue'

const props = defineProps<{
  weeks: Record<string, string>
  dates: Record<string, { start_date: string; end_date: string }>
  reportInterval: 'daily' | 'weekly' | 'monthly'
  currencyName: string
  banksSection: Record<string, { total: Record<string, number> }>
  contractsSection: Array<{
    contract_id: number
    contract_name: string
    contract_code: string
    cash_inflow: Record<string, number>
    cash_outflow: Record<string, number>
    net_cash: Record<string, number>
  }>
  companyUnallocatedCashOut: Record<string, number>
  grandTotal: {
    cash_inflow: Record<string, number>
    cash_outflow: Record<string, number>
    net_cash: Record<string, number>
    accumulated_net: Record<string, number>
  }
  title: string
}>()

const weekKeys = computed(() => Object.keys(props.weeks))
const nf = new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const formatNum = (n: number) => nf.format(n)

const openContracts = ref<Record<number, boolean>>({})
const toggleContract = (id: number) => {
  openContracts.value = { ...openContracts.value, [id]: !openContracts.value[id] }
}
const isOpen = (id: number) => openContracts.value[id] !== false
</script>

<template>
  <div class="tw-p-4">
    <p class="tw-mb-2 tw-text-lg tw-font-semibold">{{ title }}</p>
    <p class="tw-mb-2 tw-text-sm tw-opacity-90">{{ currencyName }} — {{ reportInterval }}</p>
    <div class="tw-overflow-x-auto">
      <table class="tw-min-w-full tw-border-collapse tw-text-sm">
        <thead class="tw-sticky tw-top-0 tw-z-10 tw-bg-slate-900 tw-text-white">
          <tr>
            <th class="tw-border tw-px-2 tw-py-2">Row</th>
            <th v-for="wk in weekKeys" :key="wk" class="tw-border tw-px-2 tw-py-2 tw-text-center" :title="`${dates[wk]?.start_date} → ${dates[wk]?.end_date}`">
              {{ weeks[wk] ?? wk }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr class="tw-bg-indigo-950/80 tw-text-white">
            <td :colspan="weekKeys.length + 1" class="tw-border tw-px-2 tw-py-2 tw-font-semibold">Section A — Company level (Cash & Banks Balance)</td>
          </tr>
          <tr v-for="(row, label) in banksSection" :key="label">
            <td class="tw-border tw-px-2 tw-py-1 tw-font-medium">{{ label }}</td>
            <td v-for="wk in weekKeys" :key="wk" class="tw-border tw-px-2 tw-py-1 tw-text-center tw-tabular-nums">
              {{ formatNum(row.total[wk] ?? 0) }}
            </td>
          </tr>
          <template v-for="block in contractsSection" :key="block.contract_id">
            <tr class="tw-bg-teal-950/70 tw-text-white">
              <td :colspan="weekKeys.length + 1" class="tw-border tw-px-2 tw-py-2">
                <button type="button" class="tw-w-full tw-text-start tw-font-semibold" @click="toggleContract(block.contract_id)">
                  {{ block.contract_name }} — {{ block.contract_code }}
                </button>
              </td>
            </tr>
            <template v-if="isOpen(block.contract_id)">
              <tr>
                <td class="tw-border tw-px-2 tw-py-1">Total Cash Inflow</td>
                <td v-for="wk in weekKeys" :key="wk" class="tw-border tw-px-2 tw-py-1 tw-text-center tw-text-emerald-300 tw-tabular-nums">
                  {{ formatNum(block.cash_inflow[wk] ?? 0) }}
                </td>
              </tr>
              <tr>
                <td class="tw-border tw-px-2 tw-py-1">Total Cash Outflow</td>
                <td v-for="wk in weekKeys" :key="wk" class="tw-border tw-px-2 tw-py-1 tw-text-center tw-tabular-nums">
                  {{ formatNum(block.cash_outflow[wk] ?? 0) }}
                </td>
              </tr>
              <tr>
                <td class="tw-border tw-px-2 tw-py-1">Net Cash (+/-)</td>
                <td
                  v-for="wk in weekKeys"
                  :key="wk"
                  class="tw-border tw-px-2 tw-py-1 tw-text-center tw-tabular-nums"
                  :class="(block.net_cash[wk] ?? 0) < 0 ? 'tw-text-red-400' : 'tw-text-emerald-400'"
                >
                  {{ formatNum(block.net_cash[wk] ?? 0) }}
                </td>
              </tr>
            </template>
          </template>
          <tr class="tw-bg-slate-800/80 tw-text-white">
            <td :colspan="weekKeys.length + 1" class="tw-border tw-px-2 tw-py-2 tw-font-semibold">Company cash out (unallocated)</td>
          </tr>
          <tr>
            <td class="tw-border tw-px-2 tw-py-1">Company cash out (unallocated)</td>
            <td v-for="wk in weekKeys" :key="wk" class="tw-border tw-px-2 tw-py-1 tw-text-center tw-tabular-nums">
              {{ formatNum(companyUnallocatedCashOut[wk] ?? 0) }}
            </td>
          </tr>
          <tr class="tw-bg-amber-950/50 tw-text-white">
            <td :colspan="weekKeys.length + 1" class="tw-border tw-px-2 tw-py-2 tw-font-semibold">Section C — Grand total</td>
          </tr>
          <tr>
            <td class="tw-border tw-px-2 tw-py-1">Total Cash Inflow</td>
            <td v-for="wk in weekKeys" :key="wk" class="tw-border tw-px-2 tw-py-1 tw-text-center tw-text-emerald-300 tw-tabular-nums">
              {{ formatNum(grandTotal.cash_inflow[wk] ?? 0) }}
            </td>
          </tr>
          <tr>
            <td class="tw-border tw-px-2 tw-py-1">Total Cash Outflow</td>
            <td v-for="wk in weekKeys" :key="wk" class="tw-border tw-px-2 tw-py-1 tw-text-center tw-tabular-nums">
              {{ formatNum(grandTotal.cash_outflow[wk] ?? 0) }}
            </td>
          </tr>
          <tr>
            <td class="tw-border tw-px-2 tw-py-1">Net Cash (+/-)</td>
            <td
              v-for="wk in weekKeys"
              :key="wk"
              class="tw-border tw-px-2 tw-py-1 tw-text-center tw-tabular-nums"
              :class="(grandTotal.net_cash[wk] ?? 0) < 0 ? 'tw-text-red-400' : 'tw-text-emerald-400'"
            >
              {{ formatNum(grandTotal.net_cash[wk] ?? 0) }}
            </td>
          </tr>
          <tr>
            <td class="tw-border tw-px-2 tw-py-1">Accumulated Net Cash (+/-)</td>
            <td
              v-for="wk in weekKeys"
              :key="wk"
              class="tw-border tw-px-2 tw-py-1 tw-text-center tw-tabular-nums"
              :class="(grandTotal.accumulated_net[wk] ?? 0) < 0 ? 'tw-text-red-400' : 'tw-text-emerald-400'"
            >
              {{ formatNum(grandTotal.accumulated_net[wk] ?? 0) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
@media print {
  .no-print {
    display: none !important;
  }
}
</style>
