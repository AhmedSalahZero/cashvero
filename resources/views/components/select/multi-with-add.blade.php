@props([
	'options'=>$options
])

<div class="multiselect-container">
    <button class="multiselect-trigger">
      <span class="selected-text">Select options...</span>
      <span class="arrow">▼</span>
    </button>
    <div class="multiselect-dropdown">
      <div class="multiselect-search">
        <input type="text" placeholder="Search..." class="search-input">
      </div>
      <div class="add-option">
        <input type="text" placeholder="Add new option..." class="add-option-input">
        <button type="button" class="btn-add-option">Add</button>
      </div>
      <div class="multiselect-buttons">
        <button type="button" class="btn-select-all">Select All</button>
        <button type="button" class="btn-deselect-all">Deselect All</button>
      </div>
      <div class="multiselect-options">
        <label class="option-item">
          <input type="checkbox" value="option1"> Option 1
        </label>
        <label class="option-item">
          <input type="checkbox" value="option2"> Option 2
        </label>
        <label class="option-item">
          <input type="checkbox" value="option3"> Option 3
        </label>
      </div>
    </div>
    <input type="hidden" name="selectedOptions" id="selectedOptions">
  </div>
  
@once
@push('js_end')



  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const repeaterContainer = document.querySelector('.repeater-container');
      const repeaterItems = document.querySelector('.repeater-items');
      const addRepeaterBtn = document.querySelector('.btn-add-repeater');

      // Function to initialize a single multiselect instance
      function initializeMultiselect(container) {
        const trigger = container.querySelector('.multiselect-trigger');
        const dropdown = container.querySelector('.multiselect-dropdown');
        const searchInput = container.querySelector('.search-input');
        const addOptionInput = container.querySelector('.add-option-input');
        const addOptionBtn = container.querySelector('.btn-add-option');
        const selectAllBtn = container.querySelector('.btn-select-all');
        const deselectAllBtn = container.querySelector('.btn-deselect-all');
        const optionsContainer = container.querySelector('.multiselect-options');
        const selectedText = container.querySelector('.selected-text');
        const hiddenInput = container.querySelector('.selected-options');
        let selectedValues = [];

        // Toggle dropdown
        trigger.addEventListener('click', function(e) {
          e.stopPropagation();
          dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        });

        // Close on outside click
        document.addEventListener('click', function(e) {
          if (!container.contains(e.target)) {
            dropdown.style.display = 'none';
          }
        });

        // Function to bind checkbox events
        function bindCheckboxEvents(checkbox) {
          checkbox.addEventListener('change', updateSelected);
        }

        // Update selected values and display
        function updateSelected() {
          const options = optionsContainer.querySelectorAll('.option-item input[type="checkbox"]');
          selectedValues = Array.from(options)
            .filter(option => option.checked)
            .map(option => option.value);
          selectedText.textContent = selectedValues.length ? `${selectedValues.length} selected` : 'Select options...';
          hiddenInput.value = selectedValues.join(',');
        }

        // Bind initial checkbox events
        optionsContainer.querySelectorAll('.option-item input[type="checkbox"]').forEach(bindCheckboxEvents);

        // Select All
        selectAllBtn.addEventListener('click', function(e) {
          e.preventDefault();
          optionsContainer.querySelectorAll('.option-item input[type="checkbox"]').forEach(option => option.checked = true);
          updateSelected();
        });

        // Deselect All
        deselectAllBtn.addEventListener('click', function(e) {
          e.preventDefault();
          optionsContainer.querySelectorAll('.option-item input[type="checkbox"]').forEach(option => option.checked = false);
          updateSelected();
        });

        // Search filter
        searchInput.addEventListener('input', function() {
          const query = this.value.toLowerCase();
          optionsContainer.querySelectorAll('.option-item').forEach(item => {
            const label = item.textContent.toLowerCase();
            item.style.display = label.includes(query) ? 'flex' : 'none';
          });
        });

        // Add new option
        addOptionBtn.addEventListener('click', function(e) {
          e.preventDefault();
          const newOptionText = addOptionInput.value.trim();
          if (newOptionText) {
            const newValue = `option${Date.now()}`; // Unique value using timestamp
            const newOption = document.createElement('label');
            newOption.className = 'option-item';
            newOption.innerHTML = `<input type="checkbox" value="${newValue}"> ${newOptionText}`;
            optionsContainer.appendChild(newOption);
            bindCheckboxEvents(newOption.querySelector('input[type="checkbox"]'));
            addOptionInput.value = '';
            updateSelected();
          }
        });

        // Add option with Enter key
        addOptionInput.addEventListener('keypress', function(e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            addOptionBtn.click();
          }
        });

        updateSelected(); // Initial call
      }

      // Initialize existing multiselects
      document.querySelectorAll('.multiselect-container').forEach(initializeMultiselect);

      // Add new repeater item
      addRepeaterBtn.addEventListener('click', function() {
        const newRepeaterItem = document.createElement('div');
        newRepeaterItem.className = 'repeater-item';
        newRepeaterItem.innerHTML = `
          <div class="multiselect-container">
            <button class="multiselect-trigger">
              <span class="selected-text">Select options...</span>
              <span class="arrow">▼</span>
            </button>
            <div class="multiselect-dropdown">
              <div class="multiselect-search">
                <input type="text" placeholder="Search..." class="search-input">
              </div>
              <div class="add-option">
                <input type="text" placeholder="Add new option..." class="add-option-input">
                <button type="button" class="btn-add-option">Add</button>
              </div>
              <div class="multiselect-buttons">
                <button type="button" class="btn-select-all">Select All</button>
                <button type="button" class="btn-deselect-all">Deselect All</button>
              </div>
              <div class="multiselect-options">
                <label class="option-item">
                  <input type="checkbox" value="option1"> Option 1
                </label>
                <label class="option-item">
                  <input type="checkbox" value="option2"> Option 2
                </label>
                <label class="option-item">
                  <input type="checkbox" value="option3"> Option 3
                </label>
              </div>
            </div>
            <input type="hidden" name="selectedOptions" class="selected-options">
          </div>
        `;
        repeaterItems.appendChild(newRepeaterItem);
        initializeMultiselect(newRepeaterItem.querySelector('.multiselect-container'));
      });
    });
  </script>
  
  
