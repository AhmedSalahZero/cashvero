@extends('layouts.dashboard')
@push('css')
<x-styles.commons></x-styles.commons>
@endpush
@section('css')
@include('reports.consolidated_cash_flow.styles')
@endsection
@section('sub-header')
<x-main-form-title :id="'ccf-main-title'" :class="''">{{ $title }}</x-main-form-title>
@endsection

@php
    $dir = app()->getLocale() === 'ar' ? 'rtl' : 'ltr';
    $weekKeys = array_keys($weeks);
    $exportPayload = [
        'weekKeys' => $weekKeys,
        'weekLabels' => $weeks,
        'banksSection' => $banksSection,
        'contractsSection' => $contractsSection,
        'companyUnallocatedCashOut' => $companyUnallocatedCashOut ?? [],
        'grandTotal' => $grandTotal,
        'currencyName' => $currencyName,
        'title' => $title,
    ];
@endphp

@section('content')
<div class="money-flow-dark ccf-print-wrap" dir="{{ $dir }}">
    <div class="row no-print ccf-actions">
        <div class="col-md-12">
            <a href="{{ route('reports.consolidated-cash-flow.index', ['company' => $company->id]) }}" class="btn btn-secondary">{{ __('Back') }}</a>
            <button type="button" id="ccf-export-xlsx" class="btn btn-primary">{{ __('Export Excel') }}</button>
            <button type="button" onclick="window.print()" class="btn btn-outline-primary">{{ __('Print') }}</button>
        </div>
    </div>

    <p class="mb-3"><strong>{{ __('Currency') }}:</strong> {{ $currencyName }} — <strong>{{ __('Interval') }}:</strong> {{ $reportInterval }}</p>

    @include('reports.consolidated_cash_flow._table', [
        'weekKeys' => $weekKeys,
        'weeks' => $weeks,
        'dates' => $dates,
        'reportInterval' => $reportInterval,
        'banksSection' => $banksSection,
        'contractsSection' => $contractsSection,
        'companyUnallocatedCashOut' => $companyUnallocatedCashOut ?? [],
        'grandTotal' => $grandTotal,
    ])
</div>

<script type="application/json" id="ccf-export-json">{!! json_encode($exportPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP) !!}</script>
<script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
<script>
(function () {
    var btn = document.getElementById('ccf-export-xlsx');
    if (!btn || typeof XLSX === 'undefined') return;
    btn.addEventListener('click', function () {
        var el = document.getElementById('ccf-export-json');
        if (!el) return;
        var data = JSON.parse(el.textContent);
        var wsData = [];
        var merges = [];
        var r = 0;
        function pushRow(row) { wsData.push(row); return r++; }
        function fmtNum(n) { return Number(n) || 0; }
        pushRow([data.title]);
        merges.push({ s: { r: 0, c: 0 }, e: { r: 0, c: Math.max(1, data.weekKeys.length + 1) } });
        r++;
        pushRow([]);
        r++;
        pushRow(['{{ __('Section A — Company level (Cash & Banks Balance)') }}'].concat(data.weekKeys.map(function () { return ''; })));
        merges.push({ s: { r: r - 1, c: 0 }, e: { r: r - 1, c: Math.max(1, data.weekKeys.length + 1) } });
        var headerRow = ['{{ __('Row') }}'].concat(data.weekKeys.map(function (k) { return (data.weekLabels && data.weekLabels[k]) ? String(data.weekLabels[k]) : k; }));
        pushRow(headerRow);
        Object.keys(data.banksSection || {}).forEach(function (label) {
            var row = [label];
            data.weekKeys.forEach(function (wk) {
                row.push(fmtNum((data.banksSection[label].total || {})[wk]));
            });
            pushRow(row);
        });
        pushRow([]);
        r++;
        (data.contractsSection || []).forEach(function (block) {
            pushRow([block.contract_name + ' (' + block.contract_code + ')'].concat(data.weekKeys.map(function () { return ''; })));
            merges.push({ s: { r: r - 1, c: 0 }, e: { r: r - 1, c: Math.max(1, data.weekKeys.length + 1) } });
            pushRow(headerRow);
            pushRow(['{{ __('Total Cash Inflow') }}'].concat(data.weekKeys.map(function (wk) { return fmtNum((block.cash_inflow || {})[wk]); })));
            pushRow(['{{ __('Total Cash Outflow') }}'].concat(data.weekKeys.map(function (wk) { return fmtNum((block.cash_outflow || {})[wk]); })));
            pushRow(['{{ __('Net Cash (+/-)') }}'].concat(data.weekKeys.map(function (wk) { return fmtNum((block.net_cash || {})[wk]); })));
            pushRow([]);
            r++;
        });
        pushRow(['{{ __('Company cash out (unallocated)') }}'].concat(data.weekKeys.map(function (wk) { return fmtNum((data.companyUnallocatedCashOut || {})[wk]); })));
        pushRow([]);
        r++;
        pushRow(['{{ __('Section C — Grand total (contracts only)') }}'].concat(data.weekKeys.map(function () { return ''; })));
        merges.push({ s: { r: r - 1, c: 0 }, e: { r: r - 1, c: Math.max(1, data.weekKeys.length + 1) } });
        pushRow(headerRow);
        pushRow(['{{ __('Total Cash Inflow') }}'].concat(data.weekKeys.map(function (wk) { return fmtNum((data.grandTotal.cash_inflow || {})[wk]); })));
        pushRow(['{{ __('Total Cash Outflow') }}'].concat(data.weekKeys.map(function (wk) { return fmtNum((data.grandTotal.cash_outflow || {})[wk]); })));
        pushRow(['{{ __('Net Cash (+/-)') }}'].concat(data.weekKeys.map(function (wk) { return fmtNum((data.grandTotal.net_cash || {})[wk]); })));
        pushRow(['{{ __('Accumulated Net Cash (+/-)') }}'].concat(data.weekKeys.map(function (wk) { return fmtNum((data.grandTotal.accumulated_net || {})[wk]); })));
        var ws = XLSX.utils.aoa_to_sheet(wsData);
        ws['!merges'] = merges;
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Consolidated');
        XLSX.writeFile(wb, 'consolidated-cash-flow.xlsx');
    });
})();
</script>
@endsection
