@extends('layouts.dashboard')
@section('css')
    <link href="{{url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css')}}" rel="stylesheet" type="text/css" />
    @endsection
@section('content')
<div class="row">
    <div class="col-md-12">
        <!--begin::Portlet-->
        <div class="kt-portlet">
            <div class="kt-portlet__head">
                <div class="kt-portlet__head-label">
                    <h3 class="kt-portlet__head-title head-title text-primary">
                        {{__('Saving Account')}}
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
                                {{__('Main Information')}}
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <div class="col-md-12">
                                <label>{{__('Financial Institution Name')}}</label>
                                <div class="kt-input-icon">
                                    <input type="text" name="name" class="form-control" disabled placeholder="{{__('CIB')}}">
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label>{{__('Account Number')}}</label>
                                <div class="kt-input-icon">
                                    <input type="number" min="0" name="account_number" class="form-control"  placeholder="{{__('Account Number')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Select Currency')}} @include('star')    </label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <select name="currency" class="form-control">
                                            <option value="" selected>{{__('Select')}}</option>
                                            <option>EGP</option>
                                            <option>USD</option>
                                            <option>EURO</option>
                                            <option>GBP</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label>{{__('Start Date')}}</label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <input type="text" name="contract_start_date" class="form-control" readonly placeholder="Select date" id="kt_datepicker_2" />
                                        <div class="input-group-append">
                                            <span class="input-group-text">
                                                <i class="la la-calendar-check-o"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Account Balance')}}</label>
                                <div class="kt-input-icon">
                                    <input readonly type="number" min="0" name="account_calance" class="form-control "  placeholder="{{__('Account Balance')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label>{{__('Interest Rate %')}}</label>
                                <div class="kt-input-icon">
                                    <input type="number" min="0" name="interest_rate" class="form-control"  placeholder="{{__('Interest Rate %')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Min Amount')}}</label>
                                <div class="kt-input-icon">
                                    <input type="number" min="0" name="min_amount" class="form-control"  placeholder="{{__('Min Amount')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                        </div>


                    </div>
                </div>
                <x-submitting/>
            </form>

            <!--end::Form-->

        <!--end::Portlet-->
    </div>
</div>



@endsection
@section('js')
    <!--begin::Page Scripts(used by this page) -->
        <script src="{{url('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js')}}" type="text/javascript"></script>
        <script src="{{url('assets/vendors/custom/js/vendors/bootstrap-datepicker.init.js')}}" type="text/javascript"></script>
        <script src="{{url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-datepicker.js')}}" type="text/javascript"></script>
        <script src="{{url('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js')}}" type="text/javascript"></script>
        <script src="{{url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-select.js')}}" type="text/javascript"></script>
    <!--end::Page Scripts -->

@endsection
