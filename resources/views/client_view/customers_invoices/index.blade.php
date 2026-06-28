@extends('layouts.dashboard')
@section('css')
    <link href="{{ url('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
@endsection
@section('content')
<div class="row">
    <div class="col-lg-12">
        @if (session('warning'))
            <div class="alert alert-warning">
                <ul>
                    <li>{{ session('warning') }}</li>
                </ul>
            </div>
        @endif
    </div>
</div>
    <x-table :tableTitle="__('Outstanding Customers Invoices Table')" :tableClass="'kt_table_with_no_pagination'" href="{{route('customersInvoice.create',$company)}}" :importHref="route('customersInvoiceImport',$company)" :exportHref="route('customersInvoice.export',$company)" :exportTableHref="route('table.fields.selection.view',[$company,'CustomersInvoice','customers_invoices'])">
        @slot('table_header')
            <tr class="table-standard-color">

                <th>{{ __('Customer Name') }}</th>
                <th>{{ __('Invoice Number') }}</th>
                <th>{{ __('Invoice Date') }}</th>
                <th>{{ __('Invoice Due Date') }}</th>
                <th>{{ __('Invoice Amount') }}</th>
                <th>{{ __('Currency') }}</th>
                <th>{{ __('VAT') }}</th>
                <th>{{ __('Net Amount') }}</th>
                <th>{{ __('Control') }}</th>
            </tr>
        @endslot
        @slot('table_body')
            @foreach ($customerInvoices as $item)
                <tr>

                    <td>{{$item->customer_name}}</td>
                    <td>{{$item->invoice_number}}</td>
                    <td>{{dateFormatting($item->invoice_date,'M-Y')}}</td>
                    <td>{{dateFormatting($item->invoice_due_date,'M-Y')}}</td>
                    <td>{{$item->invoice_amount}}</td>
                    <td>{{$item->currency}}</td>
                    <td>{{$item->vat_amount}}</td>
                    <td>{{$item->invoice_net_amount}}</td>

                    <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions" data-autohide-disabled="false">
                        <span class="d-flex justify-content-center" style="overflow: visible; position: relative; width: 110px;">
                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{route('customersInvoice.edit',[$company,$item])}}"><i class="fa fa-pen-alt"></i></a>
                            <form method="post"   action="{{route('customersInvoice.destroy',[$company,$item->id])}}" style="display: inline">
                                @method('DELETE')
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href=""><i class="fa fa-trash-alt"></i></button>
                            </form>
                            <a type="button" class="btn btn-secondary btn-outline-hover-warning btn-icon" href="{{route('adjustedCollectionDate.create',[$company])}}" title="Adjusted Collection Date" href=""><i class="fa fa-sliders-h"></i></a>
                        </span>
                    </td>
                </tr>
            @endforeach
        @endslot
    </x-table>
    <div class="kt-portlet">
        <div class="kt-portlet__head kt-portlet__head--lg">
            <div class="kt-portlet__head-label d-flex justify-content-start">
                {{ $customerInvoices->links() }}
            </div>
        </div>
    </div>

@endsection

@section('js')
    <script src="{{ url('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
    <script src="{{ url('assets/js/demo1/pages/crud/datatables/basic/paginations.js') }}" type="text/javascript"></script>

@endsection
