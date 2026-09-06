<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * SettlementsInfoButton
 * ==================================================================
 * The ℹ️ action button + a read-only modal listing the invoices this
 * money row settled.
 *
 *   <SettlementsInfoButton :url="row.settlements_info_url" />
 *
 * Before this, the only way to see which invoices a receipt or a
 * payment settled — and how much of it stayed behind as a down
 * payment — was to open the edit screen.
 *
 * Rows load on open, not with the page: a list of 50 records would
 * otherwise pull 50 settlement sets nobody asked for.
 */
const props = defineProps({
    url: { type: String, required: true },
});

const { t } = useI18n();

const open = ref(false);
const loading = ref(false);
const error = ref('');
const rows = ref([]);
const currency = ref('');
const totalSettlement = ref('');
const totalWithhold = ref('');
const downPayment = ref(null);

async function show() {
    open.value = true;
    loading.value = true;
    error.value = '';

    try {
        const res = await fetch(props.url, { headers: { Accept: 'application/json' } });

        if (!res.ok) {
            error.value = t('Something Went Wrong');
            return;
        }

        const data = await res.json();
        rows.value = data.rows ?? [];
        currency.value = data.currency ?? '';
        totalSettlement.value = data.total_settlement ?? '';
        totalWithhold.value = data.total_withhold ?? '';
        downPayment.value = data.down_payment_amount ?? null;
    } catch {
        error.value = t('Something Went Wrong');
    } finally {
        loading.value = false;
    }
}

function close() {
    open.value = false;
}
</script>

<template>
    <button @click="show" class="cvr-action-btn" :title="$t('Settlement Details')">ℹ️</button>

    <teleport to="body">
        <div v-if="open" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="close">
            <div class="cvr-modal rounded-lg p-6 w-full max-w-4xl max-h-[80vh] flex flex-col mx-4">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-medium cvr-text-primary">{{ $t('Settlement Details') }}</h2>
                    <button @click="close" class="cvr-action-btn" :title="$t('Close')">✖️</button>
                </div>

                <div v-if="loading" class="py-8 text-center cvr-text-muted">{{ $t('Loading') }}…</div>
                <div v-else-if="error" class="py-8 text-center cvr-text-danger">{{ error }}</div>

                <template v-else>
                    <div v-if="rows.length === 0" class="py-8 text-center cvr-text-muted">
                        {{ $t('No Settled Invoices') }}
                    </div>

                    <div v-else class="overflow-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="cvr-table-head">
                                    <th class="px-3 py-2 text-start">{{ $t('Invoice Number') }}</th>
                                    <th class="px-3 py-2 text-start">{{ $t('Invoice Date') }}</th>
                                    <th class="px-3 py-2 text-start">{{ $t('Due Date') }}</th>
                                    <th class="px-3 py-2 text-end">{{ $t('Invoice Amount') }}</th>
                                    <th class="px-3 py-2 text-end">{{ $t('Settlement Amount') }}</th>
                                    <th class="px-3 py-2 text-end">{{ $t('Withhold Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, i) in rows" :key="i" class="cvr-table-row">
                                    <td class="px-3 py-2">
                                        {{ row.invoice_number }}
                                        <span v-if="row.is_from_down_payment" class="cvr-text-muted">
                                            ({{ $t('From Down Payment') }})
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">{{ row.invoice_date }}</td>
                                    <td class="px-3 py-2">{{ row.due_date }}</td>
                                    <td class="px-3 py-2 text-end">{{ row.invoice_amount }}</td>
                                    <td class="px-3 py-2 text-end">{{ row.settlement_amount }}</td>
                                    <td class="px-3 py-2 text-end">{{ row.withhold_amount }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="cvr-table-head">
                                    <th class="px-3 py-2 text-end" colspan="4">{{ $t('Total') }}</th>
                                    <th class="px-3 py-2 text-end">{{ totalSettlement }} {{ currency }}</th>
                                    <th class="px-3 py-2 text-end">{{ totalWithhold }} {{ currency }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <p v-if="downPayment" class="mt-4 cvr-text-secondary">
                        {{ $t('Down Payment') }}: {{ downPayment }} {{ currency }}
                    </p>
                </template>

                <div class="flex justify-end mt-4">
                    <button @click="close" class="cvr-btn-secondary px-3 py-1.5 rounded border">{{ $t('Close') }}</button>
                </div>
            </div>
        </div>
    </teleport>
</template>
