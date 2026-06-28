   <th class="view-table-th {{ isset($excludeMaxWith) ? 'exclude-max-width' : '' }}  bg-lighter header-th  align-middle text-center">
                                            {{ __('Invoice Date') }}
                                        </th>
										
<th class="view-table-th   bg-lighter header-th  align-middle text-center">
                                            {{ __('Invoice Number') }}
                                        </th>
										
										@if(isset($showInvoiceCurrency) && $showInvoiceCurrency)
                                        <th class="view-table-th   bg-lighter header-th  align-middle text-center">
                                            {{ __('Currency') }}
                                        </th>
										@endif 
										

                                        <th class="view-table-th   bg-lighter header-th  align-middle text-center">
                                            {{ __('Invoice Amount') }}
                                        </th>

                                        <th class="view-table-th   bg-lighter header-th  align-middle text-center">
                                            {{ __('Withhold Amount') }}
                                        </th>
										
										 <th class="view-table-th   bg-lighter header-th  align-middle text-center">
                                            {{ __('VAT Amount') }}
                                        </th>
										

                                        <th class="view-table-th   bg-lighter header-th  align-middle text-center">
                                            {{ __('Total Deductions') }}
                                        </th>
                                        <th class="view-table-th   bg-lighter header-th  align-middle text-center">
                                        {{ __('Total Collections') }}
                                        </th>



                                        <th class="view-table-th   bg-lighter header-th  align-middle text-center">
                                            {{ __('Invoice Due Date') }}
                                        </th>



                                        <th class="view-table-th   bg-lighter  header-th  align-middle text-center">
                                            {{ __('Net Balance') }}
                                        </th>

                                        <th class="view-table-th   bg-lighter  header-th  align-middle text-center">
                                            {{ __('Status') }}
                                        </th>
                                        <th class="view-table-th   bg-lighter  header-th  align-middle text-center">
                                            {{ __('Aging') }}
                                        </th>
