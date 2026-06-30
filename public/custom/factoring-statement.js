(function ($) {
    const root = $('#factoring-statement-form');
    if (!root.length) return;

    const currenciesBaseUrl = root.data('currencies-url');
    const contractsBaseUrl = root.data('contracts-url');

    const $factoringCompany = $('#factoring-company-id');
    const $currency = $('#currency-id');
    const $contract = $('#factoring-contract-id');
    const $startDate = $('#start-date');
    const $endDate = $('#end-date');

    function rebuildSelect($select, items, valueKey, labelKey, placeholder) {
        if ($select.hasClass('kt_bootstrap_select')) {
            $select.selectpicker('destroy');
        }

        $select.empty();
        $select.append($('<option>', { value: '', text: placeholder || 'Select' }));
        items.forEach(function (item) {
            $select.append($('<option>', { value: item[valueKey], text: item[labelKey] }));
        });

        $select.addClass('kt_bootstrap_select');
        $select.selectpicker({ liveSearch: true });
    }

    function loadCurrencies() {
        const companyId = $factoringCompany.val();
        if (!companyId) {
            rebuildSelect($currency, [], 'id', 'name', 'Select');
            rebuildSelect($contract, [], 'id', 'label', 'Select');
            return;
        }

        $.get(currenciesBaseUrl + '/' + companyId)
            .done(function (response) {
                const currencies = response.currencies || {};
                const items = Object.keys(currencies).map(function (key) {
                    return { id: key, name: currencies[key] };
                });
                rebuildSelect($currency, items, 'id', 'name', 'Select');
                rebuildSelect($contract, [], 'id', 'label', 'Select');
            });
    }

    function loadContracts() {
        const companyId = $factoringCompany.val();
        const currency = $currency.val();
        if (!companyId || !currency) {
            rebuildSelect($contract, [], 'id', 'label', 'Select');
            return;
        }

        $.get(contractsBaseUrl + '/' + companyId + '/' + currency, {
            start_date: $startDate.val(),
            end_date: $endDate.val(),
        }).done(function (response) {
            rebuildSelect($contract, response.contracts || [], 'id', 'label', 'Select');
        });
    }

    $factoringCompany.on('change changed.bs.select', loadCurrencies);
    $currency.on('change changed.bs.select', loadContracts);
    $startDate.on('change', loadContracts);
    $endDate.on('change', loadContracts);

    $('.kt_bootstrap_select').selectpicker();
})(jQuery);
