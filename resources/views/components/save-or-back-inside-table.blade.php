<style>
.max-w-btn{
	max-width:125px !important;
	min-width:125px !important;
}
</style>
            <div class="row btn-for-submit--js {{ isset($isHidden)&&$isHidden ? 'd-none':'' }}">
                <div class="col-lg-6">
              
                </div>
                <div class="col-lg-6 kt-align-right">
                    <input data-save-and-add-new-department="0" type="submit" class="btn max-w-btn active-style save-form" value="{{ isset($text) ? $text : __('Save Changes') }}">
					{{-- @if($department)
                    <input data-save-and-add-new-department="1"  type="submit" class="btn  text-white bg-green save-form" value="{{ isset($text) ? $text : __('Save & Add New Department') }}">
					@endif --}}
                </div>
            </div>
        
