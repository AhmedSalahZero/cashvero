{{-- @if($model->isExpired()) --}}
                                            <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="{{ __('Renewal') }}" href="{{ route('letter.of.issuance.renewal.date',['company'=>$company->id,'letterOfGuaranteeIssuance'=>$model->id,'source'=>$model->getSource()]) }}"><i class="fa fa-sync-alt"></i></a>
{{-- @endif --}}
