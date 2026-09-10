<script setup>
/**
 * One print sheet for every Statement report.
 *
 * The reports differ in their columns, not in how they should look on
 * paper, so they all render through here: the controller hands over the
 * column headings and the rows, exactly as it hands them to the Excel
 * export. That shared source is the point — the export has always run
 * the query UNPAGINATED, so printing through the same payload prints the
 * whole report rather than the 25 rows that happened to be on screen,
 * and it cannot drift out of step with the workbook.
 */
const props = defineProps({
    company: Object,
    title: String,
    meta: { type: Array, default: () => [] },      // [{ label, value }]
    headings: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },      // [{ <heading>: value }]
    numericHeadings: { type: Array, default: () => [] },
    totals: { type: Object, default: null },       // { <heading>: value }
    printedAt: String,
});

/* `window` is not in the template's scope under <script setup>. */
function triggerPrint() {
    window.print();
}

const isNumeric = (heading) => props.numericHeadings.includes(heading);

/* The payload carries RAW numbers, deliberately: the Excel export shares
   it, and a workbook needs real numbers for its SUM formulas and number
   formats. Paper needs the opposite - "1234.5" is not a figure anyone
   reads - so the money formatting happens here, at render, rather than in
   the payload both sides read. */
function cell(row, heading) {
    const value = row[heading];

    if (value === null || value === undefined || value === '') {
        return '';
    }

    if (isNumeric(heading) && typeof value === 'number') {
        return value.toLocaleString('en-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    return value;
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
                    <h1>{{ title }}</h1>
                    <p>{{ company.name }}</p>
                </div>
                <div class="meta-right">
                    <p><strong>{{ $t('Printed At') }}</strong> {{ printedAt }}</p>
                    <p><strong>{{ $t('Results') }}</strong> {{ rows.length }}</p>
                </div>
            </header>

            <section v-if="meta.length" class="criteria">
                <div v-for="(item, i) in meta" :key="i">
                    <span>{{ item.label }}</span><strong>{{ item.value }}</strong>
                </div>
            </section>

            <table class="table">
                <thead>
                    <tr>
                        <th v-for="h in headings" :key="h" :class="{ num: isNumeric(h) }">{{ h }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in rows" :key="i">
                        <td v-for="h in headings" :key="h" :class="{ num: isNumeric(h) }">{{ cell(row, h) }}</td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td :colspan="headings.length" class="empty">{{ $t('No Data Found') }}</td>
                    </tr>
                </tbody>
                <tfoot v-if="totals">
                    <tr>
                        <th v-for="h in headings" :key="h" :class="{ num: isNumeric(h) }">{{ totals[h] ?? '' }}</th>
                    </tr>
                </tfoot>
            </table>

            <!-- A report handed over on paper needs somewhere to sign — the
                 same three lines every other printout in this app carries. -->
            <section class="signatures">
                <div><div class="line"></div>{{ $t('Prepared By') }}</div>
                <div><div class="line"></div>{{ $t('Reviewed By') }}</div>
                <div><div class="line"></div>{{ $t('Approved By') }}</div>
            </section>
        </section>
    </div>
</template>

<style scoped>
.print-page { background: #f5f7fb; min-height: 100vh; padding: 24px; color: #1f2937; }
.actions { max-width: 1200px; margin: 0 auto 12px auto; display: flex; justify-content: flex-end; }
.btn { border: 1px solid #cbd5e1; background: #fff; padding: 8px 14px; border-radius: 8px; cursor: pointer; }
.sheet { max-width: 1200px; margin: 0 auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; }
.header { display: flex; justify-content: space-between; gap: 16px; border-bottom: 2px solid #e5e7eb; padding-bottom: 14px; margin-bottom: 14px; }
.header h1 { margin: 0; font-size: 22px; }
.header p { margin: 6px 0 0 0; color: #64748b; }
.meta-right p { margin: 0 0 6px 0; font-size: 13px; }
.criteria { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px 20px; margin-bottom: 16px; }
.criteria span { display: block; font-size: 12px; color: #64748b; }
.criteria strong { font-size: 13px; }
.table { width: 100%; border-collapse: collapse; font-size: 12px; }
.table th, .table td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: start; vertical-align: top; }
.table thead th { background: #f8fafc; }
.table tfoot th { background: #f8fafc; }
.num { text-align: end !important; direction: ltr; white-space: nowrap; }
.empty { text-align: center; color: #64748b; padding: 24px; }
/* Signature lines belong on the paper copy, not on screen. */
.signatures { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 24px; margin-top: 36px; }
.signatures div { text-align: center; font-size: 12px; color: #64748b; }
.signatures .line { border-top: 1px solid #94a3b8; margin-bottom: 6px; padding-top: 0; height: 34px; }

@media print {
    /* Landscape: a statement is a wide table, and portrait shears the
       right-hand columns off the page. */
    @page { size: A4 landscape; margin: 10mm; }

    .no-print { display: none !important; }

    /* min-height: 100vh is right on screen and wrong on paper — it forces
       a full viewport height and prints a trailing blank page. */
    .print-page { background: #fff; padding: 0; min-height: 0; }
    .sheet { border: 0; border-radius: 0; max-width: 100%; padding: 0; }

    /* pt, not px: page geometry is physical. */
    body { font-size: 9pt; }
    .header h1 { font-size: 15pt; }
    .table { font-size: 8pt; }

    /* A statement runs to many pages: the column headings have to repeat
       on each one, or page two is a wall of unlabelled numbers, and the
       totals row has to stay whole. */
    thead { display: table-header-group; }
    tfoot { display: table-footer-group; }
    .table tr { page-break-inside: avoid; }
    h1 { page-break-after: avoid; }
    .criteria { page-break-inside: avoid; page-break-after: avoid; }

    /* Browsers drop backgrounds when printing, so the heading row would
       lose its separation — the heavier rule keeps it. */
    .table thead th { background: transparent; border-bottom: 2px solid #94a3b8; }
    .table tfoot th { background: transparent; border-top: 2px solid #94a3b8; font-weight: 700; }

    .signatures { page-break-inside: avoid; }
}
</style>
