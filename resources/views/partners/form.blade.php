@extends('layouts.dashboard')
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
    .kt-portlet .kt-portlet__head {
        border-bottom-color: #CCE2FD !important;
    }

    label {
        white-space: nowrap !important
    }

    [class*="col"] {
        margin-bottom: 1.5rem !important;
    }

    label {
        text-align: left !important;
    }

    .width-8 {
        max-width: initial !important;
        width: 8% !important;
        flex: initial !important;
    }

    .width-10 {
        max-width: initial !important;
        width: 10% !important;
        flex: initial !important;
    }

    .width-12 {
        max-width: initial !important;
        width: 13.5% !important;
        flex: initial !important;
    }

    .width-45 {
        max-width: initial !important;
        width: 45% !important;
        flex: initial !important;
    }

    .kt-portlet {
        overflow: visible !important;
    }

    input.form-control[disabled]:not(.ignore-global-style),
    input.form-control:not(.is-date-css)[readonly] {
        background-color: #CCE2FD !important;
        font-weight: bold !important;
    }

</style>
@endsection
{{-- @section('sub-header')
{{ __('Internal Money Transfer Form') }}
@endsection --}}
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-md-12">
        <!--begin::Portlet-->

        <form method="post" action="{{ isset($model) ?  route('partners.update',['company'=>$company->id,'partner'=>$model->id]) :route('partners.store',['company'=>$company->id]) }}" class="kt-form kt-form--label-right">
            <input id="js-in-edit-mode" type="hidden" name="in_edit_mode" value="{{ isset($model) ? 1 : 0 }}">
            <input type="hidden" name="id" value="{{ isset($model) ? $model->id : 0 }}">
            <input type="hidden" name="company_id" value="{{ $company->id }}">
            @if(isset($model))
            <input type="hidden" name="updated_by" value="{{ auth()->user()->id }}">
            @else
            <input type="hidden" name="created_by" value="{{ auth()->user()->id }}">

            @endif
            {{-- <input type="hidden" name="financial_institutions_id" value="{{ $financialInstitution->id }}"> --}}
            @csrf
            @if(isset($model))
            @method('put')
            @endif

            <div class="row">
                <div class="col-md-12">
                    <!--begin::Portlet-->
                    <div class="kt-portlet">
                        <div class="kt-portlet__head">
                            <div class="kt-portlet__head-label">
                                <h3 class="kt-portlet__head-title head-title text-primary">
                                    <x-sectionTitle :title="__((isset($model) ? 'Edit' : 'Add') . ' Partner')"></x-sectionTitle>
                                </h3>
                            </div>
                        </div>

                    </div>
                    <!--begin::Form-->
                    <form class="kt-form kt-form--label-right">
                        <div class="kt-portlet">


                            <div class="kt-portlet ">
                                <div class="kt-portlet__head">
                                    <div class="kt-portlet__head-label">
                                        <h3 class="kt-portlet__head-title head-title text-primary">
                                            {{__('Partner Information')}}
                                        </h3>
                                    </div>
                                </div>

                                <div class="kt-portlet__body">
                                    <div class="form-group">
                                        <div class="row">



                                            <div class="col-md-4 ">
                                                <label>{{__('Name')}}
                                                    @include('star')
                                                </label>
                                                <div class="kt-input-icon">
                                                    <input @if($companyHasOdoo) readonly @endif type="text" value="{{ isset($model) ? $model->getName():'' }}" name="name" class="form-control  " placeholder="{{__('Partner Name')}}">
                                                </div>
                                            </div>

                                            <div class="col-md-8 mt-3">
                                                <div class="row">
                                                    <div class="col-md-12 mb-0 mt-4 text-left">
                                                        <div class="form-group d-inline-block">
                                                            <div class="kt-radio-inline">
                                                                <label class="mr-3">

                                                                </label>
                                                                <label class="kt-radio kt-radio--success text-black font-size-18px font-weight-bold">

                                                                    <input 
																	{{-- @if($companyHasOdoo) disabled @endif --}}
																	 type="checkbox" value="1" name="is_customer" @if(isset($model) && $model->isCustomer()) checked @endisset
                                                                    > {{ __('Customer') }}
                                                                    <span></span>
                                                                </label>

                                                                <label class="kt-radio kt-radio--danger text-black font-size-18px font-weight-bold">
                                                                    <input 
																	{{-- @if($companyHasOdoo) disabled @endif  --}}
																	type="checkbox" value="1" name="is_supplier" @if(isset($model) && $model->isSupplier()) checked @endisset
                                                                    > {{ __('Supplier') }}
                                                                    <span></span>
                                                                </label>

                                                                <label class="kt-radio kt-radio--primary text-black font-size-18px font-weight-bold">
                                                                    <input 
																	{{-- @if($companyHasOdoo) disabled @endif --}}
																	 type="checkbox" value="1" name="is_employee" @if(isset($model) && $model->isEmployee()) checked @endisset
                                                                    > {{ __('Employee') }}
                                                                    <span></span>
                                                                </label>


                                                                <label class="kt-radio kt-radio--success text-black font-size-18px font-weight-bold">
                                                                    <input type="checkbox" value="1" name="is_subsidiary_company" @if(isset($model) && $model->isSubsidiaryCompany()) checked @endisset
                                                                    > {{ __('Subsidiary Company') }}
                                                                    <span></span>
                                                                </label>






                                                                <label class="kt-radio kt-radio--danger text-black font-size-18px font-weight-bold">
                                                                    <input type="checkbox" value="1" name="is_other_partner" @if(isset($model) && $model->isOtherPartner()) checked @endisset
                                                                    > {{ __('Other Partner') }}
                                                                    <span></span>
                                                                </label>



                                                                <label class="kt-radio kt-radio--primary text-black font-size-18px font-weight-bold">
                                                                    <input type="checkbox" value="1" name="is_shareholder" @if(isset($model) && $model->isShareholder()) checked @endisset
                                                                    > {{ __('Shareholder') }}
                                                                    <span></span>
                                                                </label>







                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>




                                        </div>
                                    </div>

                                </div>
                            </div>

                        </div>


                        <!--end::Form-->

                        <!--end::Portlet-->
                </div>
            </div>
            <x-submitting-by-ajax :backTo="route('partners.index',['company'=>$company->id])" />
        </form>

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
        <script>

        </script>

        <script>
            $(document).find('.datepicker-input').datepicker({
                dateFormat: 'mm-dd-yy'
                , autoclose: true
            })
            $('#m_repeater_0').repeater({
                initEmpty: false
                , isFirstItemUndeletable: true
                , defaultValues: {
                    'text-input': 'foo'
                },

                show: function() {
                    $(this).slideDown();
                    $('input.trigger-change-repeater').trigger('change')
                    $(document).find('.datepicker-input').datepicker({
                        dateFormat: 'mm-dd-yy'
                        , autoclose: true
                    })
                    $(this).find('.only-month-year-picker').each(function(index, dateInput) {
                        reinitalizeMonthYearInput(dateInput)
                    });
                    $('input:not([type="hidden"])').trigger('change');
                    $(this).find('.dropdown-toggle').remove();
                    $(this).find('select.repeater-select').selectpicker("refresh");

                },

                hide: function(deleteElement) {
                    if ($('#first-loading').length) {
                        $(this).slideUp(deleteElement, function() {

                            deleteElement();
                            //   $('select.main-service-item').trigger('change');
                        });
                    } else {
                        if (confirm('Are you sure you want to delete this element?')) {
                            $(this).slideUp(deleteElement, function() {

                                deleteElement();
                                $('input.trigger-change-repeater').trigger('change')

                            });
                        }
                    }
                }
            });

        </script>

        <script>
            let oldValForInputNumber = 0;
            $('input:not([placeholder]):not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([readonly]):not(.exclude-text):not(.date-input)').on('focus', function() {
                oldValForInputNumber = $(this).val();
                $(this).val('')
            })
            $('input:not([placeholder]):not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([readonly]):not(.exclude-text):not(.date-input)').on('blur', function() {

                if ($(this).val() == '') {
                    $(this).val(oldValForInputNumber)
                }
            })

            $(document).on('change', 'input:not([placeholder])[type="number"],input:not([placeholder])[type="password"],input:not([placeholder])[type="text"],input:not([placeholder])[type="email"],input:not(.exclude-text)', function() {
                if (!$(this).hasClass('exclude-text')) {
                    let val = $(this).val()
                    val = number_unformat(val)
                    $(this).parent().find('input[type="hidden"]:not([name="_token"])').val(val)

                }
            })

        </script>


        @endsection
