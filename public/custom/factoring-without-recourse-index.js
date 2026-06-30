(function ($) {
    const root = $('#factoring-without-recourse-index');
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

    function loadAccountNumbers($modal, preserveDefault) {
        const accountType = getSelectValue($modal.find('.difference-account-type'))
            || $modal.data('default-account-type-id');
        const bankId = getSelectValue($modal.find('.difference-bank'))
            || $modal.data('default-bank-id');
        const currency = $modal.data('invoice-currency') || 'EGP';
        const selectedValue = preserveDefault
            ? ($modal.data('default-account-number') || '')
            : (getSelectValue($modal.find('.difference-account-number')) || '');
        const $accountNumber = $modal.find('.difference-account-number');

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

    function initDifferenceModalSelects($modal) {
        $modal.find('.difference-bank, .difference-account-type, .difference-account-number').each(function () {
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

    function syncDifferenceModalDefaults($modal) {
        setSelectValue($modal.find('.difference-bank'), $modal.data('default-bank-id'));
        setSelectValue($modal.find('.difference-account-type'), $modal.data('default-account-type-id'));
    }

    $('.difference-received-modal').each(function () {
        initDifferenceModalSelects($(this));
    });

    $(document).on('change changed.bs.select', '.difference-bank, .difference-account-type', function () {
        const $modal = $(this).closest('.difference-received-modal');
        loadAccountNumbers($modal, false);
    });

    $('.difference-received-modal').on('show.bs.modal', function () {
        const $modal = $(this);
        syncDifferenceModalDefaults($modal);
    });

    $('.difference-received-modal').on('shown.bs.modal', function () {
        loadAccountNumbers($(this), true);
    });
})(jQuery);
