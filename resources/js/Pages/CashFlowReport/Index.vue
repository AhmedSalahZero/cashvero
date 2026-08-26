<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermissions } from '@/composables/usePermissions';

const props = defineProps({
    company: Object,
    currencies: Array,
    mainFunctionalCurrency: String,
    savedReports: Array,
    urls: Object,
});

const reportInterval = ref('');
const startDate = ref(new Date().toISOString().slice(0, 10));
const endDate = ref((() => { const d = new Date(); d.setMonth(d.getMonth() + 6); return d.toISOString().slice(0, 10); })());
const currency = ref(props.mainFunctionalCurrency || '');
const resetReport = ref(false);
const saveReport = ref(false);
const reportName = ref('');

function submit() {
    const query = {
        report_interval: reportInterval.value,
        start_date: startDate.value,
        end_date: endDate.value,
        currency: currency.value,
    };
    if (resetReport.value) query.reset_report = 1;
    if (saveReport.value) {
        query.save_report = 1;
        query.report_name = reportName.value;
    }
    router.get(props.urls.result, query);
}

const { can } = usePermissions();

/* ── Delete confirmation ──────────────────────────────────────── */
/* Both pages delete a SAVED cash-flow report through the same
   `delete.cashflow.report` route, so both obey the same key that
   RoutePermissionMap enforces on it. */
const deleteTarget = ref(null);
function destroyReport() {
    router.delete(deleteTarget.value.delete_url, { onFinish: () => { deleteTarget.value = null; } });
}
</script>

<template>
    <AppLayout>
        <div class="p-6 max-w-full mx-auto">
            <h1 class="text-xl font-semibold cvr-text-primary mb-6">{{ $t('Cash Flow Report') }}</h1>

            <div class="cvr-card-bg cvr-border border rounded-lg p-5 mb-6">
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">{{ $t('Report Interval') }} *</label>
                            <select v-model="reportInterval" required class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option value="daily">{{ $t('Daily') }}</option>
                                <option value="weekly">{{ $t('Weekly') }}</option>
                                <option value="monthly">{{ $t('Monthly') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Start Date') }} *</label>
                            <input v-model="startDate" type="date" required class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('End Date') }} *</label>
                            <input v-model="endDate" type="date" required class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Currency') }} *</label>
                            <select v-model="currency" required class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.label }}</option>
                            </select>
                        </div>
                    </div>

                    <p class="text-sm" style="color: var(--cvr-danger-text)">
                        {{ $t('Note: the date of Today must be included within the report duration.') }}
                    </p>

                    <div class="cvr-form-grid-3 items-end">
                        <div class="flex items-center gap-2">
                            <input id="reset_report" v-model="resetReport" type="checkbox" class="cursor-pointer" />
                            <label for="reset_report" class="cvr-form-label mb-0">{{ $t('Reset [Past Dues & Other Projected Cash In & Out]') }}</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input id="save_report" v-model="saveReport" type="checkbox" class="cursor-pointer" />
                            <label for="save_report" class="cvr-form-label mb-0">{{ $t('Do You Want To Save Report') }}</label>
                        </div>
                        <div v-if="saveReport">
                            <label class="cvr-form-label">{{ $t('Report Name') }}</label>
                            <input v-model="reportName" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="cvr-btn-primary px-4 py-2 rounded">{{ $t('View Report') }}</button>
                    </div>
                </form>
            </div>

            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-start">#</th>
                            <th class="px-4 py-3 text-start">{{ $t('Report Name') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Report Interval') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Start Date') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('End Date') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, index) in savedReports" :key="r.id" class="cvr-table-row">
                            <td class="px-4 py-3">{{ index + 1 }}</td>
                            <td class="px-4 py-3">{{ r.name }}</td>
                            <td class="px-4 py-3 capitalize">{{ r.interval }}</td>
                            <td class="px-4 py-3">{{ r.start_date_formatted }}</td>
                            <td class="px-4 py-3">{{ r.end_date_formatted }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <Link :href="r.view_url" class="cvr-action-btn" :title="$t('View')">✏️</Link>
                                    <button v-if="can('cash_flow_report.delete')" @click="deleteTarget = r" class="cvr-action-btn-danger cvr-action-btn" :title="$t('Delete')">🗑️</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="savedReports.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center cvr-text-muted">{{ $t('No saved reports yet.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Delete confirmation -->
            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">{{ $t('Delete Cashflow Report') }} {{ deleteTarget.name }}</h2>
                    <p class="text-sm cvr-text-muted mb-4">{{ $t('Are you sure you want to delete this item?') }}</p>
                    <div class="flex justify-end gap-2">
                        <button @click="deleteTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                        <button @click="destroyReport" class="cvr-btn-danger px-3 py-1.5 rounded">{{ $t('Delete') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
