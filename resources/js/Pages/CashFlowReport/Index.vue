<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

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

/* ── Delete confirmation ──────────────────────────────────────── */
const deleteTarget = ref(null);
function destroyReport() {
    router.delete(deleteTarget.value.delete_url, { onFinish: () => { deleteTarget.value = null; } });
}
</script>

<template>
    <AppLayout>
        <div class="p-6 max-w-full mx-auto">
            <h1 class="text-xl font-semibold cvr-text-primary mb-6">Cash Flow Report</h1>

            <div class="cvr-card-bg cvr-border border rounded-lg p-5 mb-6">
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">Report Interval *</label>
                            <select v-model="reportInterval" required class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">Select</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">Start Date *</label>
                            <input v-model="startDate" type="date" required class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">End Date *</label>
                            <input v-model="endDate" type="date" required class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">Currency *</label>
                            <select v-model="currency" required class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">Select</option>
                                <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.label }}</option>
                            </select>
                        </div>
                    </div>

                    <p class="text-sm" style="color: var(--cvr-danger-text)">
                        Note: the date of Today must be included within the report duration.
                    </p>

                    <div class="cvr-form-grid-3 items-end">
                        <div class="flex items-center gap-2">
                            <input id="reset_report" v-model="resetReport" type="checkbox" class="cursor-pointer" />
                            <label for="reset_report" class="cvr-form-label mb-0">Reset [Past Dues & Other Projected Cash In &amp; Out]</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input id="save_report" v-model="saveReport" type="checkbox" class="cursor-pointer" />
                            <label for="save_report" class="cvr-form-label mb-0">Do You Want To Save Report</label>
                        </div>
                        <div v-if="saveReport">
                            <label class="cvr-form-label">Report Name</label>
                            <input v-model="reportName" type="text" class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="cvr-btn-primary px-4 py-2 rounded">View Report</button>
                    </div>
                </form>
            </div>

            <div class="cvr-card-bg cvr-border border rounded-lg overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-left">#</th>
                            <th class="px-4 py-3 text-left">Report Name</th>
                            <th class="px-4 py-3 text-left">Report Interval</th>
                            <th class="px-4 py-3 text-left">Start Date</th>
                            <th class="px-4 py-3 text-left">End Date</th>
                            <th class="px-4 py-3 text-left">Actions</th>
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
                                    <Link :href="r.view_url" class="cvr-action-btn" title="View">✏️</Link>
                                    <button @click="deleteTarget = r" class="cvr-action-btn-danger cvr-action-btn" title="Delete">🗑️</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="savedReports.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center cvr-text-muted">No saved reports yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Delete confirmation -->
            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                <div class="cvr-modal rounded-lg p-6 w-full max-w-sm">
                    <h2 class="text-lg font-medium cvr-text-primary mb-4">Delete Cashflow Report {{ deleteTarget.name }}</h2>
                    <p class="text-sm cvr-text-muted mb-4">Are you sure you want to delete this item?</p>
                    <div class="flex justify-end gap-2">
                        <button @click="deleteTarget = null" class="cvr-btn-secondary px-3 py-1.5 rounded border">Close</button>
                        <button @click="destroyReport" class="cvr-btn-danger px-3 py-1.5 rounded">Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
