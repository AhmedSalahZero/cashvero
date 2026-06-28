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
                        {{__('Outstanding Customers Invoices')}}
                    </h3>
                </div>
            </div>
        </div>
            <!--begin::Form-->
            <form class="kt-form kt-form--label-right" method="POST" action={{ route('customerInvoiceTest.update',[$customerInvoiceTest])}}   enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="kt-portlet">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title head-title text-primary">
                                {{__('Outstanding Customers Invoices')}}
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label>{{__('Customer Name')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="text" name="customer_name" value="{{$customerInvoiceTest->customer_name}}" class="form-control" placeholder="{{__('Customer Name')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Business Sector')}}</label>
                                <div class="kt-input-icon">
                                    <input type="text" name="business_sector" value="{{$customerInvoiceTest->business_sector}}" class="form-control" placeholder="{{__('Business Sector')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="kt-portlet">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title head-title text-primary">
                                {{__('Invoice Information')}}
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <div class="col-md-3">
                                <label>{{__('Invoice Number')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="text" name="invoice_number" value="{{$customerInvoiceTest->invoice_number}}" class="form-control" placeholder="{{__('Invoice Number')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Invoice Date')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <input type="date" name="invoice_date" value="{{$customerInvoiceTest->invoice_date}}" class="form-control"  placeholder="Select date" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Due Within')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="due_within" value="{{$customerInvoiceTest->due_within}}"  class="form-control" placeholder="{{__('Due Within')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Due Date')}} </label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <input type="date" name="invoice_due_date" value="{{$customerInvoiceTest->invoice_due_date}}" class="form-control" placeholder="Select date" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-3">
                                <label>{{__('Contract Code/Name')}}</label>
                                <div class="kt-input-icon">
                                    <input type="text" name="contract_code" value="{{$customerInvoiceTest->contract_code}}" class="form-control" placeholder="{{__('Contract Code/Name')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Contract Date')}}</label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <input type="date" name="contract_date" value="{{$customerInvoiceTest->contract_date}}" class="form-control" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Customer Purchase Order Number')}}</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="purchase_order_number" value="{{$customerInvoiceTest->purchase_order_number}}"  class="form-control" placeholder="{{__('Purchase Order Number')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Purchase Order Date')}}</label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <input type="date" name="purchase_order_date" value="{{$customerInvoiceTest->purchase_order_date}}" class="form-control"  placeholder="Select date" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-3">
                                <label>{{__('Internal Sales Order Number')}}</label>
                                <div class="kt-input-icon">
                                    <input type="text" name="sales_order_number" value="{{$customerInvoiceTest->sales_order_number}}" class="form-control" placeholder="{{__('Internal Sales Order Number')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Internal Sales Order Date')}}</label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <input type="date" name="sales_order_date" value="{{$customerInvoiceTest->sales_order_date}}" class="form-control"  placeholder="Select date" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Sales Person Name')}}</label>
                                <div class="kt-input-icon">
                                    <input type="text" name="sales_person_name" value="{{$customerInvoiceTest->sales_person_name}}"  class="form-control" placeholder="{{__('Sales Person Name')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Sales Commission Rate %')}}</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="sales_person_rate" value="{{$customerInvoiceTest->sales_person_rate}}"  class="form-control" placeholder="{{__('Sales Commission Rate %')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="kt-portlet">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title head-title text-primary">
                                {{__('Invoice Value')}}
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label>{{__('Invoice Amount')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="invoice_amount" value="{{$customerInvoiceTest->invoice_amount}}" id="invoice_amount" class="form-control" placeholder="{{__('Invoice Amount')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Select Currency')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <select name="currency"  class="form-control">
                                            <option value="" selected>{{__('Select')}}</option>
                                            <option value="EGP"  {{$customerInvoiceTest->currency == 'EGP' ? 'selected' : ''}}>EGP</option>
                                            <option value="USD"  {{$customerInvoiceTest->currency == 'USD' ? 'selected' : ''}}>USD</option>
                                            <option value="EURO" {{$customerInvoiceTest->currency == 'EURO' ? 'selected' : ''}}>EURO</option>
                                            <option value="GBP"  {{$customerInvoiceTest->currency == 'GBP' ? 'selected' : ''}}>GBP</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label>{{__('Advance Payment Amount')}}</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="advance_payment_amount" value="{{$customerInvoiceTest->advance_payment_amount}}" id="advance_payment_amount" class="form-control" placeholder="{{__('Advance Payment Amount')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('VAT Amount')}}</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="vat_amount" value="{{$customerInvoiceTest->vat_amount}}" id="vat_amount" class="form-control" placeholder="{{__('VAT Amount')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-3">
                                <label>{{__('Deduction Name')}} </label>
                                <div class="kt-input-icon">
                                    <input type="text" name="deduction_id_one" value="{{$customerInvoiceTest->deduction_id_one}}"  class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Deduction Amount')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="deduction_amount_one" value="{{$customerInvoiceTest->deduction_amount_one}}"  class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Deduction Name')}} </label>
                                <div class="kt-input-icon">
                                    <input type="text" name="deduction_id_two" value="{{$customerInvoiceTest->deduction_id_two}}"  class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Deduction Amount')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="deduction_amount_two" value="{{$customerInvoiceTest->deduction_amount_two}}"  class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-3">
                                <label>{{__('Deduction Name')}} </label>
                                <div class="kt-input-icon">
                                    <input type="text" name="deduction_id_three" value="{{$customerInvoiceTest->deduction_id_three}}"  class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Deduction Amount')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="deduction_amount_three" value="{{$customerInvoiceTest->deduction_amount_three}}"  class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Deduction Name')}} </label>
                                <div class="kt-input-icon">
                                    <input type="text" name="deduction_id_four" value="{{$customerInvoiceTest->deduction_id_four}}"  class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Deduction Amount')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="deduction_amount_four" value="{{$customerInvoiceTest->deduction_amount_four}}"  class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-3">
                                <label>{{__('Deduction Name')}} </label>
                                <div class="kt-input-icon">
                                    <input type="text" name="deduction_id_five" value="{{$customerInvoiceTest->deduction_id_five}}"  class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Deduction Amount')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="deduction_amount_five" value="{{$customerInvoiceTest->deduction_amount_five}}"  class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Deduction Name')}} </label>
                                <div class="kt-input-icon">
                                    <input type="text" name="deduction_id_six" value="{{$customerInvoiceTest->deduction_id_six}}"  class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Deduction Amount')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="deduction_amount_six" value="{{$customerInvoiceTest->deduction_amount_six}}"  class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label>{{__('Total Deductions')}}</label>
                                <div class="kt-input-icon">
                                    <input type="number" readonly id="total_deduction" name="total_deduction" value="{{$customerInvoiceTest->total_deduction}}"  class="form-control" placeholder="{{__('Total Deductions')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/><x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Invoice Net Amount')}}</label>
                                <div class="kt-input-icon">
                                    <input type="number" readonly name="invoice_net_amount" value="{{$customerInvoiceTest->invoice_net_amount}}" id="invoice_net_amount"  class="form-control" placeholder="{{__('Invoice Net Amount')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/><x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="kt-portlet">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title head-title text-primary">
                                {{__('Notifications Section')}}
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label>{{__('Invoices Due Notification Days')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="invoices_due_notification_days" value="{{$customerInvoiceTest->invoices_due_notification_days}}" class="form-control" placeholder="{{__('Invoices Due Notification Days')}}">
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Past Due Invoices Notification Days')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="past_due_invoices_notification_days" value="{{$customerInvoiceTest->past_due_invoices_notification_days}}" class="form-control" placeholder="{{__('Past Due Invoices Notification Days')}}">
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
    <script src="{{url('assets/vendors/general/jquery.repeater/src/lib.js')}}" type="text/javascript"></script>
    <script src="{{url('assets/vendors/general/jquery.repeater/src/jquery.input.js')}}" type="text/javascript"></script>
    <script src="{{url('assets/vendors/general/jquery.repeater/src/repeater.js')}}" type="text/javascript"></script>
    <script src="{{url('assets/js/demo1/pages/crud/forms/widgets/form-repeater.js')}}" type="text/javascript"></script>
    <script>
        $('.deduction_amounts').on('change', function () {
            var total_deductions = deductionAmountTotal();

            $('#total_deduction').val(total_deductions);
        });
        $('.deduction_amounts,#vat_amount,#advance_payment_amount,#invoice_amount').on('change', function () {
            var total_deductions = deductionAmountTotal();
            var invoice_amount =0;
            if ($('#invoice_amount').val() != '') {
                invoice_amount =  parseFloat($('#invoice_amount').val());
            }
            var advance_payment_amount =0;
            if ($('#advance_payment_amount').val() != '') {
                advance_payment_amount =  parseFloat($('#advance_payment_amount').val());
            }
            var vat_amount =0;
            if ($('#vat_amount').val() != '') {
                vat_amount =  parseFloat($('#vat_amount').val());
            }
            invoice_net_amount = (invoice_amount+vat_amount)-total_deductions-advance_payment_amount;
            $('#invoice_net_amount').val(invoice_net_amount);
        });
        function deductionAmountTotal () {
            var total_deductions = 0;
            $('.deduction_amounts').each(function (index, element) {
                var value = element.value;
                if (value != '') { total_deductions +=  parseFloat(value);}
            });
            return total_deductions;
        }
    </script>
    <!--end::Page Scripts -->
@endsection
