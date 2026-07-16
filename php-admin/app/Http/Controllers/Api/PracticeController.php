<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QuestionBankItem;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API công khai cho chế độ «Ôn trắc nghiệm» phía học sinh (không cần đăng nhập).
 * Chỉ trả câu hỏi trắc nghiệm (mc) đang bật trong ngân hàng câu hỏi.
 *
 * Luồng HS: chọn lớp (tag khối) → chọn chủ đề trong lớp → tải đề.
 * `grade` là slug tag khối (khoi-10/11/12); chủ đề đếm theo giao lớp × chủ đề.
 */
class PracticeController extends Controller
{
    public const MAX_QUESTIONS = 30;

    /** @var list<string> Tag khối lớp — ẩn khỏi danh sách chủ đề khi đã lọc theo lớp */
    public const GRADE_SLUGS = ['khoi-10', 'khoi-11', 'khoi-12'];

    public function topics(Request $request): JsonResponse
    {
        $gradeSlug = trim((string) $request->query('grade', ''));

        $base = $this->baseQuery();
        if ($gradeSlug !== '') {
            $base->whereHas('tags', fn (Builder $q) => $q->where('slug', $gradeSlug));
        }
        $total = $base->count();

        $topics = Tag::query()
            ->when($gradeSlug !== '', fn ($q) => $q->whereNotIn('slug', self::GRADE_SLUGS))
            ->withCount(['questionBankItems as question_count' => function (Builder $query) use ($gradeSlug) {
                $query->where('is_active', true)
                    ->where('answer_type', 'mc')
                    ->whereNotNull('correct_index');
                if ($gradeSlug !== '') {
                    $query->whereHas('tags', fn (Builder $q) => $q->where('slug', $gradeSlug));
                }
            }])
            ->orderBy('name')
            ->get()
            ->filter(fn (Tag $tag) => $tag->question_count > 0)
            ->values()
            ->map(fn (Tag $tag) => [
                'slug' => $tag->slug,
                'name' => $tag->name,
                'color' => $tag->color,
                'question_count' => $tag->question_count,
            ]);

        return $this->jsonSuccess([
            'total' => $total,
            'topics' => $topics,
        ]);
    }

    public function questions(Request $request): JsonResponse
    {
        $count = max(1, min(self::MAX_QUESTIONS, (int) $request->query('count', 10)));
        $gradeSlug = trim((string) $request->query('grade', ''));
        $topicSlug = trim((string) $request->query('topic', ''));

        $query = $this->baseQuery();
        if ($gradeSlug !== '') {
            $query->whereHas('tags', fn (Builder $q) => $q->where('slug', $gradeSlug));
        }
        if ($topicSlug !== '') {
            $query->whereHas('tags', fn (Builder $q) => $q->where('slug', $topicSlug));
        }

        $questions = $query
            ->inRandomOrder()
            ->limit($count)
            ->get()
            ->map(fn (QuestionBankItem $item) => [
                'id' => $item->id,
                'content' => $item->content,
                'options' => array_values($item->options ?? []),
                'correct_index' => (int) $item->correct_index,
                'explanation' => $item->explanation,
            ]);

        if ($questions->isEmpty()) {
            return $this->jsonError('Chưa có câu hỏi trắc nghiệm cho chủ đề này.', 404);
        }

        return $this->jsonSuccess(['questions' => $questions]);
    }

    private function baseQuery(): Builder
    {
        return QuestionBankItem::query()
            ->where('is_active', true)
            ->where('answer_type', 'mc')
            ->whereNotNull('correct_index');
    }
}
