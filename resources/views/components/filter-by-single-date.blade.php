@props([
	'filterDate'=>'',
])
    <form class="search-bar d-flex grow align-items-center ml-3	mr-auto">
                            <div class="form-group mr-3">
                                <label class="label visibility-hidden">{{ __('Date') }}</label>
                                <input value="{{ $filterDate }}" name="filter_date" type="date" class="form-control">
                            </div>
                      
                            <button type="submit" class="btn btn-primary ">{{ __('Filter') }}</button>
                        </form>
