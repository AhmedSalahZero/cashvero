<script setup>
/**
 * Balances/Statement.vue
 * ------------------------------------------------------------------
 * Served by CustomerInvoiceDashboardController@showInvoiceStatementReport.
 * A ledger-style report: beginning balance, then every movement
 * (invoice / collection / deduction / down payment / factoring) in
 * the chosen date range, ending in a running balance column.
 *
 * Reached two ways from Balances/Index.vue: one customer's
 * statement (the per-row "Statement Report" button), or every customer
 * in a currency at once (the "bulk statement" button — showAllPartner).
 * Entirely READ-ONLY — the only interactive part is the name/date
 * filter form up top.
 *
 * The row data itself (debit/credit/comment per movement) comes
 * pre-calculated from HasBalances::formatForStatementReport() —
 * UNCHANGED. The one thing computed here in the browser is the
 * running "End Balance" column, and that's not new: the original
 * Blade template computed it the exact same way, inline, in its own
 * @foreach loop — it was never part of the backend ledger math. See
 * runningBalances below for the identical formula.
 */
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    /* Link to this screen's written guide — see
       App\Support\Instructions\PageInstructions. */
    instructionsUrl: String,
    company: Object,
    partnerId: [Number, String],
    partnerName: String, // null if the selected partner no longer exists
    currency: String,
    startDate: String,
    endDate: String,
    customerStatementText: String,
    partners: Object, // { [partnerId]: name } — already filtered to this currency
    showAllPartner: Boolean,
    filterUrl: String,
    exportUrl: String,
    backUrl: String,
    invoicesWithItsReceivedMoney: Array, // [{ date, document_type, document_no, debit, credit, end_balance, comment }]
});

/* ── Filter form — Name / Start Date / End Date, submits back to the
   same route as a normal GET. Mirrors the original Blade form exactly
   (no action attribute there either — same-URL resubmit). Picking a
   partner from the dropdown while in "all partners" bulk mode has no
   effect, matching the original's behavior (the hidden all_partners
   field is a snapshot, not a toggle — bulk mode is only entered via
   the dedicated button on the Balances page). ─────────────────────── */
const selectedPartnerId = ref(String(props.partnerId ?? ''));
const startDate = ref(props.startDate);
const endDate = ref(props.endDate);

function submitFilter() {
    router.get(props.filterUrl, {
        partner_id: selectedPartnerId.value,
        start_date: startDate.value,
        end_date: endDate.value,
        all_partners: props.showAllPartner ? 1 : 0,
    }, { preserveState: true, preserveScroll: true });
}

function currencyLabel(currency) {
    return currency === 'main_currency' ? 'Main Currency' : currency;
}

/* ── Running "End Balance" column — identical formula to the original
   Blade template's inline @php block:
     balances[0]  = item[0].end_balance                 (the beginning
                                                           balance row's
                                                           own value is
                                                           already a
                                                           real running
                                                           total as of
                                                           start date)
     balances[i]  = balances[i-1] + item[i].debit - item[i].credit
   Every row after the first only carries its OWN delta in end_balance
   (not a running total) — the accumulation is what builds the column
   people actually read. ─────────────────────────────────────────── */
const rows = computed(() => props.invoicesWithItsReceivedMoney || []);
const runningBalances = computed(() => {
    const balances = [];
    rows.value.forEach((item, index) => {
        if (index === 0) {
            balances[index] = Number(item.end_balance || 0);
        } else {
            balances[index] = balances[index - 1] + Number(item.debit || 0) - Number(item.credit || 0);
        }
    });
    return balances;
});

function formatAmount(value) {
    return Number(value || 0).toLocaleString('en-EG', { maximumFractionDigits: 2 });
}

function balanceColorVar(balance) {
    if (balance > 0) return 'var(--cvr-num-amber)';
    if (balance < 0) return 'var(--cvr-num-red)';
    return 'var(--cvr-num-green)';
}
</script>

