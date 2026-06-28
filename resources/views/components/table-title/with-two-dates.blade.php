@props([
'title','startDate','endDate',
'type'=>''
])
@php
$startDateId = $type ? 'startDate_'.$type : 'startDate';
$endDateId = $type ? 'endDate_'.$type : 'endDate';
$startDateInputName = $type ? 'startDate['. $type .']' : 'startDate';
$endDateInputName = $type ? 'endDate['. $type .']' : 'endDate';
@endphp
<div class="kt-portlet__head kt-portlet__head--lg p-0">
    <div class="kt-portlet__head-label ml-4" style="flex:2.5;">
        <span class="kt-portlet__head-icon">
            <i class="kt-font-secondary  text-main-color btn-outline-hover-danger fa fa-layer-group"></i>
        </span>
        <h3 style="font-size:20px !important;" class="kt-portlet__head-title text-main-color text-nowrap">
            {{ $title }}
        </h3>
  
        <form class="w-full flex-2  " @if($lang=='ar' ) style="margin-right:5rem !important" @else style="margin-left:5rem !important" @endif>
            <input type="hidden" name="active" value="{{ $type }}">
            <div class="row align-items-center ">
                <div class="col-md-3 d-flex align-items-center " @if($lang=='ar' ) style="margin-left:5rem !important" @else style="margin-right:5rem !important" @endif>
                    <label for="{{ $startDateId }}" class="text-nowrap mr-3">{{ __('Start Date') }}</label>
                    <input id="{{ $startDateId }}" type="date" value="{{ $startDate }}" class="form-control" name="{{ $startDateInputName }}">
                </div>
                <div class="col-md-3 d-flex align-items-center">
                    <label for="{{ $endDateId }}" class="text-nowrap mr-3">{{ __('End Date') }}</label>
                    <input type="date" value="{{ $endDate??'' }}" class="form-control" id="{{ $endDateId }}" name="{{ $endDateInputName }}">
                </div>
                <div @if($lang=='ar' ) style="margin-right:2rem !important" @else style="margin-left:2rem !important" @endif class="col-md-2 d-flex justify-content-center">
                    <label for="button"></label>
                    <button style="width:70px !important;font-size:1rem !important;" type="submit" class="btn block form-control btn-primary btn-sm "> {{ __('Submit') }}</button>

                </div>


            </div>
        </form>
    </div>
    {{ $slot }}
</div>
