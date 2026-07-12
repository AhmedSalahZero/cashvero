@extends('layouts.dashboard')
@section('css')
    <link href="{{url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css')}}" rel="stylesheet" type="text/css" />
    <link href="{{url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css')}}" rel="stylesheet" type="text/css" />
    <style>
        .money-flow-dark input,
        .money-flow-dark select,
        .money-flow-dark .dropdown-toggle.bs-placeholder {
            border: 1px solid #CCE2FD !important;
        }

        .money-flow-dark label {
            text-align: left !important;
        }

        .money-flow-dark .kt-portlet {
            overflow: visible !important;
        }
    </style>
@endsection
@section('sub-header')
    {{ __('My Profile') }}
@endsection
@section('content')
<div class="money-flow-dark">
<div class="row">
    <div class="col-12">
        <div class="kt-portlet">
            <div class="kt-portlet__head">
                <div class="kt-portlet__head-label">
                    <h3 class="kt-portlet__head-title head-title text-primary">
                        {{__('My Profile')}}
                    </h3>
                </div>
            </div>
        </div>

        <form class="kt-form kt-form--label-right" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="kt-portlet">
                <div class="kt-portlet__body">
                    <div class="form-group row col-12">
                        <div class="col-12">
                            <label>{{__('Name')}} @include('star')</label>
                            <div class="kt-input-icon">
                                <input type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control" placeholder="{{__('Name')}}" required>
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
                                <input required type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control" placeholder="{{__('Email')}}" aria-describedby="basic-addon1">
                            </div>
                        </div>
                        <div class="col-6">
                            <label>{{__('User Image')}}</label>
                            <div class="kt-input-icon">
                                @if($user->getFirstMediaUrl())
                                    <div class="mb-3">
                                        <img class="index-img" width="100" height="100" src="{{ $user->getFirstMediaUrl() }}" alt="image">
                                    </div>
                                @endif
                                <input type="file" class="form-control" name="avatar">
                                <x-tool-tip title="{{__('Kash Vero')}}"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if($hasOdooCredentials)
            <div class="kt-portlet">
                <div class="kt-portlet__head">
                    <div class="kt-portlet__head-label">
                        <h3 class="kt-portlet__head-title head-title text-primary">
                            {{__('Odoo Credentials')}}
                        </h3>
                    </div>
                </div>
                <div class="kt-portlet__body">
                    <div class="form-group row col-12">
                        <div class="col-6">
                            <label>{{__('Odoo  User Name')}}</label>
                            <div class="kt-input-icon">
                                <input type="text" name="odoo_username" value="{{ old('odoo_username', $user->odoo_username) }}" class="form-control" placeholder="{{__('Odoo  User Name')}}">
                            </div>
                        </div>
                        <div class="col-6">
                            <label>{{__('Odoo Database Password / API Key')}}</label>
                            <div class="kt-input-icon">
                                <input type="text" name="odoo_db_password" value="{{ old('odoo_db_password', $user->odoo_db_password) }}" class="form-control" placeholder="{{__('Odoo Database Password / API Key')}}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <x-submitting/>
        </form>
    </div>
</div>
</div>
@endsection
