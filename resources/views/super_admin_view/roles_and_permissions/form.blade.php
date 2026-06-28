@extends('layouts.dashboard')
@section('css')
    <link href="{{url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css')}}" rel="stylesheet" type="text/css" />
    @endsection
@section('content')
@php
	use App\Models\User;
@endphp


<div class="row">
    <div class="col-lg-12">
        <!--begin::Portlet-->
        <div class="kt-portlet">
            <div class="kt-portlet__head">
                <div class="kt-portlet__head-label">
                    <h3 class="kt-portlet__head-title head-title text-primary">
                        {{-- {{__(isset($roles) ?'Edit Section' : 'Create Section')}} --}}
						{{ __('Permissions') }}
                    </h3>
                </div>
            </div>
        </div>

            <!--begin::Form-->
          
            <form class="kt-form kt-form--label-right" method="POST" 
			{{-- action= {{isset($role) ? route('roles.permissions.update',[$scope,$role]): route('roles.permissions.store',$scope)}} --}}
			action="{{ route('roles.permissions.update',$company ? [$company->id] : []) }}"
			 enctype="multipart/form-data">
                @csrf
                {{isset($role) ?  method_field('POST'): ""}}
                <div class="kt-portlet">
                    <div class="kt-portlet__body">
                        <div class="form-group row section">
						   <div class="col-md-4">
                                <label>{{__('Company')}} </label>
                                <select id="company-select-id" update-users-based-on-company-and-role required name="company_id" class="form-control kt-selectpicker" >
                                    @php $selectedcompanies = isset($user) ?  $user->companies->pluck('id')->toArray() : []; @endphp
                                    @foreach ($companies as $item)
                                        <option {{ old('company_id') == $item->id || in_array($item->id, $selectedcompanies) ? 'selected' : ''}}  value="{{$item->id}}">{{$item->name[$lang]}}</option>
                                    @endforeach
                                </select>

                            </div> 
							
  							<div class="col-md-4">
                                <label>{{__('Role')}} </label>
                                <select  required id="role-select-id" name="role_id" class="form-control kt-selectpicker" update-users-based-on-company-and-role >
	                                    @if(auth()->user()->isSuperAdmin() || (isset($user) && $user->hasRole(User::SUPER_ADMIN) ))
										<option   value="{{ User::SUPER_ADMIN }}" @if(isset($user) && $user->hasRole(User::SUPER_ADMIN) || old('role_id') ==User::SUPER_ADMIN  ) selected @endif > {{__("Super Admin")}}</option>
										@endif 
										@if(auth()->user()->can('create company admin') || (isset($user) && $user->hasRole(User::COMPANY_ADMIN) ))
										<option   value="{{ User::COMPANY_ADMIN }}" @if(isset($user) && $user->hasRole(User::COMPANY_ADMIN) || old('role_id') ==User::COMPANY_ADMIN) selected @endif > {{__("Company Admin")}}</option>
										@endif
										@if(auth()->user()->can('create manager') || (isset($user) && $user->hasRole(User::MANAGER) ))
										<option   value="{{ User::MANAGER }}" @if(isset($user) && $user->hasRole(User::MANAGER) || old('role_id') ==User::MANAGER ) selected @endif > {{__("Manager")}}</option>
										@endif
										@if(auth()->user()->can('create user') || (isset($user) && $user->hasRole(User::USER) ))
										<option   value="{{ User::USER }}" @if(isset($user) && $user->hasRole(User::USER) || old('role_id') ==User::USER) selected @endif > {{__("User")}}</option>
										@endif
                                </select>

                            </div>
                                    <div class="col-md-4">
                                        <label>{{ __('User') }} <span class=""></span> </label>
                                        <div class="kt-input-icon">
                                            <div class="input-group date">
                                                <select
												 id="user-id"  data-current-selected="{{ isset($model) ? $model->getUserId(): old('user_id') }}" name="user_id" class="form-control role-users">
                                                    {{-- <option value="" selected>{{__('Select')}}</option> --}}
                                                </select>
                                            </div>
                                        </div>
                                    </div>
									
{{-- 
                                <div class="col-md-12">
                                    <label>{{__('Role Name')}} @include('star')</label>
                                    <div class="kt-input-icon">
                                        <input type="text" name="role" value="{{isset($role) ? $role->name : old('name')}}" class="form-control" placeholder="{{__('Role Name')}}" required>
                                        <x-tool-tip title="{{__('Kash Vero')}}"/>
                                    </div>
                                </div> --}}

                        </div>
                    </div>
                </div>

                <div class="kt-portlet">
                    <div class="kt-portlet__head">
                        <div class="kt-portlet__head-label">
                            <h3 class="kt-portlet__head-title head-title text-primary">
                                {{__('Permissions')}}
                            </h3>
                        </div>
                    </div>
                    <div class="kt-portlet__body" id="append-permission-views">
                        {{-- @include('super_admin_view.roles_and_permissions.permissions-radio') --}}
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
    <!--end::Page Scripts -->
    <script>
        $(document).on('change','#select_all',function(e) {
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
	<script>
	$(document).on('change','[update-users-based-on-company-and-role]',function(e){
		const companyId = $('select#company-select-id').val();
		const roleName = $('select#role-select-id').val();
		const currentUserSelect = $('select#user-id').attr('data-current-selected')
	
		if(roleName && companyId){
			$.ajax({
			url:"{{ route('update.users.based.on.company.and.role') }}",
			data:{
				companyId,
				roleName
			},
			type:"get",
			success:function(res){
				const users = res.users
				let userOptions = '';
				for(var i = 0 ; i <users.length ; i++){
					var selected = currentUserSelect == users[i].id ? 'selected':''
					userOptions +=' <option '+ selected +' value="'+users[i].id+'" >'+ users[i].name +'</option>';
				}
				$('select#user-id').empty().append(userOptions).trigger('change')
			}
		})
		}
		
	})
	$(document).on('change','select#user-id',function(){
		const userId = $('select#user-id').val();
		const companyId = $('select#company-select-id').val()
		if(userId){
			$.ajax({
				url:"{{ route('render.permissions.html.for.user') }}",
				data:{
					userId,
					companyId
				},
				success:function(res){
					$('#append-permission-views').empty().append(res.view)
				}
			})
		}else{
			$('#append-permission-views').empty()
		}
	})
	$('[update-users-based-on-company-and-role]:eq(0)').trigger('change');
	</script>
@endsection
