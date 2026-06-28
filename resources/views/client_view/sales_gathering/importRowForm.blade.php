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
            <form class="kt-form kt-form--label-right" method="POST" action={{ route('salesGatheringTest.update',[$company,$salesGatheringTest])}}   enctype="multipart/form-data">
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
                        <div class="row">
                            @if (false !== $found = array_search('date',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['date'])}} @include('star')</label>
                                    <div class="kt-input-icon">
                                        <div class="input-group date">
                                            <input type="date" name="date" value="{{$salesGatheringTest->date}}" class="form-control"  placeholder="Select date" />
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('country',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['country'])}}</label>
                                    <div class="kt-input-icon">
                                        <input type="text" name="country" value="{{$salesGatheringTest->country}}" class="form-control" placeholder="{{__($exportableFields['country'])}}">
                                    </div>
                                </div>
                            @endif
                            @if (false !== $found = array_search('local_or_export',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['local_or_export'])}}</label>
                                    <div class="kt-input-icon">
                                        <input type="text" name="local_or_export" value="{{$salesGatheringTest->local_or_export}}" class="form-control" placeholder="{{__($exportableFields['local_or_export'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('branch',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['branch'])}}</label>
                                    <div class="kt-input-icon">
                                        <input type="text" name="branch" value="{{$salesGatheringTest->branch}}" class="form-control" placeholder="{{__($exportableFields['branch'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('document_type',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['document_type'])}}</label>
                                    <div class="kt-input-icon">
                                        <input type="text" name="document_type" value="{{$salesGatheringTest->document_type}}" class="form-control" placeholder="{{__($exportableFields['document_type'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('document_number',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['document_number'])}}</label>
                                    <div class="kt-input-icon">
                                        <input type="text" name="document_number" value="{{$salesGatheringTest->document_number}}" class="form-control" placeholder="{{__($exportableFields['document_number'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('sales_person',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['sales_person'])}}</label>
                                    <div class="kt-input-icon">
                                        <input type="text" name="sales_person" value="{{$salesGatheringTest->sales_person}}" class="form-control" placeholder="{{__($exportableFields['sales_person'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('business_unit',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['business_unit'])}}</label>
                                    <div class="kt-input-icon">
                                        <input type="text" name="business_unit" value="{{$salesGatheringTest->business_unit}}" class="form-control" placeholder="{{__($exportableFields['business_unit'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('customer_name',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['customer_name'])}}</label>
                                    <div class="kt-input-icon">
                                        <input type="text" name="customer_name" value="{{$salesGatheringTest->customer_name}}" class="form-control" placeholder="{{__($exportableFields['customer_name'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('business_sector',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['business_sector'])}}</label>
                                    <div class="kt-input-icon">
                                        <input type="text" name="business_sector" value="{{$salesGatheringTest->business_sector}}" class="form-control" placeholder="{{__($exportableFields['business_sector'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('zone',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['zone'])}}</label>
                                    <div class="kt-input-icon">
                                        <input type="text" name="zone" value="{{$salesGatheringTest->zone}}" class="form-control" placeholder="{{__($exportableFields['zone'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('sales_channel',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['sales_channel'])}}</label>
                                    <div class="kt-input-icon">
                                        <input type="text" name="sales_channel" value="{{$salesGatheringTest->sales_channel}}" class="form-control" placeholder="{{__($exportableFields['sales_channel'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('service_provider_type',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['service_provider_type'])}}</label>
                                    <div class="kt-input-icon">
                                        <input type="text" name="service_provider_type" value="{{$salesGatheringTest->service_provider_type}}" class="form-control" placeholder="{{__($exportableFields['service_provider_type'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('service_provider_name',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['service_provider_name'])}}</label>
                                    <div class="kt-input-icon">
                                        <input type="text" name="service_provider_name" value="{{$salesGatheringTest->service_provider_name}}" class="form-control" placeholder="{{__($exportableFields['service_provider_name'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('service_provider_birth_year',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['service_provider_birth_year'])}}</label>
                                    <div class="kt-input-icon">
                                        <input type="number" name="service_provider_birth_year" value="{{$salesGatheringTest->service_provider_birth_year}}" class="form-control" placeholder="{{__($exportableFields['service_provider_birth_year'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('principle',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['principle'])}}</label>
                                    <div class="kt-input-icon">
                                        <input type="text" name="principle" value="{{$salesGatheringTest->principle}}" class="form-control" placeholder="{{__($exportableFields['principle'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('category',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['category'])}}</label>
                                    <div class="kt-input-icon">
                                        <input type="text" name="category" value="{{$salesGatheringTest->category}}" class="form-control" placeholder="{{__($exportableFields['category'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('sub_category',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['sub_category'])}}</label>
                                    <div class="kt-input-icon">
                                        <input type="text" name="sub_category" value="{{$salesGatheringTest->sub_category}}" class="form-control" placeholder="{{__($exportableFields['sub_category'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('product_or_service',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['product_or_service'])}}</label>
                                    <div class="kt-input-icon">
                                        <input type="text" name="product_or_service" value="{{$salesGatheringTest->product_or_service}}" class="form-control" placeholder="{{__($exportableFields['product_or_service'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('product_item',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['product_item'])}}</label>
                                    <div class="kt-input-icon">
                                        <input type="text" name="product_item" value="{{$salesGatheringTest->product_item}}" class="form-control" placeholder="{{__($exportableFields['product_item'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('measurment_unit',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['measurment_unit'])}}</label>
                                    <div class="kt-input-icon">
                                        <input type="text" name="measurment_unit" value="{{$salesGatheringTest->measurment_unit}}" class="form-control" placeholder="{{__($exportableFields['measurment_unit'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('return_reason',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['return_reason'])}}</label>
                                    <div class="kt-input-icon">
                                        <input type="text" name="return_reason" value="{{$salesGatheringTest->return_reason}}" class="form-control" placeholder="{{__($exportableFields['return_reason'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('quantity',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['quantity'])}}</label>
                                    <div class="kt-input-icon">
                                        <input type="number" name="quantity" value="{{$salesGatheringTest->quantity}}" class="form-control" placeholder="{{__($exportableFields['quantity'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('quantity_status',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['quantity_status'])}}</label>
                                    <div class="kt-input-icon">
                                        <input type="text" name="quantity_status" value="{{$salesGatheringTest->quantity_status}}" class="form-control" placeholder="{{__($exportableFields['quantity_status'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('quantity_bonus',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['quantity_bonus'])}}</label>
                                    <div class="kt-input-icon">
                                        <input type="number" step="any" name="quantity_bonus" value="{{$salesGatheringTest->quantity_bonus}}" class="form-control" placeholder="{{__($exportableFields['quantity_bonus'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('price_per_unit',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['price_per_unit'])}}</label>
                                    <div class="kt-input-icon">
                                        <input  type="number" step="any" name="price_per_unit" value="{{$salesGatheringTest->price_per_unit}}" class="form-control" placeholder="{{__($exportableFields['price_per_unit'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('sales_value',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['sales_value'])}}</label>
                                    <div class="kt-input-icon">
                                        <input  type="number" step="any" name="sales_value" value="{{$salesGatheringTest->sales_value}}" class="form-control" placeholder="{{__($exportableFields['sales_value'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('quantity_discount',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['quantity_discount'])}}</label>
                                    <div class="kt-input-icon">
                                        <input  type="number" step="any" name="quantity_discount" value="{{$salesGatheringTest->quantity_discount}}" class="form-control" placeholder="{{__($exportableFields['quantity_discount'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('cash_discount',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['cash_discount'])}}</label>
                                    <div class="kt-input-icon">
                                        <input  type="number" step="any" name="cash_discount" value="{{$salesGatheringTest->cash_discount}}" class="form-control" placeholder="{{__($exportableFields['cash_discount'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('special_discount',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['special_discount'])}}</label>
                                    <div class="kt-input-icon">
                                        <input  type="number" step="any" name="special_discount" value="{{$salesGatheringTest->special_discount}}" class="form-control" placeholder="{{__($exportableFields['special_discount'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('other_discounts',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['other_discounts'])}}</label>
                                    <div class="kt-input-icon">
                                        <input  type="number" step="any" name="other_discounts" value="{{$salesGatheringTest->other_discounts}}" class="form-control" placeholder="{{__($exportableFields['other_discounts'])}}">
                                    </div>
                                </div>
                            @endif

                            @if (false !== $found = array_search('net_sales_value',$db_names))
                                <div class="form-group  col-md-6">
                                    <label>{{__($exportableFields['net_sales_value'])}}</label>
                                    <div class="kt-input-icon">
                                        <input  type="number" step="any" name="net_sales_value" value="{{$salesGatheringTest->net_sales_value}}" class="form-control" placeholder="{{__($exportableFields['net_sales_value'])}}">
                                    </div>
                                </div>
                            @endif









                        </div>
                    </div>
                </div>

                {{-- <div class="kt-portlet">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title head-title text-primary">
                                {{__('Invoice Information')}}
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label>{{__('Invoice Number')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="text" name="invoice_number" value="{{$salesGatheringTest->invoice_number}}" class="form-control" placeholder="{{__('Invoice Number')}}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Invoice Date')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <input type="date" name="invoice_date" value="{{$salesGatheringTest->invoice_date}}" class="form-control"  placeholder="Select date" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Due Within')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="due_within" value="{{$salesGatheringTest->due_within}}"  class="form-control" placeholder="{{__('Due Within')}}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Due Date')}} </label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <input type="date" name="invoice_due_date" value="{{$salesGatheringTest->invoice_due_date}}" class="form-control" placeholder="Select date" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label>{{__('Contract Code/Name')}}</label>
                                <div class="kt-input-icon">
                                    <input type="text" name="contract_code" value="{{$salesGatheringTest->contract_code}}" class="form-control" placeholder="{{__('Contract Code/Name')}}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Contract Date')}}</label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <input type="date" name="contract_date" value="{{$salesGatheringTest->contract_date}}" class="form-control" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Customer Purchase Order Number')}}</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="purchase_order_number" value="{{$salesGatheringTest->purchase_order_number}}"  class="form-control" placeholder="{{__('Purchase Order Number')}}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Purchase Order Date')}}</label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <input type="date" name="purchase_order_date" value="{{$salesGatheringTest->purchase_order_date}}" class="form-control"  placeholder="Select date" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label>{{__('Internal Sales Order Number')}}</label>
                                <div class="kt-input-icon">
                                    <input type="text" name="sales_order_number" value="{{$salesGatheringTest->sales_order_number}}" class="form-control" placeholder="{{__('Internal Sales Order Number')}}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Internal Sales Order Date')}}</label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <input type="date" name="sales_order_date" value="{{$salesGatheringTest->sales_order_date}}" class="form-control"  placeholder="Select date" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Sales Person Name')}}</label>
                                <div class="kt-input-icon">
                                    <input type="text" name="sales_person_name" value="{{$salesGatheringTest->sales_person_name}}"  class="form-control" placeholder="{{__('Sales Person Name')}}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Sales Commission Rate %')}}</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="sales_person_rate" value="{{$salesGatheringTest->sales_person_rate}}"  class="form-control" placeholder="{{__('Sales Commission Rate %')}}">
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
                                    <input type="number" name="invoice_amount" value="{{$salesGatheringTest->invoice_amount}}" id="invoice_amount" class="form-control" placeholder="{{__('Invoice Amount')}}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Select Currency')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <select name="currency"  class="form-control">
                                            <option value="" selected>{{__('Select')}}</option>
                                            <option value="EGP"  {{$salesGatheringTest->currency == 'EGP' ? 'selected' : ''}}>EGP</option>
                                            <option value="USD"  {{$salesGatheringTest->currency == 'USD' ? 'selected' : ''}}>USD</option>
                                            <option value="EURO" {{$salesGatheringTest->currency == 'EURO' ? 'selected' : ''}}>EURO</option>
                                            <option value="GBP"  {{$salesGatheringTest->currency == 'GBP' ? 'selected' : ''}}>GBP</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label>{{__('Advance Payment Amount')}}</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="advance_payment_amount" value="{{$salesGatheringTest->advance_payment_amount}}" id="advance_payment_amount" class="form-control" placeholder="{{__('Advance Payment Amount')}}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('VAT Amount')}}</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="vat_amount" value="{{$salesGatheringTest->vat_amount}}" id="vat_amount" class="form-control" placeholder="{{__('VAT Amount')}}">
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label>{{__('Deduction Name')}} </label>
                                <div class="kt-input-icon">
                                    <input type="text" name="deduction_id_one" value="{{$salesGatheringTest->deduction_id_one}}"  class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Deduction Amount')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="deduction_amount_one" value="{{$salesGatheringTest->deduction_amount_one}}"  class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Deduction Name')}} </label>
                                <div class="kt-input-icon">
                                    <input type="text" name="deduction_id_two" value="{{$salesGatheringTest->deduction_id_two}}"  class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Deduction Amount')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="deduction_amount_two" value="{{$salesGatheringTest->deduction_amount_two}}"  class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label>{{__('Deduction Name')}} </label>
                                <div class="kt-input-icon">
                                    <input type="text" name="deduction_id_three" value="{{$salesGatheringTest->deduction_id_three}}"  class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Deduction Amount')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="deduction_amount_three" value="{{$salesGatheringTest->deduction_amount_three}}"  class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Deduction Name')}} </label>
                                <div class="kt-input-icon">
                                    <input type="text" name="deduction_id_four" value="{{$salesGatheringTest->deduction_id_four}}"  class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Deduction Amount')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="deduction_amount_four" value="{{$salesGatheringTest->deduction_amount_four}}"  class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label>{{__('Deduction Name')}} </label>
                                <div class="kt-input-icon">
                                    <input type="text" name="deduction_id_five" value="{{$salesGatheringTest->deduction_id_five}}"  class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Deduction Amount')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="deduction_amount_five" value="{{$salesGatheringTest->deduction_amount_five}}"  class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Deduction Name')}} </label>
                                <div class="kt-input-icon">
                                    <input type="text" name="deduction_id_six" value="{{$salesGatheringTest->deduction_id_six}}"  class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Deduction Amount')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="deduction_amount_six" value="{{$salesGatheringTest->deduction_amount_six}}"  class="form-control deduction_amounts" placeholder="{{__('Deduction Amount')}}">
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label>{{__('Total Deductions')}}</label>
                                <div class="kt-input-icon">
                                    <input type="number" readonly id="total_deduction" name="total_deduction" value="{{$salesGatheringTest->total_deduction}}"  class="form-control" placeholder="{{__('Total Deductions')}}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Invoice Net Amount')}}</label>
                                <div class="kt-input-icon">
                                    <input type="number" readonly name="invoice_net_amount" value="{{$salesGatheringTest->invoice_net_amount}}" id="invoice_net_amount"  class="form-control" placeholder="{{__('Invoice Net Amount')}}">
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
                                    <input type="number" name="invoices_due_notification_days" value="{{$salesGatheringTest->invoices_due_notification_days}}" class="form-control" placeholder="{{__('Invoices Due Notification Days')}}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{__('Past Due Invoices Notification Days')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="past_due_invoices_notification_days" value="{{$salesGatheringTest->past_due_invoices_notification_days}}" class="form-control" placeholder="{{__('Past Due Invoices Notification Days')}}">
                                </div>
                            </div>
                        </div>

                    </div>
                </div> --}}

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
