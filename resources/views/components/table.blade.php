<div class="kt-portlet kt-portlet--mobile">
    @if($tableTitle !== null)
        <div class="kt-portlet__head kt-portlet__head--lg">
            <div class="kt-portlet__head-label">
                <span class="kt-portlet__head-icon">
                    <i class="kt-font-secondary btn-outline-hover-danger fa fa-layer-group"></i>
                </span>
                <h3 class="kt-portlet__head-title">
					<x-sectionTitle :title="$tableTitle"></x-sectionTitle>
					@if(isset($instructionsIcon))
						 <span id="open-instructions" class="kt-input-icon__icon kt-input-icon__icon--right ml-2 cursor-pointer" tabindex="0" role="button" data-toggle="kt-tooltip" data-trigger="focus" title="{{ __('Uploading Instructions') }}">
							<span><i class="fa fa-question text-primary"></i></span>
						</span>
					@endif 
                </h3>
            </div>
            {{-- Export --}}
            <x-export :notPeriodClosedCustomerInvoices="$notPeriodClosedCustomerInvoices" :lastUploadFailedHref="$lastUploadFailedHref" :class="$class" :href="$href" :importHref="$importHref" :exportHref="$exportHref" :exportTableHref="$exportTableHref" :icon="$icon" :firstButtonName="$firstButtonName" :truncateHref="$truncateHref"/>

        </div>
    @endif
    <div class=" @if('kt_table_with_no_pagination_no_scroll_no_entries' != $tableClass ) kt-portlet__body @endif table-responsive">


        <!--begin: Datatable -->
        <table  
		{{-- {{ getArrIfValueNotArray($attributes) }} --}}
		
		  class="table table-striped- {{$tableClass}} table-bordered table-hover table-checkable  " >

            <thead>
                {{$table_header}}
            </thead>
            <tbody>
                {{$table_body}}
            </tbody>
        </table>

        <!--end: Datatable -->
    </div>
</div>
