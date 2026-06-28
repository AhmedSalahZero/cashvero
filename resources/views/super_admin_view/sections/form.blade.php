@extends('layouts.dashboard')
@section('css')
    <link href="{{url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{url('assets/vendors/general/select2/dist/css/select2.css')}}" rel="stylesheet" type="text/css" />

    @endsection
@section('content')
<div class="row">
    <div class="col-lg-12">
        <!--begin::Portlet-->
        <div class="kt-portlet">
            <div class="kt-portlet__head">
                <div class="kt-portlet__head-label">
                    <h3 class="kt-portlet__head-title head-title text-primary">
                        {{__(isset($section) ?'Edit Section' : 'Create Section')}}
                    </h3>
                </div>
            </div>
        </div>
            <!--begin::Form-->
            @php $section_row = isset($section) ? $section : old(); @endphp
            <form class="kt-form kt-form--label-right" method="POST" action= {{isset($section) ? route('section.update',$section): route('section.store')}} enctype="multipart/form-data">
                @csrf
                {{isset($section) ?  method_field('PUT'): ""}}
                <div class="kt-portlet">
                    <div class="kt-portlet__body">
                        <div class="form-group row section">
                            @foreach ($langs as $lang_row)
                                <div class="col-lg-6">
                                    <label>{{__('Section Name ') . $lang_row->name}} @include('star')</label>
                                    <div class="kt-input-icon">
                                        <input type="text" name="name[{{$lang_row->code}}]" value="{{@$section_row['name'][$lang_row->code]}}" class="form-control" placeholder="{{__('Section Name ') . $lang_row->name}}" required>
                                        <x-tool-tip title="{{__('Kash Vero')}}"/>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="kt-portlet">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title head-title text-primary">
                                {{__('Section Information')}}
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body">
                        <div class="form-group row">
                            <div class="col-lg-6">
                                <label>{{__('Route')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="text" name="route" value="{{@$section_row['route']}}"  class="form-control" placeholder="{{__('Route')}}" >
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <label>{{__('Sub Of')}} @include('star')    </label>
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <select name="sub_of" class="form-control kt-select2" id="kt_select2_5"  required>
                                            <optgroup label="Main Routes">
                                                <option value="0"  {{@$section_row['sub_of'] == 0 ? 'selected' : ''}}>{{__('Main')}}</option>
                                                @foreach ($main_sections as $item)
                                                    <option value="{{@$item->id}}" {{@$section_row['sub_of'] == @$item->id ? 'selected' : ''}}>{{$item->name[$lang]}}</option>
                                                @endforeach
                                            </optgroup>
                                            <optgroup label="Sub Routes">
                                                @foreach ($sub_sections as $item)
                                                    <option value="{{@$item->id}}" {{@$section_row['sub_of'] == @$item->id ? 'selected' : ''}}>{{$item->name[$lang]}}</option>
                                                @endforeach
                                            </optgroup>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <label>{{__('Icon')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="text" name="icon" value="{{@$section_row['icon']}}" class="form-control" placeholder="{{__('Icon')}}" required>
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <label>{{__('Order')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="number" name="order" value="{{@$section_row['order']}}" class="form-control" placeholder="{{__('Order')}}" required>
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row form-group-marginless">
                            <label class="col-lg-1 col-form-label">Section Side</label>
                            <div class="col-lg-11">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <label class="kt-option">
                                            <span class="kt-option__control">
                                                <span class="kt-radio kt-radio--bold kt-radio--brand kt-radio--check-bold" checked>
                                                    <input type="radio" name="section_side" value="admin" {{@$section_row['section_side'] == 'admin' ? 'checked' : ''}}>
                                                    <span></span>
                                                </span>
                                            </span>
                                            <span class="kt-option__label">
                                                <span class="kt-option__head">
                                                    <span class="kt-option__title">
                                                        {{__('Admin Side')}}
                                                    </span>

                                                </span>
                                                <span class="kt-option__body">
                                                    {{__('This Section Will Be Added In The Client Side')}}
                                                </span>
                                            </span>
                                        </label>
                                    </div>
                                    <div class="col-lg-6">
                                        <label class="kt-option">
                                            <span class="kt-option__control">
                                                <span class="kt-radio kt-radio--bold kt-radio--brand">
                                                    <input type="radio" name="section_side" value="client" {{@$section_row['section_side'] == 'client' ? 'checked' : ''}}>
                                                    <span></span>
                                                </span>
                                            </span>
                                            <span class="kt-option__label">
                                                <span class="kt-option__head">
                                                    <span class="kt-option__title">
                                                        {{__('Client Side')}}
                                                    </span>

                                                </span>
                                                <span class="kt-option__body">
                                                    {{__('This Section Will Be Added In The Client Side')}}
                                                </span>
                                            </span>
                                        </label>
                                    </div>
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
    <script src="{{url('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js')}}" type="text/javascript"></script>
    <script src="{{url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-select.js')}}" type="text/javascript"></script>
    <script src="{{url('assets/js/demo1/pages/crud/forms/widgets/select2.js')}}" type="text/javascript"></script>
    <script src="{{url('assets/js/demo1/pages/crud/forms/widgets/select2.js')}}" type="text/javascript"></script>
    <!--end::Page Scripts -->
@endsection
