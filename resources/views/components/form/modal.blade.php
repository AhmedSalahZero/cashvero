@props([
    'title' , 
    'tableId',
    'type',
    'exportRoute'
])
<form {{ $attributes->merge(['class'=>'position-absolute custom-export px-3 rounded close-when-clickaway d-none']) }} id="{{ $type }}_form-for-{{ $tableId }}" >
            <div class="pb-3 pt-4 mb-3 px-1 border-bottom">
                <h5 class="text-dark"> {{$title}} </h5>
            </div>
            {{ $slot }}
</form>