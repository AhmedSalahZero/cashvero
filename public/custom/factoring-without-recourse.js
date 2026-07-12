(function ($) {
    const root = $('#factoring-without-recourse-form');
    if (!root.length) return;

    const isEdit = root.data('mode') === 'edit';
    const exceptTxId = root.data('except-factoring-transaction-id') || null;
    const initialValues = root.data('initial-values') || null;

    const contractsBaseUrl = root.data('contracts-url');
    const currenciesBaseUrl = root.data('currencies-url');
    const invoicesBaseUrl = root.data('invoices-url');
    const calculateUrl = root.data('calculate-url');
    const accountNumbersBaseUrl = root.data('account-numbers-url');

    const $factoringDate = $('#factoring-date');
    const $factoringCompany = $('#factoring-company-id');
    const $factoringContract = $('#factoring-contract-id');
    const $customer = $('#customer-id');
    const $invoiceCurrency = $('#invoice-currency-id');
    const $customerInvoice = $('#customer-invoice-id');
    const $factoringPercentage = $('#factoring-percentage');
    const $otherCharges = $('#other-charges');
    const $financialInstitution = $('#financial-institution-id');
    const $accountType = $('#account-type-id');
    const $accountNumber = $('#account-number-id');
    const $factoringInterestAmount = $('#factoring-interest-amount-display');
    const $receivedAmount = $('#received-amount-display');

    let serverAmounts = null;
    let skipLinkedRecalculation = false;

    function ajaxParams(extra) {
        const params = extra || {};
        if (exceptTxId) {
            params.except_factoring_transaction_id = exceptTxId;
        }
        return params;
    }

    function getCustomerInvoiceId() {
        if ($customerInvoice.length) {
            return $customerInvoice.val();
        }
        return $('input[name="customer_invoice_id"]').val();
    }

    function getInvoiceCurrency() {
        if ($invoiceCurrency.length) {
            return $invoiceCurrency.val();
        }
        return $('input[name="invoice_currency"]').val();
    }

    function rebuildSelect($select, items, valueKey, labelKey, placeholder, selectedValue) {
        const wasPicker = $select.hasClass('kt_bootstrap_select');
        if (wasPicker) {
            $select.selectpicker('destroy');
            $select.removeClass('kt_bootstrap_select');
        }

        $select.empty();
        $select.append($('<option>', { value: '', text: placeholder || 'Select' }));
        items.forEach(function (item) {
            $select.append($('<option>', {
                value: item[valueKey],
                text: item[labelKey],
            }));
        });

        if (selectedValue) {
            $select.val(String(selectedValue));
        }

        $select.addClass('kt_bootstrap_select');
        $select.selectpicker({ liveSearch: true });

        if (selectedValue) {
            $select.selectpicker('val', String(selectedValue));
        }
    }

    function formatNumber(value) {
        if (value === null || value === undefined || value === '') return '';
        return Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function parseAmount(value) {
        let parsedValue = value;
        if (typeof number_unformat === 'function') {
            parsedValue = number_unformat(parsedValue);
        }
        return parseFloat(parsedValue) || 0;
    }

    function setAmountField($field, value) {
        skipLinkedRecalculation = true;
        $field.val(formatNumber(value));
        $field.trigger('change');
        skipLinkedRecalculation = false;
    }

    function updateRemainingLimit(value) {
        $('#remaining-limit-display').val(formatNumber(value));
    }

    function loadContracts(selectedContractId) {
        const company = $factoringCompany.val();
        if (!company) {
            rebuildSelect($factoringContract, [], 'id', 'label', 'Select');
            return;
        }

        const selectedValue = selectedContractId || $factoringContract.val();

        $.get(contractsBaseUrl + '/' + company, ajaxParams({ factoring_date: $factoringDate.val() }))
            .done(function (response) {
                rebuildSelect($factoringContract, response.contracts || [], 'id', 'label', 'Select', selectedValue);
                const selectedContract = (response.contracts || []).find(function (item) {
                    return String(item.id) === String($factoringContract.val());
                });
                if (selectedContract && selectedContract.remaining_limit !== undefined) {
                    updateRemainingLimit(selectedContract.remaining_limit);
                }
                if (!isEdit || selectedContractId === undefined) {
                    recalculate();
                }
            })
            .fail(function () {
                rebuildSelect($factoringContract, [], 'id', 'label', 'Select');
            });
    }

    function loadCurrencies() {
        const customerId = $customer.val() || $('input[name="customer_id"]').val();
        if (!customerId) {
            rebuildSelect($invoiceCurrency, [], 'id', 'name', 'Select');
            rebuildSelect($customerInvoice, [], 'id', 'invoice_number', 'Select');
            clearCalculatedFields();
            return;
        }

        $.get(currenciesBaseUrl + '/' + customerId, ajaxParams())
            .done(function (response) {
                const currencies = response.currencies || {};
                const items = Object.keys(currencies).map(function (key) {
                    return { id: key, name: currencies[key] };
                });
                rebuildSelect($invoiceCurrency, items, 'id', 'name', 'Select');
                rebuildSelect($customerInvoice, [], 'id', 'invoice_number', 'Select');
                clearInvoiceFields();
            });
    }

    function loadInvoices() {
        const customerId = $customer.val();
        const currency = $invoiceCurrency.val();
        if (!customerId || !currency) {
            rebuildSelect($customerInvoice, [], 'id', 'invoice_number', 'Select');
            clearInvoiceFields();
            return;
        }

        $.get(invoicesBaseUrl + '/' + customerId + '/' + currency, ajaxParams())
            .done(function (response) {
                const invoices = (response.invoices || []).map(function (invoice) {
                    return {
                        id: invoice.id,
                        invoice_number: invoice.invoice_number + ' | ' + invoice.invoice_amount_formatted,
                        invoice_due_date: invoice.invoice_due_date,
                    };
                });
                rebuildSelect($customerInvoice, invoices, 'id', 'invoice_number', 'Select');
                clearInvoiceFields();
            });
    }

    function clearInvoiceFields() {
        if (isEdit) {
            return;
        }

        $('#invoice-amount-display').val('');
        $('#invoice-due-date').val('');
        $('#invoice-due-date-display').val('');
        serverAmounts = null;
        recalculate();
    }

    function clearCalculatedFields() {
        if (isEdit) {
            return;
        }

        $('#invoice-amount-display, #invoice-due-date, #invoice-due-date-display, #factoring-amount-display, #contract-interest-rate-display, #diff-in-days-display, #factoring-interest-amount-display, #received-amount-display, #remaining-limit-display').val('');
        serverAmounts = null;
    }

    function loadAccountNumbers(selectedAccountNumber) {
        const accountType = $accountType.val();
        const bankId = $financialInstitution.val();
        const currency = getInvoiceCurrency() || 'EGP';
        const selectedValue = selectedAccountNumber || $accountNumber.val();

        if (!accountType || !bankId) {
            rebuildSelect($accountNumber, [], 'id', 'name', 'Select');
            return;
        }

        $.get(accountNumbersBaseUrl + '/' + accountType + '/' + currency + '/' + bankId)
            .done(function (response) {
                const items = Object.keys(response.data || {}).map(function (accountNumber) {
                    return { id: accountNumber, name: response.data[accountNumber] };
                });
                rebuildSelect($accountNumber, items, 'id', 'name', 'Select', selectedValue);
            });
    }

    function recalculateReceivedFromInterest() {
        if (!serverAmounts || skipLinkedRecalculation) {
            return;
        }

        const interest = parseAmount($factoringInterestAmount.val());
        const otherCharges = parseAmount($otherCharges.val());
        const received = serverAmounts.factoring_amount - interest - otherCharges;
        setAmountField($receivedAmount, received);
    }

    function recalculateInterestFromReceived() {
        if (!serverAmounts || skipLinkedRecalculation) {
            return;
        }

        const received = parseAmount($receivedAmount.val());
        const otherCharges = parseAmount($otherCharges.val());
        const interest = serverAmounts.factoring_amount - received - otherCharges;
        setAmountField($factoringInterestAmount, interest);
    }

    function recalculate() {
        const invoiceId = getCustomerInvoiceId();
        const contractId = $factoringContract.val();
        if (!invoiceId || !contractId) {
            if (!isEdit) {
                clearCalculatedFields();
            }
            return;
        }

        $.post(calculateUrl, ajaxParams({
            customer_invoice_id: invoiceId,
            factoring_contract_id: contractId,
            factoring_percentage: $factoringPercentage.val() || 0,
            other_charges: $otherCharges.val() || 0,
            factoring_date: $factoringDate.val(),
        })).done(function (response) {
            serverAmounts = {
                factoring_amount: parseFloat(response.factoring_amount) || 0,
            };

            $('#invoice-amount-display').val(formatNumber(response.invoice_amount));
            $('#invoice-due-date').val(response.invoice_due_date || '');
            $('#invoice-due-date-display').val(response.invoice_due_date || '');
            $('#factoring-amount-display').val(formatNumber(response.factoring_amount));
            $('#contract-interest-rate-display').val(formatNumber(response.contract_interest_rate));
            $('#diff-in-days-display').val(response.diff_in_days);
            updateRemainingLimit(response.remaining_limit);
            setAmountField($factoringInterestAmount, response.factoring_interest_amount);
            setAmountField($receivedAmount, response.received_amount);
        });
    }

    $factoringDate.on('change', loadContracts);
    $factoringCompany.on('change changed.bs.select', loadContracts);

    if ($customer.length) {
        $customer.on('change changed.bs.select', loadCurrencies);
    }

    if ($invoiceCurrency.length) {
        $invoiceCurrency.on('change changed.bs.select', function () {
            loadInvoices();
            loadAccountNumbers();
        });
    }

    if ($customerInvoice.length) {
        $customerInvoice.on('change changed.bs.select', recalculate);
    }

    $factoringContract.on('change changed.bs.select', recalculate);
    $factoringPercentage.on('change keyup', recalculate);
    $otherCharges.on('change keyup', recalculate);
    $financialInstitution.on('change changed.bs.select', loadAccountNumbers);
    $accountType.on('change changed.bs.select', loadAccountNumbers);

    $factoringInterestAmount.on('change keyup', recalculateReceivedFromInterest);
    $receivedAmount.on('change keyup', recalculateInterestFromReceived);

    $(document).find('.is-date-css').datepicker({
        dateFormat: 'mm-dd-yy',
        autoclose: true,
    });

    $('.kt_bootstrap_select').selectpicker();

    if (isEdit && initialValues) {
        serverAmounts = {
            factoring_amount: parseFloat(initialValues.factoring_amount) || 0,
        };
        loadAccountNumbers(initialValues.account_number);
    }

    $(document).on('change', 'input:not([placeholder])[type="text"]:not(.exclude-text)', function () {
        if (!$(this).hasClass('exclude-text')) {
            let val = $(this).val();
            if (typeof number_unformat === 'function') {
                val = number_unformat(val);
            }
            $(this).parent().find('input[type="hidden"]:not([name="_token"])').val(val);
        }
    });
})(jQuery);
