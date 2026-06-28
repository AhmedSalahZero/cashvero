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
            @php $toolTipsData = isset($toolTipsData) ? $toolTipsData->pluck('data','field')->toArray() : [] @endphp

            <!--begin::Form-->
            <form class="kt-form kt-form--label-right" method="POST" action={{(request()->is('*/edit'))  ? route('customersInvoice.update',[$company,$customerInvoice]): route('customersInvoice.store',[$company] )}}   enctype="multipart/form-data">
                @csrf
                {{(request()->is('*/edit'))  ?  method_field('PUT'): ""}}
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
                            <input type="hidden" name="company_id" value="{{$company->id}}">
                            <div class="col-md-6">
                                <label>{{__('Customer Name')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="text" name="customer_name" value="{{$customerInvoice->customer_name}}" class="form-control" placeholder="{{__('Customer Name')}}">
                                    <x-tool-tip title=" {{($toolTipsData['customer_name'][lang()] ?? '-')}}"/>
                                </div>

                            </div>
                            <div class="col-md-6">
                                <label>{{__('Business Sector')}}</label>
                                <div class="kt-input-icon">
                                    <input type="text" name="business_sector" value="{{$customerInvoice->business_sector}}"  class="form-control" placeholder="{{__('Business Sector')}}">
                                    <x-tool-tip title="{{($toolTipsData['business_sector'][lang()] ?? '-')}}"/>
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
                                    <input type="text" name="invoice_number" value="{{$customerInvoice->invoice_number}}" class="form-control" placeholder="{{__('Invoice Number')}}">
                                    <x-tool-tip title="{{($toolTipsData['invoice_number'][lang()] ?? '-')}}"/>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Invoice Date')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <input type="date" name="invoice_date" value="{{$customerInvoice->invoice_date}}" class="form-control"  placeholder="Select date" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Due Within')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" step="any" name="due_within" value="{{$customerInvoice->due_within}}"  min="0" class="form-control" placeholder="{{__('Due Within')}}">
                                    <x-tool-tip title="{{($toolTipsData['due_within'][lang()] ?? '-')}}"/>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Due Date')}} </label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <input type="date" name="invoice_due_date" value="{{$customerInvoice->invoice_due_date}}" class="form-control" placeholder="Select date" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-3">
                                <label>{{__('Contract Code/Name')}}</label>
                                <div class="kt-input-icon">
                                    <input type="text" name="contract_code" value="{{$customerInvoice->contract_code}}" class="form-control" placeholder="{{__('Contract Code/Name')}}">
                                    <x-tool-tip title="{{($toolTipsData['contract_code'][lang()] ?? '-')}}"/>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Contract Date')}}</label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <input type="date" name="contract_date" value="{{$customerInvoice->contract_date}}" class="form-control" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Customer Purchase Order Number')}}</label>
                                <div class="kt-input-icon">
                                    <input type="number" step="any" name="purchase_order_number" value="{{$customerInvoice->purchase_order_number}}" min="0" class="form-control" placeholder="{{__('Purchase Order Number')}}">
                                    <x-tool-tip title="{{($toolTipsData['purchase_order_number'][lang()] ?? '-')}}"/>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Purchase Order Date')}}</label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <input type="date" name="purchase_order_date" value="{{$customerInvoice->purchase_order_date}}" class="form-control"  placeholder="Select date" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-3">
                                <label>{{__('Internal Sales Order Number')}}</label>
                                <div class="kt-input-icon">
                                    <input type="text" name="sales_order_number" value="{{$customerInvoice->sales_order_number}}" class="form-control" placeholder="{{__('Internal Sales Order Number')}}">
                                    <x-tool-tip title="{{($toolTipsData['sales_order_number'][lang()] ?? '-')}}"/>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Internal Sales Order Date')}}</label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <input type="date" name="sales_order_date" value="{{$customerInvoice->sales_order_date}}" class="form-control"  placeholder="Select date" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Sales Person Name')}}</label>
                                <div class="kt-input-icon">
                                    <input type="text" name="sales_person_name" value="{{$customerInvoice->sales_person_name}}"  class="form-control" placeholder="{{__('Sales Person Name')}}">
                                    <x-tool-tip title="{{($toolTipsData['sales_person_name'][lang()] ?? '-')}}"/>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Sales Commission Rate %')}}</label>
                                <div class="kt-input-icon">
                                    <input type="number" step="any" name="sales_person_rate" value="{{$customerInvoice->sales_person_rate}}" min="0" class="form-control" placeholder="{{__('Sales Commission Rate %')}}">
                                    <x-tool-tip title="{{($toolTipsData['sales_person_rate'][lang()] ?? '-')}}"/>
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
                                    <input type="number" step="any" step="any" name="invoice_amount" value="{{$customerInvoice->invoice_amount}}" id="invoice_amount" class="form-control" placeholder="{{__('Invoice Amount')}}">
                                    <x-tool-tip title="{{($toolTipsData['invoice_amount'][lang()] ?? '-')}}"/>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Select Currency')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <select name="currency" class="form-control">
                                            <option value="" selected>{{__('Select')}}</option>
                                            <option value="EGP"  {{$customerInvoice->currency !== 'EGP' ?: 'selected'}} >{{__('EGP')}}</option>
                                            <option value="USD"  {{$customerInvoice->currency !== 'USD' ?: 'selected'}} >{{__('USD')}}</option>
                                            <option value="EURO" {{$customerInvoice->currency !== 'EURO' ?: 'selected'}} >{{__('EURO')}}</option>
                                            <option value="GBP"  {{$customerInvoice->currency !== 'GBP' ?: 'selected'}} >{{__('GBP')}}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label>{{__('Advance Payment Amount')}}</label>
                                <div class="kt-input-icon">
                                    <input type="number" step="any" step="any" name="advance_payment_amount" value="{{$customerInvoice->advance_payment_amount}}"  id="advance_payment_amount" class="form-control" placeholder="{{__('Advance Payment Amount')}}">
                                    <x-tool-tip title="{{($toolTipsData['advance_payment_amount'][lang()] ?? '-')}}"/>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('VAT Amount')}}</label>
                                <div class="kt-input-icon">
                                    <input type="number" step="any" step="any" name="vat_amount" value="{{$customerInvoice->vat_amount}}"  id="vat_amount" class="form-control" placeholder="{{__('VAT Amount')}}">
                                    <x-tool-tip title="{{($toolTipsData['vat_amount'][lang()] ?? '-')}}"/>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-3">
                                <label>{{__('Deduction Name')}} </label>
                                <select class="form-control kt-selectpicker" name="deduction_id_one">
                                    <option value="" >{{__('Select')}}</option>
                                    @foreach ($deductions as $item)
                                        <option value="{{$item->id}}" {{$customerInvoice->deduction_id_one !=  $item->id ?: 'selected'}}>{{__($item->viewing_name)}}</option>

                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Deduction Amount')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" step="any" name="deduction_amount_one" value="{{$customerInvoice->deduction_amount_one}}" min="0" class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                    <x-tool-tip title="{{($toolTipsData['deduction_amount_one'][lang()] ?? '-')}}"/>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Deduction Name')}} </label>
                                <select class="form-control kt-selectpicker" name="deduction_id_two">
                                    <option value="" >{{__('Select')}}</option>
                                    @foreach ($deductions as $item)
                                        <option value="{{$item->id}}" {{$customerInvoice->deduction_id_two !=  $item->id ?: 'selected'}}>{{__($item->viewing_name)}}</option>

                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Deduction Amount')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" step="any" name="deduction_amount_two" value="{{$customerInvoice->deduction_amount_two}}" min="0" class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                    <x-tool-tip title="{{($toolTipsData['deduction_amount_two'][lang()] ?? '-')}}"/>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-3">
                                <label>{{__('Deduction Name')}} </label>
                                <select class="form-control kt-selectpicker" name="deduction_id_three">
                                    <option value="" >{{__('Select')}}</option>
                                    @foreach ($deductions as $item)
                                        <option value="{{$item->id}}"  {{$customerInvoice->deduction_id_three !=  $item->id ?: 'selected'}}>{{__($item->viewing_name)}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Deduction Amount')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" step="any" name="deduction_amount_three" value="{{$customerInvoice->deduction_amount_three}}" min="0" class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                    <x-tool-tip title="{{($toolTipsData['deduction_amount_three'][lang()] ?? '-')}}"/>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Deduction Name')}} </label>
                                <select class="form-control kt-selectpicker" name="deduction_id_four">
                                    <option value="" >{{__('Select')}}</option>
                                    @foreach ($deductions as $item)
                                        <option value="{{$item->id}}"  {{$customerInvoice->deduction_id_four !=  $item->id ?: 'selected'}}>{{__($item->viewing_name)}}</option>

                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Deduction Amount')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" step="any" name="deduction_amount_four" value="{{$customerInvoice->deduction_amount_four}}" min="0" class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                    <x-tool-tip title="{{($toolTipsData['deduction_amount_four'][lang()] ?? '-')}}"/>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-3">
                                <label>{{__('Deduction Name')}} </label>
                                <select class="form-control kt-selectpicker" name="deduction_id_five">
                                    <option value="" >{{__('Select')}}</option>
                                    @foreach ($deductions as $item)
                                        <option value="{{$item->id}}"  {{$customerInvoice->deduction_id_five !=  $item->id ?: 'selected'}}>{{__($item->viewing_name)}}</option>

                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Deduction Amount')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" step="any" name="deduction_amount_five" value="{{$customerInvoice->deduction_amount_five}}" min="0" class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                    <x-tool-tip title="{{($toolTipsData['deduction_amount_five'][lang()] ?? '-')}}"/>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Deduction Name')}} </label>
                                <select class="form-control kt-selectpicker" name="deduction_id_six">
                                    <option value="" >{{__('Select')}}</option>
                                    @foreach ($deductions as $item)
                                        <option value="{{$item->id}}"  {{$customerInvoice->deduction_id_six !=  $item->id ?: 'selected'}}>{{__($item->viewing_name)}}</option>

                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>{{__('Deduction Amount')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" step="any" name="deduction_amount_six" value="{{$customerInvoice->deduction_amount_six}}" min="0" class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                    <x-tool-tip title="{{($toolTipsData['deduction_amount_six'][lang()] ?? '-')}}"/>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label>{{__('Total Deductions')}}</label>
                                <div class="kt-input-icon">
                                    <input type="number" step="any" readonly step="any" id="total_deduction" name="total_deduction" value="{{$customerInvoice->total_deduction}}" min="0" class="form-control" placeholder="{{__('Total Deductions')}}">
                                    <x-tool-tip title="{{($toolTipsData['total_deduction'][lang()] ?? '-')}}"/>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Invoice Net Amount')}}</label>
                                <div class="kt-input-icon">
                                    <input type="number" step="any" readonly step="any" name="invoice_net_amount" value="{{$customerInvoice->invoice_net_amount}}" id="invoice_net_amount" min="0" class="form-control" placeholder="{{__('Invoice Net Amount')}}">
                                    <x-tool-tip title="{{($toolTipsData['invoice_net_amount'][lang()] ?? '-')}}"/>
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
                                    <input type="number" step="any" name="invoices_due_notification_days" value="{{$customerInvoice->invoices_due_notification_days}}" class="form-control" placeholder="{{__('Invoices Due Notification Days')}}">
                                    <x-tool-tip title="{{($toolTipsData['invoices_due_notification_days'][lang()] ?? '-')}}"/>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Past Due Invoices Notification Days')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" step="any" name="past_due_invoices_notification_days" value="{{$customerInvoice->past_due_invoices_notification_days}}" class="form-control" placeholder="{{__('Past Due Invoices Notification Days')}}">
                                    <x-tool-tip title="{{($toolTipsData['past_due_invoices_notification_days'][lang()] ?? '-')}}"/>
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
