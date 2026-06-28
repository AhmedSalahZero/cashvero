@props([
	'model',
	'label'=>'',
	'classes'=>'',
	'useOldValue'=>false,
	'name',
	'id'=>'',
	'placeholder'=>'',
	'required'=>$required??false ,
	'readonly'=>false,
	'type'=>'date',
	'defaultValue'=>null
])
@if($label)
<label>
{{ $label }}
@if($required)
@include('star')
@endif 

</label>
@endif

@php
	if($name == 'contract_start_date' && !$model){
		$defaultValue = date("Y").'-01-01';
	}
	if($name == 'contract_end_date' && !$model){
		$defaultValue =date("Y").'-12-31' ;
	}
	
	$defaultValue = old($name) ? old($name):$defaultValue;
	
@endphp
                                <div class="kt-input-icon">
                                    <div class="input-group date">
                                        <input
										@if($readonly)
										readonly
										@endif
										
										
										 @if($id)  id="{{ $id }}" @endif type="{{ $type }}" name="{{ $name }}" value="{{ isset($defaultValue) ? $defaultValue : ($model && $model->{$name} ? $model->{$name} : '' ) }}" class="form-control {{ $classes }}"  @if($placeholder) placeholder="{{ $placeholder }}" @endif />
										
                                        <div class="input-group-append">
                                            <span class="input-group-text">
                                                <i class="la la-calendar-check-o"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
