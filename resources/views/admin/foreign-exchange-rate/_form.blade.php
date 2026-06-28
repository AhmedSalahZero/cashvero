 <form method="post" action="{{ isset($model) ? route('update.foreign.exchange.rate',['company'=>$company->id, 'foreignExchangeRate'=>$model->id]) :route('store.foreign.exchange.rate',['company'=>$company->id ]) }}" class="kt-form kt-form--label-right">
                            @csrf
                            @if(isset($model))
                            @method('patch')
                            @endif
                            <div class="kt-portlet">
                                <div class="kt-portlet__head">
                                    <div class="kt-portlet__head-label">
                                        <h3 class="kt-portlet__head-title head-title text-primary">
                                            {{__('Foreign Exchange Rates Section')}}
                                        </h3>
                                    </div>
                                </div>
                                <div class="kt-portlet__body">
                                    <div class="form-group row">
                                        <div class="col-md-2 mb-4">
                                            <label>{{__('Date')}} </label>
                                            <input name="date" type="date" class="form-control" value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d')  }}">
                                        </div>

                                        <div class="col-md-2">
                                            <label>{{__('From Currency')}}
                                                @include('star')
                                            </label>
                                            <div class="input-group">
                                                <select name="from_currency" id="from-currency-{{ $existingCurrency }}" class="form-control js-change-currency">
                                                    {{-- <option selected>{{__('Select')}}</option> --}}
                                                    @foreach(getCurrencies() as $currencyName => $currencyValue )
                                                    <option value="{{ $currencyName }}" @if(isset($model) && $model->getFromCurrency() == $currencyName ) selected @elseif($currencyName == $existingCurrency ) selected @endif > {{ $currencyValue }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>


                                        <div class="col-md-2">
                                            <label>{{__('To Currency')}}
                                                @include('star')
                                            </label>
                                            <div class="input-group">
                                                <select name="to_currency" id="to-currency" class="form-control js-change-currency ">
                                                    {{-- <option selected>{{__('Select')}}</option> --}}
                                                    @foreach(getCurrencies() as $currencyName => $currencyValue )
                                                    <option value="{{ $currencyName }}" @if(isset($model) && $model->getToCurrency() == $currencyName ) selected @elseif($currencyName == 'EGP' ) selected @endif > {{ $currencyValue }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-1">
                                            <label for="" class="visibility-hidden">d</label>
                                            <input id="from-currency-input" type="text" value="1 {{ $currentTab }} = " disabled class="form-control"> <span></span>
                                        </div>
                                        <div class="col-md-2 mb-4">
                                            <label>{{__('Exchange Rate')}} </label>
                                            <input name="exchange_rate" type="text" class="form-control only-greather-than-zero" value="{{ isset($model) ? $model->getExchangeRate() : 1 }}">
                                        </div>
                                        <div class="col-md-1">
                                            <label for="" class="visibility-hidden">d</label>
                                            <input id="to-currency-input" type="text" value="EGP" disabled class="form-control"> <span></span>
                                        </div>

                                        <div class="col-md-1">
                                            <label for="" class="visibility-hidden">d</label>
                                            <button type="submit" class="btn active-style save-form form-control">{{__('Save')}}</button>
                                        </div>


                                    </div>
                                </div>
                            </div>



                        </form>
