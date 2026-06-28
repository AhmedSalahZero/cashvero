<style>
    .money-flow-dark .repeater-btn {
        cursor: pointer;
        margin-right: 10px;
    }

    .money-flow-dark .repeater-btn:disabled {
        cursor: not-allowed;
        opacity: 0.5;
    }

    .money-flow-dark .repeater-row {
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .money-flow-dark .delete-button {
        background-color: #ef4444;
        color: #fff;
        border: 1px solid rgba(239, 68, 68, 0.5);
        border-radius: 5px;
        padding: 0.4rem 0.75rem;
    }

    .money-flow-dark .delete-button:hover:not(:disabled) {
        background-color: #dc2626;
    }

    .money-flow-dark .repeater-button.btn-primary {
        background: linear-gradient(135deg, #095309, #063d06);
        border-color: rgba(83, 214, 105, 0.4);
        color: #faeeda;
    }

    .money-flow-dark .repeater-button.btn-primary:hover:not(:disabled) {
        background: linear-gradient(135deg, #0a6b0c, #085208);
        border-color: rgba(83, 214, 105, 0.6);
        color: #fff;
    }
</style>

<div id="repeater-container" data-name="revenues">
	@foreach(count($company->interestRevenuesAccounts) ? $company->interestRevenuesAccounts : [null]  as $interestRevenuesAccount)
    <div class="repeater-row d-flex align-items-center" data-first="true">

        <div class="col-md-3">
            <label for="">{{ __('Bank') }} @include('star')</label>
            <select name="financial_institution_id" class="bank-select form-control">
                <option value="all" selected>All</option>
                @foreach($financialInstitutionBanks as $financialInstitutionBank)
                <option value="{{ $financialInstitutionBank->id }}" 
				{{ isset($interestRevenuesAccount) && $interestRevenuesAccount->getFinancialInstitutionId() == $financialInstitutionBank->id ? 'selected' : '' }}
				>{{ $financialInstitutionBank->getName() }}</option>
                @endforeach

            </select>
        </div>

        <div class="col-md-3">
            <label for="">{{ __('Chart Of Account Number') }} @include('star')</label>
            <input type="text" value="{{ isset($interestRevenuesAccount)  ? $interestRevenuesAccount->getOdooCode() : ''  }}" class="code-input form-control" name="odoo_code" placeholder="{{ __('Chart Of Account Number') }}">
        </div>

        {{-- @if(!isset($model)) --}}
        <button type="button" class="repeater-button btn btn-primary repeater-btn">{{ __('Repeat') }}</button>
        {{-- @endif --}}
    </div>
	@endforeach
	
	
	
</div>



@push('js_end')


<script>
    // Track selected banks across all rows
    let selectedBanks = [];

    // Get the fixed name from data-name attribute
    const container = document.getElementById('repeater-container');
    const fixedName = container.dataset.name || 'default';

    function initializeRow(row, index) {
        const bankSelect = row.querySelector('.bank-select');
        const codeInput = row.querySelector('.code-input');
        const nameInput = row.querySelector('.name-input');
        const repeaterButton = row.querySelector('.repeater-button');
        const deleteButton = row.querySelector('.delete-button');
        const isFirstRow = row.dataset.first === 'true';

        // Store current selected value to preserve it
        const currentValue = bankSelect.value;

        // Set name attributes based on fixedName
        bankSelect.name = currentValue !== 'all' ? `${fixedName}[${index}][bank]` : 'bank';
        if (codeInput) {
            codeInput.name = currentValue !== 'all' ? `${fixedName}[${index}][odoo_code]` : 'odoo_code';

        }
        // nameInput.name = currentValue !== 'all' ? `${fixedName}[${index}][name]` : 'name';

        // Remove existing event listeners by cloning elements
        const newBankSelect = bankSelect.cloneNode(true);
        const newRepeaterButton = repeaterButton.cloneNode(true);
        const newDeleteButton = deleteButton ? deleteButton.cloneNode(true) : null;
        bankSelect.replaceWith(newBankSelect);
        repeaterButton.replaceWith(newRepeaterButton);
        if (deleteButton && newDeleteButton) {
            deleteButton.replaceWith(newDeleteButton);
        }

        // Reassign variables after cloning
        const updatedBankSelect = row.querySelector('.bank-select');
        const updatedCodeInput = row.querySelector('.code-input');
        const updatedNameInput = row.querySelector('.name-input');
        const updatedRepeaterButton = row.querySelector('.repeater-button');
        const updatedDeleteButton = row.querySelector('.delete-button');

        // Restore selected value
        if (updatedBankSelect.querySelector(`option[value="${currentValue}"]`)) {
            updatedBankSelect.value = currentValue;
        } else if (updatedBankSelect.options.length > 0) {
            updatedBankSelect.value = updatedBankSelect.options[0].value;
        }

        // Update selected banks list
        if (updatedBankSelect.value !== 'all' && !selectedBanks.includes(updatedBankSelect.value)) {
            selectedBanks.push(updatedBankSelect.value);
        }

        // Update repeater button state
        updatedRepeaterButton.disabled = updatedBankSelect.value === 'all';

        // Handle bank selection change
        updatedBankSelect.addEventListener('change', () => {
            updatedRepeaterButton.disabled = updatedBankSelect.value === 'all';
            // Update name attributes based on selection
            updatedBankSelect.name = updatedBankSelect.value !== 'all' ? `${fixedName}[${index}][bank]` : 'bank';
            if (updatedCodeInput) {
                updatedCodeInput.name = updatedBankSelect.value !== 'all' ? `${fixedName}[${index}][odoo_code]` : 'odoo_code';

            }
            //          updatedNameInput.name = updatedBankSelect.value !== 'all' ? `${fixedName}[${index}][name]` : 'name';

            // Update selected banks list
            selectedBanks = Array.from(document.querySelectorAll('.bank-select'))
                .map(select => select.value)
                .filter(value => value !== 'all');
        });

        // Handle repeater button click
        updatedRepeaterButton.addEventListener('click', () => {
            if (updatedBankSelect.value !== 'all') {
                addNewRow(updatedBankSelect.value);
                const allOption = updatedBankSelect.querySelector('option[value="all"]');
                if (allOption) {
                    allOption.remove();
                }
            }
        });

        // Handle delete button click
        if (updatedDeleteButton) {
            updatedDeleteButton.addEventListener('click', () => {
                // Remove the bank from selectedBanks
                const bankValue = updatedBankSelect.value;
                selectedBanks = selectedBanks.filter(bank => bank !== bankValue);
                row.remove();
                updateRows();
            });
        }
    }

    function addNewRow(selectedBank) {
        const container = document.getElementById('repeater-container');
        const firstRow = document.querySelector('.repeater-row[data-first="true"]');
        const newRow = firstRow.cloneNode(true);

        // Configure new row
        newRow.removeAttribute('data-first');
        const bankSelect = newRow.querySelector('.bank-select');
        const allOption = bankSelect.querySelector('option[value="all"]');
        if (allOption) {
            allOption.remove();
        }

        // Remove all previously selected banks
        selectedBanks.forEach(bank => {
            const option = bankSelect.querySelector(`option[value="${bank}"]`);
            if (option) {
                option.remove();
            }
        });

        // Explicitly select the first available option
        if (bankSelect.options.length > 0) {
            bankSelect.selectedIndex = 0;
            if (bankSelect.value !== 'all' && !selectedBanks.includes(bankSelect.value)) {
                selectedBanks.push(bankSelect.value);
            }
        }

        // Clear the code and name inputs
        if (newRow.querySelector('.code-input')) {
            newRow.querySelector('.code-input').value = '';
        }
        if (newRow.querySelector('.name-input')) {
            newRow.querySelector('.name-input').value = '';

        }

        // Add delete button if not first row
        if (!newRow.querySelector('.delete-button')) {
            const deleteButton = document.createElement('button');
            deleteButton.className = 'delete-button btn';
            deleteButton.textContent = 'Delete';
            newRow.appendChild(deleteButton);
        }

        container.appendChild(newRow);
        updateRows();
    }

    function updateRows() {
        const rows = document.querySelectorAll('.repeater-row');
        rows.forEach((row, index) => {
            initializeRow(row, index);
        });
        // Add "All" option to the first row if only one row exists
        //   if (rows.length === 1) {
        //    //   const bankSelect = rows[0].querySelector('.bank-select');
        //   if (!bankSelect.querySelector('option[value="all"]')) {
        //      const newAllOption = document.createElement('option');
        //      newAllOption.value = 'all';
        //       newAllOption.textContent = 'All';
        //      bankSelect.insertBefore(newAllOption, bankSelect.firstChild);
        //    bankSelect.name = 'bank';
        //    rows[0].querySelector('.code-input').name = 'odoo_code';
        //      rows[0].querySelector('.name-input').name = 'name';
        //         rows[0].querySelector('.repeater-button').disabled = bankSelect.value === 'all';
        //       }
        //    }
    }

    // Initialize all rows on page load
    updateRows();

</script>


@endpush
