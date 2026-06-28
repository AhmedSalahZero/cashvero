@extends('layouts.dashboard')
@section('css')
@php
use App\Models\CashExpense ;
use App\Models\SupplierInvoice;
$banks =[];
$selectedBanks = [];
@endphp
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<style>
    .custom-contract-amount-css,
    .max-w-12 {
        max-width: initial !important;
        width: 12% !important;
        flex: initial !important;

    }

    label {
        text-align: left !important;
    }

    .max-w-6 {
        max-width: initial !important;
        width: 6% !important;
        flex: initial !important;
    }

    .max-w-15 {
        max-width: initial !important;
        width: 15% !important;
        flex: initial !important;
    }

    .width-8 {
        max-width: initial !important;
        width: 8% !important;
        flex: initial !important;
    }

    .width-10 {
        max-width: initial !important;
        width: 10% !important;
        flex: initial !important;
    }

    .width-12 {
        max-width: initial !important;
        width: 12.5% !important;
        flex: initial !important;
    }

    .width-40 {
        max-width: initial !important;
        width: 40% !important;
        flex: initial !important;
    }

    .kt-portlet {
        overflow: visible !important;
    }

    input.form-control[disabled]:not(.ignore-global-style),
    input.form-control:not(.is-date-css)[readonly] {
        background-color: #CCE2FD !important;
        font-weight: bold !important;
    }

</style>
@endsection
@section('sub-header')
{{ __('Allocation Form') }}
@endsection
@section('content')
<div class="row">
    <div class="col-md-12">
        <!--begin::Portlet-->

        <form method="post" action="{{ isset($model) ?  route('allocate.odoo.cash.expense',['company'=>$company->id,'cashExpense'=>$model->id]) :route('store.cash.expense',['company'=>$company->id]) }}" class="kt-form kt-form--label-right">
            <input id="js-in-edit-mode" type="hidden" name="in_edit_mode" value="{{ isset($model) ? 1 : 0 }}">
            <input id="js-money-payment-id" type="hidden" name="cash_expense_id" value="{{ isset($model) ? $model->id : 0 }}">
            <input type="hidden" name="cash_id" value="{{ isset($model) && $model->cashPayment ? $model->cashPayment->id : 0 }}">
            <input type="hidden" name="current_cheque_id" value="{{ isset($model) && $model->payableCheque ? $model->payableCheque->id : 0 }}">

            @if(isset($model))
            <input type="hidden" name="modelId" value="{{ $model->id }}">
            <input type="hidden" name="modelType" value="CashExpense">
            @endif

            {{-- <input type="hidden" id="ajax-invoice-item" data-single-model="{{ $singleModel ? 1 : 0 }}" value="{{ $singleModel ? $singleModel : 0 }}"> --}}
            @csrf
            @if(isset($model))
            @method('put')
            @endif


          

    {{-- Allocation Information "Commen Card" --}}
    @include('reports.cashExpenses._allocate')
    @include('user_comment',['model'=>$model??null])
    <x-submitting-by-ajax />

    </form>
    <!--end::Form-->

    <!--end::Portlet-->
</div>
</div>
@endsection
@section('js')
<!--begin::Page Scripts(used by this page) -->
<script src="{{ url('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/custom/js/vendors/bootstrap-datepicker.init.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-datepicker.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-select.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/vendors/general/jquery.repeater/src/lib.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/general/jquery.repeater/src/jquery.input.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/vendors/general/jquery.repeater/src/repeater.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/form-repeater.js') }}" type="text/javascript"></script>
<script>

</script>
<script>
    $(document).on('change', 'select#branch-id,select#receiving-currency-id', function() {
        const branchId = $('select#branch-id').val();
        const currencyName = $('select#receiving-currency-id').val();
        const modelId = $('#js-money-payment-id').val();
        const modelType = 'CashExpense';
        const balanceDate = $('.balance-date').val();
        if (branchId != '-1') {
            $.ajax({
                url: "{{ route('get.current.end.balance.of.cash.in.safe.statement',['company'=>$company->id]) }}"
                , data: {
                    branchId
                    , currencyName
                    , modelType
                    , modelId
                    , balanceDate
                }
                , success: function(res) {
                    const endBalance = res.end_balance;
                    $('.cash-balance-js').val(number_format(endBalance))
                }
            })
        }
    })

    $('#type').change(function() {
        selected = $(this).val();
        if (selected == 'outgoing-transfer') {
            $('#bank-charges-id').show()
        } else {
            $('#bank-charges-id').hide()

        }
        $('.js-section-parent').addClass('hidden');
        if (selected) {
            $('#' + selected).removeClass('hidden');
        }


    });
    $('#type').trigger('change')

