 <div class="kt-portlet mt-2 pt-3">

                    <div class="kt-portlet__body with-scroll pt-0">

                        <div class="table-custom-container position-relative  ">


                            <div>




                                <div class="responsive ">
                                    <table class="table kt_table_with_no_pagination_no_collapse table-for-currency  table-striped- table-bordered table-hover table-checkable position-relative table-with-two-subrows main-table-class-for-currency dataTable no-footer">
                                        <thead>

                                            <tr class="header-tr ">

                                                <th class="view-table-th max-w-serial  header-th  align-middle text-center">
                                                    {{ __('#') }}
                                                </th>

                                                <th class="view-table-th max-w-name  max-w-invoice-date header-th  align-middle text-center">
                                                    {{ __('Report Name') }}
                                                </th>

                                                <th class="view-table-th max-w-name  max-w-counts header-th  align-middle text-center">
                                                    {{ __('Report Interval') }}
                                                </th>

                                                <th class="view-table-th max-w-name  max-w-counts header-th  align-middle text-center">
                                                    {{ __('Start Date') }}
													
													<br> DD-MM-YYYY
													
                                                </th>

                                                <th class="view-table-th max-w-name  max-w-counts header-th  align-middle text-center">
                                                    {{ __('End Date') }}
													<br> DD-MM-YYYY
                                                </th>


                                                <th class="view-table-th max-w-name max-w-action  header-th  align-middle text-center">
                                                    {{ __('Actions') }}
                                                </th>







                                            </tr>

                                        </thead>
                                        <tbody>
                                            @php
                                            $previousDate = null ;
                                            @endphp
											
                                            @foreach($cashflowReports as $index => $cashflowReport)
                                            <tr class=" parent-tr reset-table-width text-nowrap  cursor-pointer sub-text-bg text-capitalize  ">
                                                <td class="sub-text-bg max-w-serial text-center   ">{{ ++$index }}</td>
                                                <td class="sub-text-bg  text-center  max-w-counts ">{{ $cashflowReport->getName()}}</td>
                                                <td class="sub-text-bg  text-center  max-w-counts ">{{ $cashflowReport->getIntervalName()}}</td>
                                               
                                                <td class="sub-text-bg  text-center max-w-counts ">{{ $cashflowReport->getStartDateFormatted() }}  </td>
                                                <td class="sub-text-bg  text-center max-w-counts ">{{ $cashflowReport->getEndDateFormatted() }}</td>
                                                <td class="sub-text-bg  text-center max-w-action   ">
                                                    <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon" title="Edit" href="{{route('result.cashflow.report',[$company,'returnResultAsArray'=>'view','cashflowReport'=>$cashflowReport->id])}}"><i class="fa fa-pen-alt"></i></a>

                                                    <a class="btn btn-secondary btn-outline-hover-danger btn-icon  " href="#" data-toggle="modal" data-target="#modal-delete-{{ $cashflowReport->id}}" title="Delete"><i class="fa fa-trash-alt"></i>
                                                    </a>
                                                

                                                    <div id="modal-delete-{{ $cashflowReport->id }}" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel1" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h4 class="modal-title">{{ __('Delete Cashflow Report ' .$cashflowReport->getName()) }}</h4>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <h3>{{ __('Are You Sure To Delete This Item ? ') }}</h3>
                                                                </div>
                                                                <form action="{{ route('delete.cashflow.report',[$company,$cashflowReport->id]) }}" method="post" id="delete_form">
                                                                    {{ csrf_field() }}
                                                                    {{ method_field('DELETE') }}
                                                                    <div class="modal-footer">
                                                                        <button class="btn btn-danger">
                                                                            {{ __('Delete') }}
                                                                        </button>
                                                                        <button class="btn btn-secondary" data-dismiss="modal" aria-hidden="true">
                                                                            {{ __('Close') }}
                                                                        </button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>

                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                            </div>

                            @push('js')
                            <script>
                                $('.table-for-currency').DataTable({
                                        dom: 'Bfrtip'

                                        , "processing": false
                                        , "scrollX": true
                                        , "scrollY": true
                                        , "ordering": false
                                        , 'paging': false
                                        , "fixedColumns": {
                                            left: 2
                                        }
                                        , "fixedHeader": {
                                            headerOffset: 60
                                        }
                                        , "serverSide": false
                                        , "responsive": false
                                        , "pageLength": 25
                                        , drawCallback: function(setting) {
                                            $('.buttons-html5').addClass('btn border-parent btn-border-export btn-secondary btn-bold  ml-2 flex-1 flex-grow-0 btn-border-radius do-not-close-when-click-away')
                                            $('.buttons-print').addClass('btn border-parent top-0 btn-border-export btn-secondary btn-bold  ml-2 flex-1 flex-grow-0 btn-border-radius do-not-close-when-click-away')
                                        },

                                    }

                                )

                            </script>
                            @endpush

                        </div>

                    </div>
                </div>
