@props(['class' => null])

@if ($class)
    <span class="tag-chip tag-chip--neutral">{{ $class->name }}</span>
@endif
