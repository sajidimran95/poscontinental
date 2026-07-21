@props([
    'label' => '',
    'for' => null,
])

<tr {{ $attributes->merge(['class' => 'desktop-field-row']) }}>
    <th scope="row" class="desktop-field-lbl">
        @if ($for)
            <label for="{{ $for }}">{{ $label }}</label>
        @else
            {{ $label }}
        @endif
    </th>
    <td class="desktop-field-ctl">
        {{ $slot }}
    </td>
</tr>
