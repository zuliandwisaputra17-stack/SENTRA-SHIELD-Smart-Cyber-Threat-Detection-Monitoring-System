@props(['status'])

@if ($status)
    <div role="status" {{ $attributes->merge(['class' => 'flex items-start gap-2 rounded-control border border-aman/40 bg-aman/10 px-3 py-2 text-sm font-medium text-aman']) }}>
        <x-icon name="circle-check" class="mt-0.5 size-4" />{{ $status }}
    </div>
@endif
