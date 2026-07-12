@php
    $accountTypeId = $accountTypeId ?? \App\Models\AccountType::where('model_name', class_basename($bankAccount))->value('id');
    $lockModalId = class_basename($bankAccount) . '-' . $bankAccount->id;
@endphp

@if(method_exists($bankAccount, 'isActive') && $accountTypeId)
    <a data-toggle="modal" data-target="#lock-or-unlock-{{ $lockModalId }}" type="button" class="btn btn-secondary @if(!$bankAccount->isActive()) btn-outline-danger @else btn-outline-success @endif btn-icon" title="{{ $bankAccount->isActive() ? __('Lock') : __('Unlock') }}" href="#"><i class="fa @if(!$bankAccount->isActive()) fa-lock @else fa-unlock @endif"></i></a>
    <div class="modal fade" id="lock-or-unlock-{{ $lockModalId }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form action="{{ route('lock.or.unlock.bank.account',['company'=>$company->id,'accountType'=>$accountTypeId,'accountId'=>$bankAccount->id]) }}" method="post">
                    @csrf
                    @method('put')
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLongTitle">{{ $bankAccount->isActive() ? __('Do You Want To Lock This Account ?') : __('Do You Want To Unlock This Account ?') }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                        <button type="submit" class="btn {{ $bankAccount->isActive() ? 'btn-danger' : 'btn-info'  }}">{{ $bankAccount->isActive() ? __('Confirm Lock') : __('Confirm Unlock') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
