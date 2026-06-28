 @if($company->hasOdooIntegrationCredentials() && $model->hasOdooError() )
 <a data-toggle="modal" data-target="#odoo-model-{{ $model->id }}" type="button" class="btn  btn-icon bg-red text-white" title="{{ __('Odoo Error') }}" href="#"><i class="fa fa-bug "></i></a>
 <div class="modal fade" id="odoo-model-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
     <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
         <div class="modal-content">
             <form action="{{ route('resend.with.odoo',['company'=>$company->id , 'moneyReceived'=>$model->id]) }}" method="post">
                 @csrf
                 <div class="modal-header">
                     <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Odoo Error') }}</h5>
                     <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                         <span aria-hidden="true">&times;</span>
                     </button>
                 </div>
				 <div class="modal-body">
				 	<h2 class="text-wrap {{ \App\Helpers\HStr::isArabic($model->getOdooError()) ? 'text-right' : 'text-left' }}">{{ $model->getOdooError()  }}</h2>
				 </div>
                 <div class="modal-footer">
                     <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                     <button type="submit" class="btn btn-success">{{ __('Resend') }}</button>
                 </div>

             </form>
         </div>
     </div>
 </div>
@endif 
