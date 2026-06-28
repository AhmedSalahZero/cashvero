<div class="kt-portlet">
    <div class="kt-portlet__foot">
        <div class="kt-form__actions">
            <div class="row">
                <div class="col-lg-6">
                    {{-- <button type="submit" class="btn btn-primary">Save</button>
                    <button type="reset" class="btn btn-secondary">Cancel</button> --}}
                </div>
                <div class="col-lg-6 kt-align-right">
                    <button type="submit" class="btn active-style">{{__('Next')}}</button>
                    @if(isset($report) && App\Models\ModifiedSeasonality::where('company_id', $companyId)->first())
                    <input type="submit" name="summary_report" id="subkit_summary_report_id" value="{{ __('Save And Go To Summary Report') }}"  class="btn btn-success">
                    @endif
                    {{-- <button type="reset" class="btn btn-secondary">{{__('Cancel')}}</button> --}}
                </div>
            </div>
        </div>
    </div>
</div>
