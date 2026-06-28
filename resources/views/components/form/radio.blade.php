	<div class="form-group d-inline-block">
	    <label class="form-label font-weight-bold">{{$title}}
	        @if($isRequired)
	        <span class="astric">*</span>
	        @endif
	    </label>
	    <div class="kt-radio-inline">
	        @foreach($radios as $index => $radioArr)
	        <label class="kt-radio {{ $radioArr['color_class'] ?? 'kt-radio--success' }}  text-black font-size-15px font-weight-bold">
	            <input
				@if(isset($radioArr['disabled']) && $radioArr['disabled'])
				disabled 
				@endif 
				 type="radio" value="{{ $radioArr['value'] }}" name="{{ $name }}" class="{{ $radioArr['radioClasses'] }} " @if($radioArr['is_checked']) checked @endif>
	            {{ $radioArr['label'] }}
	            <span></span>
	        </label>
	        @endforeach

	    </div>
	</div>
