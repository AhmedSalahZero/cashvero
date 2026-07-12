@props([
    'active' => 'statement',
    'company',
])
<div class="kt-portlet kt-portlet--tabs mb-4">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-toolbar">
            <ul class="nav nav-tabs nav-tabs-space-lg nav-tabs-line nav-tabs-bold nav-tabs-line-3x nav-tabs-line-brand" role="tablist">
                <li class="nav-item">
                    <a class="nav-link {{ $active === 'statement' ? 'active' : '' }}"
                        href="{{ route('view.factoring.statement', ['company' => $company->id]) }}">
                        <i class="fa fa-file-invoice"></i> {{ __('Factoring Statement') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $active === 'charges' ? 'active' : '' }}"
                        href="{{ route('view.factoring.charges.statement', ['company' => $company->id]) }}">
                        <i class="fa fa-receipt"></i> {{ __('Factoring Charges Statement') }}
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>
