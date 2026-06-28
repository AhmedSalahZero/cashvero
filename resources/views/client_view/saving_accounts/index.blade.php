@extends('layouts.dashboard')
@section('css')
    <link href="{{ url('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
@endsection
@section('content')
    {{-- Table Component ['href of Add Button'] & ['The Title Of The Table'] --}}
    <x-table :href="route('savingAccount.create')" :tableTitle="__('Saving Account Table')">
        {{-- Head Of The Table --}}
        @slot('table_header')
            <tr class="table-standard-color">
                <th>{{ __('Account Number') }}</th>
                <th>{{ __('Currency') }}</th>
                <th>{{ __('Interest Rate') }}</th>
                <th>{{ __('Min Amount') }}</th>
                <th>{{ __('Balance Amount') }}</th>
                <th>{{ __('Control') }}</th>
            </tr>
        @endslot
        {{-- Body Of The Table --}}
        @slot('table_body')
            <tr>
                <td>100021235</td>
                <td>EGP</td>
                <td>8.75 %</td>
                <td>10,000</td>
                <td>1,000,000</td>
                <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions"
                    data-autohide-disabled="false">
                    <span style="overflow: visible; position: relative; width: 110px;">
                        <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href=""><i class="fa fa-pen-alt"></i></a>
                        <a type="button" class="btn btn-secondary btn-outline-hover-warning btn-icon" title="Renew" href=""><i class="fa fa-sync-alt"></i></a>
                        <a type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href=""><i class="fa fa-trash-alt"></i></a>
                    </span>
                </td>
            </tr>
            <tr>
                <td>100021235</td>
                <td>EGP</td>
                <td>8.75 %</td>
                <td>10,000</td>
                <td>1,000,000</td>
                <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions"
                    data-autohide-disabled="false">
                    <span style="overflow: visible; position: relative; width: 110px;">
                        <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href=""><i class="fa fa-pen-alt"></i></a>
                        <a type="button" class="btn btn-secondary btn-outline-hover-warning btn-icon" title="Renew" href=""><i class="fa fa-sync-alt"></i></a>
                        <a type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href=""><i class="fa fa-trash-alt"></i></a>
                    </span>
                </td>
            </tr>
            <tr>
                <td>100021235</td>
                <td>EGP</td>
                <td>8.75 %</td>
                <td>10,000</td>
                <td>1,000,000</td>
                <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions"
                    data-autohide-disabled="false">
                    <span style="overflow: visible; position: relative; width: 110px;">
                        <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href=""><i class="fa fa-pen-alt"></i></a>
                        <a type="button" class="btn btn-secondary btn-outline-hover-warning btn-icon" title="Renew" href=""><i class="fa fa-sync-alt"></i></a>
                        <a type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href=""><i class="fa fa-trash-alt"></i></a>
                    </span>
                </td>
            </tr>
            <tr>
                <td>100021235</td>
                <td>EGP</td>
                <td>8.75 %</td>
                <td>10,000</td>
                <td>1,000,000</td>
                <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions"
                    data-autohide-disabled="false">
                    <span style="overflow: visible; position: relative; width: 110px;">
                        <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href=""><i class="fa fa-pen-alt"></i></a>
                        <a type="button" class="btn btn-secondary btn-outline-hover-warning btn-icon" title="Renew" href=""><i class="fa fa-sync-alt"></i></a>
                        <a type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href=""><i class="fa fa-trash-alt"></i></a>
                    </span>
                </td>
            </tr>
        @endslot
    </x-table>
@endsection

@section('js')
    <script src="{{ url('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
    <script src="{{ url('assets/js/demo1/pages/crud/datatables/basic/paginations.js') }}" type="text/javascript"></script>

@endsection
