<div class="kt-portlet">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-label">
            <h3 class="kt-portlet__head-title head-title text-primary">
                {{ __('Import By Excel') }}
            </h3>
        </div>
    </div>
    <div class="kt-portlet__body">
        <div class="form-group row">
            <div class="col-md-4">
                <a href="{{ route('download.letter.of.guarantee.issuance.template', ['company' => $company->id, 'source' => $source]) }}" class="btn btn-outline-primary btn-block">
                    {{ __('Download Template') }}
                </a>
            </div>
            <div class="col-md-5">
                <input type="file" class="form-control" id="lg-import-file" accept=".xlsx,.xls,.csv">
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-success btn-block" id="lg-import-upload-btn">{{ __('Upload Excel') }}</button>
            </div>
        </div>
        <div class="form-group row d-none" id="lg-import-status-wrapper">
            <div class="col-md-12">
                <div class="alert alert-info" id="lg-import-status-text">{{ __('Processing...') }}</div>
                <div id="lg-import-errors" class="small text-danger"></div>
            </div>
        </div>
    </div>
</div>
