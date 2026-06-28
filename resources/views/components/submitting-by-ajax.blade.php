
<div class="kt-portlet">
    <div class="kt-portlet__foot">
        <div class="kt-form__actions">
            <div class="row">
                <div class="col-lg-6">
                    {{-- <button type="submit" class="btn btn-primary">Save</button>
                    <button type="reset" class="btn btn-secondary">Cancel</button> --}}
                </div>
					<div class="col-lg-6 kt-align-right">
						@if(isset($backTo))
						<a href="{{ $backTo }}" type="submit" class="btn active-style">{{__('Close')}}</a>
						@endif
						<button
						@if(isset($validation))
						data-validation="1"
						@endif
						 type="submit" class="btn active-style submit-form-btn">{{__('Save')}}</button>
					</div>
            </div>
        </div>
    </div>
</div>
