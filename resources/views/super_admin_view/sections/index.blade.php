@extends('layouts.dashboard')
@section('css')
    <link href="{{ url('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
@endsection
@section('content')
    <div class="kt-portlet kt-portlet--mobile">
        <div class="kt-portlet__head kt-portlet__head--lg">
            <div class="kt-portlet__head-label">
                <span class="kt-portlet__head-icon">
                    <i class="kt-font-secondary btn-outline-hover-danger flaticon2-line-chart"></i>
                </span>
                <h3 class="kt-portlet__head-title">
                    {{ 'Sections Table' }}
                </h3>
            </div>
            <div class="kt-portlet__head-toolbar">
                <div class="kt-portlet__head-wrapper">
                    <div class="kt-portlet__head-actions">
                        <div class="dropdown dropdown-inline">
                            <button type="button" class="btn btn-default btn-icon-sm dropdown-toggle" data-toggle="dropdown"
                                aria-haspopup="true" aria-expanded="false">
                                <i class="la la-download"></i> {{ __('Export') }}
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <ul class="kt-nav">
                                    <li class="kt-nav__section kt-nav__section--first">
                                        <span class="kt-nav__section-text">{{ __('Choose an option') }}</span>
                                    </li>
                                    <li class="kt-nav__item">
                                        <a href="#" class="kt-nav__link">
                                            <i class="kt-nav__link-icon la la-print"></i>
                                            <span class="kt-nav__link-text">{{ __('Print') }}</span>
                                        </a>
                                    </li>
                                    <li class="kt-nav__item">
                                        <a href="#" class="kt-nav__link">
                                            <i class="kt-nav__link-icon la la-copy"></i>
                                            <span class="kt-nav__link-text">{{ __('Copy') }}</span>
                                        </a>
                                    </li>
                                    <li class="kt-nav__item">
                                        <a href="#" class="kt-nav__link">
                                            <i class="kt-nav__link-icon la la-file-excel-o"></i>
                                            <span class="kt-nav__link-text">{{ __('Excel') }}</span>
                                        </a>
                                    </li>
                                    <li class="kt-nav__item">
                                        <a href="#" class="kt-nav__link">
                                            <i class="kt-nav__link-icon la la-file-text-o"></i>
                                            <span class="kt-nav__link-text">{{ __('CSV') }}</span>
                                        </a>
                                    </li>
                                    <li class="kt-nav__item">
                                        <a href="#" class="kt-nav__link">
                                            <i class="kt-nav__link-icon la la-file-pdf-o"></i>
                                            <span class="kt-nav__link-text">{{ __('PDF') }}</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        &nbsp;
                        <a href="{{route('section.create')}}" class="btn btn-brand btn-elevate btn-icon-sm">
                            <i class="la la-plus"></i>
                            {{ __('New Record') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="kt-portlet__body">

            <!--begin: Datatable -->
            <table class="table table-striped- table-bordered table-hover table-checkable text-center kt_table_1">
                <thead>
                    <tr class="table-standard-color">
                        <th>{{ __('Order') }}</th>
                        <th>{{ __('Section Name') }}</th>
                        <th>{{ __('Section Side') }}</th>
                        <th>{{ __('Icon') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Controll') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sections as $item)
                        <tr>
                            <td>{{ $item->order }}</td>
                            <td>{{ $item->name[$lang] }}</td>
                            <td>{{ strtoupper($item->section_side) }}</td>
                            <td>
                                <div class="kt-demo-icon">
                                    <div class="kt-demo-icon__preview">
                                        <i class="{{ $item->icon }}"></i>
                                    </div>
                                    <div class="kt-demo-icon__class">
                                        {{ $item->icon }} </div>
                                </div>
                            </td>
                            <td>{{ $item->sub_of == 0 ? 'Main' : $item->parent->name[$lang] }}</td>

                            <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions"
                                data-autohide-disabled="false"><span style="overflow: visible; position: relative; width: 110px;">
                                    {{-- <div class="dropdown"> <a data-toggle="dropdown"
                                            class="btn btn-sm btn-clean btn-icon btn-icon-sm"> <i
                                                class="flaticon2-settings"></i> </a>
                                        <div class="dropdown-menu dropdown-menu-right"> <a href="#" class="dropdown-item"><i
                                                    class="la la-edit"></i> Edit Details</a> <a href="#"
                                                class="dropdown-item"><i class="la la-leaf"></i> Update Status</a> <a
                                                href="#" class="dropdown-item"><i class="la la-print"></i> Generate
                                                Report</a> </div>
                                    </div>

                                                                        <button type="button" class="btn btn-success btn-elevate btn-circle btn-icon"><i class="flaticon2-edit"></i></button>
                                    <button type="button" class="btn btn-info btn-elevate btn-circle btn-icon"><i class="flaticon-eye"></i></button>
                                    <button type="button" class="btn btn-danger btn-elevate btn-circle btn-icon"><i class="flaticon2-trash"></i></button>--}}
                                    {{-- <a title="Edit details" class="btn btn-sm btn-clean btn-icon btn-icon-sm" href="{{route('section.edit',$item)}}"> <i class="flaticon2-file"></i> </a>
                                    <a title="Delete" class="btn btn-sm btn-clean btn-icon btn-icon-sm"> <i class="flaticon2-delete"></i></a>
                                    <a title="Delete" class="btn btn-sm btn-clean btn-icon btn-icon-sm"> <i class="flaticon-eye"></i></a> --}}
                                    <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" href="{{route('section.edit',$item)}}"><i class="fa fa-edit"></i></a>
                                    <a type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon"><i class="fa fa-trash"></i></a>
                                    <button type="button" class="btn btn-secondary btn-outline-hover-warning btn-icon"><i class="fa fa-eye"></i></button>
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <!--end: Datatable -->
        </div>
    </div>
@endsection

@section('js')
    <script src="{{ url('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
    <script src="{{ url('assets/js/demo1/pages/crud/datatables/basic/paginations.js') }}" type="text/javascript">
    </script>
@endsection
