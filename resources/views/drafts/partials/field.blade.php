@php
    $type = $field['type'] ?? 'text';
    $required = !empty($field['required']);
    $options = $field['options'] ?? [];
    $value = $value ?? '';
    $showLabel = $showLabel ?? true;
    $selectedValue = (string) $value;
    $selectedLabel = '— اختر —';

    foreach ($options as $optionValue => $optionLabel) {
        if ((string) $optionValue === $selectedValue) {
            $selectedLabel = (string) $optionLabel;
            break;
        }
    }
@endphp

@if($showLabel)
    <label class="label block text-xs font-bold text-slate-600 mb-1">{{ $field['label'] }}</label>
@endif

@if($type === 'select')
    <div class="draft-combobox" data-combobox>
        {{-- Keep the real select for payload collection and validation. --}}
        <select name="{{ $name }}" data-field-name="{{ $name }}" class="draft-native-select" @if($required) required @endif>
            <option value="">— اختر —</option>
            @foreach($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @if($selectedValue === (string) $optionValue) selected @endif>{{ $optionLabel }}</option>
            @endforeach
        </select>

        <button type="button" class="input w-full text-sm draft-combobox-toggle" data-combobox-toggle
                aria-haspopup="listbox" aria-expanded="false">
            <span data-combobox-label>{{ $selectedLabel }}</span>
            <i class="fas fa-chevron-down text-slate-400"></i>
        </button>

        <div class="draft-combobox-panel" data-combobox-panel hidden>
            <input type="text" class="input text-sm draft-combobox-search" data-combobox-search
                   placeholder="ابحث..." autocomplete="off">
            <div class="draft-combobox-options" data-combobox-options role="listbox">
                <button type="button" class="draft-combobox-option" data-combobox-option data-value="" role="option"
                        aria-selected="{{ $selectedValue === '' ? 'true' : 'false' }}">— اختر —</button>
                @foreach($options as $optionValue => $optionLabel)
                    <button type="button" class="draft-combobox-option" data-combobox-option
                            data-value="{{ $optionValue }}" role="option"
                            aria-selected="{{ $selectedValue === (string) $optionValue ? 'true' : 'false' }}">{{ $optionLabel }}</button>
                @endforeach
            </div>
        </div>
    </div>
@elseif($type === 'date')
    <input type="date" name="{{ $name }}" data-field-name="{{ $name }}" class="input w-full text-sm" value="{{ $value }}" @if($required) required @endif>
@elseif($type === 'number')
    <input type="number" name="{{ $name }}" data-field-name="{{ $name }}" class="input w-full text-sm"
           value="{{ $value }}"
           @if(!is_null($field['min'] ?? null)) min="{{ $field['min'] }}" @endif
           @if(!is_null($field['step'] ?? null)) step="{{ $field['step'] }}" @endif
           @if(($field['placeholder'] ?? '') !== '') placeholder="{{ $field['placeholder'] }}" @endif
           @if($required) required @endif>
@else
    <input type="text" name="{{ $name }}" data-field-name="{{ $name }}" class="input w-full text-sm" value="{{ $value }}"
           @if(($field['placeholder'] ?? '') !== '') placeholder="{{ $field['placeholder'] }}" @endif
           @if($required) required @endif>
@endif
