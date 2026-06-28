<a data-toggle="modal" data-target="#apply-rate-for-{{ $fullySecuredOverdraft->id }}" type="button" class="btn  btn-secondary btn-outline-hover-success   btn-icon" title="{{ __('Update Interest Rate	') }}" href="#"><i class=" fa fa-percentage"></i></a>
<div class="modal fade" id="apply-rate-for-{{ $fullySecuredOverdraft->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <form action="{{ route('fully-secured-overdraft-apply.rates',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'fullySecuredOverdraft'=>$fullySecuredOverdraft->id ]) }}" method="post">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Rates Information' )  }}</h5>
                    <button type="button" class="close" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>


                <div class="modal-body">

                    <div class="row mb-3 closest-parent">
@if(hasAuthFor('create fully secured overdraft'))
                        @include('reports.fully-secured-overdraft.rates-form' , [])
@endif 




                        <div class="col-md-12">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>{{ __('#') }}</th>
                                            <th>{{ __('Date') }}</th>
                                            <th>{{ __('Borrowing Rate') }}</th>
                                            <th>{{ __('Margin Rate') }}</th>
                                            <th>{{ __('Interest Rate') }}</th>
									
                                            <th>{{ __('Actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($fullySecuredOverdraft->rates as $index=>$rate)
                                        <tr>
                                            <td> {{ ++$index }} </td>

                                            <td> {{ $rate->getDateFormatted() }} </td>
                                            <td> {{ $rate->getBorrowingRateFormatted() }} </td>
                                            <td> {{ $rate->getMarginRateFormatted() }} </td>
											
                                            <td> {{ $rate->getInterestRateFormatted() }} </td>
										
                                            <td>
                                                @if($loop->last)
												@if(hasAuthFor('update fully secured overdraft'))
                                                <a data-toggle="modal" data-target="#edit-rates-{{ $rate->id }}" type="button" class="btn btn-secondary btn-outline-hover-primary btn-icon" type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="#"><i class="fa fa-pen-alt"></i></a>
												@endif 
												@if(hasAuthFor('delete fully secured overdraft'))
                                                <a data-toggle="modal" data-target="#delete-rates-{{ $rate->id }}" type="button" class="btn btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt"></i></a>
												@endif 
                                                @endif

                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>


                </div>


                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Confirm') }}</button>
                </div>

            </form>




        </div>
    </div>
</div>
