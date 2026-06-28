{{-- تخطيط الجدول: أعمدة فترات كثيرة (يومي/أسبوعي) تحتاج nowrap + تمرير أفقي؛ تجنب fixed + break-word على الأرقام --}}
<style>
.ccf-print-wrap .ccf-table-outer {
    overflow: visible;
    max-width: 100%;
    width: 100%;
}

/* التمرير الأفقي هنا فقط حتى تبقى أسماء الصفوف مقروءة ولا تُكسر الأرقام عموديًا */
.ccf-print-wrap .ccf-table-inner {
    overflow-x: auto;
    overflow-y: visible;
    max-width: 100%;
    width: 100%;
    -webkit-overflow-scrolling: touch;
}

.ccf-print-wrap .kt-portlet--mobile {
    overflow: visible !important;
}

.ccf-print-wrap .ccf-table-inner .ccf-consolidated-table {
    table-layout: auto;
    width: max-content;
    min-width: 100%;
    max-width: none !important;
}

.ccf-print-wrap .ccf-consolidated-table th,
.ccf-print-wrap .ccf-consolidated-table td {
    vertical-align: middle !important;
}

/* أسماء البنود والعناوين الطويلة: السماح بالالتفاف */
.ccf-print-wrap .ccf-consolidated-table .is-name-cell,
.ccf-print-wrap .ccf-consolidated-table .main-with-no-child td {
    white-space: normal !important;
    word-break: normal !important;
    overflow-wrap: break-word;
}

/* رؤوس الفترات + خلايا الأرقام: سطر واحد (منع تكديس الأرقام عموديًا) */
.ccf-print-wrap .ccf-consolidated-table thead th.max-w-weeks,
.ccf-print-wrap .ccf-consolidated-table td.sub-numeric-bg {
    white-space: nowrap !important;
    word-break: normal !important;
    font-variant-numeric: tabular-nums;
}

.ccf-print-wrap .ccf-consolidated-table thead th.max-w-weeks {
    min-width: 4.75rem;
    max-width: none !important;
}

.ccf-print-wrap .ccf-consolidated-table tbody td.sub-numeric-bg {
    min-width: 3.5rem;
    padding: 8px 10px !important;
}

.expandable-percentage-input { max-width: 75px !important; min-width: 75px !important; text-align: center !important; }
.expandable-amount-input { max-width: 150px !important; min-width: 150px !important; text-align: center !important; }
.ml-son { margin-left: 10px; font-weight: 400; }
.bg-lighter, .bg-lighter * { background-color: #E2EFFE !important; color: black !important; }
.is-sub-row.is-total-row td.sub-numeric-bg,
.is-sub-row.is-total-row td.sub-text-bg { background-color: #087383 !important; color: white !important; }
.parent-tr td { border: 1px solid #E2EFFE !important; }
.main-with-no-child, .main-with-no-child td, .main-with-no-child th {
    background-color: #046187 !important;
    color: white !important;
    font-weight: bold;
}
.is-sub-row td.sub-numeric-bg, .is-sub-row td.sub-text-bg {
    border: 1.5px solid white !important;
    background-color: #f7f8fa !important;
    color: black !important;
    font-weight: 400 !important;
}
.sub-numeric-bg { text-align: center; }
th.dtfc-fixed-left { background-color: #074FA4 !important; color: white !important; }
.header-tr { background-color: #046187 !important; }
.header-tr th { background-color: #046187 !important; color: white !important; font-weight: bold; }
.ccf-section-title td { font-size: 1rem !important; padding: 10px 12px !important; }
.ccf-actions .btn { margin-inline-end: 8px; margin-bottom: 8px; }

.max-w-grand-total {
    width: 7.5rem !important;
    max-width: none !important;
    min-width: 7rem !important;
    white-space: nowrap !important;
}
.max-w-classes-name {
    width: 14rem !important;
    max-width: min(22vw, 20rem) !important;
    min-width: 10rem !important;
}
.max-w-classes-expand {
    width: 48px !important;
    max-width: 48px !important;
    min-width: 48px !important;
}

@media print {
    .no-print { display: none !important; }
    .ccf-print-wrap .ccf-table-inner {
        overflow-x: visible;
    }
    .ccf-print-wrap .ccf-consolidated-table {
        width: 100% !important;
        max-width: 100% !important;
        font-size: 9px;
    }
    .ccf-print-wrap .ccf-consolidated-table thead th.max-w-weeks,
    .ccf-print-wrap .ccf-consolidated-table td.sub-numeric-bg {
        white-space: normal !important;
        min-width: 0 !important;
    }
}
</style>
