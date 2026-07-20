@props(['group' => null, 'link' => null])

@if (! $group)
    <span class="text-muted">—</span>
@else
    @php
        $style = sprintf(
            'background:%s;color:%s;border-color:%s;',
            $group->color ?? '#2D46D6',
            $group->text_color,
            $group->color ?? '#2D46D6'
        );
    @endphp
    @if ($link)
        <a href="{{ $link }}" class="tag-chip tag-chip--link" style="{{ $style }}">{{ $group->name }}</a>
    @else
        <span class="tag-chip" style="{{ $style }}">{{ $group->name }}</span>
    @endif
@endif
