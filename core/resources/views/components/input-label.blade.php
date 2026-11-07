@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-ink-2']) }}>
    {{ $value ?? $slot }}
</label>
