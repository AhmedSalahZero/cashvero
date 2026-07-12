@php
	$newLoanFundingRateText = isset($newLoanFundingRateText) ?$newLoanFundingRateText :  __('New Loans Funding Rate (%)');
	$newLoanFundingValueText = isset($newLoanFundingValueText) ?$newLoanFundingValueText :  __('New Loans Funding Value');
@endphp
                                <tr data-repeat-formatting-decimals="2" data-repeater-style>

                                    <input type="hidden" name="id" value="{{ isset($subModel) ? $subModel->id : 0 }}">


                                    <td>
                                        <input value="{{ __('Equity Funding Rate (%)') }}" disabled class="form-control  min-width-hover-300 text-left mt-2" type="text">

                                    </td>
                                    @php
                                    $columnIndex = 0 ;
                                    @endphp
                                    @foreach($yearOrMonthsIndexes as $yearOrMonthAsIndex=>$yearOrMonthFormatted)


                                    <td>
                                        <div class="d-flex align-items-center justify-content-center">

                                            <x-repeat-right-dot-inputs :inputHiddenAttributes="'js-recalculate-equity-funding-value'" :currentVal="$eclAndNewPortfolioFundingRate ? $eclAndNewPortfolioFundingRate->getEquityFundingRatesAtYearOrMonthIndex($yearOrMonthAsIndex):0" :classes="'only-greater-than-or-equal-zero-allowed equity-funding-rates equity-funding-rate-input-hidden-class '" :is-percentage="true" :name="'equity_funding_rates['.$yearOrMonthAsIndex.']'" :columnIndex="$columnIndex"></x-repeat-right-dot-inputs>

                                        </div>
                                    </td>
									
									
									  @php
                                    $dateAsString = $dateIndexWithDate[$yearOrMonthAsIndex];
                                    $currentMonthNumber = explode('-',$dateAsString)[1];
                                    $currentYear= explode('-',$dateAsString)[0];
                                    @endphp


                                    @if($study->isMonthlyStudy() && ($study->getFinancialYearEndMonthNumber() == $currentMonthNumber || $loop->last))
                                    <td data-column-index="{{ $yearOrMonthAsIndex }}" class="exclude-from-collapse">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <x-repeat-right-dot-inputs :isNumber="false" :readonly="true" :removeThreeDots="true" :number-format-decimals="0" :mark="' '" :currentVal="'-' " :formattedInputClasses="'exclude-from-collapse exclude-from-trigger-change-when-repeat expandable-amount-input '" :classes="'exclude-from-total year-repeater-index-'.$currentYearRepeaterIndex.' ' .'only-greater-than-or-equal-zero-allowed exclude-from-collapse'" :is-percentage="true" :name="''" :columnIndex="$yearOrMonthAsIndex"></x-repeat-right-dot-inputs>
                                        </div>

                                    </td>
                                    @php
                                    $currentYearRepeaterIndex++;
                                    @endphp
                                    @endif
									
									
                                    @php
                                    $columnIndex++;
                                    @endphp
                                    @endforeach

                                    <td>
                                        <div class="d-flex align-items-center justify-content-center">
                                            <input type="text" class="form-control expandable-amount-input  sum-percentage-css" disabled value="-">
                                        </div>
                                    </td>




                                </tr>



                                <tr data-repeat-formatting-decimals="0" data-repeater-style total-row-tr data-row-total>

                                    <input type="hidden" name="id" value="{{ isset($subModel) ? $subModel->id : 0 }}">


                                    <td>
                                        <input value="{{ __('Equity Funding Value') }}" disabled class="form-control min-width-hover-300 text-left mt-2" type="text">

                                    </td>
                                    @php
                                    $columnIndex = 0 ;
									$currentYearRepeaterIndex = 0;
									$currentYearTotal= 0;
									$currentRowTotal = 0;
                                    @endphp
                                    @foreach($yearOrMonthsIndexes as $yearOrMonthAsIndex=>$yearOrMonthFormatted)
									
									  
									
                                    <td>

                                        <div class="d-flex align-items-center justify-content-center">
                                            <x-repeat-right-dot-inputs :readonly="true" :numberFormatDecimals="0" :currentVal="$currentVal = $eclAndNewPortfolioFundingRate ? $eclAndNewPortfolioFundingRate->getEquityFundingValuesAtYearOrMonthIndex($yearOrMonthAsIndex):0" :classes="'only-greater-than-or-equal-zero-allowed repeater-with-collapse-input'" data-group-index="{{ $currentYearRepeaterIndex }}" :formatted-input-classes="'equity-funding-formatted-value-class '" :is-percentage="false" :name="'equity_funding_values['.$yearOrMonthAsIndex.']'" :columnIndex="$columnIndex"></x-repeat-right-dot-inputs>

                                        </div>
                                    </td>
									
									
									@php
                                    $dateAsString = $dateIndexWithDate[$yearOrMonthAsIndex];
                                    $currentMonthNumber = explode('-',$dateAsString)[1];
                                    $currentYear= explode('-',$dateAsString)[0];
									$currentYearTotal+=$currentVal ;
									$currentRowTotal+=$currentVal;
									
                                    @endphp
									

                                    @if($study->isMonthlyStudy() && ($study->getFinancialYearEndMonthNumber() == $currentMonthNumber || $loop->last))
                                    <td data-column-index="{{ $yearOrMonthAsIndex }}" class="exclude-from-collapse">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <x-repeat-right-dot-inputs :readonly="true" :removeThreeDots="true" :number-format-decimals="0" :mark="' '" :currentVal="$currentYearTotal" :formattedInputClasses="'exclude-from-collapse exclude-from-trigger-change-when-repeat expandable-amount-input '" :classes="'exclude-from-total year-repeater-index-'.$currentYearRepeaterIndex.' ' .'only-greater-than-or-equal-zero-allowed exclude-from-collapse'" :is-percentage="true" :name="''" :columnIndex="$yearOrMonthAsIndex"></x-repeat-right-dot-inputs>
                                        </div>

                                    </td>
                                    @php
                                    $currentYearRepeaterIndex++;
									$currentYearTotal=0;
                                    @endphp
                                    @endif
									
									
									
                                    @php
                                    $columnIndex++;
                                    @endphp
                                    @endforeach

                                    <td>
                                        <div class="d-flex align-items-center justify-content-center">
                                            <input type="text" class="form-control expandable-amount-input sum-total-row sum-percentage-css" disabled value="{{ number_format($currentRowTotal) }}">
                                        </div>
                                    </td>



                                </tr>



                                <tr data-repeat-formatting-decimals="2" data-repeater-style>
                                    <td>
                                        <input disabled value="{{ $newLoanFundingRateText }}" class="form-control min-width-hover-300 text-left" type="text">
                                    </td>
                                    @php
                                    $columnIndex = 0 ;
                                    @endphp

                                    @foreach($yearOrMonthsIndexes as $yearOrMonthAsIndex=>$yearOrMonthFormatted)


                                    <td>
                                        <div class="d-flex align-items-center justify-content-center">
                                            <input type="text" data-column-index="{{ $columnIndex }}" readonly class="form-control expandable-amount-input new-loan-function-rates-js" name="new_loans_funding_rates[{{ $yearOrMonthAsIndex }}]'" value="{{ $eclAndNewPortfolioFundingRate ? $eclAndNewPortfolioFundingRate->getNewLoansFundingRatesAtYearOrMonthIndex($yearOrMonthAsIndex):100 }}"> <span class="ml-2">%</span>
                                        </div>
                                    </td>
									
									
									
									  @php
                                    $dateAsString = $dateIndexWithDate[$yearOrMonthAsIndex];
                                    $currentMonthNumber = explode('-',$dateAsString)[1];
                                    $currentYear= explode('-',$dateAsString)[0];
                                    @endphp


                                    @if($study->isMonthlyStudy() && ($study->getFinancialYearEndMonthNumber() == $currentMonthNumber || $loop->last))
                                    <td data-column-index="{{ $yearOrMonthAsIndex }}" class="exclude-from-collapse">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <x-repeat-right-dot-inputs :isNumber="false" :readonly="true" :removeThreeDots="true" :number-format-decimals="0" :mark="' '" :currentVal="'-' " :formattedInputClasses="'exclude-from-collapse exclude-from-trigger-change-when-repeat expandable-amount-input '" :classes="'exclude-from-total year-repeater-index-'.$currentYearRepeaterIndex.' ' .'only-greater-than-or-equal-zero-allowed exclude-from-collapse'" :is-percentage="true" :name="''" :columnIndex="$yearOrMonthAsIndex"></x-repeat-right-dot-inputs>
                                        </div>

                                    </td>
                                    @php
                                    $currentYearRepeaterIndex++;
                                    @endphp
                                    @endif
									
									
                                    @php
                                    $columnIndex++;
                                    @endphp

                                    @endforeach

                                    <td>
                                        <div class="d-flex align-items-center justify-content-center">
                                            <input type="text" class="form-control expandable-amount-input  sum-percentage-css" disabled value="-">
                                        </div>
                                    </td>



                                </tr>

                                <tr data-repeat-formatting-decimals="0" data-repeater-style total-row-tr data-row-total>


                                    <td>
                                        <input disabled value="{{ $newLoanFundingValueText }}" class="form-control min-width-hover-300 text-left" type="text">

                                    </td>
                                    @php
                                    $columnIndex = 0 ;
                                    $currentYearRepeaterIndex = 0 ;
									$currentYearTotal = 0 ;
									$currentRowTotal =0;
                                    @endphp

                                    @foreach($yearOrMonthsIndexes as $yearOrMonthAsIndex=>$yearOrMonthFormatted)
                                    <td>
                                        <div class="d-flex align-items-center justify-content-center">
                                            <x-repeat-right-dot-inputs :readonly="true" :numberFormatDecimals="0" :formatted-input-classes="'new-loans-funding-formatted-value-class'" :currentVal="$currentVal=$eclAndNewPortfolioFundingRate ? $eclAndNewPortfolioFundingRate->getNewLoansFundingValuesAtYearOrMonthIndex($yearOrMonthAsIndex):0" data-group-index="{{ $currentYearRepeaterIndex }}" :classes="'only-greater-than-or-equal-zero-allowed repeater-with-collapse-input'" :is-percentage="false" :name="'new_loans_funding_values['.$yearOrMonthAsIndex.']'" :columnIndex="$columnIndex"></x-repeat-right-dot-inputs>
                                        </div>
                                    </td>
									  @php
                                    $dateAsString = $dateIndexWithDate[$yearOrMonthAsIndex];
                                    $currentMonthNumber = explode('-',$dateAsString)[1];
                                    $currentYear= explode('-',$dateAsString)[0];
									$currentYearTotal+=$currentVal;
									$currentRowTotal +=$currentVal;
                                    @endphp


                                    @if($study->isMonthlyStudy() && ($study->getFinancialYearEndMonthNumber() == $currentMonthNumber || $loop->last))
                                    <td data-column-index="{{ $yearOrMonthAsIndex }}" class="exclude-from-collapse">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <x-repeat-right-dot-inputs :readonly="true" :removeThreeDots="true" :number-format-decimals="0" :mark="' '" :currentVal="$currentYearTotal" :formattedInputClasses="'exclude-from-collapse exclude-from-trigger-change-when-repeat expandable-amount-input '" :classes="'exclude-from-total year-repeater-index-'.$currentYearRepeaterIndex.' ' .'only-greater-than-or-equal-zero-allowed exclude-from-collapse'" :is-percentage="true" :name="''" :columnIndex="$yearOrMonthAsIndex"></x-repeat-right-dot-inputs>
                                        </div>

                                    </td>
                                    @php
                                    $currentYearRepeaterIndex++;
									$currentYearTotal = 0 ;
                                    @endphp
                                    @endif
									
                                    @php
                                    $columnIndex++;
								
                                    @endphp

                                    @endforeach
                                    <td>
                                        <div class="d-flex align-items-center justify-content-center">
                                            <input type="text" class="form-control expandable-amount-input sum-total-row sum-percentage-css" disabled value="{{ number_format($currentRowTotal) }}">
                                        </div>
                                    </td>



                                </tr>
