@props([
    'datatableId'=>$datatableId ,
    'btnTitle'=>__('Apply'),
    'type'
])
<div class="text-center mt-4">
                   <button type="reset" class="btn  btn-light btn-submit  {{ $type }}-btn-class ">{{ __('Reset') }}</button>
                   <button data-datatable-id="{{ $datatableId }}" class="btn btn-submit btn-primary {{ $type }}-btn-class ">{{ $btnTitle }}</button>
               </div>
               