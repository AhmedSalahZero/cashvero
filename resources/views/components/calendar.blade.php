@props([
'label'=>$label ?? '' ,
'id'=>$id ,
'name'=>$name ,
'value'=>$value ?? null,
'required'=>true,
'showLabel'=>true,
'onlyMonth'=>true,
'classes'=>''
])

@if($label && $showLabel)
<x-form.label :class="'label'" :id="$id"> {{ $label }}

    @if($required)
    @include('star')
    @endif
</x-form.label>
@endif
<div class="kt-input-icon">
    <div class="input-group date">

        <input
		 @if($required)
		 required
    @endif
		 @if($id) id="{{ $id }}" @endif
		 type="text" name="{{ $name }}" class="@if($onlyMonth)  only-month-year-picker @endif datepicker-input date-input form-control recalc-end-date start-date {{ $classes }} " value="{{$value}} " autocomplete="off" />
    </div>
</div>
