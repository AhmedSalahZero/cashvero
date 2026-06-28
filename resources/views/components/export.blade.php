<div class="kt-portlet__head-toolbar">
    <div class="kt-portlet__head-wrapper">
        <div class="kt-portlet__head-actions">
            &nbsp;
            @if ($href != '#')
            <a href={{$href}} class="btn  active-style btn-icon-sm {{$class}}">
                <i class="fas fa-{{$icon}}"></i>
                {{ __($firstButtonName) }}
            </a>
            @endif
            {{-- @if ($importHref != '#')
            <a href={{$importHref}} class="btn active-style btn-icon-sm {{$class}}">
            <i class="fas fa-file-import"></i>
            {{ __('Upload Data') }}
            </a>
            @endif --}}

            @if (isset($lastUploadFailedHref) && $lastUploadFailedHref != '#')
            <a href={{$lastUploadFailedHref}} class="btn  btn-danger btn-icon-sm {{$class}}">
                <i class="fas fa-file-import"></i>
                {{ __('Last Upload Failed Rows') }}
            </a>
            @endif

            @if ($truncateHref != '#')
            @if(count($exportables))

            @if(request()->has('field'))
            <a href="{{ route('view.uploading', getUploadingRouteParams($company->id)) }}" class="btn  active-style btn-icon-sm {{$class}}">
                <i class="fas fa-file-export"></i>
                {{ __('Reset Search') }}
            </a>
            @endif

            <div class="modal fade" id="search-form-modal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-xl" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="delete_from_to_modalTitle">{{ __('Data Filter') }}</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <input id="js-upload-type" type="hidden" value="{{ getSegmentBeforeLast() }}">
                            @csrf
                            <div class="row ">
                                <div class="form-group flex-1" style="margin-right:15px;">
                                    <label for="Select Field " class="label">{{ __('Filter Item') }}</label>
                                    <select id="js-search-modal-name" class="form-control" id="Select Field " type="date" name="delete_from_date" placeholder="{{ __('Delete From') }}">
                                        @foreach($exportables as $name=>$value)
                                        <option @if(Request('field')==$name) selected @endif value="{{ $name }}">{{ $value }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group flex-1" style="margin-right:15px;">
                                    <label for="Select Field " class="label">{{ __('Filter Text') }}</label>
                                    <input id="search-text" type="text" value="{{ request('value') }}" placeholder="{{ __('Filter Text') }}" class="form-control">
                                </div>

                                <div class="form-group flex-1" style="margin-right:15px;">
                                    <label for="search-from " class="label">{{ __('From') }}</label>
                                    <input id="search-from" type="date" value="{{ request('from') }}" class="form-control">

                                </div>

                                <div class="form-group flex-1" style="margin-right:15px;">
                                    <label for="search-to " class="label">{{ __('To') }}</label>
                                    <input id="search-to" type="date" value="{{ request('to') }}" class="form-control">

                                </div>




                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="#" id="js-search-id" type="submit" id="" class="btn btn-primary">{{ __('Go') }}</a>
                        </div>
                    </div>
                </div>
            </div>
            @endif

			@if(!$company->hasOdooIntegrationCredentials())
            {{-- Create New Record disabled for LoanSchedule --}}
			@if(!in_array('LoanSchedule', Request()->segments()))
            @php
                $createRouteParams = ['company' => $company->id, 'model' => getUploadModelNameFromRequest()];
                if (getUploadModelNameFromRequest() === 'ContractLoanSchedule' && ($contractId = getUploadContextIdFromRequest())) {
                    $createRouteParams['leasing_contract_id'] = $contractId;
                } elseif (getUploadModelNameFromRequest() === 'LoanSchedule' && ($loanId = getUploadContextIdFromRequest())) {
                    $createRouteParams['loanId'] = $loanId;
                }
            @endphp
            <a href="{{ route('create.sales.form', $createRouteParams) }}" class="btn  active-style btn-icon-sm {{$class}}">
                <i class="fas fa-plus"></i>
                {{ __('Create New Record') }}
            </a>
            @endif
			@endif

         

            <span class="kt-option__body p-2">
                <button type="submit" class="btn active-style btn-icon-sm">
                    <i class="fas fa-trash"></i>
                    {{ __('Delete Selected Rows') }}
                </button>
            </span>
            @if(getUploadModelNameFromRequest() == 'CustomerInvoice')

            <a data-toggle="modal" data-target="#close-period-modal" href="#" class="btn  active-style btn-icon-sm {{$class}}">
                <i class="fas fa-file-export"></i>
                {{ __('Close Period') }}
            </a>

            <div class="modal fade" id="close-period-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Close Period Modal') }}</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <table class="table table-bordered table-strip">
                                <thead>
                                    <tr>
                                        <th>{{ __('Date') }}</th>
                                        <th>{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($notPeriodClosedCustomerInvoices as $index=>$notPeriodClosedCustomerInvoiceArr)
                                    @php
                                    $canNotClosePeriod = $index>0 ;
                                    @endphp
                                    <tr>
                                        <td class="not-editable text-center">{{ $notPeriodClosedCustomerInvoiceArr['invoice_month'] .'-'.$notPeriodClosedCustomerInvoiceArr['invoice_year'] }}</td>
                                        {{-- <td class="not-editable text-center">{{ $notPeriodClosedCustomerInvoice->getStatus() }}</td> --}}
                                        <td class="not-editable text-center">
                                            <form class="form ajax-store-close-period-form " data-index="{{ $index }}" action="{{ route('store.close.period',['company'=>$company->id ]) }}" method="post">
                                                @csrf
                                                @if(!$canNotClosePeriod)
                                                <input type="hidden" id="close-month-input" name="month" value="{{ $notPeriodClosedCustomerInvoiceArr['invoice_month'] }}">
                                                <input type="hidden" id="close-year-input" name="year" value="{{ $notPeriodClosedCustomerInvoiceArr['invoice_year'] }}">
                                                @endif
                                                <button @if(!$canNotClosePeriod) id="close-period-btn" type="submit" @endif class="btn btn-primary btn-sm @if($canNotClosePeriod) disabled @endif "> {{ __('Close') }} </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        {{-- <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary">Save changes</button>
                        </div> --}}
                    </div>
                </div>
            </div>

            @endif




            <div class="modal fade" id="delete_from_to_modal" tabindex="-1" role="dialog" aria-labelledby="delete_from_to_modalTitle" aria-hidden="true">
                <div class="modal-dialog " role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="delete_from_to_modalTitle">{{ Request()->segment(4) == 'LabelingItem' ? __('Delete Data Between Serials') : __('Delete Data Between Two Dates') }}</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <input id="js-upload-type" type="hidden" value="{{ getSegmentBeforeLast() }}">
                            <div class="row ">
                                @if(Request()->segment(4) == 'LabelingItem')
                                @csrf
                                <div class="form-group flex-1">
                                    <label for="delete_from_id" class="label">{{ __('From') }}</label>
                                    <input id="js-delete-serial-from" class="form-control" id="delete_from_id" type="text" name="delete_from_serial" placeholder="{{ __('From Serial') }}">
                                </div>

                                <div class="form-group flex-1">
                                    <label for="delete_to_id" class="label">{{ __('To') }}</label>
                                    <input id="js-delete-serial-to" class="form-control" id="delete_to_id" type="text" name="delete_to_serial" placeholder="{{ __('To Serial') }}">
                                </div>


                                @else
                                <div class="form-group flex-1" style="margin-right:15px;">
                                    <label for="delete_from_id" class="label">{{ __('From') }}</label>
                                    <input id="js-delete-date-from" class="form-control" id="delete_from_id" type="date" name="delete_from_date" placeholder="{{ __('Delete From') }}">
                                </div>

                                <div class="form-group flex-1">
                                    <label for="delete_To_id" class="label">{{ __('To') }}</label>
                                    <input id="js-delete-date-to" class="form-control" id="delete_To_id" type="date" name="delete_to_date" placeholder="{{ __('Delete To') }}">
                                </div>

                                @endif
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="#" id="{{ Request()->segment(4) == 'LabelingItem' ? 'js-labeling-delete_from_to' :'js-delete_from_to' }}" type="submit" id="" class="btn btn-danger">{{ __('Delete') }}</a>
                        </div>
                    </div>
                </div>
            </div>


            @if(Request()->segment(4) == 'LabelingItem')
            <div class="modal fade" id="print_report" tabindex="-1" role="dialog" aria-labelledby="delete_from_to_modalTitle" aria-hidden="true">
                <div class="modal-dialog modal-xl" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="print_reportTitle">{{ __('Export As PDF') }}</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <input id="js-upload-type" type="hidden" value="{{ getSegmentBeforeLast() }}">
                            <form method="post" enctype="multipart/form-data" action="{{ route('print.custom.header',['company'=>$company->id ]) }}">
                                @csrf
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="Select Field " class="label">{{ __('Headers') }}</label>
                                            <select multiple  class="form-control select2-select" data-live-search="true" type="date" name="labeling_print_headers[]" placeholder="{{ __('Delete From') }}">
                                                @foreach($exportables as $name=>$value)
                                                <option @if(in_array($name , (array)$company->labeling_print_headers )) selected @endif value="{{ $name }}">{{ $value }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="Select Field " class="label">{{ __('Page Layout') }}</label>
                                            <select id="js-type" class="form-control select2-select" data-live-search="true" type="date" name="print_labeling_type" placeholder="{{ __('Type') }}">
                                                @foreach(['landscape'=>__('LandScape') , 'portrait'=>__('Portrait') ] as $type => $title)
                                                <option @if($company->print_labeling_type ==$type) selected @endif value="{{ $type }}">{{ $title }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                     <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="Select Field " class="label">{{ __('Page Size') }}</label>
                                            <select id="js-type" class="form-control select2-select" data-live-search="true" type="date" name="print_labeling_type" placeholder="{{ __('Type') }}">
                                                @foreach(['a3'=>__('A3') , 'a4'=>__('A4'),'a5'=>__('A5') ] as $type => $title)
                                                <option @if($company->labeling_paper_size ==$type) selected @endif value="{{ $type }}">{{ $title }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <x-form.image-uploader :required="false" :name="'labeling_logo_1'" :id="'labeling_logo_1'" :label="__('First Logo')" :image="isset($company) && $company->getFirstLabelingLogo() ? $company->getFirstLabelingLogo() : getDefaultImage()"></x-form.image-uploader>
                                    </div>
                                    <div class="col-md-3">
                                        <x-form.image-uploader :required="false" :name="'labeling_logo_2'" :id="'labeling_logo_2'" :label="__('Second Logo')" :image="isset($company) && $company->getSecondLabelingLogo() ? $company->getSecondLabelingLogo() : getDefaultImage()"></x-form.image-uploader>
                                    </div>
                                    <div class="col-md-3">
                                        <x-form.image-uploader :required="false" :name="'labeling_logo_3'" :id="'labeling_logo_3'" :label="__('Third Image')" :image="isset($company) && $company->getThirdLabelingLogo() ? $company->getThirdLabelingLogo() : getDefaultImage()"></x-form.image-uploader>
                                    </div>
                                    <div class="col-md-3">
                                        <x-form.image-uploader :required="false" :name="'labeling_stamp'" :id="'labeling_stamp'" :label="__('Stamp Image')" :image="isset($company) && $company->getStampLabelingLogo() ? $company->getStampLabelingLogo() : getDefaultImage()"></x-form.image-uploader>
                                    </div>
                                </div>

                                <div class="modal-footer">
                                    <button href="#" type="submit" id="" class="btn btn-primary ">{{ __('Export') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @endif


            @endif
        </div>
    </div>
</div>

@push('js')
@if(isset($company))
<script>
    $(document).on('change', '#js-search-modal-name', function() {
        const val = $(this).val();
        if (val.includes('date')) {
            $('#search-text').prop('disabled', true).val('')
        } else {
            $('#search-text').prop('disabled', false)
        }
    })
    $(document).on('click', '#js-search-id', function(e) {
        e.preventDefault();
        const field = $('#js-search-modal-name').val();
        const value = $('#search-text').val();
        const from = $('#search-from').val();
        const to = $('#search-to').val();
        const fieldIsRequired = !field.includes('date')
        if ((!value && fieldIsRequired) || !field) {
            Swal.fire({
                text: '{{ __("Please Select Field And Value") }}'
                , icon: 'warning'
            });
            return;
        }


        let url = "{{ route('view.uploading', getUploadingRouteParams($company->id)) }}"
        let appendToUrl = '?field=' + field + '&value=' + value;
        if (from) {
            appendToUrl += '&from=' + from;
        }
        if (to) {
            appendToUrl += '&to=' + to;
        }
        url = url + appendToUrl;
        window.location.href = url;
        return



    })









    $(document).on('click', '#js-delete_from_to', function(e) {
        e.preventDefault();
        const dateFrom = $('#js-delete-date-from').val();
        const dateTo = $('#js-delete-date-to').val();
        if (!dateFrom) {
            Swal.fire({
                text: '{{ __("Please Enter Date From") }}'
                , icon: 'warning'
            });
            return;
        }
        if (!dateTo) {
            Swal.fire({
                text: '{{ __("Please Enter Date To") }}'
                , icon: 'warning'
            })
            return;

        }
        if (dateFrom && dateTo) {
            const url = $('#js-upload-type').val() == 'uploading' ? "{{ route('multipleRowsDelete', array_merge(['company'=>$company->id , 'model'=>getUploadModelNameFromRequest()], getUploadContextIdFromRequest() ? ['loanId' => getUploadContextIdFromRequest()] : [])) }}" : "{{ route('deleteMultiRowsFromCaching',['company'=>$company->id,'modelName'=>getUploadModelNameFromRequest()]) }}"
            $('#js-delete_from_to').prop('disabled', true)

            $.ajax({
                url: url
                , method: "delete"
                , data: {
                    "_token": "{{ csrf_token() }}"
                    , "delete_date_from": dateFrom
                    , 'delete_date_to': dateTo
                    , 'rows': [1] // for validation 
                }
            }).then(function(res) {
                $('#js-delete_from_to').prop('disabled', false)

                Swal.fire({
                    text: '{{ __("Date Has Been Deleted Successfully") }}'
                    , icon: 'warning'
                }).then(function() {
                    window.location.reload();
                })
            });

        }
    })
    $(document).on('click', '#js-labeling-delete_from_to', function(e) {
        e.preventDefault();
        const serialFrom = $('#js-delete-serial-from').val();
        const serialTo = $('#js-delete-serial-to').val();
        if (!serialFrom) {
            Swal.fire({
                text: '{{ __("Please Enter Serial From") }}'
                , icon: 'warning'
            });
            return;
        }
        if (!serialTo) {
            Swal.fire({
                text: '{{ __("Please Enter Serial To") }}'
                , icon: 'warning'
            })
            return;

        }
        if (serialFrom && serialTo) {
            const url = $('#js-upload-type').val() == 'uploading' ? "{{ route('multipleRowsDelete', array_merge(['company'=>$company->id , 'model'=>getUploadModelNameFromRequest()], getUploadContextIdFromRequest() ? ['loanId' => getUploadContextIdFromRequest()] : [])) }}" : "{{ route('deleteMultiRowsFromCaching',['company'=>$company->id,'modelName'=>getUploadModelNameFromRequest()]) }}"
            $('#js-labeling-delete_from_to').prop('disabled', true)

            $.ajax({
                url: url
                , method: "delete"
                , data: {
                    "_token": "{{ csrf_token() }}"
                    , "delete_serial_from": serialFrom
                    , 'delete_serial_to': serialTo
                    , 'rows': [1] // for validation 
                }
            }).then(function(res) {
                $('#js-labeling-delete_from_to').prop('disabled', false)

                Swal.fire({
                    text: '{{ __("Date Has Been Deleted Successfully") }}'
                    , icon: 'warning'
                }).then(function() {
                    window.location.reload();
                })
            });

        }
    })








    $(document).on('click', '#close-period-btn', function(e) {
        e.preventDefault();
        $(this).attr('disabled', true);
        const month = $(document).find('#close-month-input').val();
        const year = $(document).find('#close-year-input').val();
        const form = $(document).find('#search-from').val();
        const url = "{{ route('store.close.period',['company'=>$company->id ]) }}";

        $.ajax({
            url: url
            , data: {
                "_token": "{{ csrf_token() }}"
                , month
                , year
            }
            , method: "post"
            , success: function(res) {
                Swal.fire({
                    text: 'Done'
                    , icon: 'success'
                    , timer: 1200
                }).then(res => {
                    window.location.reload();
                })
            }
            , error: function(res) {
                Swal.fire({
                    text: 'Error Happend Please Try Again'
                    , icon: 'error'
                }).then(res => {
                    window.location.reload();
                })
            }
            , complete: function(res) {
                $('#close-period-btn').attr('disabled', false)
            }

        })
    })

</script>
@endif
@endpush
