<style>
.max-w-btn{
	max-width:125px !important;
	min-width:125px !important;
}
</style>
@php
	$submitByAjax = isset($submitByAjax) ? $submitByAjax : true ;
@endphp
            <div class="row btn-for-submit--js {{ isset($isHidden)&&$isHidden ? 'd-none':'' }}">
                <div class="col-lg-6">
              
                </div>
                <div class="col-lg-6 kt-align-right">
                    <input data-save-and-continue="1"  type="submit" class="btn  text-white active-style {{ $submitByAjax ? 'save-form' :'' }}" value="{{ isset($text) ? $text : __('Save & Go To Next') }}">
                </div>
            </div>
        
