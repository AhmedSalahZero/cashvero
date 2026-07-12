@extends('layouts.dashboard')
@section('css')
<style>
    .money-flow-dark label {
        text-align: left !important;
    }
</style>
@endsection
@section('sub-header')
{{ __('Leasing Company Form') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">
        <form method="post"
            action="{{ isset($model)
                ? route('leasing.companies.update', ['company' => $company->id, 'leasingCompany' => $model->id])
                : route('leasing.companies.store', ['company' => $company->id]) }}"
            class="kt-form kt-form--label-right"
            onsubmit="this.querySelector('button[type=submit]').disabled = true;">
            @csrf
            @if(isset($model))
                @method('put')
            @endif

            <div class="kt-portlet">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title head-title text-primary">
                            <x-sectionTitle :title="__(isset($model) ? 'Edit Leasing Company' : 'Add Leasing Company')"></x-sectionTitle>
                        </h3>
                    </div>
                </div>
                <div class="kt-portlet__body">
                    <div class="row">
                        <div class="col-md-6">
                            <label>{{ __('Leasing Company') }} @include('star')</label>
                            <input type="text" name="name" class="form-control" required maxlength="255" value="{{ old('name', isset($model) ? $model->getName() : '') }}" placeholder="{{ __('Leasing Company') }}">
                        </div>
                    </div>
                </div>
            </div>

            <x-submitting :backTo="route('view.financial.institutions', ['company' => $company->id, 'active' => 'leasing_companies'])" />
        </form>
    </div>
</div>
</div>
@endsection
