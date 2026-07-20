{{-- Một hàng quiz. Dùng cả ở bảng phẳng lẫn khi tải dần nội dung nhóm. --}}
<tr class="{{ $quiz->is_active ? '' : 'row-inactive' }}">
    <td data-col="name"><strong>{{ $quiz->name }}</strong></td>
    <td data-col="group">
        @include('admin.partials.group-chip', [
            'group' => $quiz->group,
            'link' => $quiz->group ? route('admin.quizzes.index', ['group_id' => $quiz->group_id]) : null,
        ])
    </td>
    <td data-col="tags">
        @if ($quiz->tags->isEmpty())
            <span class="text-muted">—</span>
        @else
            <div class="tag-list tag-list--compact">
                @foreach ($quiz->tags as $tag)
                    @include('admin.partials.tag-chip', [
                        'tag' => $tag,
                        'link' => route('admin.quizzes.index', ['tag_id' => $tag->id]),
                    ])
                @endforeach
            </div>
        @endif
    </td>
    <td data-col="game">{{ $quiz->game?->name }}</td>
    <td data-col="keyboard">{{ $quiz->keyboard?->name }}</td>
    <td data-col="grade">{{ $quiz->grade ?: '—' }}</td>
    <td data-col="questions">{{ $quiz->questions_count }}</td>
    <td data-col="active">
        @include('admin.partials.toggle-switch', [
            'formAction' => route('admin.quizzes.toggle-active', $quiz),
            'checked' => $quiz->is_active,
            'submitOnChange' => true,
            'label' => 'Bật/tắt quiz',
        ])
    </td>
    <td data-col="actions" class="actions-cell">
        @include('admin.partials.row-action-menu', [
            'actions' => [
                ['key' => 'preview', 'label' => 'Xem trước'],
                ['key' => 'detail', 'label' => 'Chi tiết', 'href' => route('admin.quizzes.show', $quiz)],
                ['key' => 'delete', 'label' => 'Xóa', 'danger' => true, 'href' => route('admin.quizzes.destroy', $quiz), 'method' => 'DELETE', 'confirm' => "Xóa quiz «{$quiz->name}» và tất cả câu hỏi?"],
            ],
            'dataAttrs' => [
                'quiz-id' => $quiz->id,
                'quiz-name' => $quiz->name,
                'item-label' => $quiz->name,
            ],
        ])
    </td>
</tr>