</script>
<script src="/custom/money-payment.js">

</script>

<script>
    $(document).on('change', '.settlement-amount-class', function() {

    })
    $(function() {
        $('#type').trigger('change');
    })


    $(document).on('change', 'select.currency-class', function() {
        const invoiceCurrency = $('select#invoice-currency-id').val();
        const receivingCurrency = $('select#receiving-currency-id').val();
        const moneyType = $('select#type').val();
        if (invoiceCurrency != receivingCurrency && receivingCurrency && invoiceCurrency) {
            $('.show-only-when-invoice-currency-not-equal-receiving-currency').removeClass('hidden')
        } else {
            // hide 

            $('.show-only-when-invoice-currency-not-equal-receiving-currency').addClass('hidden')
        }

        if (receivingCurrency != invoiceCurrency) {
            $('#remaining-settlement-taking-js').closest('.closest-parent').removeClass('visibility-hidden');
            $('#remaining-settlement-taking-js').closest('.closest-parent').find('.taking-currency-span').html('[ ' + receivingCurrency + ' ]')
        } else {
            $('#remaining-settlement-taking-js').closest('.closest-parent').addClass('visibility-hidden');
        }


    })
    $(document).on('change', '.recalculate-amount-class', function() {
        const moneyType = $(this).attr('data-type')
        const amount = number_unformat($('.main-amount-class[data-type="' + moneyType + '"]').val());
        const exchangeRate = number_unformat($('.exchange-rate-class[data-type="' + moneyType + '"]').val());
        const amountAfterExchangeRate = amount * exchangeRate;
        $('.amount-after-exchange-rate-class[data-type="' + moneyType + '"]').val(amountAfterExchangeRate).trigger('change')
        $('.js-settlement-amount:eq(0)').trigger('change')
    })
    $(document).on('change', 'select[when-change-trigger-account-type-change]', function(e) {
        $('select.js-update-account-number-based-on-account-type').trigger('change')
    });

</script>
<script>
    $(document).on('change', '.balance-date', function() {
        $('select.js-account-number').trigger('change');
        $('select#branch-id,select#receiving-currency-id').trigger('change');
    })

    $(document).on('change', '.js-account-number', function() {
        const parent = $(this).closest('.js-section-parent');
        const financialInstitutionId = parent.find('select.financial-institution-id').val()
        const accountNumber = $(this).val();
        const accountType = parent.find('select.js-update-account-number-based-on-account-type').val();
        const modelId = $('#js-money-payment-id').val();
        const modelType = 'CashExpense';
        const balanceDate = $('.balance-date').val();
        $.ajax({
            url: "{{ route('update.balance.and.net.balance.based.on.account.number',['company'=>$company->id]) }}"
            , data: {
                accountNumber
                , accountType
                , financialInstitutionId
                , modelId
                , modelType
                , balanceDate
            }
            , type: "get"
            , success: function(res) {
                if (res.balance_date) {
                    $(parent).find('.balance-date-js').html('[ ' + res.balance_date + ' ]')
                }
                if (res.net_balance_date) {
                    $(parent).find('.net-balance-date-js').html('[ ' + res.net_balance_date + ' ]')
                }
                $(parent).find('.net-balance-js').val(number_format(res.net_balance))
                $(parent).find('.balance-js').val(number_format(res.balance))

            }
        })
    })

    $(document).on('click', '.trigger-add-new-modal', function() {
        var additionalName = '';
        if ($(this).attr('data-previous-must-be-opened')) {
            const previosSelectorQuery = $(this).attr('data-previous-select-selector');
            const previousSelectorValue = $(previosSelectorQuery).val()
            const previousSelectorTitle = $(this).attr('data-previous-select-title');
            if (!previousSelectorValue) {
                Swal.fire({
                    text: "{{ __('Please Select') }}" + ' ' + previousSelectorTitle
                    , icon: 'warning'
                })
                return;
            }
            const previousSelectorVal = $(previosSelectorQuery).val();
            const previousSelectorHtml = $(previosSelectorQuery).find('option[value="' + previousSelectorVal + '"]').html();
            additionalName = "{{' '. __('For')  }}  [" + previousSelectorHtml + ' ]'
        }
        const parent = $(this).closest('label').parent();
        parent.find('select');
        const type = $(this).attr('data-modal-title')
        const name = $(this).attr('data-modal-name')
        $('.modal-title-add-new-modal-' + name).html("{{ __('Add New ') }}" + type + additionalName);
        parent.find('.modal').modal('show')
    })
    $(document).on('click', '.store-new-add-modal', function() {
        const that = $(this);
        $(this).attr('disabled', true);
        const modalName = $(this).attr('data-modal-name');
        const modalType = $(this).attr('data-modal-type');
        const modal = $(this).closest('.modal');
        const value = modal.find('input.name-class-js').val();
        const previousSelectorSelector = $(this).attr('data-previous-select-selector');
        const previousSelectorValue = previousSelectorSelector ? $(previousSelectorSelector).val() : null;
        const previousSelectorNameInDb = $(this).attr('data-previous-select-name-in-db');

        $.ajax({
            url: "{{ route('admin.store.new.modal',['company'=>$company->id ?? 0  ]) }}"
            , data: {
                "_token": "{{ csrf_token() }}"
                , "modalName": modalName
                , "modalType": modalType
                , "value": value
                , "previousSelectorNameInDb": previousSelectorNameInDb
                , "previousSelectorValue": previousSelectorValue
            }
            , type: "POST"
            , success: function(response) {
                $(that).attr('disabled', false);
                modal.find('input').val('');
                $('.modal').modal('hide')
                if (response.status) {
                    const allSelect = $('select[data-modal-name="' + modalName + '"][data-modal-type="' + modalType + '"]');
                    const allSelectLength = allSelect.length;
                    allSelect.each(function(index, select) {
                        var isSelected = '';
                        if (index == (allSelectLength - 1)) {
                            isSelected = 'selected';
                        }
                        $(select).append(`<option ` + isSelected + ` value="` + response.id + `">` + response.value + `</option>`).selectpicker('refresh').trigger('change')
                    })

                }
            }
            , error: function(response) {}
        });
    })



    $(function() {
        $('select.currency-class').trigger('change')
        $('.recalculate-amount-class').trigger('change')
    })

