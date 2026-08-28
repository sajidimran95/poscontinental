@php
    $fieldId = $id ?? $name;
    $label = $label ?? 'Password';
    $placeholder = $placeholder ?? $label;
    $autocomplete = $autocomplete ?? 'current-password';
@endphp
<div>
    <label class="text-xs font-bold text-slate-500 mb-1.5 block" for="{{ $fieldId }}">{{ $label }}</label>
    <div class="sale-pw-wrap">
        <input id="{{ $fieldId }}" type="password" name="{{ $name }}" required autocomplete="{{ $autocomplete }}" class="sale-input sale-pw-input" placeholder="{{ $placeholder }}" minlength="{{ $minlength ?? 4 }}">
        <button type="button" class="sale-pw-toggle" data-password-toggle aria-controls="{{ $fieldId }}" aria-label="Show password" aria-pressed="false">
            <svg class="sale-pw-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg class="sale-pw-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.8 21.8 0 0 1 5.06-5.94"/><path d="M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a21.8 21.8 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>
        </button>
    </div>
</div>
