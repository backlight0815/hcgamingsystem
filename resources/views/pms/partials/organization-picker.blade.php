<div class="pm-auth-field pm-org-picker" data-org-picker data-error="{{ $errors->first($pickerName) }}">
    <label for="{{ $pickerId }}">{{ $pickerLabel }} <span aria-hidden="true">*</span></label>
    <input type="search" data-org-search aria-label="Search {{ strtolower($pickerLabel) }}" aria-controls="{{ $pickerId }}" placeholder="Search {{ strtolower($pickerLabel) }}" autocomplete="off">
    <select id="{{ $pickerId }}" name="{{ $pickerName }}" data-org-select @if(!empty($dependsOn)) data-org-depends="{{ $dependsOn }}" @endif required @if($errors->has($pickerName)) aria-invalid="true" @endif aria-describedby="{{ $pickerId }}-status">
        <option value="">{{ $pickerPlaceholder }}</option>
        @foreach($items as $item)
            <option value="{{ $item->id }}" @if(isset($item->department_ids)) data-departments="{{ $item->department_ids }}" @endif @selected((string)$selected === (string)$item->id)>{{ $item->name }}</option>
        @endforeach
    </select>
    <small id="{{ $pickerId }}-status" data-org-status role="status">{{ $errors->first($pickerName) ?: 'Type to filter, then select an option.' }}</small>
</div>
