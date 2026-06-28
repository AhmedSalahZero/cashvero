<div class="kt-portlet" >
    <div class="kt-portlet__foot" style="padding:10px !important;">
        <div class="kt-form__actions">
            <div class="row">
                <div class="col-lg-6">
				@if(isset($hint) && $hint)
				<p class="text-blue font-weight-bold">{{ $hint }}	</p>			
				@endif
           
                </div>
                <div class="col-lg-6 kt-align-right">
                    <button  type="submit" class="btn active-style">{{ __('Save') }}</button>
                    {{-- <button type="reset" class="btn btn-secondary">{{__('Cancel')}}</button> --}}
                </div>
            </div>
        </div>
    </div>
</div>
