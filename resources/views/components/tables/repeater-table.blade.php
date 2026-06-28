@props([
'showAddBtnAndPlus'=>true,
'repeater-with-select2'=>true,
'isRepeater'=>$isRepeater,
'relationName'=>$relationName,
'repeaterId'=>$repeaterId,
'tableName'=>$tableName ?? '',
'parentClass'=>$parentClass ?? '',
'initialJs'=>true ,
'initEmpty'=>false,
'removeDisabledWhenAddNew'=>false ,
'firstElementDeletable'=>false,
'hideAddBtn'=>false,
'canAddNewItem'=>true,
'removeActionBtn'=>false,
'tableClass'=>'col-md-12',
'tableClasses'=>'',
'actionBtnTitle'=>__('Action'),
'appendSaveOrBackBtn'=>false,
'addExpenseName'=>false,
'showRows'=>true,
'fontSizeClass'=>'',
'addExpenseType'=>false,
'hideByDefault'=>true,
'triggerInputChangeWhenAddNew'=>false
])
<style>
    .btn-div {
        padding: 0 !important;
        width: 30px !important;
        height: 30px !important;
    }

    .btn-div span {
        font-size: 20px !important;
        cursor: pointer;
    }

    .trash_icon {
        width: 30px;
        height: 30px;
        display: flex;
        justify-content: center;
        align-items: center;
        cursor: pointer;
    }

</style>
@php

$canAddNewItem = true;
@endphp

<div class="{{ $tableClass }} {{ $parentClass }}  js-parent-to-table " data-table-id="{{ $repeaterId??'' }}" @if($hideByDefault) style="display:none" @endif>


    @if($showRows)

    <table @if($initialJs) id="{{ $repeaterId }}" @endif class="table  {{ $repeaterId }} {{ $tableClasses }} table-white  repeater-class repeater {{ $tableName }}">
        <thead>
            <tr>
                @if(!$removeActionBtn)
                <x-tables.repeater-table-th :fontSizeClass="$fontSizeClass" class="col-md-1 action-class" :title="'+/-'"></x-tables.repeater-table-th>
                @endif
                {{ $ths }}
            </tr>
        </thead>
        <tbody data-repeater-list="{{$tableName}}">
            @if(isset($model) && $model->{$relationName}->count() )

            @foreach($model->{$relationName} as $subModel)
            <x-tables.repeater-table-tr :isRepeater="true" :model="$subModel"></x-tables.repeater-table-tr>

            @endforeach
            @else
            <x-tables.repeater-table-tr :trs="$trs" :isRepeater="true">

            </x-tables.repeater-table-tr>

            @endif

        </tbody>
        <td>
            @if($showAddBtnAndPlus)
            @if($canAddNewItem && !$removeActionBtn)
            <div data-repeater-create="" class="btn btn btn-sm text-white add-row btn-div  border-green bg-green  m-btn m-btn--icon m-btn--pill m-btn--wide {{__('right')}}">
                <span>
                    +
                    <span>
                    </span>
                </span>
            </div>
            @endif
            @endif
        </td>

    </table>
    @endif
    @if($appendSaveOrBackBtn)
    <x-save-or-back-inside-table :btn-text="__('Create')" />
    @endif
</div>
<input type="hidden" id="initi-empty-{{ $repeaterId }}" value="{{ $initEmpty }}">
<input type="hidden" id="first-element-deleteable-{{ $repeaterId }}" value="{{ $firstElementDeletable }}">

