(function ($) {
    const root = $('#factoring-with-recourse-index');
    if (!root.length) {
        return;
    }

    const accountNumbersBaseUrl = root.data('account-numbers-url');

    function getSelectValue($select) {
        if (!$select.length) {
            return '';
        }

        if ($select.hasClass('kt_bootstrap_select') || $select.parent().hasClass('bootstrap-select')) {
            const pickerVal = $select.selectpicker('val');
            if (pickerVal !== null && pickerVal !== undefined && pickerVal !== '') {
                return pickerVal;
            }
        }

        return $select.val();
    }

    function setSelectValue($select, value) {
        if (!$select.length || value === null || value === undefined || value === '') {
            return;
        }

        $select.val(String(value));

        if ($select.hasClass('kt_bootstrap_select') || $select.parent().hasClass('bootstrap-select')) {
            $select.selectpicker('val', String(value));
            $select.selectpicker('refresh');
        }
    }

    function rebuildSelect($select, items, selectedValue) {
        const wasPicker = $select.hasClass('kt_bootstrap_select');
        if (wasPicker) {
            $select.selectpicker('destroy');
            $select.removeClass('kt_bootstrap_select');
        }

        $select.empty();
        $select.append($('<option>', { value: '', text: 'Select' }));
        if (selectedValue && !items.some(function (item) {
            return String(item.id) === String(selectedValue);
        })) {
            items.unshift({ id: selectedValue, name: selectedValue });
        }
        items.forEach(function (item) {
            $select.append($('<option>', {
                value: item.id,
                text: item.name,
            }));
        });

        if (selectedValue) {
            $select.val(String(selectedValue));
        }

        $select.addClass('kt_bootstrap_select');
        $select.selectpicker({
            liveSearch: true,
            container: 'body',
        });

        if (selectedValue) {
            $select.selectpicker('val', String(selectedValue));
        }
    }

    function loadAccountNumbers($modal, bankClass, accountTypeClass, accountNumberClass, preserveDefault) {
        const accountType = getSelectValue($modal.find(accountTypeClass))
            || $modal.data('default-account-type-id');
        const bankId = getSelectValue($modal.find(bankClass))
            || $modal.data('default-bank-id');
        const currency = $modal.data('invoice-currency') || 'EGP';
        const selectedValue = preserveDefault
            ? ($modal.data('default-account-number') || '')
            : (getSelectValue($modal.find(accountNumberClass)) || '');
        const $accountNumber = $modal.find(accountNumberClass);

        if (!accountType || !bankId) {
            if (selectedValue) {
                rebuildSelect($accountNumber, [{ id: selectedValue, name: selectedValue }], selectedValue);
            }
            return;
        }

        $.get(accountNumbersBaseUrl + '/' + accountType + '/' + currency + '/' + bankId)
            .done(function (response) {
                const items = Object.keys(response.data || {}).map(function (accountNumber) {
                    return { id: accountNumber, name: response.data[accountNumber] };
                });
                rebuildSelect($accountNumber, items, selectedValue || $modal.data('default-account-number'));
            })
            .fail(function () {
                const fallback = selectedValue || $modal.data('default-account-number');
                if (fallback) {
                    rebuildSelect($accountNumber, [{ id: fallback, name: fallback }], fallback);
                }
            });
    }

    function initModalSelects($modal, selector) {
        $modal.find(selector).each(function () {
            const $select = $(this);
            if ($select.parent().hasClass('bootstrap-select')) {
                return;
            }
            $select.addClass('kt_bootstrap_select');
            $select.selectpicker({
                liveSearch: true,
                container: 'body',
            });
        });
    }

    function syncModalDefaults($modal) {
        setSelectValue($modal.find('.collect-bank, .reject-bank'), $modal.data('default-bank-id'));
        setSelectValue($modal.find('.collect-account-type, .reject-account-type'), $modal.data('default-account-type-id'));
    }

    $('.collect-modal, .reject-modal').each(function () {
        initModalSelects($(this), '.collect-bank, .collect-account-type, .collect-account-number, .reject-bank, .reject-account-type, .reject-account-number');
    });

    $(document).on('change changed.bs.select', '.collect-bank, .collect-account-type', function () {
        const $modal = $(this).closest('.collect-modal');
        $modal.data('default-account-number', '');
        loadAccountNumbers($modal, '.collect-bank', '.collect-account-type', '.collect-account-number', false);
    });

    $(document).on('change changed.bs.select', '.reject-bank, .reject-account-type', function () {
        const $modal = $(this).closest('.reject-modal');
        $modal.data('default-account-number', '');
        loadAccountNumbers($modal, '.reject-bank', '.reject-account-type', '.reject-account-number', false);
    });

    $('.collect-modal, .reject-modal').on('show.bs.modal', function () {
        syncModalDefaults($(this));
    });

    $('.collect-modal').on('shown.bs.modal', function () {
        loadAccountNumbers($(this), '.collect-bank', '.collect-account-type', '.collect-account-number', true);
    });

    $('.reject-modal').on('shown.bs.modal', function () {
        loadAccountNumbers($(this), '.reject-bank', '.reject-account-type', '.reject-account-number', true);
    });
})(jQuery);
