{{-- Nội dung modal quiz của một game (trang danh sách game) — render qua games.quiz-panel --}}
@if ($quizzes->isEmpty())
    <p class="gqp-empty">Game này chưa có quiz — có thể xóa game được rồi.</p>
@else
    <p class="gqp-hint">Xóa hoặc chuyển quiz sang game khác để có thể xóa game này. Quiz bị xóa sẽ được lưu lại kèm tên game hiện tại.</p>
    <ul class="gqp-list">
        @foreach ($quizzes as $quiz)
            <li class="gqp-row" data-quiz-id="{{ $quiz->id }}">
                <div class="gqp-row__info">
                    <a href="{{ route('admin.quizzes.show', $quiz) }}" class="gqp-row__name">{{ $quiz->name }}</a>
                    <span class="gqp-row__meta">
                        {{ $quiz->questions_count }} câu hỏi
                        · <span class="{{ $quiz->is_active ? 'gqp-status--on' : 'gqp-status--off' }}">{{ $quiz->is_active ? 'Đang bật' : 'Đang tắt' }}</span>
                    </span>
                </div>
                <div class="gqp-row__actions">
                    @if ($otherGames->isNotEmpty())
                        <select class="gqp-row__select" data-quiz-move-select aria-label="Chuyển quiz sang game khác">
                            <option value="">Chuyển sang…</option>
                            @foreach ($otherGames as $other)
                                <option value="{{ $other->id }}">{{ $other->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-secondary btn-sm" data-quiz-move data-quiz-name="{{ $quiz->name }}">Chuyển</button>
                    @endif
                    <button type="button" class="btn btn-danger btn-sm" data-quiz-delete data-quiz-name="{{ $quiz->name }}">Xóa</button>
                </div>
            </li>
        @endforeach
    </ul>
@endif
