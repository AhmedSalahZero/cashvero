@props([
'options'=>$options,
'name',
'selectedOptions'
])
<div class="multiselect-container">
    <button type="button" class="multiselect-trigger">
        <span class="selected-text">{{ __('Select') }}</span>
        <span class="arrow">▼</span>
    </button>
    <div class="multiselect-dropdown">
        <div class="multiselect-search">
            <input type="text" placeholder="{{ __('Search...') }}" class="search-input">
        </div>
        <div class="multiselect-buttons">
            <button type="button" class="btn-select-all">{{ __('Select All') }}</button>
            <button type="button" class="btn-deselect-all">{{ __('Deselect All') }}</button>
        </div>
        <div class="multiselect-options">
            @foreach($options as $optionArr)
            <label class="option-item">
                <input name="{{ $name }}" @if(in_array($optionArr['value'],$selectedOptions)) checked @endif type="checkbox" value="{{ $optionArr['value'] }}"> {{ $optionArr['title'] }}
            </label>
            @endforeach

        </div>
    </div>
</div>



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
        width: 100%;
        display: inline-block;
        font-family: Arial, sans-serif;
    }

    .multiselect-trigger {
        width: 100%;
        background: var(--bg-color);
        border: 1px solid var(--border-color);
        border-radius: 4px;
        padding: 6px 10px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;

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
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .multiselect-search {
        padding: 12px;
        border-bottom: 1px solid var(--border-color);
    }

    .search-input {
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
    .btn-deselect-all {
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

    .multiselect-options {
        max-height: 200px;
        overflow-y: auto;
        color: black;
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
        content: attr(data-count) ? ' ('attr(data-count) ' selected)': '';
        font-size: 12px;
        color: #666;
    }

</style>
