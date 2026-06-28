<style>
    .max-class {
        border: 1px solid red !important;
        color: red !important;

    }

    .min-class {
        border: 1px solid green !important;
        color: green !important;
    }

    .avg-class {
        border: 1px solid blue !important;
        color: blue !important;
    }

    .outlier-class {
        border: 1px solid orange !important;
        color: black !important;
    }

    .min-avg-max-class {
        background-color: white !important;
    }

</style>
<table class="table">
    <thead>

    </thead>
    <tbody>




        <tr>

            <td class="w-40-percentage">
                <div class="kt-input-icon">
                    <div class="input-group">
                        <input disabled type="text" class="form-control text-left ignore-global-style min-avg-max-class avg-class" value="{{  __('Average Value') }}">
                    </div>
                </div>
            </td>
            <td class="w-20-percentage">
                <div class="kt-input-icon">
                    <div class="input-group">
                        <input disabled type="text" class="form-control text-center ignore-global-style min-avg-max-class avg-class" value="{{   number_format($avg)  }}">
                    </div>
                </div>
            </td>
        </tr>


        <tr>

            <td class="w-40-percentage">
                <div class="kt-input-icon">
                    <div class="input-group">
                        <input disabled type="text" class="form-control text-left ignore-global-style min-avg-max-class min-class" value="{{  __('Min Value') }}">
                    </div>
                </div>
            </td>
            <td class="w-20-percentage">
                <div class="kt-input-icon">
                    <div class="input-group">
                        <input disabled type="text" class="form-control text-center ignore-global-style min-avg-max-class min-class" value="{{   number_format($min)  }}">
                    </div>
                </div>
            </td>
        </tr>

        <tr>

            <td class="w-40-percentage">
                <div class="kt-input-icon">
                    <div class="input-group">
                        <input disabled type="text" class="form-control text-left ignore-global-style min-avg-max-class max-class" value="{{  __('Max Value') }}">
                    </div>
                </div>
            </td>
            <td class="w-20-percentage">
                <div class="kt-input-icon">
                    <div class="input-group">
                        <input disabled type="text" class="form-control text-center ignore-global-style min-avg-max-class max-class" value="{{   number_format($max)  }}">
                    </div>
                </div>
            </td>
        </tr>

        <tr>

            <td class="w-40-percentage">
                <div class="kt-input-icon">
                    <div class="input-group">
                        <input disabled type="text" class="form-control text-left ignore-global-style min-avg-max-class outlier-class" value="{{  __('Outliers Value') }}">
                    </div>
                </div>
            </td>
            <td class="w-20-percentage">
                <div class="kt-input-icon">
                    <div class="input-group text-center justify-content-center">

                        @php
                        $currentOutliersArr = $avgMinMaxOutliers[$mainCategoriesName.' - '.$mainCategoriesName]['Outliers'] ?? [];
                        $currentModalId = convertStringToClass($mainCategoriesName.'-outliers');
                        $title = __('Outliers For ' . $mainCategoryName . ' Modal');

                        @endphp
                        @if(count($currentOutliersArr['dates'] ?? []))
                        <button class="btn btn-sm btn-brand btn-elevate btn-pill text-white" data-toggle="modal" data-target="#{{ $currentModalId }}">{{ __('Details') }}</button>
                        @include('admin.modals.avg-min-max-outliers-date-value-modals',['detailItems'=> $currentOutliersArr , 'modalId'=>$currentModalId ,'title'=>$title])
                        @else
                        <span>{{ __('- - -') }}</span>
                        @endif



                    </div>
                </div>
            </td>
        </tr>


        <tr>

            <td class="w-40-percentage">
                <div class="kt-input-icon">
                    <div class="input-group">
                        <input disabled type="text" class="form-control text-left ignore-global-style min-avg-max-class avg-class" value="{{  __('Fixed & Variable Expense Item & Expense Change') }}">
                    </div>
                </div>
            </td>
            <td class="w-20-percentage">
                <div class="kt-input-icon">
                    <div class="input-group text-center justify-content-center">


                        @php

                        $currentModalId = convertStringToClass($mainCategoriesName.'-r');
                        $title = __('Fixed & Variable Expense Item & Expense Change ' . $mainCategoryName . ' Modal');

                        @endphp

                        <button class="btn btn-sm btn-brand btn-elevate btn-pill text-white" data-toggle="modal" data-target="#{{ $currentModalId }}">{{ __('Details') }}</button>
                        @include('admin.modals.r-modals',['detailItems'=> $fixedVariableExpenseCoefficientCorrelations , 'modalId'=>$currentModalId ,'title'=>$title])




                    </div>
                </div>
            </td>
        </tr>



    </tbody>
</table>