@endpush
@endonce


  <style>
    :root {
      --primary-color: #007bff;
      --bg-color: #fff;
      --border-color: #ddd;
      --text-color: #333;
      --hover-bg: #f8f9fa;
    }

    .multiselect-container {
      position: relative;
      display: inline-block;
      font-family: Arial, sans-serif;
    }

    .multiselect-trigger {
      background: var(--bg-color);
      border: 1px solid var(--border-color);
      border-radius: 6px;
      padding: 10px 16px;
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      align-items: center;
      min-width: 200px;
      transition: border-color 0.2s ease;
    }

    .multiselect-trigger:hover {
      border-color: var(--primary-color);
    }

    .multiselect-dropdown {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: var(--bg-color);
      border: 1px solid var(--border-color);
      border-radius: 6px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      max-height: 300px;
      overflow-y: auto;
      z-index: 1000;
      display: none;
      animation: fadeIn 0.2s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .multiselect-search {
      padding: 12px;
      border-bottom: 1px solid var(--border-color);
    }

    .search-input, .add-option-input {
      width: 100%;
      padding: 8px;
      border: 1px solid var(--border-color);
      border-radius: 4px;
      font-size: 14px;
    }

    .multiselect-buttons {
      padding: 8px 12px;
      display: flex;
      gap: 8px;
      border-bottom: 1px solid var(--border-color);
    }

    .btn-select-all,
    .btn-deselect-all,
    .btn-add-option {
      flex: 1;
      padding: 6px 12px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 12px;
      transition: background 0.2s ease;
    }

    .btn-select-all {
      background: #28a745;
      color: white;
    }

    .btn-select-all:hover {
      background: #218838;
    }

    .btn-deselect-all {
      background: #dc3545;
      color: white;
    }

    .btn-deselect-all:hover {
      background: #c82333;
    }

    .btn-add-option {
      background: var(--primary-color);
      color: white;
    }

    .btn-add-option:hover {
      background: #0056b3;
    }

    .multiselect-options {
      max-height: 200px;
      overflow-y: auto;
    }

    .option-item {
      display: flex;
      align-items: center;
      padding: 10px 12px;
      cursor: pointer;
      transition: background 0.2s ease;
      border-bottom: 1px solid #f0f0f0;
    }

    .option-item:hover {
      background: var(--hover-bg);
    }

    .option-item input[type="checkbox"] {
      margin-right: 8px;
      accent-color: var(--primary-color);
    }

    .selected-text::after {
      content: attr(data-count) ? ' (' attr(data-count) ' selected)' : '';
      font-size: 12px;
      color: #666;
    }

    .add-option {
      padding: 8px 12px;
      border-bottom: 1px solid var(--border-color);
      display: flex;
      gap: 8px;
    }
  </style>
