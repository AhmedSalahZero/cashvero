@extends('layouts.dashboard')
@section('css')
    <link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}"
        rel="stylesheet" type="text/css" />
    <link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet"
        type="text/css" />
@endsection
@section('content')
    <div class="row">
        <div class="col-md-12">
            <!--begin::Portlet-->
            <div class="kt-portlet">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title head-title text-primary">
                            {{ __('Outstanding Customers Invoices') }}
                        </h3>
                    </div>
                </div>
            </div>


            <!--begin::Form-->
            <form class="kt-form kt-form--label-right" method="POST"
                action={{ route('customersInvoiceImport') }} enctype="multipart/form-data">
                @csrf
                <div class="kt-portlet">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title head-title text-primary">
                                {{ __('Outstanding Customers Invoices Import') }}
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label>{{ __('Import File') }} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="file" name="excel_file" class="form-control"
                                        placeholder="{{ __('Import File') }}">
                                    <x-tool-tip title="{{ __('Kash Vero') }}" />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label>{{ __('Date Formatting') }} @include('star')</label>
                                <div class="kt-input-icon">
                                    <select name="format" class="form-control" required>
                                        <option value="">{{__('Select')}}</option>
                                        {{-- <option value="d-m-Y" >{{__('Day-Month-Year')}}  </option>
                                        <option value="m-d-Y">{{__('Month-Day-Year')}}</option>
                                        <option value="Y-m-d" >{{__('Year-Month-Day')}}</option>
                                        <option value="Y-d-m">{{__('Year-Day-Month')}}</option> --}}
										 <option value="d-m-Y">{{ __('Day-Month-Year') }} eg [ 15-01-2024]</option>
									  <option value="d-M-Y" >{{__('Day-Month-Year')}} eg [ 15-Jan-2024]</option>
                                    <option value="m-d-Y">{{ __('Month-Day-Year') }} eg [ 05-15-2024] </option>
                                    <option value="Y-m-d">{{ __('Year-Month-Day') }} eg [2024-05-15] </option>
                                    <option value="Y-d-m">{{ __('Year-Day-Month') }} eg [2024-15-05] </option>
									
                                    </select>
                                    <x-tool-tip title="{{ __('Kash Vero') }}" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                <x-submitting />

            </form>
            <!--end::Form-->
            <x-table :tableTitle="__('Outstanding Customers Invoices Table')"
                :href="route('customerInvoiceTest.insertToMainTable')" :icon="__('file-import')"
                :firstButtonName="__('Save It Table')" :tableClass="'kt_table_with_no_pagination'">
                @slot('table_header')
                    <tr class="table-standard-color">
                        {{-- <th>#</th> --}}
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
                        <tr class="{{ isset($item->validation) ? 'table-danger' : '' }}">
                            {{-- <td>{{$item->id}}</td> --}}
                            <td>{{ $item->customer_name }}</td>
                            <td>{{ $item->invoice_number }}</td>
                            <td>{{ dateFormatting($item->invoice_date, 'd-M-Y') }}</td>
                            <td>{{ dateFormatting($item->invoice_due_date, 'd-M-Y') }}</td>
                            <td>{{ $item->invoice_amount }}</td>
                            <td>{{ $item->currency }}</td>
                            <td>{{ $item->vat_amount }}</td>
                            <td>{{ $item->invoice_net_amount }}</td>
                            {{-- <td>{{$item->}}</td> --}}
                            <td class="kt-datatable__cell--left kt-datatable__cell d-flex justify-content-center"
                                data-field="Actions" data-autohide-disabled="false">

                                <span class="d-flex justify-content-center" style="overflow: visible; position: relative; width: 110px;">
                                    <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit"
                                        href="{{ route('customerInvoiceTest.edit', [ $item->id]) }}"><i
                                            class="fa fa-pen-alt"></i></a>
                                    <form method="post"
                                        action="{{ route('customerInvoiceTest.destroy', [ $item->id]) }}"
                                        style="display: inline">
                                        @method('DELETE')
                                        @csrf
                                        <button type="submit" class="btn btn-secondary btn-outline-hover-danger btn-icon"
                                            title="Delete" href=""><i class="fa fa-trash-alt"></i></button>
                                    </form>
                                    @if ($item->validation)
                                        <button type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon"
                                            data-toggle="modal" data-target="#kt_modal_1_{{ $item->id }}"> <i
                                                class="fas fa-ban"></i></button>
                                    @endif
                                </span>
                            </td>
                        </tr>
                        @if ($item->validation)
                            <!--begin::Modal-->
                            <div class="modal fade" id="kt_modal_1_{{ $item->id }}" tabindex="-1" role="dialog"
                                aria-labelledby="exampleModalLabel" aria-hidden="true">
                                <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="exampleModalLabel">Modal title</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            </button>
                                        </div>
                                        <div class="modal-body">

                                            @foreach ($item->validation as $message)
                                                <div class="alert alert-solid-danger alert-bold" role="alert">
                                                    <div class="alert-text">{{ $message }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                            <button type="button" class="btn btn-primary">Save changes</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!--end::Modal-->
                        @endif
                    @endforeach
                @endslot

            </x-table>
            <!--end::Portlet-->
        </div>
        <div class="kt-portlet text-center">
            <div class="kt-portlet__head kt-portlet__head--lg">
                <div class="kt-portlet__head-label d-flex justify-content-start">
                    {{ $customerInvoices->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="{{ url('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
    <script src="{{ url('assets/js/demo1/pages/crud/datatables/basic/paginations.js') }}" type="text/javascript">
    </script>

@endsection
