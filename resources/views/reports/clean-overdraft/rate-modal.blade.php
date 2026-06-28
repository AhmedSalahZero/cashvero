<div class="modal fade inner-modal-class" id="edit-rates-{{ $rate->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            <form action="{{ route('clean-overdraft-edit-rates',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'rate'=>$rate->id ]) }}" method="post">
                                                @csrf
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Edit' )  }}</h5>
                                                    <button data-dismiss="modal" type="button" class="close" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>


                                                <div class="modal-body">
                                                    <div class="row mb-3 closest-parent">
                                                        @include('reports.clean-overdraft.rates-form',[
                                                        'rate'=>$rate
                                                        ])
                                                    </div>
                                                </div>

                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                                                    <button data-url="{{  route('clean-overdraft-edit-rates',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'rate'=>$rate->id]) }}" type="submit" class="btn btn-primary submit-form-btn">{{ __('Confirm') }}</button>
                                                </div>

                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal fade inner-modal-class" id="delete-rates-{{ $rate->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                        <div class="modal-content">
                                            <form action="" method="post">
                                                @csrf
                                                @method('delete')
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Do You Want To Delete This Item ?') }}</h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>

                                                    <a href="{{ route('clean-overdraft-delete-rate',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'rate'=>$rate->id ]) }}" class="btn btn-danger">{{ __('Confirm Delete') }}</a>
                                                </div>

                                            </form>
                                        </div>
                                    </div>
                                </div>
