@props([
'returnRedirectRoute'=>''
])
<div class="kt-portlet">
    <div class="kt-portlet__foot">
        <div class="kt-form__actions">
            <div class="row">
                <div class="col-lg-6">
                    {{-- <button type="submit" class="btn btn-primary">Save</button>
                    <button type="reset" class="btn btn-secondary">Cancel</button> --}}
                </div>
                <div class="col-lg-6 kt-align-right">
                    <button type="submit" class="btn active-style save-form">{{__('Save')}}</button>

                    <button type="submit" class="btn active-style save-form " data-redirect-to="{{ $returnRedirectRoute }}">{{__('Save & Close')}}</button>
                </div>
            </div>
        </div>
    </div>
</div>
