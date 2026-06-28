<div class="kt-portlet">
    <div class="kt-portlet__foot">
        <div class="kt-form__actions">
            <div class="row btn-for-submit--js {{ isset($isHidden)&&$isHidden ? 'd-none':'' }}">
                <div class="col-lg-6">
              
                </div>
                <div class="col-lg-6 kt-align-right">
                    <input  type="submit" class="btn disabled active-style save-form" value="{{ isset($text) ? $text : __('Save & Go To Next') }}">
                </div>
            </div>
        </div>
    </div>
</div>
@push('js_last')
	<script>
		$(function(){
			$('input.save-form.disabled').removeClass('disabled')
		})
	</script>
@endpush
