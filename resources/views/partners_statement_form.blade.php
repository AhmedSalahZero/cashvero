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
{{ __('Partner Statement') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">



        <!--begin::Form-->
        <form class="kt-form kt-form--label-right" method="POST" action="{{ route('result.partners.statement',['company'=>$company->id ]) }}" enctype="multipart/form-data">
            @csrf
            <div class="kt-portlet" style="overflow-x:hidden">
                <div class="kt-portlet__body">
                    <div class="form-group row">
                        <div class="col-md-2 mb-4">
                            <label>{{ __('Start Date') }} <span class="multi_selection"></span> </label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <input required type="date" class="form-control" name="start_date" value="{{ now() }}">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-2 mb-4">
                            <label>{{ __('End Date') }} <span class="multi_selection"></span> </label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <input required type="date" class="form-control" name="end_date" value="{{ now()->addYear() }}">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-4">
                            <label>{{ __('Currency') }} </label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select id="invoice-currency-id" data-live-search="true" data-actions-box="true" name="currency" required class="form-control  kt-bootstrap-select select2-select kt_bootstrap_select ajax-currency-name">
                                        @foreach(getCurrency() as $currency=>$currencyName)
                                        <option value="{{ $currency }}">{{ touppercase($currencyName) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-4">
                            <label>{{ __('Partner Type') }} </label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select data-remove-select="1" data-live-search="true" data-actions-box="true" name="partner_type" id="partner_type" required class="form-control  kt-bootstrap-select select2-select kt_bootstrap_select">
                                        @foreach($partnerTypes as $id=>$title)
                                        <option value="{{ $id }}">{{ $title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>


                        <div class="col-md-3 mb-4">
                            <label>{{ __('Partners') }} </label>
                            <div class="kt-input-icon">
                                <div class="input-group date">
                                    <select multiple data-live-search="true" data-actions-box="true" name="partner_id[]" id="customer_name" required class="form-control  kt-bootstrap-select select2-select kt_bootstrap_select">

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
<script src="/custom/money-receive.js"></script>
<script>
$('select#partner_type').trigger('change')
</script>
@endsection
