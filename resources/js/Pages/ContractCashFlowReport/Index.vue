<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePermissions } from '@/composables/usePermissions';

const props = defineProps({
    company: Object,
    clientsWithContracts: Array,  // [{id, name}]
    getContractsForCustomerUrl: String,
    savedReports: Array,
    urls: Object,
});

/* ── Customer → Contract cascading select ─────────────────────────
   Same fetch pattern already used by CashExpense/Form.vue's
   loadContractsForPartner(): the shared, UNCHANGED
   getContractsForCustomerOrSupplier() endpoint returns each
   contract's id/name/code/amount/currency/start_date/end_date. */
const partnerId = ref('');
const contractId = ref('');
const contracts = ref([]); // [{id, name, code, amount, currency, start_date, end_date}]
const loadingContracts = ref(false);

const selectedContract = computed(() => contracts.value.find(c => String(c.id) === String(contractId.value)) || null);

async function onPartnerChange() {
    contractId.value = '';
    contracts.value = [];
    if (!partnerId.value) return;
    loadingContracts.value = true;
    try {
        const { data } = await window.axios.get(props.getContractsForCustomerUrl, {
            params: { partnerId: partnerId.value, model: 'Customer', inEditMode: 0 },
        });
        contracts.value = data.contracts || [];
    } finally {
        loadingContracts.value = false;
    }
}

function fmtAmount(c) {
    if (!c) return '0';
    const amount = Number(c.amount || 0).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return `${amount} ${(c.currency || '').toUpperCase()}`;
}

/* ── Report form ──────────────────────────────────────────────── */
const reportInterval = ref('');
const startDate = ref(new Date().toISOString().slice(0, 10));
const endDate = ref((() => { const d = new Date(); d.setMonth(d.getMonth() + 6); return d.toISOString().slice(0, 10); })());
const resetReport = ref(false);
const saveReport = ref(false);
const reportName = ref('');

function submit() {
    if (!contractId.value) return;
    const query = {
        report_interval: reportInterval.value,
        start_date: startDate.value,
        end_date: endDate.value,
        partner_id: partnerId.value,
        contract_id: contractId.value,
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
        <div class="p-6 max-w-7xl mx-auto">
            <h1 class="text-xl font-semibold cvr-text-primary mb-6">{{ $t('Contract Cash Flow Report') }}</h1>

            <div class="cvr-card-bg cvr-border border rounded-lg p-5 mb-6">
                <form @submit.prevent="submit" class="space-y-4">
                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">{{ $t('Customer') }} *</label>
                            <select v-model="partnerId" @change="onPartnerChange" required class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option v-for="p in clientsWithContracts" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Contract') }} *</label>
                            <select v-model="contractId" required :disabled="!partnerId || loadingContracts" class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ loadingContracts ? $t('Loading…') : $t('Select') }}</option>
                                <option v-for="c in contracts" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Contract Code') }}</label>
                            <input :value="selectedContract?.code || ''" disabled class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Contract Amount') }}</label>
                            <input :value="fmtAmount(selectedContract)" disabled class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                    </div>

                    <div class="cvr-form-grid-4">
                        <div>
                            <label class="cvr-form-label">{{ $t('Contract Start Date') }}</label>
                            <input :value="selectedContract?.start_date || ''" type="date" disabled class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Contract End Date') }}</label>
                            <input :value="selectedContract?.end_date || ''" type="date" disabled class="cvr-input w-full px-3 py-2 rounded opacity-70" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Report Interval') }} *</label>
                            <select v-model="reportInterval" required class="cvr-input w-full px-3 py-2 rounded">
                                <option value="">{{ $t('Select') }}</option>
                                <option value="daily">{{ $t('Daily') }}</option>
                                <option value="weekly">{{ $t('Weekly') }}</option>
                                <option value="monthly">{{ $t('Monthly') }}</option>
                            </select>
                        </div>
                        <div></div>
                    </div>

                    <div class="cvr-form-grid-2">
                        <div>
                            <label class="cvr-form-label">{{ $t('Report Start Date') }} *</label>
                            <input v-model="startDate" type="date" required class="cvr-input w-full px-3 py-2 rounded" />
                        </div>
                        <div>
                            <label class="cvr-form-label">{{ $t('Report End Date') }} *</label>
                            <input v-model="endDate" type="date" required class="cvr-input w-full px-3 py-2 rounded" />
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
                        <button type="submit" :disabled="!contractId" class="cvr-btn-primary px-4 py-2 rounded disabled:opacity-50">{{ $t('View Report') }}</button>
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
