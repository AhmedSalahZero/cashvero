@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
    .kt-portlet {
        overflow: visible !important;
    }

</style>
@endsection
@section('sub-header')
{{ __('Bank Statement') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">


        <!--begin::Form-->
        <form class="kt-form kt-form--label-right" method="get" action="{{ route('result.bank.statement',['company'=>$company->id ]) }}" enctype="multipart/form-data">
            @csrf
            <div class="kt-portlet" style="overflow-x:hidden">
                <div class="kt-portlet__body">
                    <div class="form-group row">
                        <div class="col-md-3 mb-4">
                            <label>{{ __('Start Date') }} <span class="multi_selection"></span> </label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <input required type="date" class="form-control" name="start_date" value="{{ now() }}">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-4">
                            <label>{{ __('End Date') }} <span class="multi_selection"></span> </label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <input required type="date" class="form-control" name="end_date" value="{{ now()->addYear() }}">
                                </div>
                            </div>
                        </div>





                        <div class="col-md-3 mb-4">
                            <label>{{ __('Currency') }} </label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select js-when-change-trigger-change-account-type data-live-search="true" data-actions-box="true" name="currency" required class="form-control current-currency  kt-bootstrap-select select2-select kt_bootstrap_select ajax-currency-name">
                                        @foreach(getCurrency() as $currency=>$currencyName)
                                        <option @if($currency == $selectedCurrency)  selected @endif value="{{ $currency }}">{{ touppercase($currencyName) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-5 width-45">
                            <label>{{__('Bank')}} @include('star')</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">

                                    <select js-when-change-trigger-change-account-type data-financial-institution-id name="financial_institution_id" class="form-control ">
                                        @foreach($financialInstitutionBanks as $index=>$financialInstitutionBank)
                                        <option value="{{ $financialInstitutionBank->id }}" {{ isset($model) && $model->getCashInBankReceivingBankId() == $financialInstitutionBank->id ? 'selected' : '' }}>{{ $financialInstitutionBank->getName() }}</option>
                                        @endforeach
                                    </select>

                                </div>
                            </div>
                        </div>


                        <div class="col-md-2 width-12">
                            <label>{{__('Account Type')}} @include('star')</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select required name="account_type" class="form-control js-update-account-number-based-on-account-type">
                                        <option value="" selected>{{__('Select')}}</option>
                                        @foreach($accountTypes as $index => $accountType)
                                        <option @if($selectedAccountTypeName == $accountType->getModelName())  selected @endif value="{{ $accountType->id }}" @if(isset($model) && $model->getCashInBankAccountTypeId() == $accountType->id) selected @endif>{{ $accountType->getName() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-2 width-12">
                            <label>{{__('Account Number')}} @include('star')</label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select required data-current-selected="{{ isset($model) ? $model->getCashInBankAccountNumber(): 0 }}" name="account_number" class="form-control js-account-number">
                                        <option value="" selected>{{__('Select')}}</option>
                                    </select>
                                </div>
                            </div>
                        </div>








                        <x-submitting />







                    </div>

                </div>
          
            </div>





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
<script src="/custom/money-receive.js">

</script>

@endsection
