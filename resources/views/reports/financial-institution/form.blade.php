@extends('layouts.dashboard')
@section('css')
@php
	use App\Models\FinancialInstitutionAccount;
@endphp
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>

    .money-flow-dark .kt-portlet .kt-portlet__head {
        border-bottom-color: #1490a833 !important;
    }

    .money-flow-dark label {
        text-align: left !important;
    }

    .money-flow-dark .width-8 {
        max-width: initial !important;
        width: 8% !important;
        flex: initial !important;
    }

    .money-flow-dark .width-10 {
        max-width: initial !important;
        width: 10% !important;
        flex: initial !important;
    }

    .money-flow-dark .width-12 {
        max-width: initial !important;
        width: 13.5% !important;
        flex: initial !important;
    }

    .money-flow-dark .width-45 {
        max-width: initial !important;
        width: 45% !important;
        flex: initial !important;
    }

    .money-flow-dark .kt-portlet {}

</style>
@endsection
@section('sub-header')
{{ __('Financial Institutions Form') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">
        <!--begin::Portlet-->

        <form onsubmit="this.querySelector('button[type=submit]').disabled = true;" method="post" action="{{ isset($model) ?  route('update.financial.institutions',['company'=>$company->id,'financialInstitution'=>$model->id]) :route('store.financial.institutions',['company'=>$company->id]) }}" class="kt-form kt-form--label-right">
            <input id="js-in-edit-mode" type="hidden" name="in_edit_mode" value="{{ isset($model) ? 1 : 0 }}">
            <input id="js-money-received-id" type="hidden" name="id" value="{{ isset($model) ? $model->id : 0 }}">
            @csrf
            @if(isset($model))
            @method('put')
            @endif

            <div class="row">
                <div class="col-lg-12">
                    <!--begin::Portlet-->
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title head-title text-primary">
                                    <x-sectionTitle :title="__((isset($model) ? 'Edit' : 'Add') . ' Financial Institution')"></x-sectionTitle>
                                </h3>
                            </div>
                        </div>
                    </div>
                    <!--begin::Form-->
                    <form class="kt-form kt-form--label-right">
                        <div class="kt-portlet">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title head-title text-primary">
                                        <x-sectionTitle :title="__('Financial Institution Type')"></x-sectionTitle>
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="form-group row">
                                    <div class="col-lg-3">
                                        <label>{{__('Select Financial Institution Type')}} @include('star') </label>
                                        <div class="kt-input-icon">
                                            <div class="input-group date">
                                                <select name="type" class="form-control select2-select" data-live-search="true" data-actions-box="true" id="type">
                                                    {{-- <option value="">{{__('Select')}}</option> --}}
                                                    <option @if(isset($model) && $model->isBank() ) selected @endif value="bank">{{__('Banks')}}</option>
                                                    {{-- <option @if(isset($model) && $model->isLeasingCompanies() ) selected @endif value="leasing_companies">{{__('Leasing Companies')}}</option> --}}
                                                    {{-- <option @if(isset($model) && $model->isFactoringCompanies() ) selected @endif value="factoring_companies">{{__('Factoring Companies')}}</option> --}}
                                                    {{-- <option @if(isset($model) && $model->isMortgageCompanies() ) selected @endif value="mortgage_companies">{{__('Mortgage Companies')}}</option> --}}
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-5 bank_class hidden">
								                                        <label>{{__('Select Bank ')}} @include('star') </label>
                                        <div class="kt-input-icon">
                                            <div class="input-group date">
                                                <select name="bank_id" data-live-search="true" data-actions-box="true" class="form-control select2-select ">
                                                    {{-- <option value="">{{__('Select')}}</option> --}}
                                                    @foreach($banks as $bankId=>$bankName)
                                                    <option @if(isset($model) && $bankId==$model->bank_id ) selected @endif value="{{ $bankId }}">{{ $bankName}}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-5 financial-institution-name">
                                        <label>{{__('Financial Institution Name')}} @include('star')</label>
                                        <div class="kt-input-icon">
                                            <input value="{{ isset($model) ? $model->getName() : null  }}" type="text" name="name" class="form-control" placeholder="{{__('Financial Institution Name')}}">
                                        </div>
                                    </div>


                                    <div class="col-lg-4">
                                        <label>{{__('Branch Name')}} @include('star')</label>
                                        <div class="kt-input-icon">
                                            <input required	 value="{{ old('branch_name',isset($model) ? $model->getBranchName() : null)  }}" type="text" name="branch_name" class="form-control" placeholder="{{__('Branch Name')}}">
                                        </div>
                                    </div>



                                </div>
                            </div>
                        </div>
                        <div class="kt-portlet hidden banks_view">
                            <div class="kt-portlet__head">
                                <div class="kt-portlet__head-label">
                                    <h3 class="kt-portlet__head-title head-title text-primary">
                                        <x-sectionTitle :title="__('Company Account Information')"></x-sectionTitle>
                                    </h3>
                                </div>
                            </div>
                            <div class="kt-portlet__body">
                                <div class="form-group row">
                                    <div class="col-lg-4">
                                        <label>{{__('Company Account Number')}} @include('star')</label>
                                        <div class="kt-input-icon">
                                            <input required type="text" value="{{ old('company_account_number',isset($model) ? $model->getCompanyAccountNumber() : null)   }}" name="company_account_number" class="form-control" placeholder="{{__('Company Account Number')}}">
                                        </div>
                                    </div>

                                



                             
                            </div>

                            @if(!isset($model))
                            <div class="form-group row" style="flex:1;">
                                <div class="col-md-12 mt-3">


                                    <div class="" style="width:100%">

                                        <div id="m_repeater_0" class="cash-and-banks-repeater">
                                            <div class="form-group  m-form__group row  ">
                                                <div data-repeater-list="accounts" class="col-lg-12">
												@php
													$accounts =  old('accounts',$model->accounts ?? [null]) ; 
													$accounts = is_array($accounts) ? fillObjectFromArray($accounts,FinancialInstitutionAccount::class) : $accounts;
												@endphp
                                                    @foreach( $accounts as $account)
                                                    @include('reports.financial-institution.repeater' , [
                                             	       'account'=>$account,
                                                    ])

                                                    @endforeach
                                                   






                                                </div>
                                            </div>
                                            <div class="m-form__group form-group row">

                                                <div class="col-lg-6">
                                                    <div data-repeater-create="" class="btn btn btn-sm btn-success m-btn m-btn--icon m-btn--pill m-btn--wide {{__('right')}}" id="add-row">
                                                        <span>
                                                            <i class="fa fa-plus"> </i>
                                                            <span>
                                                                {{ __('Add') }}
                                                            </span>
                                                        </span>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>


                                    </div>



                                </div>

                            </div>
                            @endif


                        </div>
                </div>



                <x-submitting />
        </form>

        <!--end::Form-->

        <!--end::Portlet-->
    </div>
</div>
</div>

@endsection
@section('js')
<!--begin::Page Scripts(used by this page) -->
<script src="{{ url('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/custom/js/vendors/bootstrap-datepicker.init.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-datepicker.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-select.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/vendors/general/jquery.repeater/src/lib.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/general/jquery.repeater/src/jquery.input.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/vendors/general/jquery.repeater/src/repeater.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/form-repeater.js') }}" type="text/javascript"></script>
<script>
    $(document).on('change', '#type', function() {
        if ($(this).val() == 'bank') {
            $('.banks_view').removeClass('hidden');
            $('.bank_class').removeClass('hidden')
            $('.financial-institution-name').addClass('hidden')
        } else {
            $('.banks_view').addClass('hidden');
            $('.bank_class').addClass('hidden');
            $('.financial-institution-name').removeClass('hidden')


        }
    });
    $('#type').trigger('change')

</script>

<script>
    $(document).find('.datepicker-input').datepicker({
        dateFormat: 'mm-dd-yy'
        , autoclose: true
    })
    $('#m_repeater_0').repeater({
        initEmpty: false
        , isFirstItemUndeletable: true
        , defaultValues: {
            'text-input': 'foo'
        },

        show: function() {
            $(this).slideDown();
            $('input.trigger-change-repeater').trigger('change')
            $(document).find('.datepicker-input').datepicker({
                dateFormat: 'mm-dd-yy'
                , autoclose: true
            })
            $(this).find('.only-month-year-picker').each(function(index, dateInput) {
                reinitalizeMonthYearInput(dateInput)
            });
            $('input:not([type="hidden"])').trigger('change');
            $(this).find('.dropdown-toggle').remove();
            $(this).find('select.repeater-select').selectpicker("refresh");

        },

        hide: function(deleteElement) {
            if ($('#first-loading').length) {
                $(this).slideUp(deleteElement, function() {

                    deleteElement();
                    //   $('select.main-service-item').trigger('change');
                });
            } else {
                if (confirm('Are you sure you want to delete this element?')) {
                    $(this).slideUp(deleteElement, function() {

                        deleteElement();
                        $('input.trigger-change-repeater').trigger('change')

                    });
                }
            }
        }
    });

</script>

<script>
    let oldValForInputNumber = 0;
    $('input:not([placeholder]):not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([readonly]):not(.exclude-text):not(.date-input)').on('focus', function() {
        oldValForInputNumber = $(this).val();
        $(this).val('')
    })
    $('input:not([placeholder]):not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([readonly]):not(.exclude-text):not(.date-input)').on('blur', function() {

        if ($(this).val() == '') {
            $(this).val(oldValForInputNumber)
        }
    })

    $(document).on('change', 'input:not([placeholder])[type="number"],input:not([placeholder])[type="password"],input:not([placeholder])[type="text"],input:not([placeholder])[type="email"],input:not(.exclude-text)', function() {
        if (!$(this).hasClass('exclude-text')) {
            let val = $(this).val()
            val = number_unformat(val)
            $(this).parent().find('input[type="hidden"]:not([name="_token"])').val(val)

        }
    })

</script>
@endsection
