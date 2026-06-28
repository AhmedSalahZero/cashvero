@extends('layouts.dashboard')
@section('css')
    <link href="{{url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css')}}" rel="stylesheet" type="text/css" />
    <style>
        .money-flow-dark .kt-portlet {
            overflow: visible !important;
        }

        .money-flow-dark .kt-portlet .kt-portlet__head {
            border-bottom-color: #1490a833 !important;
        }

        .money-flow-dark label {
            text-align: left !important;
        }

        .money-flow-dark .table thead tr th {
            background-color: var(--bg-card-hover) !important;
            color: var(--text-primary) !important;
        }

        .money-flow-dark .table tbody td {
            color: var(--text-primary) !important;
        }

        .money-flow-dark .kt-checkbox.kt-checkbox--success {
            color: var(--text-primary) !important;
        }
    </style>
@endsection

@section('content')
@php
    $currentPermissionUser = $user ?? \App\Models\User::find(Request()->segment(3));
@endphp
<div class="money-flow-dark">
<div class="row">
    <div class="col-lg-12">
        <!--begin::Portlet-->
        <div class="kt-portlet">
            <div class="kt-portlet__head">
                <div class="kt-portlet__head-label">
                    <h3 class="kt-portlet__head-title head-title text-primary">
					{{ __('Edit Permission For ' . $currentPermissionUser->name . ' [ ' . $currentPermissionUser->email  .' ]' ) }}
                    </h3>
                </div>
            </div>
        </div>
            <!--begin::Form-->
     
            <form class="kt-form kt-form--label-right" method="POST" action="{{  route('user.permissions.update',$company ? ['user'=>$currentPermissionUser->id,'company'=>$company->id]:['user'=>$currentPermissionUser->id]) }}" enctype="multipart/form-data">
			<input type="hidden" name="user_id" value="{{ $currentPermissionUser->id }}">
			
                @csrf
             

                <div class="kt-portlet">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title head-title text-primary">
                                {{__('Section Information')}}
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body">
                        @include('super_admin_view.roles_and_permissions.permissions-radio',['user'=>$currentPermissionUser])
                    </div>
                </div>

                <x-submitting/>
            </form>

            <!--end::Form-->

        <!--end::Portlet-->
    </div>
</div>
</div>
@endsection
@section('js')
    <!--begin::Page Scripts(used by this page) -->
    <script src="{{url('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js')}}" type="text/javascript"></script>
    <script src="{{url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-select.js')}}" type="text/javascript"></script>
    <!--end::Page Scripts -->
    <script>
        $('#select_all').change(function(e) {
            if ($(this).prop("checked")) {
                $('.view').prop("checked", true);
                $('.create').prop("checked", true);
                $('.edit').prop("checked", true);
                $('.delete').prop("checked", true);
            } else {
                $('.view').prop("checked", false);
                $('.create').prop("checked", false);
                $('.edit').prop("checked", false);
                $('.delete').prop("checked", false);
            }
        });
    </script>
@endsection
