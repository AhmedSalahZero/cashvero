@props([
	'startDate'=>'',
	'endDate'=>''
])
    <form class="search-bar d-flex grow align-items-center ml-3	mr-auto">
                            <div class="form-group mr-3">
                                <label class="label">{{ __('Start Date') }}</label>
                                <input value="{{ $startDate }}" name="filter_start_date" type="date" class="form-control">
                            </div>
                            <div class="form-group mr-3">
                                <label class="label">{{ __('End Date') }}</label>
                                <input value="{{ $endDate }}" name="filter_end_date" type="date" class="form-control">
                            </div>
                            <button type="submit" class="btn btn-primary ">{{ __('Filter') }}</button>
                        </form>
