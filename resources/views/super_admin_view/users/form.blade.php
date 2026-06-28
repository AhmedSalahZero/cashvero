@extends('layouts.dashboard')
@php
	use App\Models\User;
@endphp
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

        .money-flow-dark .input-group-text {
            background: var(--bg-input) !important;
            border-color: var(--border) !important;
            color: var(--text-primary) !important;
        }
    </style>
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-12">
        <!--begin::Portlet-->
        <div class="kt-portlet">
            <div class="kt-portlet__head">
                <div class="kt-portlet__head-label">
                    <h3 class="kt-portlet__head-title head-title text-primary">
                        {{__('Users')}}
                    </h3>
                </div>
            </div>
        </div>
            <!--begin::Form-->
			@php
             $user_row = isset($user) ? $user : old();
		@endphp 				
            <form class="kt-form kt-form--label-right" method="POST" action= {{isset($user) ? route('user.update',$user): route('user.store')}} enctype="multipart/form-data">
                @csrf
                {{isset($user) ?  method_field('PUT'): ""}}
                <div class="kt-portlet">
                    <div class="kt-portlet__body">
                        <div class="form-group row col-12 col-12">
                            <div class="col-12">
                                <label>{{__('Name')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="text" name="name" value="{{@$user_row['name']}}" class="form-control" placeholder="{{__('Name')}}" required>
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
                                {{__('User Information')}}
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body">
                        <div class="form-group row col-12">
                            <div class="col-6">
                                <label>{{__('Email')}} @include('star')</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">@</span></div>
                                    <input required type="email" name="email" value="{{@$user_row['email']}}" class="form-control" placeholder="{{__('Email')}}" aria-describedby="basic-addon1">
                                </div>
                            </div>
                            <div class="col-6">
                                <label>{{__('User Image')}} @include('star')</label>
                                <div class="kt-input-icon">
                                    <input type="file" class="form-control" name="avatar" >
                                    <x-tool-tip title="{{__('Kash Vero')}}"/>
                                </div>
                            </div>
                        </div>
                        @if(!isset($user))
                        <div class="form-group row col-12">
                            <div class="col-6">
                                <label>{{__('Password')}} @include('star')</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-key"></i></span></div>
                                    <input required id="password" type="password" name="password"  value="{{@$user_row['email']}}" class="form-control" placeholder="{{__('Password')}}" aria-describedby="basic-addon1">
                                </div>
                            </div>
                            <div class="col-6">
                                <label>{{__('Confirm Password')}} @include('star')</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fa fa-key"></i></span></div>
                                    <input required id="password-confirm" type="password" class="form-control" name="password_confirmation" placeholder="{{__('Password')}}" aria-describedby="basic-addon1">
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>


                <div class="kt-portlet">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title head-title text-primary">
                                {{__('Assign Companies To This User')}}
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body">
                        <div class="form-group row col-12">

                            <div class="col-6">
                                <label>{{__('Select Companies - (Multi Selection)')}} </label>
                                <select required name="companies[]" class="form-control kt-selectpicker" multiple>
                                    @php $selectedcompanies = isset($user) ?  $user->companies->pluck('id')->toArray() : []; @endphp
                                    @foreach ($companies as $item)
                                        <option {{ old('companies') == $item->id || in_array($item->id, $selectedcompanies) ? 'selected' : ''}}  value="{{$item->id}}">{{$item->name[$lang]}}</option>
                                    @endforeach
                                </select>

                            </div>
                            <div class="col-6">
                                <label>{{__('Role')}} </label>
                                <select required id="role-select-id" name="role" class="form-control kt-selectpicker"  >
                                    <option value="">{{__('Select')}}</option>
	                                    @if(auth()->user()->isSuperAdmin() || (isset($user) && $user->hasRole(User::SUPER_ADMIN) ))
										<option   value="{{ User::SUPER_ADMIN }}" @if(isset($user) && $user->hasRole(User::SUPER_ADMIN)) selected @endif > {{__("Super Admin")}}</option>
										@endif 
										@if(auth()->user()->can('create company admin') || (isset($user) && $user->hasRole(User::COMPANY_ADMIN) ))
										<option   value="{{ User::COMPANY_ADMIN }}" @if(isset($user) && $user->hasRole(User::COMPANY_ADMIN)) selected @endif > {{__("Company Admin")}}</option>
										@endif
										@if(auth()->user()->can('create manager') || (isset($user) && $user->hasRole(User::MANAGER) ))
										<option   value="{{ User::MANAGER }}" @if(isset($user) && $user->hasRole(User::MANAGER)) selected @endif > {{__("Manager")}}</option>
										@endif
										@if(auth()->user()->can('create user') || (isset($user) && $user->hasRole(User::USER) ))
										<option   value="{{ User::USER }}" @if(isset($user) && $user->hasRole(User::USER)) selected @endif > {{__("User")}}</option>
										@endif
                                </select>

                            </div>
							<div class="col-6 max-users-div mt-3">
								   <label>{{__('Max Users Allowed')}} @include('star')</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">#</span></div>
                                    <input type="text" name="max_users" value="{{$user_row['max_users'] ?? 10 }}" class="form-control" placeholder="{{__('Max Users Allowed')}}" aria-describedby="basic-addon1">
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
</div>
@endsection
@section('js')
    <!--begin::Page Scripts(used by this page) -->
    <script src="{{url('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js')}}" type="text/javascript"></script>
    <script src="{{url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-select.js')}}" type="text/javascript"></script>
    <!--end::Page Scripts -->
	<script>
	$(function(){
		$(document).on('change','#role-select-id',function(){
			const value = $(this).val();
			if(value == 'company-admin'){
				$('.max-users-div').fadeIn(500)
			}else{
				$('.max-users-div').fadeOut(500)
			}
		});
		$('#role-select-id').trigger('change')
	})
	</script>
@endsection
