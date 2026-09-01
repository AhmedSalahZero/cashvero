<script setup>
const props = defineProps({
    company: Object,
    record: Object,
    printedAt: String,
});

function triggerPrint() {
    window.print();
}
</script>

<template>
    <div class="print-page">
        <div class="no-print actions">
            <button type="button" class="btn" @click="triggerPrint">{{ $t('Print') }}</button>
        </div>

        <section class="sheet">
            <header class="header">
                <div>
                    <h1>{{ $t('Money Payment') }}</h1>
                    <p>{{ company.name }}</p>
                </div>
                <div class="meta">
                    <p><strong>{{ $t('Record #') }}</strong> {{ record.id }}</p>
                    <p><strong>{{ $t('Printed At') }}</strong> {{ printedAt }}</p>
                </div>
            </header>

            <section class="summary">
                <div><span>{{ $t('Type') }}</span><strong>{{ record.type }}</strong></div>
                <div><span>{{ $t('Supplier Name') }}</span><strong>{{ record.partner_name }}</strong></div>
                <div><span>{{ $t('Payment Date') }}</span><strong>{{ record.date }}</strong></div>
                <div><span>{{ $t('Amount') }}</span><strong class="num">{{ record.amount }} {{ record.currency }}</strong></div>
            </section>

            <section v-if="record.details?.length" class="block">
                <h2>{{ $t('Transaction Details') }}</h2>
                <table class="table">
                    <tbody>
                        <tr v-for="(item, idx) in record.details" :key="idx">
                            <th>{{ item.label }}</th>
                            <td>{{ item.value }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section v-if="record.settlements?.length" class="block">
                <h2>{{ $t('Settled Invoices') }}</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ $t('Invoice Number') }}</th>
                            <th>{{ $t('Invoice Date') }}</th>
                            <th>{{ $t('Due Date') }}</th>
                            <th>{{ $t('Currency') }}</th>
                            <th class="num">{{ $t('Settlement Amount') }}</th>
                            <th class="num">{{ $t('Withhold Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, idx) in record.settlements" :key="idx">
                            <td>{{ item.invoice_number }}</td>
                            <td>{{ item.invoice_date }}</td>
                            <td>{{ item.invoice_due_date }}</td>
                            <td>{{ item.currency }}</td>
                            <td class="num">{{ Number(item.settlement_amount || 0).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</td>
                            <td class="num">{{ Number(item.withhold_amount || 0).toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4">{{ $t('Total Settlement') }}</th>
                            <th class="num">{{ record.settlement_total }}</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </section>
        </section>

            <section class="signatures">
                <div><div class="line"></div>{{ $t('Prepared By') }}</div>
                <div><div class="line"></div>{{ $t('Reviewed By') }}</div>
                <div><div class="line"></div>{{ $t('Approved By') }}</div>
            </section>
    </div>
</template>

<style scoped>
.print-page { background: #f5f7fb; min-height: 100vh; padding: 24px; color: #1f2937; }
.actions { max-width: 960px; margin: 0 auto 12px auto; display: flex; justify-content: flex-end; }
.btn { border: 1px solid #cbd5e1; background: #fff; padding: 8px 14px; border-radius: 8px; cursor: pointer; }
.sheet { max-width: 960px; margin: 0 auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; }
.header { display: flex; justify-content: space-between; gap: 16px; border-bottom: 2px solid #e5e7eb; padding-bottom: 14px; margin-bottom: 18px; }
.header h1 { margin: 0; font-size: 22px; }
.header p { margin: 6px 0 0 0; color: #64748b; }
.meta p { margin: 0 0 6px 0; font-size: 13px; }
.summary { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 20px; margin-bottom: 18px; }
.summary span { display: block; font-size: 12px; color: #64748b; }
.summary strong { font-size: 14px; }
.block { margin-top: 14px; }
.block h2 { margin: 0 0 8px 0; font-size: 16px; }
.table { width: 100%; border-collapse: collapse; font-size: 13px; }
.table th, .table td { border: 1px solid #e5e7eb; padding: 8px 10px; text-align: start; vertical-align: top; }
.table thead th { background: #f8fafc; }
.table tfoot th { background: #f8fafc; }
.num { text-align: end !important; direction: ltr; }
/* Signature lines belong on the paper copy, not on screen. */
.signatures { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 24px; margin-top: 36px; }
.signatures div { text-align: center; font-size: 12px; color: #64748b; }
.signatures .line { border-top: 1px solid #94a3b8; margin-bottom: 6px; padding-top: 0; height: 34px; }

@media print {
    /* A4 with a margin the printer will honour, instead of whatever the
       browser happens to default to. */
    @page { size: A4; margin: 14mm; }

    .no-print { display: none !important; }

    /* min-height: 100vh is right on screen and wrong on paper: it forces
       the sheet to a full viewport height and prints a trailing blank
       page. */
    .print-page { background: #fff; padding: 0; min-height: 0; }
    .sheet { border: 0; border-radius: 0; max-width: 100%; padding: 0; }

    /* pt, not px: page geometry is physical, so physical units print the
       same everywhere. */
    body { font-size: 11pt; }
    .header h1 { font-size: 16pt; }
    .block h2 { font-size: 12pt; }
    .table { font-size: 10pt; }

    /* A settlement list can run past one page; the column headings have to
       come with it, or page two is a wall of unlabelled numbers. */
    thead { display: table-header-group; }
    tfoot { display: table-footer-group; }
    .table tr { page-break-inside: avoid; }
    .block { page-break-inside: avoid; }
    h1, h2 { page-break-after: avoid; }

    /* Browsers drop backgrounds when printing, so the heading row would
       lose its separation — the heavier rule keeps it. */
    .table thead th { background: transparent; border-bottom: 2px solid #94a3b8; }

    .signatures { page-break-inside: avoid; }
}
</style>
