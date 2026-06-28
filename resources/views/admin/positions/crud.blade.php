@extends('layouts.dashboard')
@section('css')
<x-styles.commons></x-styles.commons>
<style>

</style>
<style>
[data-repeater-delete]{
	margin-top:15px !important;
}
</style>

@endsection
@section('sub-header')
<x-main-form-title :id="'main-form-title'" :class="''">{{ __('Positions') }}</x-main-form-title>
@endsection
@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="kt-portlet">


            <div class="kt-portlet__body">
                <form class="kt-form kt-form--label-right" method="POST" action="{{isset($model) ? $updateRoute : $storeRoute}}">
                    {{ csrf_field() }}
                    {{isset($model) ?  method_field('PUT') : '' }}
                    <div class="kt-portlet__body">
                        <div class="row">
                            <div @if(!isset($model)) id="m_repeater_2" @endif class="w-100">
                                <div class="form-group  m-form__group row">
                                    <div @if(!isset($model)) data-repeater-list="positions" @endif class="col-lg-12">
                                        <div data-repeater-item class="form-group m-form__group row align-items-center repeater_item">
                                            <div class="col-md-6">
                                                <label>{{__('Name')}}<span class="astric">*</span></label>
                                                <div class="m-form__group m-form__group--inline">
                                                    <div class="m-form__control">
                                                        <input value="{{ isset($model )  ? $model->getName() : null }}" type="text" name="name" required="required" class="form-control m-input" placeholder="{{__('Enter')}} {{__('Name')}}" />
                                                    </div>
                                                </div>
                                                <div class="d-md-none m--margin-bottom-10"></div>
                                            </div>
                                            <div class="col-md-6">
                                                <label>{{__('Position Type')}}<span class="astric">*</span></label>
                                                <div class="m-form__group m-form__group--inline">
                                                    <div class="m-form__control">
                                                        <select name="position_type" class="form-control">
                                                            @foreach( $positionTypes as $id => $name )
                                                            <option value="{{$id  }}" @if(isset($model )? $id==$model->position_type :false ) selected @endif >{{$name}}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="d-md-none m--margin-bottom-10"></div>
                                            </div>




                                            @if(!isset($model))
                                            <div class="">
                                                <i data-repeater-delete="" class="btn-sm btn btn-danger m-btn m-btn--icon m-btn--pill trash_icon fas fa-times-circle">

                                                </i>
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @if(!isset($model))
                                <div class="m-form__group form-group row">

                                    <div class="col-lg-6">
                                        <div data-repeater-create="" class="btn btn btn-sm btn-success m-btn m-btn--icon m-btn--pill m-btn--wide {{__('right')}}" id="add-row">
                                            <span>
                                                <i class="fa fa-plus"> </i>
                                                <span>
                                                    {{ __('Add') }}
                                                </span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            </div>

                        </div>

                    </div>
            <x-save-btn />
                   
                </form>
            </div>
        </div>
    </div>
    @endsection
    @section('js')
    <x-js.commons></x-js.commons>



    @endsection