</script>

<script>
    $(document).on('change', '[data-update-category-name-based-on-category]', function(e) {
        const expenseCategoryId = $('select.expense_category').val()
        if (!expenseCategoryId) {
            return;
        }
        $.ajax({
            url: "{{route('update.expense.category.name.based.on.category',['company'=>$company->id])}}"
            , data: {
                expenseCategoryId
            , }
            , type: "GET"
            , success: function(res) {
                var options = '';
                var currentSelectedId = $('select.category_name').attr('data-current-selected')

                for (var categoryName in res.categoryNames) {
                    var categoryNameId = res.categoryNames[categoryName];
                    options += `<option ${currentSelectedId == categoryNameId ? 'selected' : '' } value="${categoryNameId}"> ${categoryName}  </option> `;
                }
                $('select.category_name').empty().append(options).selectpicker("refresh");
                $('select.category_name').trigger('change')
            }
        })
    })
    $('[data-update-category-name-based-on-category]').trigger('change')

</script>

<script>
    $(document).on('change', 'select.contracts-js', function() {
        const parent = $(this).closest('tr')
        const code = $(this).find('option:selected').data('code')
        const amount = $(this).find('option:selected').data('amount')
        const currency = $(this).find('option:selected').data('currency') ? $(this).find('option:selected').data('currency').toUpperCase() : null;
        if (currency) {
            $(parent).find('.contract-code').val(code)
            $(parent).find('.contract-amount').val(number_format(amount) + ' ' + currency)
        }
    })
    $(document).on('change', 'select.suppliers-or-customers-js', function() {
        const parent = $(this).closest('tr')
        const partnerId = parseInt($(this).val())
        const model = $('#model_type').val()
        let inEditMode = "{{ $inEditMode ?? 0 }}";

        $.ajax({
            url: "{{ route('get.contracts.for.customer.or.supplier',['company'=>$company->id]) }}"
            , data: {
                partnerId
                , model
                , inEditMode
            }
            , type: "get"
            , success: function(res) {
                let contracts = '';
                const currentSelected = $(parent).find('select.contracts-js').data('current-selected')
                for (var contract of res.contracts) {
                    contracts += `<option ${currentSelected ==contract.id ? 'selected' :'' } value="${contract.id}" data-code="${contract.code}" data-amount="${contract.amount}" data-currency="${contract.currency}" >${contract.name}</option>`;
                }
                parent.find('select.contracts-js').empty().append(contracts).trigger('change')
            }
        })
    })
    $(function() {
        $('select.suppliers-or-customers-js').trigger('change')
    })

</script>
@endsection
