@props([
'title'=>$title,
'helperTitle'=>$hasHelper ?? '',
'fontSizeClass'=>null,
'icon'=>false
])

<th {{ $attributes->merge(['class'=>'form-label font-weight-bold  text-center align-middle']) }}>
	<div class="d-flex align-items-center justify-content-center ">
	<span class="{{ $fontSizeClass }}">{!! $title !!}</span>
    @if($helperTitle)
    <span class="kt-input-icon__icon kt-input-icon__icon--right ml-2" tabindex="0" role="button" data-toggle="kt-tooltip" data-trigger="focus" title="{{ str_replace('{title}',$title,$helperTitle) }}">
        <span><i class="fa fa-question text-primary"></i></span>
    </span>
    @endif
	    @if($icon)
    <span class="kt-input-icon__icon kt-input-icon__icon--right ml-2" tabindex="0" role="button" data-toggle="kt-tooltip" data-trigger="focus" >
        <span><i title="{{ __('Expand / Collapse') }}" class=" cursor-pointer fa fa-expand-arrows-alt text-primary exclude-icon"></i></span>
    </span>
    @endif
	</div>

</th>
