@php
    $checklistId = $id ?? 'class-checklist-' . uniqid();
    $selectedIds = array_map('intval', $selectedIds ?? []);
@endphp

<div class="tag-checklist" id="{{ $checklistId }}" role="group" aria-label="Chọn lớp">
    @forelse ($classes as $class)
        <div class="tag-checklist-row">
            <label class="tag-checklist-item">
                <input type="checkbox"
                       name="class_ids[]"
                       value="{{ $class->id }}"
                       @checked(in_array($class->id, $selectedIds, true))>
                <span class="tag-checklist-label">{{ $class->name }}{{ $class->grade ? " (Khối {$class->grade})" : '' }}</span>
            </label>
        </div>
    @empty
        <p class="tag-checklist-empty">Chưa có lớp nào. Tạo lớp ở trang «Học sinh».</p>
    @endforelse
</div>
