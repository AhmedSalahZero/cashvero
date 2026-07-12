@props([
    'hasBatchCollection',
    'hasSearch',
    'moneyReceivedType',
    'searchFields',
    'factoringCompany',
])
<div class="kt-portlet__head-toolbar">
    <div class="kt-portlet__head-wrapper">
        <div class="kt-portlet__head-actions">
            &nbsp;

            @if($hasSearch)
            <a data-type="multi" data-toggle="modal" data-target="#search-money-modal-{{ $moneyReceivedType }}" href="#" title="{{ __('Search') }}" class="btn active-style btn-icon-sm">
                <i class="fas fa-search"></i>
                {{ __('Search') }}
            </a>

            <div class="modal fade" id="search-money-modal-{{ $moneyReceivedType }}" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-xl" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Search Form') }}</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <form action="{{ route('factoring.contracts.index', ['company' => $company->id, 'factoringCompany' => $factoringCompany->id]) }}" class="row">
                                <div class="form-group col-4">
                                    <label class="label">{{ __('Field Name') }}</label>
                                    <select class="form-control js-search-modal" data-type="{{ $moneyReceivedType }}" name="field">
                                        @foreach($searchFields as $name => $value)
                                            <option @if(Request('field') == $name) selected @endif value="{{ $name }}">{{ $value }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-4">
                                    <label class="label">{{ __('Search Text') }}</label>
                                    <input name="value" type="text" value="{{ request('value') }}" placeholder="{{ __('Search Text') }}" class="form-control search-field">
                                </div>
                                <div class="form-group col-2">
                                    <label class="label">{{ __('From') }} <span class="data-type-span">{{ __('[ Created At ]') }}</span></label>
                                    <input name="from" type="date" value="{{ request('from') }}" class="form-control">
                                </div>
                                <div class="form-group col-2">
                                    <label class="label">{{ __('To') }} <span class="data-type-span">{{ __('[ Created At ]') }}</span></label>
                                    <input name="to" type="date" value="{{ request('to') }}" class="form-control">
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-primary">{{ __('Search') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
