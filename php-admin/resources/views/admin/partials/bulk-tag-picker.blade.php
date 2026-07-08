@php
    $pickerId = $id ?? 'bulkTagPicker';
@endphp

@include('admin.partials.tag-checklist', [
    'tags' => $tags,
    'selectedIds' => $selectedIds ?? [],
    'id' => $pickerId,
])