<template>
    <AppLayout>
        <div class="px-6 pt-4">
            <Link v-if="instructionsUrl" :href="instructionsUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm">
                {{ $t('📖 Instructions') }}
            </Link>
        </div>
        <div class="p-6">
            <Link :href="backUrl" class="cvr-btn-secondary inline-flex items-center gap-1 px-3 py-1.5 rounded border text-sm mb-3">
                {{ $t('← Back to Balances') }}
            </Link>
            <div class="flex items-center justify-between flex-wrap gap-3 mb-1">
                <h1 class="text-xl font-semibold cvr-text-primary">
                    {{ customerStatementText }}
                    <span v-if="partnerName" class="cvr-text-secondary font-normal">— {{ partnerName }}</span>
                    <span class="cvr-text-secondary font-normal">[ {{ currencyLabel(currency) }} ]</span>
                </h1>
                <a v-if="exportUrl && rows.length" :href="exportUrl" class="cvr-btn-secondary px-3 py-1.5 rounded border text-sm">
                    {{ $t('⬇️ Export to Excel') }}
                </a>
            </div>
            <p class="text-sm cvr-text-muted mb-6">
                {{ showAllPartner ? $t('Every ') + customerStatementText.toLowerCase() + $t(' in this currency') : $t('Movements for the selected date range') }}
            </p>

            <!-- Filter form -->
            <div class="cvr-card-bg cvr-border border rounded-lg p-4 mb-6">
                <div class="cvr-form-grid-8-2-2">
                    <div>
                        <label class="cvr-form-label">{{ $t('Name') }}</label>
                        <select v-model="selectedPartnerId" class="cvr-input w-full px-3 py-2 rounded">
                            <option v-for="(name, id) in partners" :key="id" :value="id">{{ name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('Start Date') }}</label>
                        <input v-model="startDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                    <div>
                        <label class="cvr-form-label">{{ $t('End Date') }}</label>
                        <input v-model="endDate" type="date" class="cvr-input w-full px-3 py-2 rounded" />
                    </div>
                </div>
                <button @click="submitFilter" class="cvr-btn-primary px-4 py-1.5 rounded text-sm mt-4">{{ $t('Submit') }}</button>
            </div>

            <!-- Table -->
            <div class="cvr-card-bg cvr-border border rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="cvr-table-head">
                        <tr>
                            <th class="px-4 py-3 text-center">#</th>
                            <th class="px-4 py-3 text-center">{{ $t('Date') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Document Type') }}</th>
                            <th class="px-4 py-3 text-center">{{ $t('Document No') }}</th>
                            <th class="px-4 py-3 text-right">{{ $t('Debit') }}</th>
                            <th class="px-4 py-3 text-right">{{ $t('Credit') }}</th>
                            <th class="px-4 py-3 text-right">{{ $t('End Balance') }}</th>
                            <th class="px-4 py-3 text-start">{{ $t('Comment') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, index) in rows" :key="index" class="cvr-table-row">
                            <td class="px-4 py-3 text-center cvr-text-secondary">{{ index + 1 }}</td>
                            <td class="px-4 py-3 text-center cvr-text-secondary">{{ item.date }}</td>
                            <td class="px-4 py-3 text-start cvr-text-primary">{{ item.document_type }}</td>
                            <td class="px-4 py-3 text-center cvr-text-secondary">{{ item.document_no }}</td>
                            <td class="px-4 py-3 text-right cvr-num">{{ formatAmount(item.debit) }}</td>
                            <td class="px-4 py-3 text-right cvr-num">{{ formatAmount(item.credit) }}</td>
                            <td class="px-4 py-3 text-right font-medium" :style="{ color: balanceColorVar(runningBalances[index]) }">{{ formatAmount(runningBalances[index]) }}</td>
                            <td class="px-4 py-3 text-start cvr-text-secondary">
                                <span class="block max-w-[320px] whitespace-normal">{{ item.comment }}</span>
                            </td>
                        </tr>
                        <tr v-if="rows.length === 0">
                            <td colspan="8" class="px-4 py-8 text-center cvr-text-muted">{{ $t('No movements found for this date range.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
