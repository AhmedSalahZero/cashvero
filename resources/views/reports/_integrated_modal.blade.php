 @if(
	$company->hasOdooIntegrationCredentials() && 
 $model->fullyIntegratedWithOdoo())
<style>
.modal-header.blue{
	    border-bottom-color:#a8bcee !important;
}
</style>
 <a data-toggle="modal" data-target="#fully-integrated-id-{{ $model->id }}" type="button" class="btn btn-primary  btn-icon" title="{{ __('Fully Integrated') }}" href="#"><i class="fa fa-thumbs-up"></i></a>
 <div class="modal fade" id="fully-integrated-id-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
     <div class="modal-dialog modal-dialog-centered" role="document">
         <div class="modal-content">
             <form action="#" method="post">
                 @csrf
					{{-- <input type="hidden" name="model_name" value="{{ getModelNameWithoutNamespace($model) }}" >
					<input type="hidden" name="table_name" value="{{ $model->getTable() }}" > --}}
                 <div class="modal-header blue">
                     <h5 class="modal-title text-blue " id="exampleModalLongTitle">{{ __('Odoo References') }}</h5>
					 {{-- <hr class="text"> --}}
                     <button type="button" class="close  " data-dismiss="modal" aria-label="Close">
                         <span aria-hidden="true">&times;</span>
                     </button>
                 </div>
				 <div class="modal-body">
				 
				 	<div>
					<ul class="list-unstyled ">
				 @foreach($model->getOdooReferenceNames() as $referenceName)
						<li class="mb-3 text-left">{{ $referenceName }}</li>
					@endforeach 
					</ul>
					</div>
				 </div>
                 <div class="modal-footer">
                     <button type="button" class="btn btn-primary" data-dismiss="modal">{{ __('Close') }}</button>
                     {{-- <button type="submit" class="btn btn-success">{{ __('Confirm') }}</button> --}}
                 </div>

             </form>
         </div>
     </div>
 </div>
@endif 
