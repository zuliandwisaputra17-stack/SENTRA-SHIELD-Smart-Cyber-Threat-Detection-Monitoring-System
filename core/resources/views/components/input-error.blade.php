@props(['messages'])
@if ($messages)
    <ul {{ $attributes->merge(['class' => 'space-y-1 text-sm text-awas']) }} role="alert">
        @foreach ((array) $messages as $message)
            <li class="flex items-start gap-1.5"><x-icon name="circle-alert" class="mt-0.5 size-4" />{{ $message }}</li>
        @endforeach
    </ul>
@endif