@if($initialJs)
@push('js_end')
<script>
    var initEmpty = $("#initi-empty-{{ $repeaterId }}").val() === "1" ? true : false;
    var firstElementDeleteable = $("#first-element-deleteable-{{ $repeaterId }}").val() === "1" ? true : false;
    var studyStartDate = $('#study-start-date').val()
    var studyEndDate = $('#study-end-date').val()

    function initMultiselect(container) {
        const $container = $(container)
        const $trigger = $container.find('.multiselect-trigger')
        const $dropdown = $container.find('.multiselect-dropdown')
        if (!$dropdown.length) {
            return
        }
        const $searchInput = $container.find('.search-input')
        const $addOptionInput = $container.find('.add-option-input')
        const $addOptionBtn = $container.find('.btn-add-option')
        const $selectAllBtn = $container.find('.btn-select-all')
        const $deselectAllBtn = $container.find('.btn-deselect-all')
        const $optionsContainer = $container.find('.multiselect-options')
        const $selectedText = $container.find('.selected-text')
        const $selectedOptionsContainer = $container.find('.selected-options-container')
        let selectedValues = []

        // Toggle dropdown
        $trigger.on('click', function(e) {
            e.stopPropagation()
            $dropdown.toggle()
        })

        // Close on outside click
        $(document).on('click', function(e) {
            if (!$container.has(e.target).length) {
                $dropdown.hide()
            }
        })

        // Bind checkbox events
        function bindCheckboxEvents($checkbox) {
            $checkbox.on('change', updateSelected)
        }

        // Update selected values and display
        function updateSelected() {
            const $options = $optionsContainer.find('.option-item input[type="checkbox"]')
            selectedValues = $options.filter(':checked').map(function() {
                return $(this).val()
            }).get()
            $selectedText.text(selectedValues.length ? `${selectedValues.length} selected` : 'Select options...')

            // Clear existing hidden inputs
            $selectedOptionsContainer.empty()
            // Add a hidden input for each selected value
            selectedValues.forEach(function(value) {
                $selectedOptionsContainer.append(
                    `<input type="hidden" name="selectedOptions[]" value="${value}">`
                )
            })
        }

        // Bind initial checkboxes
        $optionsContainer.find('.option-item input[type="checkbox"]').each(function() {
            bindCheckboxEvents($(this))
        })

        // Select All
        $selectAllBtn.on('click', function(e) {
            e.preventDefault()
            $optionsContainer.find('.option-item input[type="checkbox"]').prop('checked', true)
            updateSelected()
        })

        // Deselect All
        $deselectAllBtn.on('click', function(e) {
            e.preventDefault()
            $optionsContainer.find('.option-item input[type="checkbox"]').prop('checked', false)
            updateSelected()
        })

        // Search filter
        $searchInput.on('input', function() {
            const query = $(this).val().toLowerCase()
            $optionsContainer.find('.option-item').each(function() {
                const label = $(this).text().toLowerCase()
                $(this).toggle(label.includes(query))
            })
        })





        updateSelected() // Initial call
    }
    @if($repeaterId)
    $('#' + "{{ $repeaterId }}").repeater({
        initEmpty: initEmpty
        , isFirstItemUndeletable: !firstElementDeleteable
        , defaultValues: {
			"percentage_payload":0,
			"loan_amounts":0,
            "is_active": 1
            , "replacement_cost_rate": 0
            , "replacement_interval": 1
            , "depreciation_duration": 5
            , 'counts': 1
            , 'grace_period': 0
            , 'tenor': 12
            , "margin_rate": 0
            , "step_rate": 0
            , "loan_type": "normal"
            , "loan_nature": "fixed-at-end"
            , "installment_interval": "monthly"
            , "step_interval": "annually",
"monthly_cost_of_unit":0,
            "amount": 0
            , "increase_interval": "annually"
            , "payment_terms": "cash"
            , "vat_rate": 0
            , "start_date": studyStartDate
            , "end_date": studyEndDate
            , "withhold_tax_rate": 0
            , "increase_rate": 0
            , "contingency_rate": 0
            , "cost_annual_increase_rate": 0,
			"amortization_months":12
        },

        show: function() {
            initMultiselect();


            var appendNewOptionsToAllSelects = function(currentRepeaterItem) {

                if ($('[data-modal-title]').length) {

                    let currentSelect = $(currentRepeaterItem).find('select').attr('data-modal-name')
                    let modalType = $(currentRepeaterItem).find('select').attr('data-modal-type')
                    let selects = {}
                    $('select[data-modal-name="' + currentSelect + '"][data-modal-type="' + modalType + '"] option').each(function(index, option) {
                        selects[$(option).attr('value')] = $(option).html()
                    })

                    $('select[data-modal-name="' + currentSelect + '"][data-modal-type="' + modalType + '"]').each(function(index, select) {
                        var selectedValue = $(select).val()
                        var currentOptions = ''
                        var currentOptionsValue = []
                        $(select).find('option').each(function(index, option) {
                            var currentOption = $(option).attr('value')
                            var isCurrentSelected = currentOption == selectedValue ? 'selected' : ''
                            currentOptions += '<option value="' + currentOption + '" ' + isCurrentSelected + ' > ' + $(option).html() + ' </option>'
                            currentOptionsValue.push(currentOption)
                        })
                        for (var allOptionValue in selects) {
                            if (!currentOptionsValue.includes(allOptionValue)) {
                                var isCurrentSelected = false
                                currentOptions += '<option value="' + allOptionValue + '" ' + isCurrentSelected + ' > ' + selects[allOptionValue] + ' </option>'
                                currentOptionsValue.push(allOptionValue)
                            }
                        }
                        $(select).empty().append(currentOptions).selectpicker('refresh').trigger('change')

                    })
                }
            }
            $(this).slideDown();
            $('input.trigger-change-repeater').trigger('change')
            $(this).find('.only-month-year-picker').each(function(index, dateInput) {
                reinitalizeMonthYearInput(dateInput)
            });
            $(document).find('.datepicker-input:not(.only-month-year-picker)').datepicker({
                dateFormat: 'yy-mm-dd'
                , autoclose: true
            })
			if(!isNonBanking){
				$(this).find('input:not(.exclude-from-trigger-change-when-repeat):not([type="hidden"])').trigger('change');
			}else{
			$(this).find('.input-hidden-parent input:not([type="hidden"])').val(0);
			}
			const triggerInputChangeWhenAddNew = +"{{ $triggerInputChangeWhenAddNew }}"
			if(triggerInputChangeWhenAddNew){
				$(this).find('input:not([type="hidden"])').trigger('change')
			}
			
			
            //$('input.equity-funding-formatted-value-class').trigger('change');
            $(this).find('.dropdown-toggle').remove();
            $(this).find('select.repeater-select').selectpicker("refresh");
            appendNewOptionsToAllSelects(this)
            initMultiselect($(this));

            const removeDisabledWhenAddNew = +"{{ $removeDisabledWhenAddNew }}";
            if (removeDisabledWhenAddNew) {
                $(this).find('input').prop('disabled', false);
                $(this).find('select').prop('disabled', false).selectpicker('refresh')
            }
			$('.recalculate-total-branches').trigger('change');
        },

        hide: function(deleteElement) {

            if ($('#first-loading').length) {
                $(this).slideUp(deleteElement, function() {

                    deleteElement();
                    //   $('select.main-service-item').trigger('change');
                });
            } else {

                if (confirm('Are you sure you want to delete this element?')) {

                    $(this).slideUp(deleteElement, function() {
                        deleteElement();

                        $('select.main-service-item').trigger('change');
                        $('input.trigger-change-repeater').trigger('change')

                    });
                }
            }
        }
    });
    @endif

</script>
@endpush
@endif
