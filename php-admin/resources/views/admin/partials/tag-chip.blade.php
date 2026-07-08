@props(['tag', 'link' => null])

@php
    $style = sprintf(
        'background:%s;color:%s;border-color:%s;',
        $tag->color ?? '#2D46D6',
        $tag->text_color,
        $tag->color ?? '#2D46D6'
    );
@endphp

@if ($link)
    <a href="{{ $link }}" class="tag-chip tag-chip--link" style="{{ $style }}" data-tag-id="{{ $tag->id }}">{{ $tag->name }}</a>
@else
    <span class="tag-chip" style="{{ $style }}" data-tag-id="{{ $tag->id }}">{{ $tag->name }}</span>
@endif
