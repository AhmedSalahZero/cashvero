{{-- @if($model->isExpired()) --}}
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="{{ __('Renewal') }}" href="{{ route('time.of.deposit.renewal.date',['company'=>$company->id,'timeOfDeposit'=>$model->id]) }}"><i class="fa fa-sync-alt"></i></a>
{{-- @endif --}}
