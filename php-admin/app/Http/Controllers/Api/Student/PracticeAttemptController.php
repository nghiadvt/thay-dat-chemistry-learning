<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\PracticeAttempt;
use App\Models\PracticeAttemptAnswer;
use App\Models\QuestionBankItem;
use App\Support\FeatureRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Ghi lại lượt học sinh tự luyện (ngoài phòng live của giáo viên) để màn thống
 * kê của giáo viên có dữ liệu.
 *
 * Nguyên tắc: server TỰ CHẤM bằng cách so đáp án gửi lên với correct_index
 * trong ngân hàng câu hỏi. Không bao giờ nhận `is_correct` hay `score` do client
 * gửi — nếu không học sinh chỉ cần sửa request là có điểm tuyệt đối.
 */
class PracticeAttemptController extends Controller
{
    /** Mở một lượt luyện: ghi sẵn mỗi câu một dòng với trạng thái "chưa làm". */
    public function store(Request $request): JsonResponse
    {
        $student = $request->user('student');

        $validated = $request->validate([
            'feature_key' => ['required', Rule::in(FeatureRegistry::keys())],
            'label' => ['nullable', 'string', 'max:150'],
            'topic_slug' => ['nullable', 'string', 'max:100'],
            'grade_slug' => ['nullable', 'string', 'max:32'],
            'question_ids' => ['required', 'array', 'min:1', 'max:100'],
            'question_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        // Chỉ nhận câu hỏi có thật; tránh tạo lượt rỗng hoặc trỏ vào id rác.
        $questionIds = QuestionBankItem::query()
            ->whereIn('id', $validated['question_ids'])
            ->pluck('id')
            ->all();

        if ($questionIds === []) {
            return $this->jsonError('Không tìm thấy câu hỏi nào hợp lệ cho lượt luyện này.', 422);
        }

        // Giữ đúng thứ tự hiển thị phía học sinh.
        $ordered = array_values(array_filter(
            $validated['question_ids'],
            fn ($id) => in_array($id, $questionIds, true)
        ));

        $attempt = DB::transaction(function () use ($student, $validated, $ordered) {
            $attempt = PracticeAttempt::create([
                'student_id' => $student->id,
                'feature_key' => $validated['feature_key'],
                'label' => $validated['label'] ?? null,
                'topic_slug' => $validated['topic_slug'] ?? null,
                'grade_slug' => $validated['grade_slug'] ?? null,
                'total_questions' => count($ordered),
                'started_at' => now(),
            ]);

            foreach ($ordered as $position => $questionId) {
                PracticeAttemptAnswer::create([
                    'attempt_id' => $attempt->id,
                    'question_bank_item_id' => $questionId,
                    'position' => $position,
                ]);
            }

            return $attempt;
        });

        return $this->jsonSuccess([
            'attempt_id' => $attempt->id,
            'total_questions' => $attempt->total_questions,
        ], 201);
    }

    /** Nộp bài: chấm lại toàn bộ ở server rồi chốt lượt. */
    public function finish(Request $request, PracticeAttempt $attempt): JsonResponse
    {
        $student = $request->user('student');

        if ($attempt->student_id !== $student->id) {
            return $this->jsonError('Lượt luyện này không thuộc về em.', 403);
        }

        if ($attempt->isFinished()) {
            return $this->jsonError('Lượt luyện này đã nộp rồi.', 409);
        }

        $validated = $request->validate([
            'duration_ms' => ['nullable', 'integer', 'min:0', 'max:86400000'],
            'answers' => ['present', 'array'],
            'answers.*.position' => ['required', 'integer', 'min:0'],
            'answers.*.answer_index' => ['nullable', 'integer', 'min:0', 'max:25'],
        ]);

        $submitted = collect($validated['answers'])->keyBy('position');

        $correct = DB::transaction(function () use ($attempt, $submitted) {
            $rows = $attempt->answers()->with('question:id,correct_index')->get();
            $correct = 0;

            foreach ($rows as $row) {
                $answerIndex = $submitted->get($row->position)['answer_index'] ?? null;

                if ($answerIndex === null) {
                    continue; // để nguyên trạng thái "chưa làm"
                }

                $isCorrect = $row->question !== null
                    && (int) $row->question->correct_index === (int) $answerIndex;

                $row->forceFill([
                    'answer_index' => $answerIndex,
                    'is_correct' => $isCorrect,
                    'answered_at' => now(),
                ])->save();

                if ($isCorrect) {
                    $correct++;
                }
            }

            return $correct;
        });

        $attempt->forceFill([
            'correct_count' => $correct,
            'score' => $correct,
            'duration_ms' => $validated['duration_ms'] ?? 0,
            'finished_at' => now(),
        ])->save();

        return $this->jsonSuccess([
            'attempt_id' => $attempt->id,
            'correct_count' => $attempt->correct_count,
            'total_questions' => $attempt->total_questions,
            'accuracy_percent' => $attempt->accuracyPercent(),
            'grade' => $attempt->grade(),
        ]);
    }
}
