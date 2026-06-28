@props([
'isMultiple'=>false,
'isPercentage',
'classes'=>'only-greater-than-zero-allowed',
'currentVal',
'columnIndex'=>$columnIndex ,
'name',
'numberFormatDecimals'=>2 ,
'inputHiddenAttributes'=>'',
'formattedInputClasses'=>'',
'removeThreeDots'=>false,
'removeCurrency'=>false,
'multiple'=>false,
'readonly'=>false,
'mark'=>'',
'removeThreeDotsClass'=>false,
'isNumber'=>true,
'disabled'=>false,
'dataCurrentYear'=>null,
'showIcon'=>false,
'currentModalId'=>'',
'justifyLeft'=>false,
'bgColor'=>null
])
<div class="
@if(!$removeThreeDotsClass)
form-group 
three-dots-parent
@endif 

">
    <div class="input-group input-group-sm align-items-center  flex-nowrap
	@if($justifyLeft)
		justify-content-start
		@else 
		
		justify-content-center
		
	@endif 
	
	">
        <div class="input-hidden-parent">

            <input
				@if($bgColor)
				style="background-color:{{ $bgColor }}"
				@endif 
			 data-number-of-decimals="{{ $numberFormatDecimals }}" @if($readonly) readonly @endif @if($disabled) disabled @endif @if($name) data-name="{{ removeSquareBrackets($name) }}" @endif onchange="this.style.width = ((this.value.length + 1) * 10) + 'px';" class="form-control copy-value-to-his-input-hidden 

			 @if($isPercentage) expandable-percentage-input  @else expandable-amount-input @endif
			  repeat-to-right-input-formatted  {{ $formattedInputClasses }} " type="text" value="{{ $isNumber ?  number_format($currentVal,$numberFormatDecimals) : $currentVal  }}" @if(!is_null($columnIndex)) data-column-index="{{ $columnIndex }}" @endif>
            <input @if(!is_null($dataCurrentYear)) data-current-year-index="{{ $dataCurrentYear }}" @endif data-number-of-decimals="{{ $numberFormatDecimals }}" @if($multiple) multiple @endif {{ $inputHiddenAttributes  }} {{ $attributes->merge([]) }} type="hidden" data-name="{{ removeSquareBrackets($name) }}" class=" input-hidden-with-name  {{ $classes }}" value="{{ $currentVal  }}" @if(!is_null($columnIndex)) data-column-index="{{ $columnIndex }}" @endif @if($isMultiple) multiple @endif @if($name) name="{{ $name }}" @endif>
        </div>
        <div>
            @if($showIcon)
            <i data-toggle="modal" data-target="#{{ $currentModalId }}" class="flaticon2-information recalculate-decrease-rates kt-font-primary exclude-icon ml-2 cursor-pointer "></i>

            @endif
            @if($mark)
            <span class="ml-2 currency-class">{{ $mark }}</span>
            @endif
        </div>
        @if(!$removeCurrency && !$mark)
        @if($isPercentage)
        <span class="ml-2">%</span>
        @else
        <span class="ml-2 currency-class">
            {{ $company->getMainFunctionalCurrency() }}
        </span>

        @endif
        @endif
    </div>
    @if(!$removeThreeDots)
    <i data-name="{{ removeSquareBrackets($name) }}" class="fa
	

	 fa-ellipsis-h pull-left repeat-to-right row-repeater-icon " data-column-index="{{ $columnIndex}}" data-section="target" title="{{__('Repeat Right')}}"></i>
    @elseif(!$removeThreeDotsClass)


    <i class="fa fa-ellipsis-h pull-left repeat-to-right row-repeater-icon visibility-hidden"></i>
    @endif
</div>
