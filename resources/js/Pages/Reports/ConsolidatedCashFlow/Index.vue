<script setup lang="ts">
/**
 * Filter form for Consolidated Cash Flow (for use with Inertia when wired in Vite).
 * The live app uses `resources/views/reports/consolidated_cash_flow/index.blade.php`.
 */
export type ContractOption = { id: number; name: string; code: string | null }

defineProps<{
  companyId: number
  activeContracts: ContractOption[]
  submitUrl: string
}>()
</script>

<template>
  <div class="p-4">
    <form :action="submitUrl" method="get" class="space-y-4">
      <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <label class="block">
          <span class="mb-1 block text-sm font-medium">Report Interval</span>
          <select name="report_interval" required class="w-full rounded border border-slate-600 bg-slate-900 p-2">
            <option value="daily">daily</option>
            <option value="weekly" selected>weekly</option>
            <option value="monthly">monthly</option>
          </select>
        </label>
        <label class="block">
          <span class="mb-1 block text-sm font-medium">Start Date</span>
          <input type="date" name="start_date" required class="w-full rounded border border-slate-600 bg-slate-900 p-2" />
        </label>
        <label class="block">
          <span class="mb-1 block text-sm font-medium">End Date</span>
          <input type="date" name="end_date" required class="w-full rounded border border-slate-600 bg-slate-900 p-2" />
        </label>
      </div>
      <label class="block">
        <span class="mb-1 block text-sm font-medium">Contracts</span>
        <select name="contract_ids[]" multiple class="min-h-[120px] w-full rounded border border-slate-600 bg-slate-900 p-2">
          <option v-for="c in activeContracts" :key="c.id" :value="c.id">
            {{ c.name }}<template v-if="c.code"> [{{ c.code }}]</template>
          </option>
        </select>
      </label>
      <button type="submit" class="rounded bg-teal-600 px-4 py-2 text-white hover:bg-teal-500">Run Report</button>
    </form>
  </div>
</template>
